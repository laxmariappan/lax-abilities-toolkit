# Lax Abilities Toolkit

Connect your WordPress site to AI assistants — Claude Desktop, Cursor, VS Code, and any MCP-compatible client — so they can read and manage your content through natural conversation.

Built on the [WP Abilities API](https://developer.wordpress.org/reference/) (WordPress 6.9+) and the [MCP Adapter](https://github.com/Automattic/mcp-adapter) plugin, Lax Abilities Toolkit exposes your posts, pages, categories, tags, and media library as structured tools that AI clients understand natively.

---

## What you can do

Once connected, your AI assistant can:

- **Write and publish** — draft posts and pages, schedule them for a future date, set featured images
- **Edit content** — update titles, body, status, categories, tags, and metadata
- **Browse your library** — list posts by status, filter media by type, look up a single item by ID
- **Manage taxonomies** — create categories and tags, list them with post counts, delete unused ones
- **Handle media** — browse the media library, retrieve image URLs and dimensions, delete files
- **Answer site questions** — name, URL, WordPress version, timezone, language, admin email

Everything is scoped to the logged-in user's WordPress capabilities, so the AI can only do what that user account is allowed to do.

---

## Requirements

- **WordPress 6.9+** (the Abilities API ships in core)
- **PHP 7.4+**
- **[MCP Adapter plugin](https://github.com/Automattic/mcp-adapter)** — exposes abilities to AI clients over HTTP
- **[Node.js LTS](https://nodejs.org)** — needed on the computer running the AI client (includes `npx`)

---

## Installation

1. Download the latest release zip from the [Releases page](https://github.com/laxmariappan/lax-abilities-toolkit/releases).
2. In WordPress admin, go to **Plugins → Add New → Upload Plugin** and upload the zip.
3. Activate **Lax Abilities Toolkit**.
4. Install and activate the **MCP Adapter** plugin the same way.
5. Go to **Settings → Lax Abilities** — the setup guide is right there on that page.

---

## Connecting to an AI client

### Step 1 — Create an Application Password

Application Passwords let AI clients log in to your site securely without using your main WordPress password.

1. In WordPress admin, click your name in the top-right corner, then **Edit Profile** (or go to **Users → Profile**).
2. Scroll to the **Application Passwords** section near the bottom.
3. Type a name — something like `Claude Desktop` — and click **Add New Application Password**.
4. **Copy the password shown** — it looks like `xxxx xxxx xxxx xxxx xxxx xxxx`. You won't see it again.

> The **Settings → Lax Abilities** page has a password field that auto-fills all config snippets below. Paste your password there to get ready-to-use configs.

---

### Step 2 — Configure your AI client

Pick the client you use:

#### Claude Desktop

1. [Download Claude Desktop](https://claude.ai/download) if you haven't already.
2. Open (or create) the config file for your OS:
   - **macOS:** `~/Library/Application Support/Claude/claude_desktop_config.json`
   - **Windows:** `%APPDATA%\Claude\claude_desktop_config.json`
   - **Linux:** `~/.config/Claude/claude_desktop_config.json`
3. Add the entry below inside `"mcpServers"`. If the file is empty, paste the whole block:

```json
{
  "mcpServers": {
    "my-wordpress-site": {
      "command": "npx",
      "args": ["-y", "@automattic/mcp-wordpress-remote"],
      "env": {
        "WP_API_URL": "https://yoursite.com/wp-json/mcp/mcp-adapter-default-server",
        "WP_API_USERNAME": "your-username",
        "WP_API_PASSWORD": "your-application-password"
      }
    }
  }
}
```

4. Replace the three values with your actual site URL, WordPress username, and the application password from Step 1.
5. Fully quit Claude Desktop (`Cmd+Q` on Mac / `Alt+F4` on Windows) and reopen it.
6. A hammer icon will appear in the chat toolbar — that's your WordPress tools.

---

#### Cursor

1. [Download Cursor](https://cursor.com) if you haven't already.
2. Open or create `~/.cursor/mcp.json` (global) or `.cursor/mcp.json` in your project root:

```json
{
  "mcpServers": {
    "my-wordpress-site": {
      "command": "npx",
      "args": ["-y", "@automattic/mcp-wordpress-remote"],
      "env": {
        "WP_API_URL": "https://yoursite.com/wp-json/mcp/mcp-adapter-default-server",
        "WP_API_USERNAME": "your-username",
        "WP_API_PASSWORD": "your-application-password"
      }
    }
  }
}
```

3. Reload Cursor: `Cmd/Ctrl + Shift + P` → **Developer: Reload Window**.
4. The server appears in **Cursor Settings → MCP**.

---

#### VS Code

1. Install **VS Code 1.99 or later** (MCP support is built in).
2. Create `.vscode/mcp.json` in your project root:

```json
{
  "servers": {
    "my-wordpress-site": {
      "type": "stdio",
      "command": "npx",
      "args": ["-y", "@automattic/mcp-wordpress-remote"],
      "env": {
        "WP_API_URL": "https://yoursite.com/wp-json/mcp/mcp-adapter-default-server",
        "WP_API_USERNAME": "your-username",
        "WP_API_PASSWORD": "your-application-password"
      }
    }
  }
}
```

3. VS Code discovers the server automatically — no restart needed.
4. Open GitHub Copilot Chat (or any MCP-aware extension) to see the WordPress tools.

---

#### Other MCP clients

Set these three environment variables, then run the bridge:

```sh
export WP_API_URL="https://yoursite.com/wp-json/mcp/mcp-adapter-default-server"
export WP_API_USERNAME="your-username"
export WP_API_PASSWORD="your-application-password"

npx -y @automattic/mcp-wordpress-remote
```

---

### Step 3 — Start a conversation

Once connected, paste this starter prompt to orient your AI and let it discover what it can do:

```
I've connected my WordPress site to you via MCP using the Lax Abilities Toolkit plugin.

Site: My Site Name (https://yoursite.com)
My WordPress username: your-username

You have access to abilities to create and manage posts, pages, categories, tags, and media on my site.

Please start by discovering all available abilities using your MCP tools, then give me a brief summary of what you can help me do.
```

> The **Settings → Lax Abilities** page generates a pre-filled version of this prompt with your actual site name, URL, and username — just copy and paste.

---

## Ability Groups

From **Settings → Lax Abilities → Ability Groups**, you can enable or disable entire groups of abilities. For example, you can turn off media abilities if you only want the AI to manage written content, or disable site-info if you prefer to keep that private.

Changes take effect immediately — no plugin reactivation needed.

---

## All built-in abilities

### Posts

| Ability | Description |
|---|---|
| `lax-abilities/create-post` | Create a post; supports scheduling, featured image, categories, tags |
| `lax-abilities/update-post` | Update title, content, status, date, and metadata |
| `lax-abilities/list-posts` | List posts filtered by status (draft, publish, future, etc.) |
| `lax-abilities/get-post` | Full details of a single post by ID |
| `lax-abilities/delete-post` | Move a post to trash |

### Pages

| Ability | Description |
|---|---|
| `lax-abilities/create-page` | Create a page with parent and template support |
| `lax-abilities/update-page` | Update a page |
| `lax-abilities/list-pages` | List pages |
| `lax-abilities/get-page` | Full details of a single page by ID |
| `lax-abilities/delete-page` | Move a page to trash |

### Categories

| Ability | Description |
|---|---|
| `lax-abilities/create-category` | Create a category (returns existing data if name already exists) |
| `lax-abilities/list-categories` | List all categories with IDs, slugs, and post counts |
| `lax-abilities/delete-category` | Permanently delete a category by ID |

### Tags

| Ability | Description |
|---|---|
| `lax-abilities/create-tag` | Create a tag (returns existing data if name already exists) |
| `lax-abilities/list-tags` | List all tags with IDs, slugs, and post counts |
| `lax-abilities/delete-tag` | Permanently delete a tag by ID |

### Media

| Ability | Description |
|---|---|
| `lax-abilities/list-media` | List media items with URLs, dimensions, alt text; filter by MIME type or parent post |
| `lax-abilities/get-media` | Full details of a single attachment including all registered image sizes |
| `lax-abilities/delete-media` | Permanently delete a media attachment and its associated files |

### Site

| Ability | Description |
|---|---|
| `lax-abilities/site-info` | Site name, URL, tagline, WordPress version, timezone, language, admin email |

---

## Scheduling posts

`create-post` and `update-post` accept an optional `publish_date` field in `YYYY-MM-DD HH:MM:SS` format (site timezone). When the date is in the future the post status is automatically set to `future`. The response includes `scheduled_for` and `scheduled_for_gmt` so the AI can confirm exactly when it will go live.

```json
{
  "title": "My Upcoming Post",
  "content": "Hello world.",
  "publish_date": "2025-09-01 09:00:00"
}
```

---

## Developer: Extending with custom post types

Register any post type with the `lax_abilities_registered_post_types` filter. The plugin generates `create`, `update`, `list`, `get`, and `delete` abilities automatically.

```php
add_filter( 'lax_abilities_registered_post_types', function( $types ) {
    $types['product'] = array(
        'label'             => 'Product',
        'plural'            => 'Products',
        'capability_type'   => 'product',
        'taxonomies'        => array( 'product_cat', 'product_tag' ),
        'supports_schedule' => false,
    );
    return $types;
} );
// Registers: lax-abilities/create-product, lax-abilities/update-product,
//            lax-abilities/list-products, lax-abilities/get-product,
//            lax-abilities/delete-product
```

Only post types with `public = true` are shown in the admin UI and registered as abilities. Internal WordPress types (nav menus, patterns, templates, etc.) are automatically excluded.

### Post type config options

| Key | Type | Default | Description |
|---|---|---|---|
| `label` | string | derived from slug | Singular label |
| `plural` | string | label + "s" | Plural label |
| `capability_type` | string | post type slug | Used to derive `publish_{type}s` cap |
| `taxonomies` | string[] | `[]` | Taxonomy slugs; generates `{taxonomy}_ids` input fields |
| `supports_parent` | bool | `false` | Adds `parent_id` field |
| `supports_template` | bool | `false` | Adds `template` field |
| `supports_schedule` | bool | `true` | Adds `publish_date` scheduling field |

---

## Developer: Extending with custom taxonomies

```php
add_filter( 'lax_abilities_registered_taxonomies', function( $taxonomies ) {
    $taxonomies['product_cat'] = array(
        'label'        => 'Product Category',
        'plural'       => 'Product Categories',
        'hierarchical' => true,
        'ability_slug' => 'product-category',
    );
    return $taxonomies;
} );
// Registers: lax-abilities/create-product-category
//            lax-abilities/list-product-categories
//            lax-abilities/delete-product-category
```

Only taxonomies with `publicly_queryable` or `show_ui` set to `true` are included.

---

## Developer: Extra fields and meta

### Add fields to the create/update schema

```php
add_filter( 'lax_abilities_input_schema_product', function( $schema, $post_type ) {
    $schema['properties']['price'] = array(
        'type'        => 'string',
        'description' => 'Product price.',
    );
    return $schema;
}, 10, 2 );
```

### Persist extra fields before insert/update

```php
add_filter( 'lax_abilities_post_data_product', function( $post_data, $params, $post_type ) {
    if ( isset( $params['price'] ) ) {
        $post_data['meta_input']['_price'] = sanitize_text_field( $params['price'] );
    }
    return $post_data;
}, 10, 3 );
```

### Extend the response

```php
// create/update responses
add_filter( 'lax_abilities_post_response_product', function( $response, $post ) {
    $response['price'] = get_post_meta( $post->ID, '_price', true );
    return $response;
}, 10, 2 );

// list items
add_filter( 'lax_abilities_list_item_product', function( $item, $post, $post_type ) {
    $item['price'] = get_post_meta( $post->ID, '_price', true );
    return $item;
}, 10, 3 );

// single-item detail
add_filter( 'lax_abilities_post_detail_product', function( $item, $post, $post_type ) {
    $item['price'] = get_post_meta( $post->ID, '_price', true );
    $item['sku']   = get_post_meta( $post->ID, '_sku', true );
    return $item;
}, 10, 3 );
```

---

## Filter reference

| Filter | Args | Description |
|---|---|---|
| `lax_abilities_registered_post_types` | `$types` | Add or modify post type configurations |
| `lax_abilities_registered_taxonomies` | `$taxonomies` | Add or modify taxonomy configurations |
| `lax_abilities_input_schema_{post_type}` | `$schema, $post_type` | Extend create/update input schema |
| `lax_abilities_post_data_{post_type}` | `$post_data, $params, $post_type` | Modify post data before insert/update |
| `lax_abilities_post_response_{post_type}` | `$response, $post` | Extend create/update response |
| `lax_abilities_list_item_{post_type}` | `$item, $post, $post_type` | Extend individual list items |
| `lax_abilities_post_detail_{post_type}` | `$item, $post, $post_type` | Extend single-item get response |

---

## License

GPL v2 or later.
