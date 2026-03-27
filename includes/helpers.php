<?php
/**
 * Shared helper functions for Lax Abilities Toolkit.
 *
 * @package LaxAbilitiesToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts a post type or taxonomy key to a safe ability-name slug.
 * WP ability names only allow [a-z0-9-] between the namespace and the slash.
 *
 * @param  string $key  e.g. "post_tag", "acf-field-group"
 * @return string       e.g. "post-tag", "acf-field-group"
 */
function lax_abilities_to_slug( $key ) {
	return strtolower( preg_replace( '/[^a-z0-9-]/', '-', $key ) );
}

/**
 * Parses a human-readable or ISO 8601 date string into MySQL format (Y-m-d H:i:s).
 *
 * @param  string $date_string
 * @return string|null  MySQL-format date on success, null on failure.
 */
function lax_abilities_parse_date( $date_string ) {
	$timestamp = strtotime( $date_string );
	if ( false === $timestamp || $timestamp <= 0 ) {
		return null;
	}
	return date( 'Y-m-d H:i:s', $timestamp );
}

/**
 * Builds a normalised response array for a post or post-like object.
 *
 * Includes scheduling metadata when the post status is "future" so callers
 * know exactly when the post will go live.
 *
 * Developers can extend the response for a specific post type:
 *
 *     add_filter( 'lax_abilities_post_response_product', function( $response, $post ) {
 *         $response['price'] = get_post_meta( $post->ID, '_price', true );
 *         return $response;
 *     }, 10, 2 );
 *
 * @param  WP_Post $post
 * @param  string  $post_type
 * @return array
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
			/* translators: 1: title, 2: scheduled datetime */
			__( '"%1$s" scheduled for %2$s.', 'lax-abilities-toolkit' ),
			$post->post_title,
			$post->post_date
		);
	} else {
		$response['message'] = sprintf(
			/* translators: 1: title, 2: status */
			__( '"%1$s" saved with status: %2$s.', 'lax-abilities-toolkit' ),
			$post->post_title,
			$post->post_status
		);
	}

	if ( in_array( $post->post_status, array( 'draft', 'future', 'pending', 'private' ), true ) ) {
		$response['preview_url'] = get_preview_post_link( $post->ID );
	}

	/**
	 * Filters the post response for a specific post type.
	 *
	 * @param array   $response  The response array.
	 * @param WP_Post $post      The post object.
	 */
	return apply_filters( "lax_abilities_post_response_{$post_type}", $response, $post );
}
