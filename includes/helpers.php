<?php
/**
 * Shared helper functions for Lax Abilities Toolkit.
 *
 * @package LaxAbilitiesToolkit
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// =============================================================================
// Slug & string utilities
// =============================================================================

/**
 * Converts any string to a safe ability-name slug.
 *
 * WP ability names may only contain [a-z0-9-] in each segment.
 *
 * @since 1.0.0
 *
 * @param  string $key  Raw string, e.g. "Post Tag", "acf-field-group", "product_cat".
 * @return string       Sanitised slug, e.g. "post-tag", "acf-field-group", "product-cat".
 */
function lax_abilities_to_slug( $key ) {
	return strtolower( preg_replace( '/[^a-z0-9-]+/i', '-', $key ) );
}

// =============================================================================
// Date utilities
// =============================================================================

/**
 * Parses a human-readable or ISO 8601 date string into MySQL format (Y-m-d H:i:s).
 *
 * @since 1.0.0
 *
 * @param  string $date_string  Any date/time string recognised by strtotime().
 * @return string|null          MySQL-format datetime string, or null on failure.
 */
function lax_abilities_parse_date( $date_string ) {
	$timestamp = strtotime( $date_string );
	if ( false === $timestamp || $timestamp <= 0 ) {
		return null;
	}
	return gmdate( 'Y-m-d H:i:s', $timestamp );
}

// =============================================================================
// Capability resolution
// =============================================================================

/**
 * Resolves the full set of capability strings for a given post type config.
 *
 * Resolution order:
 *  1. Uses WP's registered post type object when the type is registered —
 *     this is the most accurate source and handles custom `capabilities` arrays.
 *  2. Falls back to deriving caps from `capability_type` (e.g. "product" →
 *     "publish_products").
 *  3. Merges any explicit `capabilities` keys from the config array last,
 *     allowing total override.
 *
 * @since 1.1.0
 *
 * @param  array  $config     Post type configuration array.
 * @param  string $post_type  WP post type slug.
 * @return array {
 *     @type string $publish       Capability required to publish new items.
 *     @type string $edit_own      Capability required to edit own items.
 *     @type string $edit_others   Capability required to edit others' items.
 *     @type string $delete_own    Capability required to delete own items.
 *     @type string $delete_others Capability required to delete others' items.
 *     @type string $read_private  Capability required to read private items.
 * }
 */
function lax_abilities_get_caps( $config, $post_type = '' ) {
	$cap_type = isset( $config['capability_type'] ) ? $config['capability_type'] : 'post';

	// Try WP's registered post type object first — most accurate.
	if ( $post_type && post_type_exists( $post_type ) ) {
		$pto = get_post_type_object( $post_type );
		if ( $pto && isset( $pto->cap ) ) {
			$resolved = array(
				'publish'       => $pto->cap->publish_posts,
				'edit_own'      => $pto->cap->edit_posts,
				'edit_others'   => $pto->cap->edit_others_posts,
				'delete_own'    => $pto->cap->delete_posts,
				'delete_others' => $pto->cap->delete_others_posts,
				'read_private'  => $pto->cap->read_private_posts,
			);
			// Merge explicit overrides from the filter config.
			if ( ! empty( $config['capabilities'] ) && is_array( $config['capabilities'] ) ) {
				$resolved = array_merge( $resolved, $config['capabilities'] );
			}
			return $resolved;
		}
	}

	// Derive from capability_type.
	$defaults = array(
		'publish'       => 'publish_' . $cap_type . 's',
		'edit_own'      => 'edit_' . $cap_type . 's',
		'edit_others'   => 'edit_others_' . $cap_type . 's',
		'delete_own'    => 'delete_' . $cap_type . 's',
		'delete_others' => 'delete_others_' . $cap_type . 's',
		'read_private'  => 'read_private_' . $cap_type . 's',
	);

	if ( ! empty( $config['capabilities'] ) && is_array( $config['capabilities'] ) ) {
		return array_merge( $defaults, $config['capabilities'] );
	}

	return $defaults;
}

// =============================================================================
// Response builders
// =============================================================================

/**
 * Builds a normalised response array for a post or post-like object.
 *
 * Automatically includes scheduling metadata (`scheduled_for`,
 * `scheduled_for_gmt`) when the post status is "future", so callers know
 * exactly when the item will go live.
 *
 * Developers can extend the response for a specific post type:
 *
 *     add_filter(
 *         'lax_abilities_post_response_product',
 *         function( array $response, WP_Post $post ): array {
 *             $response['price'] = get_post_meta( $post->ID, '_price', true );
 *             return $response;
 *         },
 *         10,
 *         2
 *     );
 *
 * @since 1.0.0
 *
 * @param  WP_Post $post       The post object.
 * @param  string  $post_type  WP post type slug (used for filter name).
 * @return array               Normalised response array.
 */
function lax_abilities_build_post_response( WP_Post $post, $post_type = 'post' ) {
	$response = array(
		'id'       => $post->ID,
		'url'      => get_permalink( $post->ID ),
		'slug'     => $post->post_name,
		'status'   => $post->post_status,
		'edit_url' => get_edit_post_link( $post->ID, 'raw' ),
	);

	if ( 'future' === $post->post_status ) {
		$response['scheduled_for']     = $post->post_date;
		$response['scheduled_for_gmt'] = $post->post_date_gmt;
		$response['message']           = sprintf(
			/* translators: 1: post title, 2: scheduled local datetime */
			__( '"%1$s" scheduled for %2$s.', 'lax-abilities-toolkit' ),
			$post->post_title,
			$post->post_date
		);
	} else {
		$response['message'] = sprintf(
			/* translators: 1: post title, 2: post status */
			__( '"%1$s" saved with status: %2$s.', 'lax-abilities-toolkit' ),
			$post->post_title,
			$post->post_status
		);
	}

	if ( in_array( $post->post_status, array( 'draft', 'future', 'pending', 'private' ), true ) ) {
		$response['preview_url'] = get_preview_post_link( $post->ID );
	}

	/**
	 * Filters the response returned after creating or updating a post.
	 *
	 * The filter name is dynamic: `lax_abilities_post_response_{$post_type}`.
	 * For example, for WooCommerce products: `lax_abilities_post_response_product`.
	 *
	 * @since 1.0.0
	 *
	 * @param array   $response  The response array.
	 * @param WP_Post $post      The post object.
	 */
	return apply_filters( "lax_abilities_post_response_{$post_type}", $response, $post );
}
