<?php
/**
 * Media library abilities: list, get, delete.
 *
 * Provides read and delete access to the WordPress media library.
 * AI clients can list media items, retrieve full attachment details,
 * and delete attachments — all scoped to the authenticated user's
 * capabilities via map_meta_cap.
 *
 * Note: Remote URL sideloading (upload-media) is intentionally omitted
 * from this release to keep the plugin's scope focused. It will be
 * revisited in a future version with appropriate security hardening.
 *
 * @package LaxAbilitiesToolkit
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers all media abilities.
 *
 * @since 1.1.0
 *
 * @return void
 */
function lax_abilities_register_media_abilities() {
	if ( ! lax_abilities_is_group_enabled( 'media' ) ) {
		return;
	}

	$cat = LAX_ABILITIES_CATEGORY;

	// ── list ─────────────────────────────────────────────────────────────────

	wp_register_ability(
		'lax-abilities/list-media',
		array(
			'label'       => __( 'List Media', 'lax-abilities-toolkit' ),
			'description' => __( 'Get a list of media library items with URLs, dimensions, and alt text.', 'lax-abilities-toolkit' ),
			'category'    => $cat,
			'input_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'limit' => array(
						'type'        => 'integer',
						'default'     => 20,
						'description' => __( 'Number of items to return (max 100).', 'lax-abilities-toolkit' ),
					),
					'mime_type' => array(
						'type'        => 'string',
						'description' => __( 'Filter by MIME type prefix (e.g. "image", "image/jpeg", "application/pdf").', 'lax-abilities-toolkit' ),
						'default'     => '',
					),
					'post_id' => array(
						'type'        => 'integer',
						'description' => __( 'Filter to media attached to a specific post ID.', 'lax-abilities-toolkit' ),
						'default'     => 0,
					),
				),
			),
			'execute_callback'    => 'lax_abilities_list_media_handler',
			'permission_callback' => function () {
				return current_user_can( 'upload_files' );
			},
			'meta' => array(
				'show_in_rest' => true,
				'mcp'          => array( 'public' => true ),
				'annotations'  => array( 'readonly' => true, 'destructive' => false ),
			),
		)
	);

	// ── get ──────────────────────────────────────────────────────────────────

	wp_register_ability(
		'lax-abilities/get-media',
		array(
			'label'       => __( 'Get Media Item', 'lax-abilities-toolkit' ),
			'description' => __( 'Get full details of a single media attachment by ID.', 'lax-abilities-toolkit' ),
			'category'    => $cat,
			'input_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'id' => array(
						'type'        => 'integer',
						'description' => __( 'Attachment ID.', 'lax-abilities-toolkit' ),
					),
				),
				'required' => array( 'id' ),
			),
			'execute_callback'    => 'lax_abilities_get_media_handler',
			'permission_callback' => function () {
				return current_user_can( 'upload_files' );
			},
			'meta' => array(
				'show_in_rest' => true,
				'mcp'          => array( 'public' => true ),
				'annotations'  => array( 'readonly' => true, 'destructive' => false ),
			),
		)
	);

	// ── delete ───────────────────────────────────────────────────────────────

	wp_register_ability(
		'lax-abilities/delete-media',
		array(
			'label'       => __( 'Delete Media Item', 'lax-abilities-toolkit' ),
			'description' => __( 'Permanently delete a media attachment and its associated files from the media library.', 'lax-abilities-toolkit' ),
			'category'    => $cat,
			'input_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'id' => array(
						'type'        => 'integer',
						'description' => __( 'Attachment ID to delete.', 'lax-abilities-toolkit' ),
					),
				),
				'required' => array( 'id' ),
			),
			'execute_callback'    => 'lax_abilities_delete_media_handler',
			'permission_callback' => function ( $params ) {
				if ( empty( $params['id'] ) ) {
					return false;
				}
				return current_user_can( 'delete_post', absint( $params['id'] ) );
			},
			'meta' => array(
				'show_in_rest' => true,
				'mcp'          => array( 'public' => true ),
				'annotations'  => array( 'readonly' => false, 'destructive' => true ),
			),
		)
	);
}

// =============================================================================
// Execute handlers
// =============================================================================

/**
 * Lists media library items.
 *
 * @since 1.1.0
 *
 * @param  array $params Validated input parameters.
 * @return array         Response with `items` array.
 */
function lax_abilities_list_media_handler( $params ) {
	$limit     = isset( $params['limit'] ) ? min( absint( $params['limit'] ), 100 ) : 20;
	$mime_type = isset( $params['mime_type'] ) ? sanitize_text_field( $params['mime_type'] ) : '';
	$post_id   = isset( $params['post_id'] ) ? absint( $params['post_id'] ) : 0;

	$query_args = array(
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'posts_per_page' => $limit,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'no_found_rows'  => true,
	);

	if ( ! empty( $mime_type ) ) {
		$query_args['post_mime_type'] = $mime_type;
	}

	if ( $post_id > 0 ) {
		$query_args['post_parent'] = $post_id;
	}

	$query = new WP_Query( $query_args );

	$items = array_map(
		function ( WP_Post $attachment ) {
			return lax_abilities_build_media_item( $attachment->ID );
		},
		$query->posts
	);

	return array(
		'success' => true,
		'count'   => count( $items ),
		'items'   => $items,
	);
}

