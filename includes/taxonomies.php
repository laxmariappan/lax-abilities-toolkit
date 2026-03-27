<?php
/**
 * Dynamic taxonomy abilities: create and list.
 *
 * Register additional taxonomies via the lax_abilities_registered_taxonomies filter:
 *
 *     add_filter( 'lax_abilities_registered_taxonomies', function( $taxonomies ) {
 *         $taxonomies['product_cat'] = array(
 *             'label'        => 'Product Category',
 *             'plural'       => 'Product Categories',
 *             'hierarchical' => true,
 *             'ability_slug' => 'product-category',  // ability name: lax-abilities/create-product-category
 *         );
 *         $taxonomies['product_tag'] = array(
 *             'label'        => 'Product Tag',
 *             'plural'       => 'Product Tags',
 *             'hierarchical' => false,
 *             'ability_slug' => 'product-tag',
 *         );
 *         return $taxonomies;
 *     } );
 *
 * @package LaxAbilitiesToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// =============================================================================
// Registry
// =============================================================================

/**
 * Returns the array of taxonomies to register abilities for.
 *
 * @return array<string, array>
 */
function lax_abilities_get_taxonomies() {
	$defaults = array(
		'category' => array(
			'label'        => __( 'Category', 'lax-abilities-toolkit' ),
			'plural'       => __( 'Categories', 'lax-abilities-toolkit' ),
			'hierarchical' => true,
			'ability_slug' => 'category',
		),
		'post_tag' => array(
			'label'        => __( 'Tag', 'lax-abilities-toolkit' ),
			'plural'       => __( 'Tags', 'lax-abilities-toolkit' ),
			'hierarchical' => false,
			'ability_slug' => 'tag',
		),
	);

	/**
	 * Filters the taxonomies that get create/list abilities registered.
	 *
	 * Each entry is keyed by the WP taxonomy slug. Supported config keys:
	 *
	 *   label        string  Singular human-readable label.
	 *   plural       string  Plural human-readable label.
	 *   hierarchical bool    Whether the taxonomy supports parent terms.
	 *   ability_slug string  Override for the ability name slug (default: sanitised taxonomy key).
	 *                        Resulting ability names: lax-abilities/create-{ability_slug}
	 *                                                 lax-abilities/list-{ability_slug}s
	 *
	 * @param array $taxonomies Registered taxonomy configurations.
	 */
	return apply_filters( 'lax_abilities_registered_taxonomies', $defaults );
}

/**
 * Register create/list abilities for all configured taxonomies.
 */
function lax_abilities_register_taxonomy_abilities_all() {
	$defaults = array(
		'label'        => '',
		'plural'       => '',
		'hierarchical' => false,
		'ability_slug' => '',
	);

	foreach ( lax_abilities_get_taxonomies() as $taxonomy => $config ) {
		$config = wp_parse_args( $config, $defaults );

		if ( empty( $config['label'] ) ) {
			$config['label'] = ucwords( str_replace( array( '-', '_' ), ' ', $taxonomy ) );
		}
		if ( empty( $config['plural'] ) ) {
			$config['plural'] = $config['label'] . 's';
		}
		if ( empty( $config['ability_slug'] ) ) {
			$config['ability_slug'] = lax_abilities_to_slug( $taxonomy );
		}

		lax_abilities_register_taxonomy_abilities( $taxonomy, $config );
	}
}

// =============================================================================
// Ability registration
// =============================================================================

/**
 * Register create and list abilities for a single taxonomy.
 *
 * @param string $taxonomy WP taxonomy slug.
 * @param array  $config   Merged taxonomy configuration.
 */
