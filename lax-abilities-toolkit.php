<?php
/**
 * Plugin Name: Lax Abilities Toolkit
 * Plugin URI:  https://github.com/laxmariappan/lax-abilities-toolkit
 * Description: A developer-friendly WordPress toolkit that exposes content management as Abilities via the WP Abilities API. Supports any post type or taxonomy via filter hooks — works with WooCommerce, ACF, and any custom CPT out of the box.
 * Version:     1.0.0
 * Author:      Lax Mariappan
 * Author URI:  https://laxmariappan.com
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: lax-abilities-toolkit
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LAX_ABILITIES_VERSION',  '1.0.0' );
define( 'LAX_ABILITIES_DIR',      plugin_dir_path( __FILE__ ) );
define( 'LAX_ABILITIES_CATEGORY', 'lax-abilities' );

require_once LAX_ABILITIES_DIR . 'includes/helpers.php';
require_once LAX_ABILITIES_DIR . 'includes/post-types.php';
require_once LAX_ABILITIES_DIR . 'includes/taxonomies.php';
require_once LAX_ABILITIES_DIR . 'includes/site.php';

/**
 * Register the lax-abilities category before abilities are registered.
 * Runs on wp_abilities_api_categories_init (fires before wp_abilities_api_init).
 * Skips registration if another plugin has already claimed this category.
 */
add_action( 'wp_abilities_api_categories_init', 'lax_abilities_register_category' );

function lax_abilities_register_category() {
	if ( ! wp_has_ability_category( LAX_ABILITIES_CATEGORY ) ) {
		wp_register_ability_category( LAX_ABILITIES_CATEGORY, array(
			'label'       => __( 'Lax Abilities', 'lax-abilities-toolkit' ),
			'description' => __( 'Content management abilities for posts, pages, taxonomies and more.', 'lax-abilities-toolkit' ),
		) );
	}
}

/**
 * Register all abilities.
 */
add_action( 'wp_abilities_api_init', 'lax_abilities_register_all' );

function lax_abilities_register_all() {
	if ( ! function_exists( 'wp_register_ability' ) ) {
		add_action( 'admin_notices', 'lax_abilities_api_notice' );
		return;
	}

	lax_abilities_register_post_type_abilities_all();
	lax_abilities_register_taxonomy_abilities_all();
	lax_abilities_register_site_abilities();
}

/**
 * Admin notice when the WP Abilities API is unavailable.
 */
function lax_abilities_api_notice() {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'Lax Abilities Toolkit requires WordPress 6.9 or higher with the Abilities API.', 'lax-abilities-toolkit' ); ?></p>
	</div>
	<?php
}

/**
 * Plugin action links.
 */
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'lax_abilities_plugin_links' );

function lax_abilities_plugin_links( $links ) {
	if ( function_exists( 'admin_url' ) ) {
		array_unshift( $links, sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'options-general.php?page=mcp-adapter' ),
			__( 'MCP Settings', 'lax-abilities-toolkit' )
		) );
	}
	return $links;
}
