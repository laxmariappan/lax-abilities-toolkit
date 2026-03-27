/**
 * Lax Abilities Toolkit — Admin Settings Page
 *
 * Renders the plugin settings page using @wordpress/components for a native
 * WordPress look and feel. Data is passed from PHP via wp_localize_script.
 *
 * @package LaxAbilitiesToolkit
 * @since   1.3.0
 */

import { useState, createRoot } from '@wordpress/element';
import {
	Card,
	CardBody,
	CardHeader,
	TabPanel,
	Button,
	Notice,
	Flex,
	FlexItem,
	TextControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import './style.css';

/** @type {Object} Data injected by PHP via wp_localize_script. */
const data = window.laxAbilitiesAdmin || {};

// =============================================================================
// Config generators
// =============================================================================

function getServerBlock( password ) {
	return {
		command: 'npx',
		args: [ '-y', '@automattic/mcp-wordpress-remote' ],
		env: {
			WP_API_URL: data.mcpEndpoint || '',
			WP_API_USERNAME: data.username || '',
			WP_API_PASSWORD: password || 'YOUR_APP_PASSWORD',
		},
	};
}

function getConfig( client, password ) {
	const key   = data.serverKey || 'my-wordpress-site';
	const block = getServerBlock( password );

	if ( 'vscode' === client ) {
		return JSON.stringify(
			{ servers: { [ key ]: { type: 'stdio', ...block } } },
			null,
			2
		);
	}

	if ( 'generic' === client ) {
		const pass = password || 'YOUR_APP_PASSWORD';
		return [
			`export WP_API_URL="${ data.mcpEndpoint || '' }"`,
			`export WP_API_USERNAME="${ data.username || '' }"`,
			`export WP_API_PASSWORD="${ pass }"`,
			'',
			'npx -y @automattic/mcp-wordpress-remote',
		].join( '\n' );
	}

	// claude + cursor share the same mcpServers format.
	return JSON.stringify( { mcpServers: { [ key ]: block } }, null, 2 );
}

function getStarterPrompt() {
	return [
		"I've connected my WordPress site to you via MCP using the Lax Abilities Toolkit plugin.",
		'',
		`Site: ${ data.siteName } (${ data.siteUrl })`,
		`My WordPress username: ${ data.username }`,
		'',
		'You have access to the following abilities on my site:',
		'• Create, read, update, and delete posts and pages',
		'• Create and manage categories and tags',
		'• Browse and manage the media library',
		'• Retrieve site information (name, URL, WordPress version, timezone, etc.)',
		'',
		'Please start by discovering all available abilities using your MCP tools, then give me a brief summary of what you can help me do on this WordPress site.',
	].join( '\n' );
}

// =============================================================================
// Per-client instructions
// =============================================================================

const CLIENT_INFO = {
	claude: {
		download: 'https://claude.ai/download',
		app: 'Claude Desktop',
		paths: [
			{ os: 'macOS',   path: '~/Library/Application Support/Claude/claude_desktop_config.json' },
			{ os: 'Windows', path: '%APPDATA%\\Claude\\claude_desktop_config.json' },
			{ os: 'Linux',   path: '~/.config/Claude/claude_desktop_config.json' },
		],
		steps: [
			__( 'Download and install Claude Desktop.', 'lax-abilities-toolkit' ),
			__( 'Open (or create) the config file shown above in any text editor.', 'lax-abilities-toolkit' ),
			__( 'If the file already exists, add the server entry inside "mcpServers". Otherwise paste the full snippet.', 'lax-abilities-toolkit' ),
			__( 'Fill in your Application Password, save the file.', 'lax-abilities-toolkit' ),
			__( 'Quit Claude Desktop completely (Cmd/Ctrl+Q) and reopen it.', 'lax-abilities-toolkit' ),
			__( 'The WordPress tools will appear in the chat toolbar (hammer icon).', 'lax-abilities-toolkit' ),
		],
	},
	cursor: {
		download: 'https://cursor.com',
		app: 'Cursor',
		paths: [
			{ os: 'Global',      path: '~/.cursor/mcp.json' },
			{ os: 'Per-project', path: '.cursor/mcp.json  (project root — takes precedence)' },
		],
		steps: [
			__( 'Download and install Cursor.', 'lax-abilities-toolkit' ),
			__( 'Open or create ~/.cursor/mcp.json (global) or .cursor/mcp.json (project root).', 'lax-abilities-toolkit' ),
			__( 'Paste the snippet and fill in your Application Password.', 'lax-abilities-toolkit' ),
			__( 'Reload Cursor: Cmd/Ctrl + Shift + P → "Developer: Reload Window".', 'lax-abilities-toolkit' ),
			__( 'The MCP server will appear in Cursor Settings → MCP.', 'lax-abilities-toolkit' ),
		],
	},
	vscode: {
		download: 'https://code.visualstudio.com',
		app: 'VS Code',
		paths: [
			{ os: 'Workspace', path: '.vscode/mcp.json  (project root — recommended)' },
			{ os: 'Global',    path: 'VS Code Settings JSON → "mcp" key' },
		],
		steps: [
			__( 'Install VS Code 1.99 or later (MCP support is built in).', 'lax-abilities-toolkit' ),
			__( 'Create .vscode/mcp.json in your project root.', 'lax-abilities-toolkit' ),
			__( 'Paste the snippet and fill in your Application Password.', 'lax-abilities-toolkit' ),
			__( 'VS Code discovers the server automatically — no restart needed.', 'lax-abilities-toolkit' ),
			__( 'Open GitHub Copilot Chat or any MCP-aware extension to see the WordPress tools.', 'lax-abilities-toolkit' ),
		],
	},
	generic: {
		download: null,
		app: 'Generic / Other',
		paths: [],
		steps: [
			__( 'Set the three environment variables from the snippet in your MCP client or shell.', 'lax-abilities-toolkit' ),
			__( 'Replace YOUR_APP_PASSWORD with your Application Password.', 'lax-abilities-toolkit' ),
			__( 'Point your MCP client\'s server command to: npx -y @automattic/mcp-wordpress-remote', 'lax-abilities-toolkit' ),
		],
	},
};

// =============================================================================
// Sub-components
// =============================================================================

/**
 * A button that copies text to the clipboard and shows brief feedback.
 */
function CopyButton( { getText } ) {
	const [ label, setLabel ] = useState( __( 'Copy', 'lax-abilities-toolkit' ) );

	function handleCopy() {
		navigator.clipboard
			.writeText( getText() )
			.then( () => {
				setLabel( '✓ ' + __( 'Copied!', 'lax-abilities-toolkit' ) );
				setTimeout( () => setLabel( __( 'Copy', 'lax-abilities-toolkit' ) ), 2000 );
			} )
			.catch( () => {} );
	}

	return (
		<Button variant="secondary" size="compact" onClick={ handleCopy }>
			{ label }
		</Button>
	);
}

/**
 * A dark code block with a copy button below it.
 */
function CodeBlock( { value } ) {
	return (
		<div className="lax-code-block">
			<pre className="lax-pre">{ value }</pre>
			<div className="lax-code-actions">
				<CopyButton getText={ () => value } />
			</div>
		</div>
	);
}

/**
 * A single row in the status table.
 */
function StatusRow( { label, ok, okText, failText } ) {
	return (
		<tr>
			<td>{ label }</td>
			<td>
				<span className={ `lax-badge lax-badge--${ ok ? 'ok' : 'fail' }` }>
					{ ok ? '✓' : '✗' }
				</span>
				<span className={ ok ? 'lax-status-ok' : 'lax-status-err' }>
					{ ok ? okText : failText }
				</span>
			</td>
		</tr>
	);
}

/**
 * Content for a single client tab: download link, file paths, steps,
 * config snippet, and starter prompt.
 */
function ClientTab( { name, password } ) {
	const info   = CLIENT_INFO[ name ];
	const config = getConfig( name, password );
	const prompt = getStarterPrompt();

	return (
		<div className="lax-client-tab">
			{ info.download && (
				<p>
					<a href={ info.download } target="_blank" rel="noreferrer">
						↗ { __( 'Download', 'lax-abilities-toolkit' ) } { info.app }
					</a>
				</p>
			) }

			{ info.paths.length > 0 && (
				<>
					<strong>{ __( 'Config file location:', 'lax-abilities-toolkit' ) }</strong>
					<ul className="lax-paths">
						{ info.paths.map( ( p, i ) => (
							<li key={ i }>
								<span className="lax-os">{ p.os }</span>
								<code>{ p.path }</code>
							</li>
						) ) }
					</ul>
				</>
			) }

			<ol className="lax-step-list">
				{ info.steps.map( ( step, i ) => (
					<li key={ i }>{ step }</li>
				) ) }
			</ol>

			<h4>{ __( 'Config Snippet', 'lax-abilities-toolkit' ) }</h4>
			<CodeBlock value={ config } />

			<h4>{ __( 'Starter Prompt', 'lax-abilities-toolkit' ) }</h4>
			<p className="lax-help-text">
				{ __( 'Paste this into your AI chat right after connecting — it orients the AI to your site and triggers ability discovery:', 'lax-abilities-toolkit' ) }
			</p>
			<CodeBlock value={ prompt } />
		</div>
	);
}

// =============================================================================
// Main admin component
// =============================================================================

function LaxAbilitiesAdmin() {
	const [ password, setPassword ] = useState( '' );

	const tabs = [
		{ name: 'claude',  title: 'Claude Desktop' },
		{ name: 'cursor',  title: 'Cursor' },
		{ name: 'vscode',  title: 'VS Code' },
		{ name: 'generic', title: 'Generic / Other' },
	];

	return (
		<div className="lax-admin wrap">
			<h1>{ __( 'Lax Abilities Toolkit', 'lax-abilities-toolkit' ) }</h1>
			<p className="lax-tagline">
				{ __( 'Connect your WordPress site to Claude, Cursor, VS Code, and any MCP-compatible AI.', 'lax-abilities-toolkit' ) }
			</p>

			{ /* ── Status ─────────────────────────────────────────────── */ }
			<Card className="lax-card">
				<CardHeader>
					<strong>{ __( 'Status', 'lax-abilities-toolkit' ) }</strong>
				</CardHeader>
				<CardBody>
					<table className="widefat striped lax-status-table">
						<tbody>
							<StatusRow
								label={ __( 'WordPress Abilities API', 'lax-abilities-toolkit' ) }
								ok={ !! data.hasAbilitiesApi }
								okText={ __( 'Available (WordPress 6.9+)', 'lax-abilities-toolkit' ) }
								failText={ __( 'Not available — update WordPress to 6.9 or later', 'lax-abilities-toolkit' ) }
							/>
							<StatusRow
								label={ __( 'MCP Adapter Plugin', 'lax-abilities-toolkit' ) }
								ok={ !! data.hasAdapter }
								okText={ __( 'Active', 'lax-abilities-toolkit' ) }
								failText={ __( 'Not active — install the MCP Adapter plugin', 'lax-abilities-toolkit' ) }
							/>
							<StatusRow
								label={ __( 'Block Editor', 'lax-abilities-toolkit' ) }
								ok={ !! data.blockEditorActive }
								okText={ __( 'Active — content auto-converts to Gutenberg blocks', 'lax-abilities-toolkit' ) }
								failText={ __( 'Classic editor detected — content stored as HTML', 'lax-abilities-toolkit' ) }
							/>
							<tr>
								<td>{ __( 'MCP Endpoint', 'lax-abilities-toolkit' ) }</td>
								<td><code className="lax-endpoint">{ data.mcpEndpoint }</code></td>
							</tr>
							<tr>
								<td>{ __( 'Plugin Version', 'lax-abilities-toolkit' ) }</td>
								<td>{ data.version }</td>
							</tr>
						</tbody>
					</table>
				</CardBody>
			</Card>

			{ /* ── Node.js prerequisite ───────────────────────────────── */ }
			<Notice status="info" isDismissible={ false } className="lax-notice">
				<strong>{ __( 'Prerequisite: Node.js', 'lax-abilities-toolkit' ) }</strong>
				{ ' — ' }
				{ __( 'All clients below use npx to run the MCP bridge. Download the LTS version from', 'lax-abilities-toolkit' ) }
				{ ' ' }
				<a href="https://nodejs.org" target="_blank" rel="noreferrer">nodejs.org</a>
				{ ' ' }
				{ __( "if you haven't already (it includes npx).", 'lax-abilities-toolkit' ) }
			</Notice>

			{ /* ── Application Password ──────────────────────────────── */ }
			<Card className="lax-card">
				<CardHeader>
					<strong>{ __( 'Application Password', 'lax-abilities-toolkit' ) }</strong>
				</CardHeader>
				<CardBody>
					<p>
						{ __( 'Go to', 'lax-abilities-toolkit' ) }
						{ ' ' }
						<a href={ data.appPasswordUrl } target="_blank" rel="noreferrer">
							{ __( 'Users → Your Profile → Application Passwords', 'lax-abilities-toolkit' ) }
						</a>
						{ ', ' }
						{ __( "type a name like \"Claude Desktop\", click Add, and copy the password shown. Paste it here — the config snippets below will update automatically.", 'lax-abilities-toolkit' ) }
					</p>
					<Flex align="flex-end" gap={ 3 } wrap>
						<FlexItem>
							<TextControl
								label={ __( 'Your Application Password', 'lax-abilities-toolkit' ) }
								value={ password }
								onChange={ setPassword }
								placeholder="xxxx xxxx xxxx xxxx xxxx xxxx"
								type="password"
								className="lax-pw-input"
								__nextHasNoMarginBottom
							/>
						</FlexItem>
						<FlexItem>
							<Button
								variant="secondary"
								href={ data.appPasswordUrl }
								target="_blank"
								rel="noreferrer"
								__next40pxDefaultSize
							>
								{ __( 'Open Profile Page ↗', 'lax-abilities-toolkit' ) }
							</Button>
						</FlexItem>
					</Flex>
				</CardBody>
			</Card>

			{ /* ── Connect section ────────────────────────────────────── */ }
			<Card className="lax-card">
				<CardHeader>
					<strong>{ __( 'Connect Your AI Client', 'lax-abilities-toolkit' ) }</strong>
				</CardHeader>
				<CardBody>
					<TabPanel tabs={ tabs }>
						{ ( tab ) => (
							<ClientTab name={ tab.name } password={ password } />
						) }
					</TabPanel>
				</CardBody>
			</Card>

			{ /* ── Registered abilities ─────────────────────────────────*/ }
			<Card className="lax-card">
				<CardHeader>
					<Flex justify="space-between" style={ { width: '100%' } }>
						<FlexItem>
							<strong>{ __( 'Registered Abilities', 'lax-abilities-toolkit' ) }</strong>
						</FlexItem>
						<FlexItem>
							<span className="lax-count">
								{ ( data.abilities || [] ).length }
							</span>
						</FlexItem>
					</Flex>
				</CardHeader>
				<CardBody>
					{ data.abilities && data.abilities.length > 0 ? (
						<table className="widefat striped">
							<thead>
								<tr>
									<th>{ __( 'Ability', 'lax-abilities-toolkit' ) }</th>
									<th>{ __( 'Label', 'lax-abilities-toolkit' ) }</th>
									<th>{ __( 'Description', 'lax-abilities-toolkit' ) }</th>
								</tr>
							</thead>
							<tbody>
								{ data.abilities.map( ( a, i ) => (
									<tr key={ i }>
										<td><code>{ a.name }</code></td>
										<td>{ a.label }</td>
										<td>{ a.description }</td>
									</tr>
								) ) }
							</tbody>
						</table>
					) : (
						<p>
							{ __( 'No lax-abilities registered yet. Ensure the plugin is activated correctly.', 'lax-abilities-toolkit' ) }
						</p>
					) }
				</CardBody>
			</Card>

			<p className="lax-footer">
				{ `Lax Abilities Toolkit v${ data.version } — ` }
				<a href="https://github.com/laxmariappan/lax-abilities-toolkit" target="_blank" rel="noreferrer">GitHub</a>
				{ ' | MCP Adapter: ' }
				<a href="https://github.com/WordPress/mcp-adapter" target="_blank" rel="noreferrer">WordPress/mcp-adapter</a>
			</p>
		</div>
	);
}

// =============================================================================
// Mount
// =============================================================================

const rootEl = document.getElementById( 'lax-abilities-admin-root' );
if ( rootEl ) {
	createRoot( rootEl ).render( <LaxAbilitiesAdmin /> );
}
