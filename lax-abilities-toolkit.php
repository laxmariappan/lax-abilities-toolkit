<?php
/**
 * Plugin Name: Lax Abilities Toolkit
 * Plugin URI:  https://github.com/laxmariappan/lax-abilities-toolkit
 * Description: Exposes WordPress content management as Abilities via the WP Abilities API. Supports any post type or taxonomy via filter hooks. Works with MCP Adapter to connect Claude, Cursor, VS Code, and any MCP-compatible AI client to your WordPress site.
 * Version:     1.2.0
 * Author:      Lax Mariappan
 * Author URI:  https://laxmariappan.com
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: lax-abilities-toolkit
 * Domain Path: /languages
 *
 * @package LaxAbilitiesToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin version.
 *
 * @since 1.0.0
 * @var string
 */
define( 'LAX_ABILITIES_VERSION', '1.2.0' );

/**
 * Absolute path to the plugin directory (with trailing slash).
 *
 * @since 1.0.0
 * @var string
 */
define( 'LAX_ABILITIES_DIR', plugin_dir_path( __FILE__ ) );

/**
 * URL to the plugin directory (with trailing slash).
 *
 * @since 1.0.0
 * @var string
 */
define( 'LAX_ABILITIES_URL', plugin_dir_url( __FILE__ ) );

/**
 * The WP Ability category slug used by this plugin.
 *
 * @since 1.0.0
 * @var string
 */
define( 'LAX_ABILITIES_CATEGORY', 'lax-abilities' );

// Load includes.
require_once LAX_ABILITIES_DIR . 'includes/helpers.php';
require_once LAX_ABILITIES_DIR . 'includes/blocks.php';
require_once LAX_ABILITIES_DIR . 'includes/post-types.php';
require_once LAX_ABILITIES_DIR . 'includes/taxonomies.php';
require_once LAX_ABILITIES_DIR . 'includes/media.php';
require_once LAX_ABILITIES_DIR . 'includes/site.php';
require_once LAX_ABILITIES_DIR . 'includes/admin.php';

/**
 * Registers the lax-abilities category before abilities that reference it.
 *
 * Hooked on `wp_abilities_api_categories_init`, which fires before
 * `wp_abilities_api_init`. Skips registration when another plugin has already
 * claimed the same category slug.
 *
 * @since 1.0.0
 * @return void
 */
function lax_abilities_register_category() {
	if ( ! wp_has_ability_category( LAX_ABILITIES_CATEGORY ) ) {
		wp_register_ability_category(
			LAX_ABILITIES_CATEGORY,
			array(
				'label'       => __( 'Lax Abilities', 'lax-abilities-toolkit' ),
				'description' => __( 'Content management abilities for posts, pages, taxonomies, media, and more.', 'lax-abilities-toolkit' ),
			)
		);
	}
}
add_action( 'wp_abilities_api_categories_init', 'lax_abilities_register_category' );

/**
 * Registers all abilities from all sub-modules.
 *
 * @since 1.0.0
 * @return void
 */
function lax_abilities_register_all() {
	if ( ! function_exists( 'wp_register_ability' ) ) {
		add_action( 'admin_notices', 'lax_abilities_api_notice' );
		return;
	}

	lax_abilities_register_post_type_abilities_all();
	lax_abilities_register_taxonomy_abilities_all();
	lax_abilities_register_media_abilities();
	lax_abilities_register_site_abilities();
}
add_action( 'wp_abilities_api_init', 'lax_abilities_register_all' );

/**
 * Displays an admin notice when the WP Abilities API is unavailable.
 *
 * @since 1.0.0
 * @return void
 */
function lax_abilities_api_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: Minimum WordPress version */
				esc_html__( 'Lax Abilities Toolkit requires WordPress %s or higher with the Abilities API.', 'lax-abilities-toolkit' ),
				'6.9'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Adds a Settings link on the Plugins list page.
 *
 * @since 1.0.0
 *
 * @param  string[] $links Plugin action links.
 * @return string[]
 */
function lax_abilities_plugin_links( $links ) {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( admin_url( 'options-general.php?page=lax-abilities-toolkit' ) ),
		esc_html__( 'Settings', 'lax-abilities-toolkit' )
	);
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'lax_abilities_plugin_links' );