function lax_abilities_register_taxonomy_abilities( $taxonomy, $config ) {
	$slug  = $config['ability_slug'];
	$label = $config['label'];
	$cat   = LAX_ABILITIES_CATEGORY;

	$create_props = array(
		'name'        => array( 'type' => 'string', 'description' => sprintf( __( '%s name.', 'lax-abilities-toolkit' ), $label ) ),
		'slug'        => array( 'type' => 'string', 'description' => __( 'Slug (auto-generated from name if omitted).', 'lax-abilities-toolkit' ), 'default' => '' ),
		'description' => array( 'type' => 'string', 'description' => __( 'Description.', 'lax-abilities-toolkit' ), 'default' => '' ),
	);
	if ( ! empty( $config['hierarchical'] ) ) {
		$create_props['parent_id'] = array(
			'type'        => 'integer',
			'description' => __( 'Parent term ID (0 for top-level).', 'lax-abilities-toolkit' ),
			'default'     => 0,
		);
	}

	// ── create ───────────────────────────────────────────────────────────────
	wp_register_ability( "lax-abilities/create-{$slug}", array(
		'label'               => sprintf( __( 'Create %s', 'lax-abilities-toolkit' ), $label ),
		'description'         => sprintf( __( 'Create a new %s term. Returns existing term data if the name already exists.', 'lax-abilities-toolkit' ), strtolower( $label ) ),
		'category'            => $cat,
		'input_schema'        => array(
			'type'       => 'object',
			'properties' => $create_props,
			'required'   => array( 'name' ),
		),
		'execute_callback'    => function ( $params ) use ( $taxonomy, $config ) {
			return lax_abilities_create_term_handler( $params, $taxonomy, $config );
		},
		'permission_callback' => function () {
			return current_user_can( 'manage_categories' );
		},
		'meta' => array(
			'show_in_rest' => true,
			'mcp'          => array( 'public' => true ),
			'annotations'  => array( 'readonly' => false, 'destructive' => false ),
		),
	) );

	// ── list ─────────────────────────────────────────────────────────────────
	wp_register_ability( "lax-abilities/list-{$slug}s", array(
		'label'               => sprintf( __( 'List %s', 'lax-abilities-toolkit' ), $config['plural'] ),
		'description'         => sprintf( __( 'List all %s with IDs, slugs, and post counts.', 'lax-abilities-toolkit' ), strtolower( $config['plural'] ) ),
		'category'            => $cat,
		'input_schema'        => array(
			'type'       => 'object',
			'properties' => array(
				'hide_empty' => array(
					'type'        => 'boolean',
					'default'     => false,
					'description' => __( 'Exclude terms with no assigned posts.', 'lax-abilities-toolkit' ),
				),
			),
		),
		'execute_callback'    => function ( $params ) use ( $taxonomy, $config ) {
			return lax_abilities_list_terms_handler( $params, $taxonomy, $config );
		},
		'permission_callback' => '__return_true',
		'meta' => array(
			'show_in_rest' => true,
			'mcp'          => array( 'public' => true ),
			'annotations'  => array( 'readonly' => true, 'destructive' => false ),
		),
	) );
}

// =============================================================================
// Handlers
// =============================================================================

/**
 * Execute handler: create a taxonomy term.
 *
 * @param  array  $params
 * @param  string $taxonomy
 * @param  array  $config
 * @return array|WP_Error
 */
function lax_abilities_create_term_handler( $params, $taxonomy, $config ) {
	$args = array(
		'description' => isset( $params['description'] ) ? sanitize_text_field( $params['description'] ) : '',
	);

	if ( ! empty( $params['slug'] ) ) {
		$args['slug'] = sanitize_title( $params['slug'] );
	}

	if ( ! empty( $config['hierarchical'] ) && isset( $params['parent_id'] ) ) {
		$args['parent'] = absint( $params['parent_id'] );
	}

	$result = wp_insert_term( sanitize_text_field( $params['name'] ), $taxonomy, $args );

	if ( is_wp_error( $result ) ) {
		// Surface existing term data instead of failing hard.
		if ( 'term_exists' === $result->get_error_code() ) {
			$existing_id   = (int) $result->get_error_data();
			$existing_term = get_term( $existing_id, $taxonomy );
			$response = array(
				'success'        => false,
				'already_exists' => true,
				'id'             => $existing_id,
				'name'           => $existing_term->name,
				'slug'           => $existing_term->slug,
				'message'        => sprintf( __( '"%s" already exists — returning existing term.', 'lax-abilities-toolkit' ), $existing_term->name ),
			);
			if ( ! empty( $config['hierarchical'] ) ) {
				$response['parent_id'] = (int) $existing_term->parent;
			}
			return $response;
		}
		return $result;
	}

	$term     = get_term( $result['term_id'], $taxonomy );
	$response = array(
		'success'     => true,
		'id'          => (int) $result['term_id'],
		'name'        => $term->name,
		'slug'        => $term->slug,
		'description' => $term->description,
		'message'     => sprintf( __( '"%s" created.', 'lax-abilities-toolkit' ), $term->name ),
	);
	if ( ! empty( $config['hierarchical'] ) ) {
		$response['parent_id'] = (int) $term->parent;
	}
	return $response;
}

/**
 * Execute handler: list all terms for a taxonomy.
 *
 * @param  array  $params
 * @param  string $taxonomy
 * @param  array  $config
 * @return array|WP_Error
 */
function lax_abilities_list_terms_handler( $params, $taxonomy, $config ) {
	$terms = get_terms( array(
		'taxonomy'   => $taxonomy,
		'hide_empty' => isset( $params['hide_empty'] ) ? (bool) $params['hide_empty'] : false,
		'orderby'    => 'name',
		'order'      => 'ASC',
	) );

	if ( is_wp_error( $terms ) ) {
		return $terms;
	}

	$items = array_map( function ( WP_Term $term ) use ( $config ) {
		$item = array(
			'id'          => $term->term_id,
			'name'        => $term->name,
			'slug'        => $term->slug,
			'description' => $term->description,
			'post_count'  => $term->count,
		);
		if ( ! empty( $config['hierarchical'] ) ) {
			$item['parent_id'] = (int) $term->parent;
		}
		return $item;
	}, $terms );

	return array( 'success' => true, 'count' => count( $items ), 'items' => $items );
}