/**
 * Gets full details of a single media attachment.
 *
 * @since 1.1.0
 *
 * @param  array       $params Validated input parameters.
 * @return array|WP_Error      Response or WP_Error.
 */
function lax_abilities_get_media_handler( $params ) {
	$attachment_id = absint( $params['id'] );
	$attachment    = get_post( $attachment_id );

	if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
		return new WP_Error( 'lax_abilities_not_found', __( 'Media item not found.', 'lax-abilities-toolkit' ) );
	}

	return lax_abilities_build_media_response( $attachment_id );
}

/**
 * Permanently deletes a media attachment and its associated files.
 *
 * @since 1.1.0
 *
 * @param  array       $params Validated input parameters.
 * @return array|WP_Error      Response or WP_Error.
 */
function lax_abilities_delete_media_handler( $params ) {
	$attachment_id = absint( $params['id'] );
	$attachment    = get_post( $attachment_id );

	if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
		return new WP_Error( 'lax_abilities_not_found', __( 'Media item not found.', 'lax-abilities-toolkit' ) );
	}

	if ( ! current_user_can( 'delete_post', $attachment_id ) ) {
		return new WP_Error( 'lax_abilities_forbidden', __( 'You do not have permission to delete this media item.', 'lax-abilities-toolkit' ) );
	}

	$title  = $attachment->post_title;
	$result = wp_delete_attachment( $attachment_id, true ); // true = force delete, no trash for media.

	if ( ! $result ) {
		return new WP_Error( 'lax_abilities_delete_failed', __( 'Could not delete the media item.', 'lax-abilities-toolkit' ) );
	}

	return array(
		'success' => true,
		'id'      => $attachment_id,
		'message' => sprintf(
			/* translators: %s: attachment title */
			__( '"%s" deleted from the media library.', 'lax-abilities-toolkit' ),
			$title
		),
	);
}

// =============================================================================
// Internal utilities
// =============================================================================

/**
 * Builds a concise media item summary (used in list responses).
 *
 * @since 1.1.0
 *
 * @param  int   $attachment_id Attachment post ID.
 * @return array                Item summary array.
 */
function lax_abilities_build_media_item( $attachment_id ) {
	$attachment = get_post( $attachment_id );
	$metadata   = wp_get_attachment_metadata( $attachment_id );

	return array(
		'id'            => $attachment_id,
		'title'         => $attachment->post_title,
		'filename'      => basename( get_attached_file( $attachment_id ) ),
		'url'           => wp_get_attachment_url( $attachment_id ),
		'mime_type'     => $attachment->post_mime_type,
		'alt_text'      => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
		'caption'       => $attachment->post_excerpt,
		'date'          => $attachment->post_date,
		'width'         => isset( $metadata['width'] ) ? (int) $metadata['width'] : 0,
		'height'        => isset( $metadata['height'] ) ? (int) $metadata['height'] : 0,
		'thumbnail_url' => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ),
	);
}

/**
 * Builds a full media attachment response (used in upload and get responses).
 *
 * @since 1.1.0
 *
 * @param  int   $attachment_id Attachment post ID.
 * @return array                Full response array.
 */
function lax_abilities_build_media_response( $attachment_id ) {
	$attachment = get_post( $attachment_id );
	$metadata   = wp_get_attachment_metadata( $attachment_id );
	$file_path  = get_attached_file( $attachment_id );

	$response = array(
		'success'       => true,
		'id'            => $attachment_id,
		'title'         => $attachment->post_title,
		'filename'      => $file_path ? basename( $file_path ) : '',
		'url'           => wp_get_attachment_url( $attachment_id ),
		'mime_type'     => $attachment->post_mime_type,
		'alt_text'      => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
		'caption'       => $attachment->post_excerpt,
		'description'   => $attachment->post_content,
		'date'          => $attachment->post_date,
		'edit_url'      => get_edit_post_link( $attachment_id, 'raw' ),
		'width'         => isset( $metadata['width'] ) ? (int) $metadata['width'] : 0,
		'height'        => isset( $metadata['height'] ) ? (int) $metadata['height'] : 0,
		'file_size'     => $file_path && file_exists( $file_path ) ? filesize( $file_path ) : 0,
		'thumbnail_url' => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ),
		'medium_url'    => wp_get_attachment_image_url( $attachment_id, 'medium' ),
		'message'       => sprintf(
			/* translators: %s: attachment title */
			__( 'Media "%s" ready.', 'lax-abilities-toolkit' ),
			$attachment->post_title
		),
	);

	// Include available image sizes for image attachments.
	if ( wp_attachment_is_image( $attachment_id ) && ! empty( $metadata['sizes'] ) ) {
		$sizes = array();
		foreach ( $metadata['sizes'] as $size_name => $size_data ) {
			$size_url = wp_get_attachment_image_url( $attachment_id, $size_name );
			if ( $size_url ) {
				$sizes[ $size_name ] = array(
					'url'    => $size_url,
					'width'  => (int) $size_data['width'],
					'height' => (int) $size_data['height'],
				);
			}
		}
		$response['sizes'] = $sizes;
	}

	return $response;
}
