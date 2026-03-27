# Changelog

All notable changes to Lax Abilities Toolkit are documented here.
This project follows [Semantic Versioning](https://semver.org/).

---

## [1.1.0] — 2026-03-27

### Added
- **Delete ability** for every registered post type (`lax-abilities/delete-{post_type}`).
  Moves to trash by default; `force_delete: true` permanently deletes (requires elevated capability).
- **Media abilities**: `upload-media` (sideload from URL), `list-media`, `get-media`, `delete-media`.
- **Taxonomy delete ability** (`lax-abilities/delete-{slug}`) for every registered taxonomy.
- **Admin settings page** (Settings → Lax Abilities) with:
  - MCP Adapter status check
  - MCP server endpoint URL
  - Step-by-step Application Password guide
  - Ready-to-paste config snippets for Claude Desktop, Cursor, VS Code, and generic env-var clients
  - Live table of all registered `lax-abilities/*` abilities
- **Action hooks** on every write operation:
  `lax_abilities_before_create_{post_type}`, `lax_abilities_after_create_{post_type}`,
  `lax_abilities_before_update_{post_type}`, `lax_abilities_after_update_{post_type}`,
  `lax_abilities_before_delete_{post_type}`, `lax_abilities_after_delete_{post_type}`.
- **GitHub Actions release workflow** — pushes a tag (e.g. `v1.1.0`) to automatically build
  and attach the distributable zip to a GitHub Release.
- **`bin/build.sh`** — local build script for generating a distributable zip.
- **`.distignore`** — used by `wp dist-archive` and the build script.

### Changed
- **RBAC improvements** across all abilities:
  - List abilities now use `WP_Query` with `perm=readable` to automatically scope results to
    what the current user can read. Non-public statuses (draft, private, future) require
    `edit_{type}s` capability.
  - Get ability checks `edit_post` (via `map_meta_cap`) for non-public posts.
  - Update ability re-checks `current_user_can('edit_post', $id)` inside the handler as
    defence-in-depth. Authors cannot edit other authors' posts.
  - Delete ability uses `current_user_can('delete_post', $id)` (respects ownership via
    `map_meta_cap`). Force-delete additionally requires `delete_others_{type}s` capability.
- **Capability resolution** now uses `get_post_type_object()->cap` when the post type is
  registered in WordPress, falling back to `capability_type` derivation for unregistered types.
  Developers can still override individual caps via `config['capabilities']`.
- **List ability names** now use the plural label slug (e.g. `list-categories`, `list-tags`,
  `list-posts`) instead of the potentially incorrect `{slug}s` pattern.
- Post type **`get` permission callback** is smarter: published posts only require `read`;
  draft/private/future posts require `edit_post` on the specific item.
- Error codes are now consistently prefixed `lax_abilities_*` for easier filtering.
- `gmdate()` used instead of `date()` for timezone-safe date formatting.

### Fixed
- Category ability name was incorrectly `list-categorys` — now correctly `list-categories`.

---

## [1.0.0] — 2026-03-27

### Added
- Initial release.
- Dynamic post type abilities (create, update, list, get) registered via
  `lax_abilities_registered_post_types` filter.
- Dynamic taxonomy abilities (create, list) registered via
  `lax_abilities_registered_taxonomies` filter.
- Built-in support for `post` and `page` post types.
- Built-in support for `category` and `post_tag` taxonomies.
- Scheduling support via `publish_date` field; auto-promotes status to `future`.
- `scheduled_for` / `scheduled_for_gmt` in all post responses when status is `future`.
- Developer extension filters: `lax_abilities_input_schema_{post_type}`,
  `lax_abilities_post_data_{post_type}`, `lax_abilities_post_response_{post_type}`,
  `lax_abilities_list_item_{post_type}`, `lax_abilities_post_detail_{post_type}`.
- `lax-abilities/site-info` ability.
- WP 7.0 compatible: registers category on `wp_abilities_api_categories_init`.
- Graceful duplicate handling on term creation.
