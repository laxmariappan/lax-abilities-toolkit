<?php
/**
 * Ability group settings.
 *
 * Stores per-group enabled/disabled state in wp_options and exposes a REST
 * endpoint so the React admin UI can read and write settings without a page
 * reload.
 *
 * Groups map 1-to-1 with the ability registration modules:
 *  - one entry per registered post type  (e.g. 'post', 'page', 'product')
 *  - one entry per registered taxonomy   (e.g. 'category', 'post_tag')
 *  - 'media'     → list / get / delete media abilities
 *  - 'site-info' → site-info ability
 *
 * Any group key not present in the saved option defaults to *enabled*, so
 * custom post types added by developers via filter work out of the box.
 *
 * @package LaxAbilitiesToolkit
 * @since   1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** wp_options key used to store group settings. */
define( 'LAX_ABILITIES_GROUPS_OPTION', 'lax_abilities_groups' );

// =============================================================================
// Public helpers
// =============================================================================

/**
 * Returns the raw saved option array (may be empty on a fresh install).
 *
 * @since 1.3.0
 *
 * @return array<string, bool> Group key → enabled state.
 */
function lax_abilities_get_groups_option() {
	return (array) get_option( LAX_ABILITIES_GROUPS_OPTION, array() );
}

/**
 * Returns true when the given ability group is enabled.
 *
 * Groups not present in the saved option are treated as enabled so that
 * custom post types / taxonomies added via developer filters are not
 * accidentally silenced on first use.
 *
 * @since 1.3.0
 *
 * @param string $key Group key (WP post type slug, taxonomy slug, or 'media' / 'site-info').
 * @return bool
 */
function lax_abilities_is_group_enabled( $key ) {
	$saved = lax_abilities_get_groups_option();
	return isset( $saved[ $key ] ) ? (bool) $saved[ $key ] : true;
}

/**
 * Sanitises and persists new group settings.
 *
 * @since 1.3.0
 *
 * @param  array<string, mixed> $new_groups Key → truthy/falsy map.
 * @return array<string, bool>              Sanitised values as stored.
 */
function lax_abilities_save_groups( array $new_groups ) {
	$sanitized = array();
	foreach ( $new_groups as $key => $value ) {
		$sanitized[ sanitize_key( $key ) ] = (bool) $value;
	}
	update_option( LAX_ABILITIES_GROUPS_OPTION, $sanitized );
	return $sanitized;
}

// =============================================================================
// REST API
// =============================================================================

/**
 * Registers GET and POST endpoints for the group settings.
 *
 * Both endpoints require `manage_options` capability, which limits access to
 * site administrators.
 *
 * @since 1.3.0
 *
 * @return void
 */
function lax_abilities_register_settings_endpoint() {
	register_rest_route(
		'lax-abilities/v1',
		'/settings',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'lax_abilities_rest_get_settings',
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'lax_abilities_rest_save_settings',
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
				'args' => array(
					'groups' => array(
						'type'     => 'object',
						'required' => true,
					),
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'lax_abilities_register_settings_endpoint' );

/**
 * REST GET handler — returns the current group settings.
 *
 * @since 1.3.0
 *
 * @return WP_REST_Response
 */
function lax_abilities_rest_get_settings() {
	return rest_ensure_response( array( 'groups' => lax_abilities_get_groups_option() ) );
}

/**
 * REST POST handler — saves new group settings.
 *
 * @since 1.3.0
 *
 * @param  WP_REST_Request $request Incoming request.
 * @return WP_REST_Response
 */
function lax_abilities_rest_save_settings( WP_REST_Request $request ) {
	$body   = $request->get_json_params();
	$groups = isset( $body['groups'] ) ? (array) $body['groups'] : array();
	$saved  = lax_abilities_save_groups( $groups );
	return rest_ensure_response( array( 'groups' => $saved ) );
}
