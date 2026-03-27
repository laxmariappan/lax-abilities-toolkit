<?php
/**
 * Dynamic taxonomy abilities: create, list, delete.
 *
 * Abilities are generated for every taxonomy registered via the
 * `lax_abilities_registered_taxonomies` filter.
 *
 * ## Adding a custom taxonomy
 *
 *     add_filter(
 *         'lax_abilities_registered_taxonomies',
 *         function( array $taxonomies ): array {
 *             $taxonomies['product_cat'] = array(
 *                 'label'        => 'Product Category',
 *                 'plural'       => 'Product Categories',
 *                 'hierarchical' => true,
 *                 'ability_slug' => 'product-category',
 *             );
 *             return $taxonomies;
 *         }
 *     );
 *     // Registers: lax-abilities/create-product-category
 *     //            lax-abilities/list-product-categories
 *     //            lax-abilities/delete-product-category
 *
 * @package LaxAbilitiesToolkit
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// =============================================================================
// Registry
// =============================================================================

/**
 * Returns ALL configured taxonomy entries after applying developer filters.
 *
 * Used by the admin UI and settings page. Only public taxonomies
 * (publicly_queryable or show_ui) are included to avoid surfacing
 * internal taxonomies to AI clients.
 *
 * @since 1.3.0
 *
 * @return array<string, array> Keyed by WP taxonomy slug.
 */
function lax_abilities_all_taxonomies() {
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

	$taxonomies = apply_filters( 'lax_abilities_registered_taxonomies', $defaults );

	// Remove internal taxonomies that are not shown in the UI.
	return array_filter(
		$taxonomies,
		function ( $config, $slug ) {
			$tax_obj = get_taxonomy( $slug );
			if ( ! $tax_obj ) {
				return true; // Not registered yet; allow through.
			}
			return (bool) ( $tax_obj->publicly_queryable || $tax_obj->show_ui );
		},
		ARRAY_FILTER_USE_BOTH
	);
}

/**
 * Returns the taxonomy configurations to register abilities for,
 * filtered to only enabled groups (see Settings → Lax Abilities).
 *
 * @since 1.0.0
 *
 * @return array<string, array> Keyed by WP taxonomy slug.
 */
function lax_abilities_get_taxonomies() {
	return array_filter(
		lax_abilities_all_taxonomies(),
		function ( $config, $slug ) {
			return lax_abilities_is_group_enabled( $slug );
		},
		ARRAY_FILTER_USE_BOTH
	);
}

/**
 * Registers abilities for all configured taxonomies.
 *
 * @since 1.0.0
 *
 * @return void
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
 * Registers create / list / delete abilities for one taxonomy.
 *
 * @since 1.0.0
 *
 * @param string $taxonomy WP taxonomy slug.
 * @param array  $config   Merged taxonomy configuration.
 * @return void
 */
function lax_abilities_register_taxonomy_abilities( $taxonomy, $config ) {
	$slug        = $config['ability_slug'];
	$plural_slug = lax_abilities_to_slug( $config['plural'] );
	$label       = $config['label'];
	$cat         = LAX_ABILITIES_CATEGORY;

	$create_props = array(
		'name' => array(
			'type'        => 'string',
			/* translators: %s: taxonomy singular label */
			'description' => sprintf( __( '%s name.', 'lax-abilities-toolkit' ), $label ),
		),
		'slug' => array(
			'type'        => 'string',
			'description' => __( 'URL slug (auto-generated from name if omitted).', 'lax-abilities-toolkit' ),
			'default'     => '',
		),
		'description' => array(
			'type'        => 'string',
			'description' => __( 'Term description.', 'lax-abilities-toolkit' ),
			'default'     => '',
		),
	);

	if ( ! empty( $config['hierarchical'] ) ) {
		$create_props['parent_id'] = array(
			'type'        => 'integer',
			'description' => __( 'Parent term ID (0 for top-level).', 'lax-abilities-toolkit' ),
			'default'     => 0,
		);
	}

	// ── create ───────────────────────────────────────────────────────────────

	wp_register_ability(
		"lax-abilities/create-{$slug}",
		array(
			'label'       => sprintf(
				/* translators: %s: taxonomy singular label */
				__( 'Create %s', 'lax-abilities-toolkit' ),
				$label
			),
			'description' => sprintf(
				/* translators: %s: lowercase taxonomy singular label */
				__( 'Create a new %s term. Returns existing term data when the name already exists.', 'lax-abilities-toolkit' ),
				strtolower( $label )
			),
			'category'     => $cat,
			'input_schema' => array(
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
		)
	);

	// ── list ─────────────────────────────────────────────────────────────────

	wp_register_ability(
		"lax-abilities/list-{$plural_slug}",
		array(
			'label'       => sprintf(
				/* translators: %s: taxonomy plural label */
				__( 'List %s', 'lax-abilities-toolkit' ),
				$config['plural']
			),
			'description' => sprintf(
				/* translators: %s: lowercase taxonomy plural label */
				__( 'List all %s with IDs, slugs, descriptions, and post counts.', 'lax-abilities-toolkit' ),
				strtolower( $config['plural'] )
			),
			'category'     => $cat,
			'input_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'hide_empty' => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => __( 'Exclude terms that have no assigned posts.', 'lax-abilities-toolkit' ),
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
		)
	);

	// ── delete ───────────────────────────────────────────────────────────────

	wp_register_ability(
		"lax-abilities/delete-{$slug}",
		array(
			'label'       => sprintf(
				/* translators: %s: taxonomy singular label */
				__( 'Delete %s', 'lax-abilities-toolkit' ),
				$label
			),
			'description' => sprintf(
				/* translators: %s: lowercase taxonomy singular label */
				__( 'Permanently delete a %s term by ID. Posts assigned to this term will be reassigned to the parent term or left unassigned.', 'lax-abilities-toolkit' ),
				strtolower( $label )
			),
			'category'     => $cat,
			'input_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'id' => array(
						'type'        => 'integer',
						/* translators: %s: taxonomy singular label */
						'description' => sprintf( __( '%s term ID to delete.', 'lax-abilities-toolkit' ), $label ),
					),
				),
				'required' => array( 'id' ),
			),
			'execute_callback'    => function ( $params ) use ( $taxonomy, $config ) {
				return lax_abilities_delete_term_handler( $params, $taxonomy, $config );
			},
			'permission_callback' => function () {
				return current_user_can( 'manage_categories' );
			},
			'meta' => array(
				'show_in_rest' => true,
				'mcp'          => array( 'public' => true ),
				'annotations'  => array( 'readonly' => false, 'destructive' => true ),
			),
		)
	);
}

