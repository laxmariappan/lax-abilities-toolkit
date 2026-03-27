<?php
/**
 * Admin settings page: MCP connection info and configuration snippets.
 *
 * Provides a single-page admin UI that shows the MCP server endpoint,
 * guides users through creating an Application Password, and generates
 * ready-to-paste configuration snippets for Claude Desktop, Cursor,
 * VS Code, and any other MCP-compatible client.
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
 * Enqueues admin page styles.
 *
 * @since 1.1.0
 *
 * @param string $hook_suffix The current admin page hook suffix.
 * @return void
 */
function lax_abilities_admin_styles( $hook_suffix ) {
	if ( 'settings_page_lax-abilities-toolkit' !== $hook_suffix ) {
		return;
	}
	// Inline styles — no external file needed for this small page.
	wp_add_inline_style(
		'wp-admin',
		'
		.lax-abilities-wrap { max-width: 860px; }
		.lax-abilities-wrap h2 { margin-top: 2em; }
		.lax-abilities-wrap .lax-card {
			background: #fff;
			border: 1px solid #c3c4c7;
			border-radius: 4px;
			padding: 16px 20px;
			margin: 12px 0 20px;
		}
		.lax-abilities-wrap .lax-card h3 { margin-top: 0; }
		.lax-abilities-wrap pre {
			background: #1e1e2e;
			color: #cdd6f4;
			padding: 14px 16px;
			border-radius: 4px;
			overflow-x: auto;
			font-size: 12px;
			line-height: 1.6;
			white-space: pre-wrap;
			word-break: break-all;
		}
		.lax-abilities-wrap .lax-endpoint {
			font-family: monospace;
			background: #f0f0f1;
			padding: 8px 12px;
			border-radius: 3px;
			display: inline-block;
			word-break: break-all;
		}
		.lax-abilities-wrap .lax-status-ok  { color: #00a32a; font-weight: 600; }
		.lax-abilities-wrap .lax-status-err { color: #d63638; font-weight: 600; }
		.lax-abilities-wrap .nav-tab-wrapper { margin-bottom: 0; }
		.lax-abilities-wrap .tab-content { display: none; padding-top: 4px; }
		.lax-abilities-wrap .tab-content.active { display: block; }
		.lax-abilities-copy-btn {
			cursor: pointer;
			margin-left: 8px;
			vertical-align: middle;
		}
		'
	);

	wp_add_inline_script(
		'jquery',
		'
		jQuery( function( $ ) {
			// Tab switching.
			$( ".lax-abilities-wrap" ).on( "click", ".nav-tab", function( e ) {
				e.preventDefault();
				var target = $( this ).data( "tab" );
				$( ".nav-tab" ).removeClass( "nav-tab-active" );
				$( this ).addClass( "nav-tab-active" );
				$( ".tab-content" ).removeClass( "active" );
				$( "#tab-" + target ).addClass( "active" );
			} );
			// Copy-to-clipboard.
			$( ".lax-abilities-wrap" ).on( "click", ".lax-abilities-copy-btn", function() {
				var text = $( this ).siblings( "pre" ).text();
				navigator.clipboard.writeText( text ).then( function() {
					alert( "Copied to clipboard!" );
				} );
			} );
		} );
		'
	);
}
add_action( 'admin_enqueue_scripts', 'lax_abilities_admin_styles' );

/**
 * Renders the settings / connection info page.
 *
 * @since 1.1.0
 *
 * @return void
 */
function lax_abilities_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$mcp_adapter_active = class_exists( 'MCP_Adapter\\Plugin' ) || function_exists( 'mcp_adapter_init' )
		|| ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'mcp-adapter/mcp-adapter.php' ) );

	$abilities_api_active = function_exists( 'wp_register_ability' );
	$site_url             = trailingslashit( home_url() );
	$mcp_endpoint         = $site_url . 'wp-json/mcp/mcp-adapter-default-server';
	$app_passwords_url    = admin_url( 'profile.php#application-passwords-section' );
	$username             = wp_get_current_user()->user_login;

	$claude_config  = lax_abilities_get_client_config( $mcp_endpoint, $username, 'claude' );
	$cursor_config  = lax_abilities_get_client_config( $mcp_endpoint, $username, 'cursor' );
	$vscode_config  = lax_abilities_get_client_config( $mcp_endpoint, $username, 'vscode' );
	$generic_config = lax_abilities_get_client_config( $mcp_endpoint, $username, 'generic' );
	?>
	<div class="wrap lax-abilities-wrap">
		<h1><?php esc_html_e( 'Lax Abilities Toolkit', 'lax-abilities-toolkit' ); ?></h1>
		<p><?php esc_html_e( 'Connect any MCP-compatible AI client (Claude, Cursor, VS Code, etc.) to this WordPress site.', 'lax-abilities-toolkit' ); ?></p>

		<!-- Status -->
		<h2><?php esc_html_e( 'Status', 'lax-abilities-toolkit' ); ?></h2>
		<div class="lax-card">
			<table class="widefat striped" style="border:0">
				<tbody>
					<tr>
						<td><?php esc_html_e( 'Abilities API', 'lax-abilities-toolkit' ); ?></td>
						<td>
							<?php if ( $abilities_api_active ) : ?>
								<span class="lax-status-ok">&#10003; <?php esc_html_e( 'Active', 'lax-abilities-toolkit' ); ?></span>
							<?php else : ?>
								<span class="lax-status-err">&#10007; <?php esc_html_e( 'Not available — requires WordPress 6.9+', 'lax-abilities-toolkit' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'MCP Adapter plugin', 'lax-abilities-toolkit' ); ?></td>
						<td>
							<?php if ( $mcp_adapter_active ) : ?>
								<span class="lax-status-ok">&#10003; <?php esc_html_e( 'Active', 'lax-abilities-toolkit' ); ?></span>
							<?php else : ?>
								<span class="lax-status-err">
									&#10007;
									<?php
									printf(
										/* translators: %s: link to GitHub repo */
										esc_html__( 'Not active — install the %s plugin to expose these abilities over MCP.', 'lax-abilities-toolkit' ),
										'<a href="https://github.com/WordPress/mcp-adapter" target="_blank" rel="noopener noreferrer">MCP Adapter</a>'
									);
									?>
								</span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Plugin version', 'lax-abilities-toolkit' ); ?></td>
						<td><?php echo esc_html( LAX_ABILITIES_VERSION ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>

		<!-- MCP Endpoint -->
		<h2><?php esc_html_e( 'MCP Server Endpoint', 'lax-abilities-toolkit' ); ?></h2>
		<div class="lax-card">
			<p><?php esc_html_e( 'Use this URL when configuring your MCP client:', 'lax-abilities-toolkit' ); ?></p>
			<p><code class="lax-endpoint"><?php echo esc_url( $mcp_endpoint ); ?></code></p>
			<p>
				<?php
				printf(
					/* translators: %s: link to Application Passwords section */
					esc_html__( 'Authentication uses %s — never your regular login password.', 'lax-abilities-toolkit' ),
					'<a href="' . esc_url( $app_passwords_url ) . '">' . esc_html__( 'Application Passwords', 'lax-abilities-toolkit' ) . '</a>'
				);
				?>
			</p>
			<ol>
				<li><?php esc_html_e( 'Go to your profile page and scroll to "Application Passwords".', 'lax-abilities-toolkit' ); ?></li>
				<li><?php esc_html_e( 'Enter a name (e.g. "Claude Desktop") and click Add New Application Password.', 'lax-abilities-toolkit' ); ?></li>
				<li><?php esc_html_e( 'Copy the generated password — it is shown only once.', 'lax-abilities-toolkit' ); ?></li>
				<li><?php esc_html_e( 'Paste it into your client configuration below.', 'lax-abilities-toolkit' ); ?></li>
			</ol>
			<a href="<?php echo esc_url( $app_passwords_url ); ?>" class="button button-primary">
				<?php esc_html_e( 'Create Application Password', 'lax-abilities-toolkit' ); ?>
			</a>
		</div>

		<!-- Client Config Snippets -->
		<h2><?php esc_html_e( 'Client Configuration', 'lax-abilities-toolkit' ); ?></h2>
		<p>
			<?php
			esc_html_e(
				'Replace YOUR_APP_PASSWORD with the Application Password you created above. The username shown is your current WordPress username.',
				'lax-abilities-toolkit'
			);
			?>
		</p>

		<div class="nav-tab-wrapper">
			<a href="#" class="nav-tab nav-tab-active" data-tab="claude"><?php esc_html_e( 'Claude Desktop', 'lax-abilities-toolkit' ); ?></a>
			<a href="#" class="nav-tab" data-tab="cursor"><?php esc_html_e( 'Cursor', 'lax-abilities-toolkit' ); ?></a>
			<a href="#" class="nav-tab" data-tab="vscode"><?php esc_html_e( 'VS Code', 'lax-abilities-toolkit' ); ?></a>
			<a href="#" class="nav-tab" data-tab="generic"><?php esc_html_e( 'Generic / Other', 'lax-abilities-toolkit' ); ?></a>
		</div>

		<div class="lax-card">
			<!-- Claude Desktop -->
			<div id="tab-claude" class="tab-content active">
				<h3><?php esc_html_e( 'Claude Desktop', 'lax-abilities-toolkit' ); ?></h3>
				<p>
					<?php
					printf(
						/* translators: %s: path to config file */
						esc_html__( 'Add the following to your %s file:', 'lax-abilities-toolkit' ),
						'<code>claude_desktop_config.json</code>'
					);
					?>
					&nbsp;
					<a href="https://modelcontextprotocol.io/docs/tools/inspector" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( '(Where is this file?)', 'lax-abilities-toolkit' ); ?>
					</a>
				</p>
				<button class="button lax-abilities-copy-btn"><?php esc_html_e( 'Copy', 'lax-abilities-toolkit' ); ?></button>
				<pre><?php echo esc_html( $claude_config ); ?></pre>
			</div>

			<!-- Cursor -->
			<div id="tab-cursor" class="tab-content">
				<h3><?php esc_html_e( 'Cursor', 'lax-abilities-toolkit' ); ?></h3>
				<p>
					<?php
					printf(
						/* translators: %s: path to config file */
						esc_html__( 'Add to %s in your project root or home directory:', 'lax-abilities-toolkit' ),
						'<code>.cursor/mcp.json</code>'
					);
					?>
				</p>
				<button class="button lax-abilities-copy-btn"><?php esc_html_e( 'Copy', 'lax-abilities-toolkit' ); ?></button>
				<pre><?php echo esc_html( $cursor_config ); ?></pre>
			</div>

			<!-- VS Code -->
			<div id="tab-vscode" class="tab-content">
				<h3><?php esc_html_e( 'VS Code (GitHub Copilot / MCP extension)', 'lax-abilities-toolkit' ); ?></h3>
				<p>
					<?php
					printf(
						/* translators: %s: path to settings file */
						esc_html__( 'Add to your workspace %s:', 'lax-abilities-toolkit' ),
						'<code>.vscode/mcp.json</code>'
					);
					?>
				</p>
				<button class="button lax-abilities-copy-btn"><?php esc_html_e( 'Copy', 'lax-abilities-toolkit' ); ?></button>
				<pre><?php echo esc_html( $vscode_config ); ?></pre>
			</div>

			<!-- Generic -->
			<div id="tab-generic" class="tab-content">
				<h3><?php esc_html_e( 'Generic / Environment Variables', 'lax-abilities-toolkit' ); ?></h3>
				<p><?php esc_html_e( 'Most MCP-compatible clients accept environment variables. Set these before launching your client:', 'lax-abilities-toolkit' ); ?></p>
				<button class="button lax-abilities-copy-btn"><?php esc_html_e( 'Copy', 'lax-abilities-toolkit' ); ?></button>
				<pre><?php echo esc_html( $generic_config ); ?></pre>
				<p>
					<strong><?php esc_html_e( 'Then run:', 'lax-abilities-toolkit' ); ?></strong><br>
					<code>npx -y @automattic/mcp-wordpress-remote</code>
				</p>
			</div>
		</div>

		<!-- Registered Abilities -->
		<?php if ( $abilities_api_active ) : ?>
		<h2><?php esc_html_e( 'Registered Abilities', 'lax-abilities-toolkit' ); ?></h2>
		<div class="lax-card">
			<p><?php esc_html_e( 'The following abilities are currently registered under the lax-abilities category:', 'lax-abilities-toolkit' ); ?></p>
			<?php
			$abilities = wp_get_abilities();
			$lax_abilities = array_filter(
				$abilities,
				function ( $ability ) {
					return 0 === strpos( $ability->get_name(), 'lax-abilities/' );
				}
			);

			if ( ! empty( $lax_abilities ) ) :
				?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Ability', 'lax-abilities-toolkit' ); ?></th>
							<th><?php esc_html_e( 'Label', 'lax-abilities-toolkit' ); ?></th>
							<th><?php esc_html_e( 'Description', 'lax-abilities-toolkit' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $lax_abilities as $ability ) : ?>
						<tr>
							<td><code><?php echo esc_html( $ability->get_name() ); ?></code></td>
							<td><?php echo esc_html( $ability->get_label() ); ?></td>
							<td><?php echo esc_html( $ability->get_description() ); ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p><?php esc_html_e( 'No lax-abilities registered yet. This is unusual — please check the plugin is activated correctly.', 'lax-abilities-toolkit' ); ?></p>
			<?php endif; ?>
		</div>
		<?php endif; ?>

		<p style="color: #666; font-size: 12px; margin-top: 2em;">
			<?php
			printf(
				/* translators: 1: link to GitHub repo, 2: link to WordPress/mcp-adapter */
				esc_html__( 'Lax Abilities Toolkit v%1$s — %2$s | MCP Adapter: %3$s', 'lax-abilities-toolkit' ),
				esc_html( LAX_ABILITIES_VERSION ),
				'<a href="https://github.com/laxmariappan/lax-abilities-toolkit" target="_blank" rel="noopener noreferrer">GitHub</a>',
				'<a href="https://github.com/WordPress/mcp-adapter" target="_blank" rel="noopener noreferrer">WordPress/mcp-adapter</a>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Returns a ready-to-paste MCP client configuration snippet.
 *
 * @since 1.1.0
 *
 * @param  string $mcp_endpoint The MCP server REST API URL.
 * @param  string $username     Current WP username.
 * @param  string $client       One of: claude, cursor, vscode, generic.
 * @return string               Formatted configuration string.
 */
function lax_abilities_get_client_config( $mcp_endpoint, $username, $client ) {
	$server_key = 'wordpress-' . sanitize_title( parse_url( home_url(), PHP_URL_HOST ) );

	$server_block = array(
		'command' => 'npx',
		'args'    => array(
			'-y',
			'@automattic/mcp-wordpress-remote',
		),
		'env'     => array(
			'WP_API_URL'      => $mcp_endpoint,
			'WP_API_USERNAME' => $username,
			'WP_API_PASSWORD' => 'YOUR_APP_PASSWORD',
		),
	);

	switch ( $client ) {
		case 'claude':
			return wp_json_encode(
				array(
					'mcpServers' => array(
						$server_key => $server_block,
					),
				),
				JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
			);

		case 'cursor':
			return wp_json_encode(
				array(
					'mcpServers' => array(
						$server_key => $server_block,
					),
				),
				JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
			);

		case 'vscode':
			return wp_json_encode(
				array(
					'servers' => array(
						$server_key => $server_block,
					),
				),
				JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
			);

		case 'generic':
		default:
			return implode( "\n", array(
				'export WP_API_URL="' . $mcp_endpoint . '"',
				'export WP_API_USERNAME="' . $username . '"',
				'export WP_API_PASSWORD="YOUR_APP_PASSWORD"',
			) );
	}
}
