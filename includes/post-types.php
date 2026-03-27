<?php
/**
 * Dynamic post-type abilities: create, update, list, get, delete.
 *
 * Abilities are generated automatically for every post type registered via
 * the `lax_abilities_registered_post_types` filter.
 *
 * ## Extending with a custom post type
 *
 *     add_filter(
 *         'lax_abilities_registered_post_types',
 *         function( array $types ): array {
 *             $types['product'] = array(
 *                 'label'           => 'Product',
 *                 'plural'          => 'Products',
 *                 'capability_type' => 'product',
 *                 'taxonomies'      => array( 'product_cat', 'product_tag' ),
 *                 'supports_schedule' => false,
 *             );
 *             return $types;
 *         }
 *     );
 *
 * ## Adding extra input fields
 *
 *     add_filter(
 *         'lax_abilities_input_schema_product',
 *         function( array $schema, string $post_type ): array {
 *             $schema['properties']['price'] = array(
 *                 'type'        => 'string',
 *                 'description' => 'Product price.',
 *             );
 *             return $schema;
 *         },
 *         10,
 *         2
 *     );
 *
 * ## Persisting extra fields before insert / update
 *
 *     add_filter(
 *         'lax_abilities_post_data_product',
 *         function( array $post_data, array $params, string $post_type ): array {
 *             if ( isset( $params['price'] ) ) {
 *                 $post_data['meta_input']['_price'] = sanitize_text_field( $params['price'] );
 *             }
 *             return $post_data;
 *         },
 *         10,
 *         3
 *     );
 *
 * @package LaxAbilitiesToolkit
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// =============================================================================
// Registry
// =============================================================================

/**
 * Returns ALL configured post type entries after applying developer filters.
 *
 * This is the unfiltered source used by the admin UI and settings page.
 * It excludes WordPress-internal post types (those with `public => false`)
 * so that types like nav_menu_item, wp_block, wp_template, etc. are never
 * surfaced to AI clients regardless of filter usage.
 *
 * @since 1.3.0
 *
 * @return array<string, array> Keyed by WP post type slug.
 */
function lax_abilities_all_post_types() {
	$defaults = array(
		'post' => array(
			'label'             => __( 'Post', 'lax-abilities-toolkit' ),
			'plural'            => __( 'Posts', 'lax-abilities-toolkit' ),
			'capability_type'   => 'post',
			'taxonomies'        => array( 'category', 'post_tag' ),
			'supports_parent'   => false,
			'supports_template' => false,
			'supports_schedule' => true,
		),
		'page' => array(
			'label'             => __( 'Page', 'lax-abilities-toolkit' ),
			'plural'            => __( 'Pages', 'lax-abilities-toolkit' ),
			'capability_type'   => 'page',
			'taxonomies'        => array(),
			'supports_parent'   => true,
			'supports_template' => true,
			'supports_schedule' => false,
		),
	);

	/**
	 * Filters the post types that get Abilities registered.
	 *
	 * Each entry is keyed by the WP post type slug. Accepted config keys:
	 *
	 * @since 1.0.0
	 *
	 * @param array $post_types {
	 *     Post type configuration map.
	 *
	 *     @type string   $label             Singular human-readable label.
	 *     @type string   $plural            Plural human-readable label.
	 *     @type string   $capability_type   Used to derive publish/edit/delete caps.
	 *                                       Overridden when the post type is registered
	 *                                       in WordPress and has its own cap object.
	 *     @type string[] $taxonomies        WP taxonomy slugs supported by this type.
	 *                                       Each generates `{taxonomy}_ids` input field.
	 *     @type bool     $supports_parent   Include `parent_id` input field.
	 *     @type bool     $supports_template Include `template` input field.
	 *     @type bool     $supports_schedule Include `publish_date` scheduling field.
	 *     @type array    $capabilities      Override individual cap strings. Keys:
	 *                                       publish, edit_own, edit_others,
	 *                                       delete_own, delete_others, read_private.
	 * }
	 */
	$types = apply_filters( 'lax_abilities_registered_post_types', $defaults );

	// Remove WP-internal post types (public => false). This guards against
	// accidental exposure of nav_menu_item, wp_block, wp_template, etc.
	return array_filter(
		$types,
		function ( $config, $slug ) {
			$pt_obj = get_post_type_object( $slug );
			// If the post type isn't registered yet, allow it through — the
			// developer takes responsibility for registering it before abilities fire.
			if ( ! $pt_obj ) {
				return true;
			}
			return (bool) $pt_obj->public;
		},
		ARRAY_FILTER_USE_BOTH
	);
}

