<?php
/**
 * Admin settings page: React-powered UI for MCP connection setup.
 *
 * The admin UI is built with @wordpress/components and compiled by wp-scripts
 * into build/admin.js. This file is responsible only for:
 *  1. Registering the settings page.
 *  2. Enqueueing the compiled React bundle.
 *  3. Passing PHP data to JavaScript via wp_localize_script.
 *  4. Rendering the root mount point div.
 *
 * @package LaxAbilitiesToolkit
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the plugin settings page under Settings.
 *
 * @since 1.1.0
 *
 * @return void
 */
function lax_abilities_admin_menu() {
	add_options_page(
		__( 'Lax Abilities Toolkit', 'lax-abilities-toolkit' ),
		__( 'Lax Abilities', 'lax-abilities-toolkit' ),
		'manage_options',
		'lax-abilities-toolkit',
		'lax_abilities_settings_page'
	);
}
add_action( 'admin_menu', 'lax_abilities_admin_menu' );

/**
 * Enqueues the React admin bundle and passes PHP data to JS.
 *
 * Only runs on the plugin's own settings page.
 *
 * @since 1.3.0
 *
 * @param string $hook_suffix Current admin page hook suffix.
 * @return void
 */
function lax_abilities_admin_enqueue( $hook_suffix ) {
	if ( 'settings_page_lax-abilities-toolkit' !== $hook_suffix ) {
		return;
	}

	// wp-scripts generates an asset file with the correct dependency list and
	// a content hash version string.
	// wp-scripts names output files after the entry filename (index.js → index.js / index.asset.php).
	$asset_file = LAX_ABILITIES_DIR . 'build/index.asset.php';
	$asset      = file_exists( $asset_file )
		? require $asset_file
		: array(
			'dependencies' => array( 'wp-element', 'wp-components', 'wp-i18n' ),
			'version'      => LAX_ABILITIES_VERSION,
		);

	wp_enqueue_script(
		'lax-abilities-admin',
		LAX_ABILITIES_URL . 'build/index.js',
		$asset['dependencies'],
		$asset['version'],
		true // Load in footer.
	);

	// wp-scripts outputs imported CSS as style-index.css (with an RTL variant).
	if ( file_exists( LAX_ABILITIES_DIR . 'build/style-index.css' ) ) {
		wp_enqueue_style(
			'lax-abilities-admin',
			LAX_ABILITIES_URL . 'build/style-index.css',
			array( 'wp-components' ),
			$asset['version']
		);
	}

	// Build the list of registered lax-abilities for display in the UI.
	$abilities = array();
	if ( function_exists( 'wp_get_abilities' ) ) {
		foreach ( wp_get_abilities() as $ability ) {
			if ( 0 === strpos( $ability->get_name(), 'lax-abilities/' ) ) {
				$abilities[] = array(
					'name'        => $ability->get_name(),
					'label'       => $ability->get_label(),
					'description' => $ability->get_description(),
				);
			}
		}
	}

	$mcp_adapter_active = class_exists( 'MCP_Adapter\\Plugin' )
		|| function_exists( 'mcp_adapter_init' )
		|| ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'mcp-adapter/mcp-adapter.php' ) );

	// Build per-group toggle data for the settings UI.
	$groups = array();

	// Post types.
	if ( function_exists( 'lax_abilities_all_post_types' ) ) {
		foreach ( lax_abilities_all_post_types() as $slug => $config ) {
			$groups[] = array(
				'key'         => $slug,
				'label'       => $config['label'],
				'description' => sprintf(
					/* translators: %s: post type label */
					__( 'Abilities for the %s post type (create, list, get, update, delete).', 'lax-abilities-toolkit' ),
					$config['label']
				),
				'enabled'     => lax_abilities_is_group_enabled( $slug ),
			);
		}
	}

	// Taxonomies.
	if ( function_exists( 'lax_abilities_all_taxonomies' ) ) {
		foreach ( lax_abilities_all_taxonomies() as $slug => $config ) {
			$groups[] = array(
				'key'         => $slug,
				'label'       => $config['label'],
				'description' => sprintf(
					/* translators: %s: taxonomy plural label */
					__( 'Abilities for %s (create, list, delete).', 'lax-abilities-toolkit' ),
					$config['plural']
				),
				'enabled'     => lax_abilities_is_group_enabled( $slug ),
			);
		}
	}

	// Media.
	$groups[] = array(
		'key'         => 'media',
		'label'       => __( 'Media Library', 'lax-abilities-toolkit' ),
		'description' => __( 'List, get, and delete media library items.', 'lax-abilities-toolkit' ),
		'enabled'     => lax_abilities_is_group_enabled( 'media' ),
	);

	// Site info.
	$groups[] = array(
		'key'         => 'site-info',
		'label'       => __( 'Site Info', 'lax-abilities-toolkit' ),
		'description' => __( 'Retrieve site name, URL, tagline, WordPress version, timezone, and language.', 'lax-abilities-toolkit' ),
		'enabled'     => lax_abilities_is_group_enabled( 'site-info' ),
	);

	wp_localize_script(
		'lax-abilities-admin',
		'laxAbilitiesAdmin',
		array(
			'mcpEndpoint'       => trailingslashit( home_url() ) . 'wp-json/mcp/mcp-adapter-default-server',
			'siteUrl'           => home_url(),
			'siteName'          => get_bloginfo( 'name' ),
			'serverKey'         => 'wordpress-' . sanitize_title( wp_parse_url( home_url(), PHP_URL_HOST ) ),
			'username'          => wp_get_current_user()->user_login,
			'hasAbilitiesApi'   => function_exists( 'wp_register_ability' ),
			'hasAdapter'        => $mcp_adapter_active,
			'blockEditorActive' => function_exists( 'lax_abilities_is_block_editor_active' )
				? lax_abilities_is_block_editor_active()
				: false,
			'abilities'         => $abilities,
			'groups'            => $groups,
			'settingsEndpoint'  => rest_url( 'lax-abilities/v1/settings' ),
			'nonce'             => wp_create_nonce( 'wp_rest' ),
			'appPasswordUrl'    => admin_url( 'profile.php#application-passwords-section' ),
			'version'           => LAX_ABILITIES_VERSION,
		)
	);

	// Allow JS strings to be translated via wp i18n.
	wp_set_script_translations( 'lax-abilities-admin', 'lax-abilities-toolkit' );
}
add_action( 'admin_enqueue_scripts', 'lax_abilities_admin_enqueue' );

// =============================================================================
// Settings page renderer
// =============================================================================

/**
 * Renders the React mount point for the settings page.
 *
 * The actual UI is rendered by the React bundle loaded above.
 *
 * @since 1.1.0
 *
 * @return void
 */
function lax_abilities_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	echo '<div id="lax-abilities-admin-root" style="padding:16px 0"></div>';
}
