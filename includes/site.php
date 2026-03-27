<?php
/**
 * Site info ability.
 *
 * @package LaxAbilitiesToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the site-info ability.
 */
function lax_abilities_register_site_abilities() {
	wp_register_ability( 'lax-abilities/site-info', array(
		'label'       => __( 'Get Site Info', 'lax-abilities-toolkit' ),
		'description' => __( 'Returns site name, URL, tagline, WordPress version, timezone, and language.', 'lax-abilities-toolkit' ),
		'category'    => LAX_ABILITIES_CATEGORY,
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'name'        => array( 'type' => 'string', 'description' => __( 'Site name.', 'lax-abilities-toolkit' ) ),
				'url'         => array( 'type' => 'string', 'format' => 'uri', 'description' => __( 'Site URL.', 'lax-abilities-toolkit' ) ),
				'description' => array( 'type' => 'string', 'description' => __( 'Site tagline.', 'lax-abilities-toolkit' ) ),
				'wp_version'  => array( 'type' => 'string', 'description' => __( 'WordPress version.', 'lax-abilities-toolkit' ) ),
				'timezone'    => array( 'type' => 'string', 'description' => __( 'Site timezone string (e.g. "America/New_York").', 'lax-abilities-toolkit' ) ),
				'language'    => array( 'type' => 'string', 'description' => __( 'Site language (e.g. "en-US").', 'lax-abilities-toolkit' ) ),
				'admin_email' => array( 'type' => 'string', 'description' => __( 'Admin email address.', 'lax-abilities-toolkit' ) ),
			),
		),
		'execute_callback'    => 'lax_abilities_site_info_handler',
		'permission_callback' => '__return_true',
		'meta' => array(
			'show_in_rest' => true,
			'mcp'          => array( 'public' => true ),
			'annotations'  => array( 'readonly' => true, 'destructive' => false ),
		),
	) );
}

/**
 * Execute handler: return site information.
 *
 * @return array
 */
function lax_abilities_site_info_handler() {
	return array(
		'name'        => get_bloginfo( 'name' ),
		'url'         => home_url(),
		'description' => get_bloginfo( 'description' ),
		'wp_version'  => get_bloginfo( 'version' ),
		'timezone'    => wp_timezone_string(),
		'language'    => get_bloginfo( 'language' ),
		'admin_email' => get_bloginfo( 'admin_email' ),
	);
}