/**
 * Returns the post type configurations to register abilities for,
 * filtered to only enabled groups (see Settings → Lax Abilities).
 *
 * Developers add post types via the `lax_abilities_registered_post_types` filter.
 *
 * @since 1.0.0
 *
 * @return array<string, array> Keyed by WP post type slug.
 */
function lax_abilities_get_post_types() {
	return array_filter(
		lax_abilities_all_post_types(),
		function ( $config, $slug ) {
			return lax_abilities_is_group_enabled( $slug );
		},
		ARRAY_FILTER_USE_BOTH
	);
}

/**
 * Registers all abilities for every configured post type.
 *
 * @since 1.0.0
 *
 * @return void
 */
function lax_abilities_register_post_type_abilities_all() {
	$defaults = array(
		'label'             => '',
		'plural'            => '',
		'capability_type'   => 'post',
		'taxonomies'        => array(),
		'supports_parent'   => false,
		'supports_template' => false,
		'supports_schedule' => true,
		'capabilities'      => array(),
	);

	foreach ( lax_abilities_get_post_types() as $post_type => $config ) {
		$config = wp_parse_args( $config, $defaults );

		// Derive labels from post type slug when not supplied.
		if ( empty( $config['label'] ) ) {
			$config['label'] = ucwords( str_replace( array( '-', '_' ), ' ', $post_type ) );
		}
		if ( empty( $config['plural'] ) ) {
			$config['plural'] = $config['label'] . 's';
		}

		lax_abilities_register_post_type_abilities( $post_type, $config );
	}
}

// =============================================================================
// Ability registration
// =============================================================================

/**
 * Registers create / update / list / get / delete abilities for one post type.
 *
 * @since 1.0.0
 *
 * @param string $post_type WP post type slug.
 * @param array  $config    Merged post type configuration.
 * @return void
 */
