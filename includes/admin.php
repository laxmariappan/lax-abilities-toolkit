<?php
/**
 * Admin settings page: getting-started guide and MCP connection info.
 *
 * Provides a complete walkthrough for connecting any MCP-compatible AI client
 * (Claude Desktop, Cursor, VS Code, etc.) to this WordPress site. Designed to
 * be beginner-friendly: shows the exact config file path per client and OS,
 * explains prerequisites, and generates a ready-to-paste configuration snippet.
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
 * Enqueues admin-page styles and scripts (only on our page).
 *
 * @since 1.1.0
 *
 * @param string $hook_suffix Current admin page hook suffix.
 * @return void
 */
function lax_abilities_admin_styles( $hook_suffix ) {
	if ( 'settings_page_lax-abilities-toolkit' !== $hook_suffix ) {
		return;
	}

	wp_add_inline_style(
		'wp-admin',
		'
		/* ── Layout ─────────────────────────────────────────────── */
		.lax-wrap { max-width: 900px; }
		.lax-wrap h2 { margin-top: 2em; border-bottom: 1px solid #dcdcde; padding-bottom: .4em; }
		.lax-tagline { font-size: 14px; color: #50575e; margin-top: 0; }

		/* ── Cards ──────────────────────────────────────────────── */
		.lax-card {
			background: #fff;
			border: 1px solid #c3c4c7;
			border-radius: 4px;
			padding: 18px 22px;
			margin: 10px 0 22px;
		}
		.lax-card h3 { margin-top: 0; font-size: 15px; }
		.lax-card p:last-child { margin-bottom: 0; }

		/* ── Quick-start steps ──────────────────────────────────── */
		.lax-steps { counter-reset: lax-step; list-style: none; margin: 0; padding: 0; }
		.lax-steps li {
			counter-increment: lax-step;
			display: flex;
			align-items: flex-start;
			gap: 14px;
			padding: 12px 0;
			border-bottom: 1px solid #f0f0f1;
		}
		.lax-steps li:last-child { border-bottom: 0; }
		.lax-step-num {
			min-width: 32px; height: 32px; border-radius: 50%;
			background: #2271b1; color: #fff;
			display: flex; align-items: center; justify-content: center;
			font-weight: 700; font-size: 14px; flex-shrink: 0;
		}
		.lax-steps li.done .lax-step-num { background: #00a32a; }
		.lax-step-body { flex: 1; }
		.lax-step-body strong { display: block; margin-bottom: 3px; }
		.lax-step-body p { margin: 2px 0 0; color: #50575e; font-size: 13px; }

		/* ── Status table ────────────────────────────────────────── */
		.lax-status-ok  { color: #00a32a; font-weight: 600; }
		.lax-status-err { color: #d63638; font-weight: 600; }

		/* ── Code / endpoint ─────────────────────────────────────── */
		.lax-endpoint {
			font-family: monospace;
			background: #f0f0f1;
			padding: 8px 12px;
			border-radius: 3px;
			display: inline-block;
			word-break: break-all;
			font-size: 13px;
		}
		.lax-wrap pre {
			background: #1e1e2e;
			color: #cdd6f4;
			padding: 14px 16px;
			border-radius: 4px;
			overflow-x: auto;
			font-size: 12px;
			line-height: 1.6;
			white-space: pre;
			word-break: normal;
			margin: 8px 0 4px;
		}

		/* ── Tabs ────────────────────────────────────────────────── */
		.lax-wrap .nav-tab-wrapper { margin-bottom: 0; }
		.lax-wrap .tab-panel { display: none; padding: 18px 22px; background: #fff; border: 1px solid #c3c4c7; border-top: 0; border-radius: 0 0 4px 4px; }
		.lax-wrap .tab-panel.active { display: block; }

		/* ── Copy button ─────────────────────────────────────────── */
		.lax-copy-btn { cursor: pointer; margin-left: 8px; vertical-align: middle; }

		/* ── Client guide ────────────────────────────────────────── */
		.lax-client-steps { margin: 10px 0 14px; padding-left: 20px; }
		.lax-client-steps li { margin-bottom: 6px; font-size: 13px; }
		.lax-paths { margin: 4px 0 10px 0; padding: 0; list-style: none; }
		.lax-paths li { display: flex; gap: 8px; font-size: 12px; margin-bottom: 4px; }
		.lax-paths .os { min-width: 66px; color: #50575e; }
		.lax-paths code { background: #f0f0f1; padding: 1px 5px; border-radius: 2px; word-break: break-all; }

		/* ── Notice/tip box ─────────────────────────────────────── */
		.lax-tip {
			background: #f0f6fc;
			border-left: 4px solid #2271b1;
			padding: 10px 14px;
			margin: 10px 0;
			font-size: 13px;
			border-radius: 0 3px 3px 0;
		}
		.lax-tip.warn { background: #fcf9e8; border-color: #dba617; }
		'
	);

	wp_add_inline_script(
		'jquery',
		'
		jQuery( function( $ ) {

			// ── Tab switching ──────────────────────────────────────
			$( ".lax-wrap" ).on( "click", ".nav-tab", function( e ) {
				e.preventDefault();
				var target = $( this ).data( "tab" );
				$( this ).closest( ".lax-wrap" ).find( ".nav-tab" )
					.removeClass( "nav-tab-active" );
				$( this ).addClass( "nav-tab-active" );
				$( ".tab-panel" ).removeClass( "active" );
				$( "#tab-" + target ).addClass( "active" );
			} );

			// ── Copy to clipboard ──────────────────────────────────
			$( ".lax-wrap" ).on( "click", ".lax-copy-btn", function() {
				var $btn  = $( this );
				var text  = $btn.closest( ".lax-copy-group" ).find( "pre" ).text();
				var label = $btn.text();
				navigator.clipboard.writeText( text ).then( function() {
					$btn.text( "Copied ✓" );
					setTimeout( function() { $btn.text( label ); }, 2000 );
				} ).catch( function() {
					alert( "Could not copy — please select and copy manually." );
				} );
			} );

		} );
		'
	);
}
add_action( 'admin_enqueue_scripts', 'lax_abilities_admin_styles' );

// =============================================================================
// Settings page renderer
// =============================================================================

/**
 * Renders the full settings / getting-started page.
 *
 * @since 1.1.0
 *
 * @return void
 */
function lax_abilities_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	/* ── Status checks ─────────────────────────────────────────────────── */
	$abilities_api_active = function_exists( 'wp_register_ability' );
	$mcp_adapter_active   = class_exists( 'MCP_Adapter\\Plugin' )
		|| function_exists( 'mcp_adapter_init' )
		|| ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'mcp-adapter/mcp-adapter.php' ) );

	$site_url          = trailingslashit( home_url() );
	$mcp_endpoint      = $site_url . 'wp-json/mcp/mcp-adapter-default-server';
	$app_passwords_url = admin_url( 'profile.php#application-passwords-section' );
	$username          = wp_get_current_user()->user_login;
	$plugin_install    = admin_url( 'plugin-install.php?s=mcp-adapter&tab=search&type=term' );

	/* ── Config snippets ───────────────────────────────────────────────── */
	$snippet_claude  = lax_abilities_get_client_config( $mcp_endpoint, $username, 'claude' );
	$snippet_cursor  = lax_abilities_get_client_config( $mcp_endpoint, $username, 'cursor' );
	$snippet_vscode  = lax_abilities_get_client_config( $mcp_endpoint, $username, 'vscode' );
	$snippet_generic = lax_abilities_get_client_config( $mcp_endpoint, $username, 'generic' );

	?>
	<div class="wrap lax-wrap">

		<h1><?php esc_html_e( 'Lax Abilities Toolkit', 'lax-abilities-toolkit' ); ?></h1>
		<p class="lax-tagline">
			<?php esc_html_e( 'Give any MCP-compatible AI — Claude, Cursor, VS Code and more — direct access to this WordPress site.', 'lax-abilities-toolkit' ); ?>
		</p>

		<?php /* ── Quick Start ──────────────────────────────────────────────── */ ?>
		<h2><?php esc_html_e( 'Quick Start', 'lax-abilities-toolkit' ); ?></h2>
		<div class="lax-card">
			<ol class="lax-steps">

				<li class="<?php echo $abilities_api_active ? 'done' : ''; ?>">
					<span class="lax-step-num">1</span>
					<div class="lax-step-body">
						<strong><?php esc_html_e( 'WordPress 6.9 or later', 'lax-abilities-toolkit' ); ?></strong>
						<?php if ( $abilities_api_active ) : ?>
							<p class="lax-status-ok">&#10003; <?php esc_html_e( 'Your site meets the requirement.', 'lax-abilities-toolkit' ); ?></p>
						<?php else : ?>
							<p class="lax-status-err">&#10007;
								<?php esc_html_e( 'The Abilities API is not available. Please update WordPress to 6.9 or later.', 'lax-abilities-toolkit' ); ?>
							</p>
						<?php endif; ?>
					</div>
				</li>

				<li class="<?php echo $mcp_adapter_active ? 'done' : ''; ?>">
					<span class="lax-step-num">2</span>
					<div class="lax-step-body">
						<strong><?php esc_html_e( 'Install and activate the MCP Adapter plugin', 'lax-abilities-toolkit' ); ?></strong>
						<?php if ( $mcp_adapter_active ) : ?>
							<p class="lax-status-ok">&#10003; <?php esc_html_e( 'MCP Adapter is active.', 'lax-abilities-toolkit' ); ?></p>
						<?php else : ?>
							<p>
								<?php
								printf(
									/* translators: 1: link to plugin search, 2: link to GitHub */
									esc_html__( 'This free plugin exposes your abilities over HTTP so AI clients can reach them. %1$s or install it %2$s.', 'lax-abilities-toolkit' ),
									'<a href="' . esc_url( $plugin_install ) . '">' . esc_html__( 'Search the plugin directory', 'lax-abilities-toolkit' ) . '</a>',
									'<a href="https://github.com/WordPress/mcp-adapter" target="_blank" rel="noopener noreferrer">' . esc_html__( 'from GitHub', 'lax-abilities-toolkit' ) . '</a>'
								);
								?>
							</p>
						<?php endif; ?>
					</div>
				</li>

				<li>
					<span class="lax-step-num">3</span>
					<div class="lax-step-body">
						<strong><?php esc_html_e( 'Create an Application Password', 'lax-abilities-toolkit' ); ?></strong>
						<p>
							<?php
							printf(
								/* translators: %s: link to Application Passwords section */
								esc_html__( 'Go to %s, scroll to "Application Passwords", type a name (e.g. "Claude Desktop"), and click Add. Copy the password — it is shown only once.', 'lax-abilities-toolkit' ),
								'<a href="' . esc_url( $app_passwords_url ) . '">' . esc_html__( 'your Profile page', 'lax-abilities-toolkit' ) . '</a>'
							);
							?>
						</p>
						<a href="<?php echo esc_url( $app_passwords_url ); ?>" class="button button-secondary" style="margin-top:6px">
							<?php esc_html_e( 'Open Profile → Application Passwords', 'lax-abilities-toolkit' ); ?>
						</a>
					</div>
				</li>

				<li>
					<span class="lax-step-num">4</span>
					<div class="lax-step-body">
						<strong><?php esc_html_e( 'Add the config snippet to your AI client', 'lax-abilities-toolkit' ); ?></strong>
						<p><?php esc_html_e( 'Choose your client in the "Connect Your AI Client" section below, follow the steps, then restart the app.', 'lax-abilities-toolkit' ); ?></p>
					</div>
				</li>

			</ol>
		</div>

		<?php /* ── Status ──────────────────────────────────────────────────── */ ?>
		<h2><?php esc_html_e( 'Status', 'lax-abilities-toolkit' ); ?></h2>
		<div class="lax-card">
			<table class="widefat striped" style="border:0">
				<tbody>
					<tr>
						<td><?php esc_html_e( 'Abilities API', 'lax-abilities-toolkit' ); ?></td>
						<td>
							<?php if ( $abilities_api_active ) : ?>
								<span class="lax-status-ok">&#10003; <?php esc_html_e( 'Active (WordPress 6.9+)', 'lax-abilities-toolkit' ); ?></span>
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
										esc_html__( 'Not active — install the %s plugin.', 'lax-abilities-toolkit' ),
										'<a href="https://github.com/WordPress/mcp-adapter" target="_blank" rel="noopener noreferrer">MCP Adapter</a>'
									);
									?>
								</span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Block editor support', 'lax-abilities-toolkit' ); ?></td>
						<td>
							<?php if ( lax_abilities_is_block_editor_active() ) : ?>
								<span class="lax-status-ok">&#10003; <?php esc_html_e( 'Active — content will be auto-converted to Gutenberg blocks', 'lax-abilities-toolkit' ); ?></span>
							<?php else : ?>
								<span style="color:#666">
									<?php esc_html_e( 'Classic editor detected — content stored as HTML', 'lax-abilities-toolkit' ); ?>
								</span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'MCP server endpoint', 'lax-abilities-toolkit' ); ?></td>
						<td><code class="lax-endpoint"><?php echo esc_url( $mcp_endpoint ); ?></code></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Plugin version', 'lax-abilities-toolkit' ); ?></td>
						<td><?php echo esc_html( LAX_ABILITIES_VERSION ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>

		<?php /* ── Node.js prerequisite notice ─────────────────────────────── */ ?>
		<div class="lax-tip warn">
			<strong><?php esc_html_e( 'Before you start: Node.js is required', 'lax-abilities-toolkit' ); ?></strong>
			&nbsp;—&nbsp;
			<?php
			printf(
				/* translators: %s: link to nodejs.org */
				esc_html__( 'All clients below use %s to run the MCP bridge. If you have not installed it yet, download the LTS version from %s (it also installs npx).', 'lax-abilities-toolkit' ),
				'<code>npx</code>',
				'<a href="https://nodejs.org" target="_blank" rel="noopener noreferrer">nodejs.org</a>'
			);
			?>
		</div>

		<?php /* ── Client guides ─────────────────────────────────────────────── */ ?>
		<h2><?php esc_html_e( 'Connect Your AI Client', 'lax-abilities-toolkit' ); ?></h2>
		<p style="color:#50575e;font-size:13px">
			<?php
			printf(
				/* translators: %s: placeholder text */
				esc_html__( 'In the snippet for your chosen client, replace %s with the Application Password you created in Step 3.', 'lax-abilities-toolkit' ),
				'<code>YOUR_APP_PASSWORD</code>'
			);
			?>
		</p>

		<div class="nav-tab-wrapper">
			<a href="#" class="nav-tab nav-tab-active" data-tab="claude"><?php esc_html_e( 'Claude Desktop', 'lax-abilities-toolkit' ); ?></a>
			<a href="#" class="nav-tab" data-tab="cursor"><?php esc_html_e( 'Cursor', 'lax-abilities-toolkit' ); ?></a>
			<a href="#" class="nav-tab" data-tab="vscode"><?php esc_html_e( 'VS Code', 'lax-abilities-toolkit' ); ?></a>
			<a href="#" class="nav-tab" data-tab="generic"><?php esc_html_e( 'Other / Generic', 'lax-abilities-toolkit' ); ?></a>
		</div>

		<!-- ── Claude Desktop ─────────────────────────────────────────────── -->
		<div id="tab-claude" class="tab-panel active">
			<h3><?php esc_html_e( 'Claude Desktop', 'lax-abilities-toolkit' ); ?></h3>

			<p>
				<?php
				printf(
					/* translators: %s: download link */
					esc_html__( 'Download Claude Desktop from %s if you have not already.', 'lax-abilities-toolkit' ),
					'<a href="https://claude.ai/download" target="_blank" rel="noopener noreferrer">claude.ai/download</a>'
				);
				?>
			</p>

			<strong><?php esc_html_e( 'Where is the config file?', 'lax-abilities-toolkit' ); ?></strong>
			<ul class="lax-paths">
				<li><span class="os">macOS</span>    <code>~/Library/Application Support/Claude/claude_desktop_config.json</code></li>
				<li><span class="os">Windows</span>  <code>%APPDATA%\Claude\claude_desktop_config.json</code></li>
				<li><span class="os">Linux</span>    <code>~/.config/Claude/claude_desktop_config.json</code></li>
			</ul>

			<strong><?php esc_html_e( 'Steps:', 'lax-abilities-toolkit' ); ?></strong>
			<ol class="lax-client-steps">
				<li><?php esc_html_e( 'Open (or create) the config file at the path above in any text editor.', 'lax-abilities-toolkit' ); ?></li>
				<li>
					<?php
					printf(
						/* translators: %s: key name */
						esc_html__( 'If the file already exists, find the %s object and add the new server entry inside it. Otherwise paste the full snippet below.', 'lax-abilities-toolkit' ),
						'<code>"mcpServers"</code>'
					);
					?>
				</li>
				<li><?php esc_html_e( 'Replace YOUR_APP_PASSWORD with your Application Password.', 'lax-abilities-toolkit' ); ?></li>
				<li><?php esc_html_e( 'Save the file.', 'lax-abilities-toolkit' ); ?></li>
				<li><?php esc_html_e( 'Completely quit and reopen Claude Desktop (Cmd/Ctrl + Q, then reopen).', 'lax-abilities-toolkit' ); ?></li>
				<li><?php esc_html_e( 'The WordPress tools will now appear in the chat toolbar.', 'lax-abilities-toolkit' ); ?></li>
			</ol>

			<div class="lax-copy-group">
				<button class="button lax-copy-btn"><?php esc_html_e( 'Copy', 'lax-abilities-toolkit' ); ?></button>
				<pre><?php echo esc_html( $snippet_claude ); ?></pre>
			</div>
		</div>

		<!-- ── Cursor ──────────────────────────────────────────────────────── -->
		<div id="tab-cursor" class="tab-panel">
			<h3><?php esc_html_e( 'Cursor', 'lax-abilities-toolkit' ); ?></h3>

			<p>
				<?php
				printf(
					/* translators: %s: download link */
					esc_html__( 'Download Cursor from %s if you have not already.', 'lax-abilities-toolkit' ),
					'<a href="https://cursor.com" target="_blank" rel="noopener noreferrer">cursor.com</a>'
				);
				?>
			</p>

			<strong><?php esc_html_e( 'Where is the config file?', 'lax-abilities-toolkit' ); ?></strong>
			<ul class="lax-paths">
				<li><span class="os">Global</span>      <code>~/.cursor/mcp.json</code></li>
				<li><span class="os">Per-project</span> <code>.cursor/mcp.json</code> <span style="color:#50575e"><?php esc_html_e( '(in your project root — takes precedence)', 'lax-abilities-toolkit' ); ?></span></li>
			</ul>

			<strong><?php esc_html_e( 'Steps:', 'lax-abilities-toolkit' ); ?></strong>
			<ol class="lax-client-steps">
				<li><?php esc_html_e( 'Open or create ~/.cursor/mcp.json (global) or .cursor/mcp.json (project).', 'lax-abilities-toolkit' ); ?></li>
				<li>
					<?php
					printf(
						/* translators: %s: key name */
						esc_html__( 'If the file already has an %s object, add the new server entry inside it. Otherwise paste the full snippet.', 'lax-abilities-toolkit' ),
						'<code>"mcpServers"</code>'
					);
					?>
				</li>
				<li><?php esc_html_e( 'Replace YOUR_APP_PASSWORD with your Application Password.', 'lax-abilities-toolkit' ); ?></li>
				<li>
					<?php
					esc_html_e( 'Reload Cursor: open the Command Palette (Ctrl/Cmd + Shift + P) and run "Developer: Reload Window".', 'lax-abilities-toolkit' );
					?>
				</li>
				<li><?php esc_html_e( 'The WordPress MCP server will now appear under the MCP section in settings.', 'lax-abilities-toolkit' ); ?></li>
			</ol>

			<div class="lax-copy-group">
				<button class="button lax-copy-btn"><?php esc_html_e( 'Copy', 'lax-abilities-toolkit' ); ?></button>
				<pre><?php echo esc_html( $snippet_cursor ); ?></pre>
			</div>
		</div>

		<!-- ── VS Code ──────────────────────────────────────────────────────── -->
		<div id="tab-vscode" class="tab-panel">
			<h3><?php esc_html_e( 'VS Code', 'lax-abilities-toolkit' ); ?></h3>

			<p>
				<?php
				printf(
					/* translators: %s: download link */
					esc_html__( 'Download VS Code from %s if you have not already. MCP support is built in from VS Code 1.99+.', 'lax-abilities-toolkit' ),
					'<a href="https://code.visualstudio.com" target="_blank" rel="noopener noreferrer">code.visualstudio.com</a>'
				);
				?>
			</p>

			<strong><?php esc_html_e( 'Where is the config file?', 'lax-abilities-toolkit' ); ?></strong>
			<ul class="lax-paths">
				<li><span class="os">Workspace</span> <code>.vscode/mcp.json</code> <span style="color:#50575e"><?php esc_html_e( '(recommended — create in your project root)', 'lax-abilities-toolkit' ); ?></span></li>
				<li><span class="os">Global</span>    <code><?php esc_html_e( 'VS Code Settings JSON → "mcp" key', 'lax-abilities-toolkit' ); ?></code></li>
			</ul>

			<strong><?php esc_html_e( 'Steps:', 'lax-abilities-toolkit' ); ?></strong>
			<ol class="lax-client-steps">
				<li>
					<?php
					printf(
						/* translators: %s: file path */
						esc_html__( 'Create %s in the root of your project (or open it if it already exists).', 'lax-abilities-toolkit' ),
						'<code>.vscode/mcp.json</code>'
					);
					?>
				</li>
				<li><?php esc_html_e( 'Paste the snippet below into the file.', 'lax-abilities-toolkit' ); ?></li>
				<li><?php esc_html_e( 'Replace YOUR_APP_PASSWORD with your Application Password.', 'lax-abilities-toolkit' ); ?></li>
				<li><?php esc_html_e( 'VS Code auto-discovers the server — no restart needed. A prompt may appear asking you to confirm.', 'lax-abilities-toolkit' ); ?></li>
				<li>
					<?php
					esc_html_e( 'Open GitHub Copilot Chat (or any MCP-aware extension) and you will see the WordPress tools listed.', 'lax-abilities-toolkit' );
					?>
				</li>
			</ol>

			<div class="lax-copy-group">
				<button class="button lax-copy-btn"><?php esc_html_e( 'Copy', 'lax-abilities-toolkit' ); ?></button>
				<pre><?php echo esc_html( $snippet_vscode ); ?></pre>
			</div>
		</div>

		<!-- ── Generic ──────────────────────────────────────────────────────── -->
		<div id="tab-generic" class="tab-panel">
			<h3><?php esc_html_e( 'Other / Generic', 'lax-abilities-toolkit' ); ?></h3>

			<p>
				<?php esc_html_e( 'Any MCP-compatible client that accepts environment variables can connect by running the bridge command below.', 'lax-abilities-toolkit' ); ?>
			</p>

			<strong><?php esc_html_e( 'Steps:', 'lax-abilities-toolkit' ); ?></strong>
			<ol class="lax-client-steps">
				<li><?php esc_html_e( 'Set the three environment variables shown below in your shell or client configuration.', 'lax-abilities-toolkit' ); ?></li>
				<li><?php esc_html_e( 'Replace YOUR_APP_PASSWORD with your Application Password.', 'lax-abilities-toolkit' ); ?></li>
				<li><?php esc_html_e( 'Point your client\'s MCP server command to:', 'lax-abilities-toolkit' ); ?><br><code>npx -y @automattic/mcp-wordpress-remote</code></li>
				<li>
					<?php
					printf(
						/* translators: %s: link to package */
						esc_html__( 'See the %s package docs for client-specific integration examples.', 'lax-abilities-toolkit' ),
						'<a href="https://www.npmjs.com/package/@automattic/mcp-wordpress-remote" target="_blank" rel="noopener noreferrer">@automattic/mcp-wordpress-remote</a>'
					);
					?>
				</li>
			</ol>

			<div class="lax-copy-group">
				<button class="button lax-copy-btn"><?php esc_html_e( 'Copy', 'lax-abilities-toolkit' ); ?></button>
				<pre><?php echo esc_html( $snippet_generic ); ?></pre>
			</div>
		</div>

		<?php /* ── Registered abilities table ──────────────────────────────── */ ?>
		<?php if ( $abilities_api_active ) : ?>
		<h2><?php esc_html_e( 'Registered Abilities', 'lax-abilities-toolkit' ); ?></h2>
		<div class="lax-card">
			<p style="color:#50575e;margin-top:0">
				<?php esc_html_e( 'These are the abilities currently active on your site. AI clients can invoke any of them.', 'lax-abilities-toolkit' ); ?>
			</p>
			<?php
			$abilities     = wp_get_abilities();
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

		<p style="color:#888;font-size:12px;margin-top:2.5em">
			<?php
			printf(
				/* translators: 1: plugin version, 2: GitHub link, 3: MCP Adapter link */
				esc_html__( 'Lax Abilities Toolkit v%1$s &mdash; %2$s | MCP Adapter: %3$s', 'lax-abilities-toolkit' ),
				esc_html( LAX_ABILITIES_VERSION ),
				'<a href="https://github.com/laxmariappan/lax-abilities-toolkit" target="_blank" rel="noopener noreferrer">GitHub</a>',
				'<a href="https://github.com/WordPress/mcp-adapter" target="_blank" rel="noopener noreferrer">WordPress/mcp-adapter</a>'
			);
			?>
		</p>

	</div>
	<?php
}

// =============================================================================
// Config snippet generator
// =============================================================================

/**
 * Returns a ready-to-paste MCP client configuration snippet.
 *
 * @since 1.1.0
 *
 * @param  string $mcp_endpoint The MCP server REST API URL.
 * @param  string $username     Current WordPress username.
 * @param  string $client       One of: claude, cursor, vscode, generic.
 * @return string               Formatted configuration string.
 */
function lax_abilities_get_client_config( $mcp_endpoint, $username, $client ) {
	$server_key = 'wordpress-' . sanitize_title( wp_parse_url( home_url(), PHP_URL_HOST ) );

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
			return implode(
				"\n",
				array(
					'export WP_API_URL="' . $mcp_endpoint . '"',
					'export WP_API_USERNAME="' . $username . '"',
					'export WP_API_PASSWORD="YOUR_APP_PASSWORD"',
					'',
					'npx -y @automattic/mcp-wordpress-remote',
				)
			);
	}
}