// =============================================================================
// Execute handlers
// =============================================================================

/**
 * Creates a taxonomy term.
 *
 * When the term name already exists, returns the existing term data with
 * `already_exists: true` rather than returning an error. This makes the
 * ability idempotent for create-or-get workflows.
 *
 * @since 1.0.0
 *
 * @param  array  $params   Validated input parameters.
 * @param  string $taxonomy WP taxonomy slug.
 * @param  array  $config   Taxonomy configuration.
 * @return array|WP_Error   Response array or WP_Error.
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
		// Gracefully surface existing term data instead of hard-failing.
		if ( 'term_exists' === $result->get_error_code() ) {
			$existing_id   = (int) $result->get_error_data();
			$existing_term = get_term( $existing_id, $taxonomy );

			if ( is_wp_error( $existing_term ) || ! $existing_term ) {
				return $result;
			}

			$response = array(
				'success'        => false,
				'already_exists' => true,
				'id'             => $existing_id,
				'name'           => $existing_term->name,
				'slug'           => $existing_term->slug,
				'message'        => sprintf(
					/* translators: %s: term name */
					__( '"%s" already exists — returning existing term.', 'lax-abilities-toolkit' ),
					$existing_term->name
				),
			);

			if ( ! empty( $config['hierarchical'] ) ) {
				$response['parent_id'] = (int) $existing_term->parent;
			}

			return $response;
		}

		return $result;
	}

	$term = get_term( $result['term_id'], $taxonomy );

	if ( is_wp_error( $term ) || ! $term ) {
		return new WP_Error( 'lax_abilities_term_fetch_failed', __( 'Term was created but could not be retrieved.', 'lax-abilities-toolkit' ) );
	}

	$response = array(
		'success'     => true,
		'id'          => (int) $result['term_id'],
		'name'        => $term->name,
		'slug'        => $term->slug,
		'description' => $term->description,
		'message'     => sprintf(
			/* translators: %s: term name */
			__( '"%s" created.', 'lax-abilities-toolkit' ),
			$term->name
		),
	);

	if ( ! empty( $config['hierarchical'] ) ) {
		$response['parent_id'] = (int) $term->parent;
	}

	return $response;
}

/**
 * Lists all terms for a taxonomy.
 *
 * @since 1.0.0
 *
 * @param  array  $params   Validated input parameters.
 * @param  string $taxonomy WP taxonomy slug.
 * @param  array  $config   Taxonomy configuration.
 * @return array|WP_Error   Response array or WP_Error.
 */
function lax_abilities_list_terms_handler( $params, $taxonomy, $config ) {
	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => isset( $params['hide_empty'] ) ? (bool) $params['hide_empty'] : false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		)
	);

	if ( is_wp_error( $terms ) ) {
		return $terms;
	}

	$items = array_map(
		function ( WP_Term $term ) use ( $config ) {
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
		},
		$terms
	);

	return array(
		'success' => true,
		'count'   => count( $items ),
		'items'   => $items,
	);
}

/**
 * Permanently deletes a taxonomy term.
 *
 * @since 1.1.0
 *
 * @param  array  $params   Validated input parameters.
 * @param  string $taxonomy WP taxonomy slug.
 * @param  array  $config   Taxonomy configuration.
 * @return array|WP_Error   Response array or WP_Error.
 */
function lax_abilities_delete_term_handler( $params, $taxonomy, $config ) {
	$term_id = absint( $params['id'] );
	$term    = get_term( $term_id, $taxonomy );

	if ( is_wp_error( $term ) || ! $term ) {
		return new WP_Error(
			'lax_abilities_not_found',
			sprintf( __( '%s not found.', 'lax-abilities-toolkit' ), $config['label'] )
		);
	}

	$name   = $term->name;
	$result = wp_delete_term( $term_id, $taxonomy );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	if ( ! $result ) {
		return new WP_Error(
			'lax_abilities_delete_failed',
			__( 'Could not delete the term.', 'lax-abilities-toolkit' )
		);
	}

	return array(
		'success' => true,
		'id'      => $term_id,
		'message' => sprintf(
			/* translators: %s: term name */
			__( '"%s" deleted.', 'lax-abilities-toolkit' ),
			$name
		),
	);
}