function lax_abilities_register_post_type_abilities( $post_type, $config ) {
	$slug        = lax_abilities_to_slug( $post_type );
	$plural_slug = lax_abilities_to_slug( $config['plural'] );
	$label       = $config['label'];
	$plural      = $config['plural'];
	$caps        = lax_abilities_get_caps( $config, $post_type );
	$cat         = LAX_ABILITIES_CATEGORY;

	$create_schema = lax_abilities_build_create_schema( $config, $post_type );
	$update_schema = lax_abilities_build_update_schema( $config, $post_type );

	// ── create ───────────────────────────────────────────────────────────────

	wp_register_ability(
		"lax-abilities/create-{$slug}",
		array(
			'label'       => sprintf(
				/* translators: %s: singular post type label */
				__( 'Create %s', 'lax-abilities-toolkit' ),
				$label
			),
			'description' => sprintf(
				/* translators: %s: lowercase singular post type label */
				__( 'Create a new %s. Supports scheduling via publish_date.', 'lax-abilities-toolkit' ),
				strtolower( $label )
			),
			'category'         => $cat,
			'input_schema'     => $create_schema,
			'execute_callback' => function ( $params ) use ( $post_type, $config ) {
				return lax_abilities_create_post_handler( $params, $post_type, $config );
			},
			'permission_callback' => function () use ( $caps ) {
				return current_user_can( $caps['publish'] );
			},
			'meta' => array(
				'show_in_rest' => true,
				'mcp'          => array( 'public' => true ),
				'annotations'  => array( 'readonly' => false, 'destructive' => false ),
			),
		)
	);

	// ── update ───────────────────────────────────────────────────────────────

	wp_register_ability(
		"lax-abilities/update-{$slug}",
		array(
			'label'       => sprintf(
				/* translators: %s: singular post type label */
				__( 'Update %s', 'lax-abilities-toolkit' ),
				$label
			),
			'description' => sprintf(
				/* translators: %s: lowercase singular post type label */
				__( 'Update an existing %s by ID. Authors can only update their own items; editors can update any.', 'lax-abilities-toolkit' ),
				strtolower( $label )
			),
			'category'         => $cat,
			'input_schema'     => $update_schema,
			'execute_callback' => function ( $params ) use ( $post_type, $config ) {
				return lax_abilities_update_post_handler( $params, $post_type, $config );
			},
			'permission_callback' => function ( $params ) use ( $caps ) {
				if ( empty( $params['id'] ) ) {
					return false;
				}
				// map_meta_cap('edit_post', $id) respects ownership automatically.
				return current_user_can( 'edit_post', absint( $params['id'] ) );
			},
			'meta' => array(
				'show_in_rest' => true,
				'mcp'          => array( 'public' => true ),
				'annotations'  => array( 'readonly' => false, 'destructive' => false ),
			),
		)
	);

	// ── list ─────────────────────────────────────────────────────────────────

	wp_register_ability(
		"lax-abilities/list-{$plural_slug}",
		array(
			'label'       => sprintf(
				/* translators: %s: plural post type label */
				__( 'List %s', 'lax-abilities-toolkit' ),
				$plural
			),
			'description' => sprintf(
				/* translators: %s: lowercase plural post type label */
				__( 'Get a list of %s. Results are scoped to what the current user can read. Scheduled items include published_for date.', 'lax-abilities-toolkit' ),
				strtolower( $plural )
			),
			'category'     => $cat,
			'input_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'limit'  => array(
						'type'        => 'integer',
						'default'     => 10,
						'description' => __( 'Number of items to return (max 50).', 'lax-abilities-toolkit' ),
					),
					'status' => array(
						'type'        => 'string',
						'enum'        => array( 'publish', 'draft', 'future', 'pending', 'private', 'any' ),
						'default'     => 'publish',
						'description' => __( 'Filter by post status. Non-public statuses require edit capability.', 'lax-abilities-toolkit' ),
					),
				),
			),
			'execute_callback'    => function ( $params ) use ( $post_type, $config ) {
				return lax_abilities_list_posts_handler( $params, $post_type, $config );
			},
			'permission_callback' => function ( $params ) use ( $caps ) {
				$status = isset( $params['status'] ) ? $params['status'] : 'publish';
				if ( 'publish' === $status ) {
					return current_user_can( 'read' );
				}
				if ( 'private' === $status ) {
					return current_user_can( $caps['read_private'] );
				}
				// draft, future, pending, any — require edit capability.
				return current_user_can( $caps['edit_own'] );
			},
			'meta' => array(
				'show_in_rest' => true,
				'mcp'          => array( 'public' => true ),
				'annotations'  => array( 'readonly' => true, 'destructive' => false ),
			),
		)
	);

	// ── get (single) ─────────────────────────────────────────────────────────

	wp_register_ability(
		"lax-abilities/get-{$slug}",
		array(
			'label'       => sprintf(
				/* translators: %s: singular post type label */
				__( 'Get %s', 'lax-abilities-toolkit' ),
				$label
			),
			'description' => sprintf(
				/* translators: %s: lowercase singular post type label */
				__( 'Get full details of a single %s by ID, including content, taxonomy terms, and metadata.', 'lax-abilities-toolkit' ),
				strtolower( $label )
			),
			'category'     => $cat,
			'input_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'id' => array(
						'type'        => 'integer',
						/* translators: %s: singular post type label */
						'description' => sprintf( __( '%s ID.', 'lax-abilities-toolkit' ), $label ),
					),
				),
				'required' => array( 'id' ),
			),
			'execute_callback'    => function ( $params ) use ( $post_type, $config ) {
				return lax_abilities_get_post_handler( $params, $post_type, $config );
			},
			'permission_callback' => function ( $params ) use ( $post_type ) {
				if ( empty( $params['id'] ) ) {
					return true; // Let the handler return a 404-style error.
				}
				$post = get_post( absint( $params['id'] ) );
				if ( ! $post || $post->post_type !== $post_type ) {
					return true; // Let the handler return a 404-style error.
				}
				if ( 'publish' === $post->post_status ) {
					return current_user_can( 'read' );
				}
				// Private, draft, future, pending — check edit_post which uses map_meta_cap.
				return current_user_can( 'edit_post', $post->ID );
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
		"lax-abilities/delete-{$slug}",
		array(
			'label'       => sprintf(
				/* translators: %s: singular post type label */
				__( 'Delete %s', 'lax-abilities-toolkit' ),
				$label
			),
			'description' => sprintf(
				/* translators: %s: lowercase singular post type label */
				__( 'Move a %s to the trash. Use force_delete: true to permanently delete (requires elevated capability). Authors can only delete their own items.', 'lax-abilities-toolkit' ),
				strtolower( $label )
			),
			'category'     => $cat,
			'input_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'id' => array(
						'type'        => 'integer',
						/* translators: %s: singular post type label */
						'description' => sprintf( __( '%s ID to delete.', 'lax-abilities-toolkit' ), $label ),
					),
					'force_delete' => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => __( 'Permanently delete instead of moving to trash. Requires delete_others capability.', 'lax-abilities-toolkit' ),
					),
				),
				'required' => array( 'id' ),
			),
			'execute_callback'    => function ( $params ) use ( $post_type, $config ) {
				return lax_abilities_delete_post_handler( $params, $post_type, $config );
			},
			'permission_callback' => function ( $params ) use ( $caps ) {
				if ( empty( $params['id'] ) ) {
					return false;
				}
				$post_id = absint( $params['id'] );
				// map_meta_cap('delete_post', $id) respects post ownership automatically.
				if ( ! current_user_can( 'delete_post', $post_id ) ) {
					return false;
				}
				// Force-delete requires delete_others capability as an additional safeguard.
				if ( ! empty( $params['force_delete'] ) ) {
					return current_user_can( $caps['delete_others'] );
				}
				return true;
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
// Schema builders
// =============================================================================

/**
 * Builds the JSON Schema for the create ability's input.
 *
 * @since 1.0.0
 *
 * @param  array  $config     Post type configuration.
 * @param  string $post_type  WP post type slug.
 * @return array              JSON Schema object.
 */
function lax_abilities_build_create_schema( $config, $post_type ) {
	$props = array(
		'title'   => array(
			'type'        => 'string',
			'description' => __( 'Title.', 'lax-abilities-toolkit' ),
		),
		'content' => array(
			'type'        => 'string',
			'description' => __( 'Content body. Accepts plain text, HTML, or existing Gutenberg block markup.', 'lax-abilities-toolkit' ),
			'default'     => '',
		),
		'content_format' => array(
			'type'        => 'string',
			'enum'        => array( 'auto', 'blocks', 'classic' ),
			'default'     => 'auto',
			'description' => __( 'How to store the content. "auto" converts to Gutenberg blocks when the block editor is active (recommended). "blocks" always converts to blocks. "classic" stores content as-is.', 'lax-abilities-toolkit' ),
		),
		'status'  => array(
			'type'        => 'string',
			'enum'        => array( 'draft', 'publish', 'future', 'pending', 'private' ),
			'default'     => 'draft',
			'description' => __( 'Post status. Use "future" together with publish_date to schedule.', 'lax-abilities-toolkit' ),
		),
		'excerpt'   => array(
			'type'        => 'string',
			'description' => __( 'Excerpt.', 'lax-abilities-toolkit' ),
			'default'     => '',
		),
		'author_id' => array(
			'type'        => 'integer',
			'description' => __( 'Author user ID. Defaults to the currently authenticated user.', 'lax-abilities-toolkit' ),
		),
		'featured_image_id' => array(
			'type'        => 'integer',
			'description' => __( 'Attachment ID to set as the featured image (post thumbnail). Use list-media to find attachment IDs. Pass 0 to remove the existing featured image.', 'lax-abilities-toolkit' ),
		),
	);

	if ( ! empty( $config['supports_schedule'] ) ) {
		$props['publish_date'] = array(
			'type'        => 'string',
			'description' => __( 'Schedule date/time in ISO 8601 or MySQL format (e.g. "2025-06-01 09:00:00"). Automatically sets status to "future" for future dates.', 'lax-abilities-toolkit' ),
		);
	}

	if ( ! empty( $config['supports_parent'] ) ) {
		$props['parent_id'] = array(
			'type'        => 'integer',
			'description' => __( 'Parent item ID (0 for top-level).', 'lax-abilities-toolkit' ),
			'default'     => 0,
		);
	}

	if ( ! empty( $config['supports_template'] ) ) {
		$props['template'] = array(
			'type'        => 'string',
			'description' => __( 'Page template filename relative to the theme (e.g. "page-full-width.php"). Empty string uses default template.', 'lax-abilities-toolkit' ),
			'default'     => '',
		);
	}

	foreach ( $config['taxonomies'] as $taxonomy ) {
		$tax_obj   = get_taxonomy( $taxonomy );
		$tax_label = $tax_obj ? strtolower( $tax_obj->labels->name ) : $taxonomy;
		$props[ $taxonomy . '_ids' ] = array(
			'type'        => 'array',
			'items'       => array( 'type' => 'integer' ),
			/* translators: %s: taxonomy name */
			'description' => sprintf( __( 'Array of %s term IDs.', 'lax-abilities-toolkit' ), $tax_label ),
			'default'     => array(),
		);
	}

	$schema = array(
		'type'       => 'object',
		'properties' => $props,
		'required'   => array( 'title' ),
	);

	/**
	 * Filters the input schema for a specific post type's create/update abilities.
	 *
	 * Use this to add extra fields (e.g. ACF fields, WooCommerce product data).
	 * The same filter applies to both create and update — extra fields should be
	 * optional so the update ability can supply only the fields being changed.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $schema    JSON Schema object (type: object, with properties/required).
	 * @param string $post_type The WP post type slug.
	 */
	return apply_filters( "lax_abilities_input_schema_{$post_type}", $schema, $post_type );
}

/**
 * Builds the JSON Schema for the update ability's input.
 *
 * Identical to the create schema but all fields are optional and `id` is required.
 *
 * @since 1.0.0
 *
 * @param  array  $config     Post type configuration.
 * @param  string $post_type  WP post type slug.
 * @return array              JSON Schema object.
 */
function lax_abilities_build_update_schema( $config, $post_type ) {
	$schema = lax_abilities_build_create_schema( $config, $post_type );

	// Prepend `id`, make all other fields optional, require only `id`.
	$schema['properties'] = array_merge(
		array(
			'id' => array(
				'type'        => 'integer',
				'description' => __( 'ID of the item to update.', 'lax-abilities-toolkit' ),
			),
		),
		$schema['properties']
	);
	$schema['required'] = array( 'id' );

	return $schema;
}

// =============================================================================
// Execute handlers
// =============================================================================

/**
 * Creates a new post.
 *
 * @since 1.0.0
 *
 * @param  array  $params    Validated input parameters.
 * @param  string $post_type WP post type slug.
 * @param  array  $config    Post type configuration.
 * @return array|WP_Error    Success response or WP_Error on failure.
 */
function lax_abilities_create_post_handler( $params, $post_type, $config ) {
	/**
	 * Fires before a new post is created via an Ability.
	 *
	 * @since 1.1.0
	 *
	 * @param array  $params    The raw input parameters.
	 * @param string $post_type The WP post type slug.
	 */
	do_action( "lax_abilities_before_create_{$post_type}", $params, $post_type );

	$raw_content = wp_kses_post( isset( $params['content'] ) ? $params['content'] : '' );
	$raw_content = lax_abilities_maybe_convert_to_blocks( $raw_content, $params );

	$post_data = array(
		'post_type'    => $post_type,
		'post_title'   => sanitize_text_field( $params['title'] ),
		'post_content' => $raw_content,
		'post_status'  => isset( $params['status'] ) ? sanitize_key( $params['status'] ) : 'draft',
		'post_author'  => isset( $params['author_id'] ) ? absint( $params['author_id'] ) : get_current_user_id(),
		'post_excerpt' => isset( $params['excerpt'] ) ? sanitize_text_field( $params['excerpt'] ) : '',
		'post_parent'  => isset( $params['parent_id'] ) ? absint( $params['parent_id'] ) : 0,
	);

	if ( ! empty( $config['supports_schedule'] ) && ! empty( $params['publish_date'] ) ) {
		$date = lax_abilities_parse_date( $params['publish_date'] );
		if ( null === $date ) {
			return new WP_Error(
				'lax_abilities_invalid_date',
				__( 'publish_date could not be parsed. Use ISO 8601 or MySQL format (e.g. "2025-06-01 09:00:00").', 'lax-abilities-toolkit' )
			);
		}
		$post_data['post_date']     = $date;
		$post_data['post_date_gmt'] = get_gmt_from_date( $date );
		// Auto-promote to future when no explicit status was given or publish was assumed.
		if ( ( ! isset( $params['status'] ) || 'publish' === $params['status'] ) && strtotime( $date ) > time() ) {
			$post_data['post_status'] = 'future';
		}
	}

	/**
	 * Filters the post data array before the post is inserted.
	 *
	 * Use this to set post meta (`meta_input`), override fields, or integrate
	 * with CPT plugins (e.g. WooCommerce, ACF).
	 *
	 * @since 1.0.0
	 *
	 * @param array  $post_data  Data array passed to wp_insert_post().
	 * @param array  $params     Raw input parameters from the ability call.
	 * @param string $post_type  The WP post type slug.
	 */
	$post_data = apply_filters( "lax_abilities_post_data_{$post_type}", $post_data, $params, $post_type );

	$post_id = wp_insert_post( $post_data, true );
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	lax_abilities_sync_taxonomies( $post_id, $params, $config['taxonomies'] );
	lax_abilities_sync_template( $post_id, $params, $config );

	if ( ! empty( $params['featured_image_id'] ) ) {
		set_post_thumbnail( $post_id, absint( $params['featured_image_id'] ) );
	}

	/**
	 * Fires after a new post has been created via an Ability.
	 *
	 * @since 1.1.0
	 *
	 * @param int    $post_id   The newly created post ID.
	 * @param array  $params    The raw input parameters.
	 * @param string $post_type The WP post type slug.
	 */
	do_action( "lax_abilities_after_create_{$post_type}", $post_id, $params, $post_type );

	return array_merge(
		array( 'success' => true ),
		lax_abilities_build_post_response( get_post( $post_id ), $post_type )
	);
}

/**
 * Updates an existing post.
 *
 * Authors may only update their own posts. Editors and above may update any post.
 * This is enforced by WordPress' map_meta_cap via the permission_callback.
 *
 * @since 1.0.0
 *
 * @param  array  $params    Validated input parameters.
 * @param  string $post_type WP post type slug.
 * @param  array  $config    Post type configuration.
 * @return array|WP_Error    Success response or WP_Error on failure.
 */
function lax_abilities_update_post_handler( $params, $post_type, $config ) {
	$post_id = absint( $params['id'] );
	$post    = get_post( $post_id );

	if ( ! $post || $post->post_type !== $post_type ) {
		return new WP_Error(
			'lax_abilities_not_found',
			/* translators: %s: singular post type label */
			sprintf( __( '%s not found.', 'lax-abilities-toolkit' ), $config['label'] )
		);
	}

	// Re-check ownership in the handler as a defence-in-depth measure.
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return new WP_Error(
			'lax_abilities_forbidden',
			__( 'You do not have permission to edit this item.', 'lax-abilities-toolkit' )
		);
	}

	/**
	 * Fires before an existing post is updated via an Ability.
	 *
	 * @since 1.1.0
	 *
	 * @param int    $post_id   The post ID being updated.
	 * @param array  $params    The raw input parameters.
	 * @param string $post_type The WP post type slug.
	 */
	do_action( "lax_abilities_before_update_{$post_type}", $post_id, $params, $post_type );

	$post_data = array( 'ID' => $post_id );

	if ( isset( $params['title'] ) ) {
		$post_data['post_title'] = sanitize_text_field( $params['title'] );
	}
	if ( isset( $params['content'] ) ) {
		$post_data['post_content'] = lax_abilities_maybe_convert_to_blocks(
			wp_kses_post( $params['content'] ),
			$params
		);
	}
	if ( isset( $params['status'] ) ) {
		$post_data['post_status'] = sanitize_key( $params['status'] );
	}
	if ( isset( $params['excerpt'] ) ) {
		$post_data['post_excerpt'] = sanitize_text_field( $params['excerpt'] );
	}
	if ( isset( $params['parent_id'] ) ) {
		$post_data['post_parent'] = absint( $params['parent_id'] );
	}

	if ( ! empty( $config['supports_schedule'] ) && ! empty( $params['publish_date'] ) ) {
		$date = lax_abilities_parse_date( $params['publish_date'] );
		if ( null === $date ) {
			return new WP_Error(
				'lax_abilities_invalid_date',
				__( 'publish_date could not be parsed. Use ISO 8601 or MySQL format.', 'lax-abilities-toolkit' )
			);
		}
		$post_data['post_date']     = $date;
		$post_data['post_date_gmt'] = get_gmt_from_date( $date );
		if ( ! isset( $params['status'] ) && strtotime( $date ) > time() ) {
			$post_data['post_status'] = 'future';
		}
	}

	/** This filter is documented in includes/post-types.php */
	$post_data = apply_filters( "lax_abilities_post_data_{$post_type}", $post_data, $params, $post_type );

	$result = wp_update_post( $post_data, true );
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	lax_abilities_sync_taxonomies( $post_id, $params, $config['taxonomies'] );
	lax_abilities_sync_template( $post_id, $params, $config );

	if ( isset( $params['featured_image_id'] ) ) {
		$fid = absint( $params['featured_image_id'] );
		if ( $fid > 0 ) {
			set_post_thumbnail( $post_id, $fid );
		} else {
			delete_post_thumbnail( $post_id );
		}
	}

	/**
	 * Fires after an existing post has been updated via an Ability.
	 *
	 * @since 1.1.0
	 *
	 * @param int    $post_id   The updated post ID.
	 * @param array  $params    The raw input parameters.
	 * @param string $post_type The WP post type slug.
	 */
	do_action( "lax_abilities_after_update_{$post_type}", $post_id, $params, $post_type );

	return array_merge(
		array( 'success' => true ),
		lax_abilities_build_post_response( get_post( $post_id ), $post_type )
	);
}

