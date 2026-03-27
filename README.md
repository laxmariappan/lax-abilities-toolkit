# Lax Abilities Toolkit

A developer-friendly WordPress plugin that exposes content management as **Abilities** via the [WP Abilities API](https://developer.wordpress.org/reference/) (WordPress 6.9+). Works out of the box with posts, pages, categories, and tags — and extends to any custom post type or taxonomy via filter hooks.

> Designed to work with the [MCP Adapter](https://github.com/Automattic/mcp-adapter) plugin, exposing your site's content to AI agents via the Model Context Protocol.

---

## Requirements

- WordPress 6.9 or higher (Abilities API)
- PHP 7.4 or higher

---

## Built-in Abilities

| Ability | Description |
|---|---|
| `lax-abilities/create-post` | Create a post, with optional scheduling |
| `lax-abilities/update-post` | Update a post, with optional rescheduling |
| `lax-abilities/list-posts` | List posts filtered by status |
| `lax-abilities/get-post` | Get full details of a single post |
| `lax-abilities/create-page` | Create a page with parent/template support |
| `lax-abilities/update-page` | Update a page |
| `lax-abilities/list-pages` | List pages |
| `lax-abilities/get-page` | Get full details of a single page |
| `lax-abilities/create-category` | Create a category (returns existing on duplicate) |
| `lax-abilities/list-categorys` | List all categories with counts |
| `lax-abilities/create-tag` | Create a tag (returns existing on duplicate) |
| `lax-abilities/list-tags` | List all tags with counts |
| `lax-abilities/site-info` | Site name, URL, WP version, timezone, language |

---

## Scheduling Support

`create-post` and `update-post` accept a `publish_date` field:

```json
{
  "title": "My Post",
  "content": "Hello world",
  "publish_date": "2025-06-01 09:00:00"
}
```

When `publish_date` is in the future the status is automatically set to `future`. The response always includes `scheduled_for` and `scheduled_for_gmt` when a post is scheduled, so the caller knows exactly when it will go live.

---

## Extending: Custom Post Types

Register any CPT with the `lax_abilities_registered_post_types` filter. The plugin automatically generates `create`, `update`, `list`, and `get` abilities for it.

```php
add_filter( 'lax_abilities_registered_post_types', function( $types ) {
    $types['product'] = array(
        'label'           => 'Product',
        'plural'          => 'Products',
        'capability_type' => 'product',
        'taxonomies'      => array( 'product_cat', 'product_tag' ),
        'supports_schedule' => false,
    );
    return $types;
} );
```

This registers: `lax-abilities/create-product`, `lax-abilities/update-product`, `lax-abilities/list-products`, `lax-abilities/get-product`.

### Config options

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

## Extending: Custom Taxonomies

```php
add_filter( 'lax_abilities_registered_taxonomies', function( $taxonomies ) {
    $taxonomies['product_cat'] = array(
        'label'        => 'Product Category',
        'plural'       => 'Product Categories',
        'hierarchical' => true,
        'ability_slug' => 'product-category', // ability name: lax-abilities/create-product-category
    );
    return $taxonomies;
} );
```

---

## Extending: Extra Fields & Meta

### Add fields to the create/update schema

```php
add_filter( 'lax_abilities_input_schema_product', function( $schema, $post_type ) {
    $schema['properties']['price'] = array(
        'type'        => 'string',
        'description' => 'Product price.',
    );
    $schema['properties']['sku'] = array(
        'type'        => 'string',
        'description' => 'Product SKU.',
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
    if ( isset( $params['sku'] ) ) {
        $post_data['meta_input']['_sku'] = sanitize_text_field( $params['sku'] );
    }
    return $post_data;
}, 10, 3 );
```

### Extend the response

```php
// Extend create/update responses
add_filter( 'lax_abilities_post_response_product', function( $response, $post ) {
    $response['price'] = get_post_meta( $post->ID, '_price', true );
    $response['sku']   = get_post_meta( $post->ID, '_sku', true );
    return $response;
}, 10, 2 );

// Extend list items
add_filter( 'lax_abilities_list_item_product', function( $item, $post, $post_type ) {
    $item['price'] = get_post_meta( $post->ID, '_price', true );
    return $item;
}, 10, 3 );

// Extend single-item detail
add_filter( 'lax_abilities_post_detail_product', function( $item, $post, $post_type ) {
    $item['price']      = get_post_meta( $post->ID, '_price', true );
    $item['sku']        = get_post_meta( $post->ID, '_sku', true );
    $item['stock']      = get_post_meta( $post->ID, '_stock', true );
    return $item;
}, 10, 3 );
```

---

## Filter Reference

| Filter | Args | Description |
|---|---|---|
| `lax_abilities_registered_post_types` | `$types` | Add/modify post type configurations |
| `lax_abilities_registered_taxonomies` | `$taxonomies` | Add/modify taxonomy configurations |
| `lax_abilities_input_schema_{post_type}` | `$schema, $post_type` | Extend create/update input schema |
| `lax_abilities_post_data_{post_type}` | `$post_data, $params, $post_type` | Modify post data before insert/update |
| `lax_abilities_post_response_{post_type}` | `$response, $post` | Extend create/update response |
| `lax_abilities_list_item_{post_type}` | `$item, $post, $post_type` | Extend individual list items |
| `lax_abilities_post_detail_{post_type}` | `$item, $post, $post_type` | Extend single-item get response |

---

## License

GPL v2 or later.
