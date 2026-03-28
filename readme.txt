=== Lax Abilities Toolkit ===
Contributors: laxmariappan
Tags: mcp, ai, automation, content management, claude
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect your WordPress site to AI assistants via MCP. Manage posts, pages, categories, tags, and media through natural conversation.

== Description ==

Lax Abilities Toolkit connects your WordPress site to any MCP-compatible AI client — Claude Desktop, Cursor, VS Code, and others — so they can read and manage your content through natural conversation.

Built on the WP Abilities API (WordPress 6.9+) and the MCP Adapter plugin, it exposes your posts, pages, categories, tags, and media library as structured tools that AI clients understand natively.

**What your AI assistant can do once connected:**

* **Write and publish** — draft posts and pages, schedule them for a future date
* **Edit content** — update titles, body, status, categories, tags, and metadata
* **Browse your library** — list posts by status, filter media by type, look up a single item by ID
* **Manage taxonomies** — create categories and tags, list them with post counts, delete unused ones
* **Handle media** — browse the media library, retrieve image URLs and dimensions, delete files
* **Answer site questions** — name, URL, WordPress version, timezone, language, admin email

Everything is scoped to the logged-in user's WordPress capabilities. The AI can only do what that user account is allowed to do.

**Works with any MCP client:**

Claude Desktop, Cursor, VS Code (1.99+), and any other client that supports the Model Context Protocol.

**Ability Groups:**

From Settings → Lax Abilities → Ability Groups, you can enable or disable entire groups of abilities. Turn off media abilities if you only want the AI to manage written content, or disable site-info to keep that private.

**Developer-friendly:**

Register custom post types and taxonomies via filter hooks. The plugin generates full create, update, list, get, and delete abilities automatically.

== Installation ==

1. Download the latest release zip from the [releases page](https://github.com/laxmariappan/lax-abilities-toolkit/releases).
2. In WordPress admin go to **Plugins → Add New → Upload Plugin** and upload the zip.
3. Activate **Lax Abilities Toolkit**.
4. Install and activate the **MCP Adapter** plugin the same way.
5. Go to **Settings → Lax Abilities** — the setup guide and ready-to-use config snippets are right there.

== Connecting to an AI client ==

= Step 1 — Create an Application Password =

1. In WordPress admin, go to **Users → Profile**.
2. Scroll to **Application Passwords**.
3. Type a name (e.g. `Claude Desktop`) and click **Add New Application Password**.
4. Copy the password shown — you won't see it again.

The **Settings → Lax Abilities** page has a password field that auto-fills all config snippets below.

= Step 2 — Configure your client =

Add your site URL, WordPress username, and Application Password to your MCP client config. The Settings page generates a ready-to-paste snippet for Claude Desktop, Cursor, and VS Code.

= Step 3 — Start a conversation =

Open a conversation in your AI client and tell it what you need done. The Settings page generates a pre-filled starter prompt with your actual site name, URL, and username.

== Frequently Asked Questions ==

= Which WordPress version is required? =

WordPress 6.9 or higher. Lax Abilities Toolkit uses the WP Abilities API, which ships in core from 6.9 onwards.

= Do I need the MCP Adapter plugin? =

Yes. The MCP Adapter plugin bridges WordPress abilities to AI clients over HTTP. It is a required companion plugin.

= Which AI clients are supported? =

Any client that supports the Model Context Protocol (MCP). This includes Claude Desktop, Cursor, VS Code 1.99+, and others.

= Is this only for Claude? =

No. The plugin is client-agnostic. It works with any MCP-compatible AI assistant.

= Can the AI do anything it wants on my site? =

No. Every ability is scoped to the WordPress capabilities of the authenticated user. If you connect with an Editor account, the AI cannot perform Administrator-only actions.

= Can I limit which abilities are available? =

Yes. Go to **Settings → Lax Abilities → Ability Groups** to enable or disable ability groups.

= Can I add my own post types or taxonomies? =

Yes. Use the `lax_abilities_registered_post_types` and `lax_abilities_registered_taxonomies` filters. The plugin generates full CRUD abilities automatically.

= Is this the same as Claude2Blog? =

Lax Abilities Toolkit is the advanced version of Claude2Blog. Claude2Blog focused on publishing posts. This plugin extends that into full site management and works with any MCP client.

== Screenshots ==

1. Settings page with MCP connection guide and ready-to-use config snippets.
2. Ability Groups panel for enabling and disabling ability categories.

== Changelog ==

= 1.3.0 =
* Added Ability Groups — enable or disable groups from the Settings page.
* Extended developer filter reference.

= 1.2.0 =
* Added scheduling support for posts and pages via `publish_date` field.
* Added `site-info` ability.

= 1.1.0 =
* Added media abilities: list, get, delete.
* Added taxonomy abilities for categories and tags.

= 1.0.0 =
* Initial release. Post and page CRUD via MCP.

== Upgrade Notice ==

= 1.3.0 =
New Ability Groups setting lets you control which ability categories are exposed to AI clients.