/**
 * Lists posts with results scoped to what the current user can read.
 *
 * Uses WP_Query with `perm=readable` so WordPress automatically filters
 * out posts the user is not allowed to see.
 *
 * @since 1.0.0
 *
 * @param  array  $params    Validated input parameters.
 * @param  string $post_type WP post type slug.
 * @param  array  $config    Post type configuration.
 * @return array             Success response with `items` array.
 */
function lax_abilities_list_posts_handler( $params, $post_type, $config ) {
	$limit  = isset( $params['limit'] ) ? min( absint( $params['limit'] ), 50 ) : 10;
	$status = isset( $params['status'] ) ? sanitize_key( $params['status'] ) : 'publish';

	$query = new WP_Query(
		array(
			'post_type'      => $post_type,
			'posts_per_page' => $limit,
			'post_status'    => $status,
			'perm'           => 'readable', // Automatically scopes to user's readable posts.
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		)
	);

	$items = array_map(
		function ( WP_Post $post ) use ( $post_type, $config ) {
			$item = array(
				'id'       => $post->ID,
				'title'    => $post->post_title,
				'slug'     => $post->post_name,
				'status'   => $post->post_status,
				'date'     => $post->post_date,
				'url'      => get_permalink( $post->ID ),
				'edit_url' => get_edit_post_link( $post->ID, 'raw' ),
				'author'   => get_the_author_meta( 'display_name', $post->post_author ),
				'excerpt'  => wp_trim_words( $post->post_content, 30 ),
			);

			if ( 'future' === $post->post_status ) {
				$item['scheduled_for']     = $post->post_date;
				$item['scheduled_for_gmt'] = $post->post_date_gmt;
			}

			foreach ( $config['taxonomies'] as $taxonomy ) {
				$item[ $taxonomy ] = wp_get_object_terms( $post->ID, $taxonomy, array( 'fields' => 'names' ) );
			}

			/**
			 * Filters a single item in the list response for a specific post type.
			 *
			 * @since 1.0.0
			 *
			 * @param array   $item      The item array.
			 * @param WP_Post $post      The post object.
			 * @param string  $post_type The WP post type slug.
			 */
			return apply_filters( "lax_abilities_list_item_{$post_type}", $item, $post, $post_type );
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
 * Gets full details of a single post.
 *
 * @since 1.0.0
 *
 * @param  array  $params    Validated input parameters.
 * @param  string $post_type WP post type slug.
 * @param  array  $config    Post type configuration.
 * @return array|WP_Error    Item detail array or WP_Error on failure.
 */
function lax_abilities_get_post_handler( $params, $post_type, $config ) {
	$post_id = absint( $params['id'] );
	$post    = get_post( $post_id );

	if ( ! $post || $post->post_type !== $post_type ) {
		return new WP_Error(
			'lax_abilities_not_found',
			sprintf( __( '%s not found.', 'lax-abilities-toolkit' ), $config['label'] )
		);
	}

	$item = array(
		'id'        => $post->ID,
		'title'     => $post->post_title,
		'content'   => $post->post_content,
		'excerpt'   => $post->post_excerpt,
		'slug'      => $post->post_name,
		'status'    => $post->post_status,
		'date'      => $post->post_date,
		'date_gmt'  => $post->post_date_gmt,
		'modified'  => $post->post_modified,
		'author_id' => (int) $post->post_author,
		'author'    => get_the_author_meta( 'display_name', $post->post_author ),
		'parent_id' => (int) $post->post_parent,
		'url'       => get_permalink( $post->ID ),
		'edit_url'  => get_edit_post_link( $post->ID, 'raw' ),
	);

	if ( 'future' === $post->post_status ) {
		$item['scheduled_for']     = $post->post_date;
		$item['scheduled_for_gmt'] = $post->post_date_gmt;
	}

	foreach ( $config['taxonomies'] as $taxonomy ) {
		$terms           = wp_get_object_terms( $post->ID, $taxonomy );
		$item[ $taxonomy ] = array_map(
			function ( WP_Term $t ) {
				return array(
					'id'   => $t->term_id,
					'name' => $t->name,
					'slug' => $t->slug,
				);
			},
			is_wp_error( $terms ) ? array() : $terms
		);
	}

	if ( ! empty( $config['supports_template'] ) ) {
		$item['template'] = get_page_template_slug( $post->ID );
	}

	/**
	 * Filters the full detail response for a specific post type.
	 *
	 * @since 1.0.0
	 *
	 * @param array   $item      The detail array.
	 * @param WP_Post $post      The post object.
	 * @param string  $post_type The WP post type slug.
	 */
	return apply_filters( "lax_abilities_post_detail_{$post_type}", $item, $post, $post_type );
}

/**
 * Deletes (trashes or permanently removes) a post.
 *
 * Default behaviour moves the post to trash. Pass `force_delete: true`
 * to permanently delete — this requires the `delete_others` capability
 * as an additional safeguard.
 *
 * @since 1.1.0
 *
 * @param  array  $params    Validated input parameters.
 * @param  string $post_type WP post type slug.
 * @param  array  $config    Post type configuration.
 * @return array|WP_Error    Success response or WP_Error on failure.
 */
function lax_abilities_delete_post_handler( $params, $post_type, $config ) {
	$post_id      = absint( $params['id'] );
	$force_delete = ! empty( $params['force_delete'] );
	$post         = get_post( $post_id );

	if ( ! $post || $post->post_type !== $post_type ) {
		return new WP_Error(
			'lax_abilities_not_found',
			sprintf( __( '%s not found.', 'lax-abilities-toolkit' ), $config['label'] )
		);
	}

	// Re-check capability in the handler as defence-in-depth.
	if ( ! current_user_can( 'delete_post', $post_id ) ) {
		return new WP_Error(
			'lax_abilities_forbidden',
			__( 'You do not have permission to delete this item.', 'lax-abilities-toolkit' )
		);
	}

	$caps = lax_abilities_get_caps( $config, $post_type );
	if ( $force_delete && ! current_user_can( $caps['delete_others'] ) ) {
		return new WP_Error(
			'lax_abilities_forbidden',
			__( 'Permanent deletion requires elevated capabilities. Use force_delete: false to trash instead.', 'lax-abilities-toolkit' )
		);
	}

	$title = $post->post_title;

	/**
	 * Fires before a post is deleted via an Ability.
	 *
	 * @since 1.1.0
	 *
	 * @param int    $post_id      The post ID being deleted.
	 * @param bool   $force_delete Whether this is a permanent deletion.
	 * @param string $post_type    The WP post type slug.
	 */
	do_action( "lax_abilities_before_delete_{$post_type}", $post_id, $force_delete, $post_type );

	$result = wp_delete_post( $post_id, $force_delete );

	if ( ! $result ) {
		return new WP_Error(
			'lax_abilities_delete_failed',
			__( 'Could not delete the item. It may have already been deleted.', 'lax-abilities-toolkit' )
		);
	}

	/**
	 * Fires after a post has been deleted via an Ability.
	 *
	 * @since 1.1.0
	 *
	 * @param int    $post_id      The deleted post ID.
	 * @param bool   $force_delete Whether this was a permanent deletion.
	 * @param string $post_type    The WP post type slug.
	 */
	do_action( "lax_abilities_after_delete_{$post_type}", $post_id, $force_delete, $post_type );

	return array(
		'success'      => true,
		'id'           => $post_id,
		'force_delete' => $force_delete,
		'message'      => $force_delete
			? sprintf( __( '"%s" permanently deleted.', 'lax-abilities-toolkit' ), $title )
			: sprintf( __( '"%s" moved to trash.', 'lax-abilities-toolkit' ), $title ),
	);
}

// =============================================================================
// Internal utilities
// =============================================================================

/**
 * Converts content to Gutenberg block markup based on the `content_format` param.
 *
 * Called by both the create and update handlers before saving `post_content`.
 *
 * @since 1.2.0
 *
 * @param  string $content Raw content (plain text, HTML, or already blocks).
 * @param  array  $params  Input parameters (reads `content_format` key).
 * @return string          Possibly-converted content.
 */
function lax_abilities_maybe_convert_to_blocks( $content, $params ) {
	$format = isset( $params['content_format'] ) ? $params['content_format'] : 'auto';

	if ( 'classic' === $format ) {
		return $content;
	}

	if ( 'blocks' === $format || lax_abilities_is_block_editor_active() ) {
		return lax_abilities_content_to_blocks( $content );
	}

	return $content;
}

/**
 * Syncs taxonomy terms for a post from the input params.
 *
 * @since 1.0.0
 *
 * @param  int      $post_id    Post ID.
 * @param  array    $params     Input params.
 * @param  string[] $taxonomies Taxonomy slugs to sync.
 * @return void
 */
function lax_abilities_sync_taxonomies( $post_id, $params, $taxonomies ) {
	foreach ( $taxonomies as $taxonomy ) {
		$key = $taxonomy . '_ids';
		if ( isset( $params[ $key ] ) && is_array( $params[ $key ] ) ) {
			wp_set_object_terms( $post_id, array_map( 'absint', $params[ $key ] ), $taxonomy );
		}
	}
}

/**
 * Saves the page template meta field when the post type supports it.
 *
 * @since 1.0.0
 *
 * @param  int   $post_id Post ID.
 * @param  array $params  Input params.
 * @param  array $config  Post type configuration.
 * @return void
 */
function lax_abilities_sync_template( $post_id, $params, $config ) {
	if ( ! empty( $config['supports_template'] ) && isset( $params['template'] ) ) {
		update_post_meta( $post_id, '_wp_page_template', sanitize_text_field( $params['template'] ) );
	}
}
