<?php
/**
 * Dynamic post-type abilities: create, update, list, get.
 *
 * Register additional post types via the lax_abilities_registered_post_types filter:
 *
 *     add_filter( 'lax_abilities_registered_post_types', function( $types ) {
 *         $types['product'] = array(
 *             'label'           => 'Product',
 *             'plural'          => 'Products',
 *             'capability_type' => 'product',
 *             'taxonomies'      => array( 'product_cat', 'product_tag' ),
 *             'supports_schedule' => false,
 *         );
 *         return $types;
 *     } );
 *
 * Extend the create/update schema with extra fields:
 *
 *     add_filter( 'lax_abilities_input_schema_product', function( $schema, $post_type ) {
 *         $schema['properties']['price'] = array(
 *             'type'        => 'string',
 *             'description' => 'Product price.',
 *         );
 *         return $schema;
 *     }, 10, 2 );
 *
 * Persist extra fields before insert/update:
 *
 *     add_filter( 'lax_abilities_post_data_product', function( $post_data, $params, $post_type ) {
 *         if ( isset( $params['price'] ) ) {
 *             $post_data['meta_input']['_price'] = sanitize_text_field( $params['price'] );
 *         }
 *         return $post_data;
 *     }, 10, 3 );
 *
 * Extend the response:
 *
 *     add_filter( 'lax_abilities_post_response_product', function( $response, $post ) {
 *         $response['price'] = get_post_meta( $post->ID, '_price', true );
 *         return $response;
 *     }, 10, 2 );
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
 * Returns the array of post types to register abilities for.
 *
 * @return array<string, array>
 */
function lax_abilities_get_post_types() {
	$defaults = array(
		'post' => array(
			'label'             => __( 'Post', 'lax-abilities-toolkit' ),
			'plural'            => __( 'Posts', 'lax-abilities-toolkit' ),
			'capability_type'   => 'post',
			'taxonomies'        => array( 'category', 'post_tag' ),
			'supports_parent'   => false,
			'supports_template' => false,
			'supports_schedule' => true,
		),
		'page' => array(
			'label'             => __( 'Page', 'lax-abilities-toolkit' ),
			'plural'            => __( 'Pages', 'lax-abilities-toolkit' ),
			'capability_type'   => 'page',
			'taxonomies'        => array(),
			'supports_parent'   => true,
			'supports_template' => true,
			'supports_schedule' => false,
		),
	);

	/**
	 * Filters the post types that get abilities registered.
	 *
	 * Each entry is keyed by the WP post type slug. Supported config keys:
	 *
	 *   label             string   Singular human-readable label.
	 *   plural            string   Plural human-readable label.
	 *   capability_type   string   Used to derive publish/edit caps (e.g. 'post', 'page', 'product').
	 *   taxonomies        string[] WP taxonomy slugs this post type uses.
	 *   supports_parent   bool     Include parent_id field.
	 *   supports_template bool     Include page template field.
	 *   supports_schedule bool     Include publish_date scheduling field.
	 *
	 * @param array $post_types Registered post type configurations.
	 */
	return apply_filters( 'lax_abilities_registered_post_types', $defaults );
}

/**
 * Register create / update / list / get abilities for all configured post types.
 */
function lax_abilities_register_post_type_abilities_all() {
	$defaults = array(
		'label'             => '',
		'plural'            => '',
		'capability_type'   => 'post',
		'taxonomies'        => array(),
		'supports_parent'   => false,
		'supports_template' => false,
		'supports_schedule' => true,
	);

	foreach ( lax_abilities_get_post_types() as $post_type => $config ) {
		$config = wp_parse_args( $config, $defaults );

		// Derive labels from post type slug if not provided.
		if ( empty( $config['label'] ) ) {
			$config['label'] = ucwords( str_replace( array( '-', '_' ), ' ', $post_type ) );
		}
		if ( empty( $config['plural'] ) ) {
			$config['plural'] = $config['label'] . 's';
		}

		lax_abilities_register_post_type_abilities( $post_type, $config );
	}
}

// =============================================================================
// Ability registration
// =============================================================================

/**
 * Register create / update / list / get abilities for a single post type.
 *
 * @param string $post_type WP post type slug.
 * @param array  $config    Merged post type configuration.
 */
function lax_abilities_register_post_type_abilities( $post_type, $config ) {
	$slug     = lax_abilities_to_slug( $post_type );
	$label    = $config['label'];
	$plural   = $config['plural'];
	$cap_type = $config['capability_type'];
	$cat      = LAX_ABILITIES_CATEGORY;

	// Build schemas (developers can extend via filters).
	$create_schema = lax_abilities_build_create_schema( $config, $post_type );
	$update_schema = lax_abilities_build_update_schema( $config, $post_type );

	// ── create ───────────────────────────────────────────────────────────────
	wp_register_ability( "lax-abilities/create-{$slug}", array(
		'label'               => sprintf( __( 'Create %s', 'lax-abilities-toolkit' ), $label ),
		'description'         => sprintf(
			__( 'Create a new %s. Supports scheduling via publish_date.', 'lax-abilities-toolkit' ),
			strtolower( $label )
		),
		'category'            => $cat,
		'input_schema'        => $create_schema,
		'execute_callback'    => function ( $params ) use ( $post_type, $config ) {
			return lax_abilities_create_post_handler( $params, $post_type, $config );
		},
		'permission_callback' => function () use ( $cap_type ) {
			return current_user_can( 'publish_' . $cap_type . 's' );
		},
		'meta' => array(
			'show_in_rest' => true,
			'mcp'          => array( 'public' => true ),
			'annotations'  => array( 'readonly' => false, 'destructive' => false ),
		),
	) );

	// ── update ───────────────────────────────────────────────────────────────
	wp_register_ability( "lax-abilities/update-{$slug}", array(
		'label'               => sprintf( __( 'Update %s', 'lax-abilities-toolkit' ), $label ),
		'description'         => sprintf(
			__( 'Update an existing %s by ID. Supports rescheduling via publish_date.', 'lax-abilities-toolkit' ),
			strtolower( $label )
		),
		'category'            => $cat,
		'input_schema'        => $update_schema,
		'execute_callback'    => function ( $params ) use ( $post_type, $config ) {
			return lax_abilities_update_post_handler( $params, $post_type, $config );
		},
		'permission_callback' => function ( $params ) use ( $cap_type ) {
			if ( empty( $params['id'] ) ) {
				return false;
			}
			return current_user_can( 'edit_post', absint( $params['id'] ) );
		},
		'meta' => array(
			'show_in_rest' => true,
			'mcp'          => array( 'public' => true ),
		),
	) );

	// ── list ─────────────────────────────────────────────────────────────────
	wp_register_ability( "lax-abilities/list-{$slug}s", array(
		'label'               => sprintf( __( 'List %s', 'lax-abilities-toolkit' ), $plural ),
		'description'         => sprintf(
			__( 'Get a paginated list of %s with status, taxonomy terms, and scheduled dates.', 'lax-abilities-toolkit' ),
			strtolower( $plural )
		),
		'category'            => $cat,
		'input_schema'        => array(
			'type'       => 'object',
			'properties' => array(
				'limit'  => array(
					'type'        => 'integer',
					'default'     => 10,
					'description' => __( 'Number to return (max 50).', 'lax-abilities-toolkit' ),
				),
				'status' => array(
					'type'        => 'string',
					'enum'        => array( 'publish', 'draft', 'future', 'pending', 'private', 'any' ),
					'default'     => 'publish',
					'description' => __( 'Filter by post status.', 'lax-abilities-toolkit' ),
				),
			),
		),
		'execute_callback'    => function ( $params ) use ( $post_type, $config ) {
			return lax_abilities_list_posts_handler( $params, $post_type, $config );
		},
		'permission_callback' => function () {
			return current_user_can( 'read' );
		},
		'meta' => array(
			'show_in_rest' => true,
			'mcp'          => array( 'public' => true ),
			'annotations'  => array( 'readonly' => true, 'destructive' => false ),
		),
	) );

	// ── get (single) ─────────────────────────────────────────────────────────
	wp_register_ability( "lax-abilities/get-{$slug}", array(
		'label'               => sprintf( __( 'Get %s', 'lax-abilities-toolkit' ), $label ),
		'description'         => sprintf(
			__( 'Get full details of a single %s by ID, including content, terms, and metadata.', 'lax-abilities-toolkit' ),
			strtolower( $label )
		),
		'category'            => $cat,
		'input_schema'        => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array(
					'type'        => 'integer',
					/* translators: %s: post type label */
					'description' => sprintf( __( '%s ID.', 'lax-abilities-toolkit' ), $label ),
				),
			),
			'required' => array( 'id' ),
		),
		'execute_callback'    => function ( $params ) use ( $post_type, $config ) {
			return lax_abilities_get_post_handler( $params, $post_type, $config );
		},
		'permission_callback' => function () {
			return current_user_can( 'read' );
		},
		'meta' => array(
			'show_in_rest' => true,
			'mcp'          => array( 'public' => true ),
			'annotations'  => array( 'readonly' => true, 'destructive' => false ),
		),
	) );
}

// =============================================================================
// Schema builders
// =============================================================================

/**
 * Build the input schema for the create ability.
 *
 * @param  array  $config
 * @param  string $post_type
 * @return array  JSON Schema object.
 */
function lax_abilities_build_create_schema( $config, $post_type ) {
	$props = array(
		'title'   => array( 'type' => 'string', 'description' => __( 'Title.', 'lax-abilities-toolkit' ) ),
		'content' => array( 'type' => 'string', 'description' => __( 'Content (HTML allowed).', 'lax-abilities-toolkit' ), 'default' => '' ),
		'status'  => array(
			'type'        => 'string',
			'enum'        => array( 'draft', 'publish', 'future', 'pending', 'private' ),
			'default'     => 'draft',
			'description' => __( 'Post status. Use "future" with publish_date to schedule.', 'lax-abilities-toolkit' ),
		),
		'excerpt'   => array( 'type' => 'string', 'description' => __( 'Excerpt.', 'lax-abilities-toolkit' ), 'default' => '' ),
		'author_id' => array( 'type' => 'integer', 'description' => __( 'Author user ID (defaults to current user).', 'lax-abilities-toolkit' ) ),
	);

	if ( ! empty( $config['supports_schedule'] ) ) {
		$props['publish_date'] = array(
			'type'        => 'string',
			'description' => __( 'Schedule date/time in ISO 8601 or MySQL format (e.g. "2025-06-01 09:00:00"). Automatically sets status to "future" when the date is in the future.', 'lax-abilities-toolkit' ),
		);
	}

	if ( ! empty( $config['supports_parent'] ) ) {
		$props['parent_id'] = array(
			'type'        => 'integer',
			'description' => __( 'Parent ID (0 for top-level).', 'lax-abilities-toolkit' ),
			'default'     => 0,
		);
	}

	if ( ! empty( $config['supports_template'] ) ) {
		$props['template'] = array(
			'type'        => 'string',
			'description' => __( 'Page template filename (e.g. "page-full-width.php").', 'lax-abilities-toolkit' ),
			'default'     => '',
		);
	}

	foreach ( $config['taxonomies'] as $taxonomy ) {
		$tax_obj   = get_taxonomy( $taxonomy );
		$tax_label = $tax_obj ? strtolower( $tax_obj->labels->name ) : $taxonomy;
		$props[ $taxonomy . '_ids' ] = array(
			'type'        => 'array',
			'items'       => array( 'type' => 'integer' ),
			/* translators: %s: taxonomy name */
			'description' => sprintf( __( 'Array of %s term IDs.', 'lax-abilities-toolkit' ), $tax_label ),
			'default'     => array(),
		);
	}

	$schema = array(
		'type'       => 'object',
		'properties' => $props,
		'required'   => array( 'title' ),
	);

	/**
	 * Filters the input schema for a specific post type's create ability.
	 *
	 * Use this to add extra fields (e.g. custom meta, ACF fields).
	 *
	 * @param array  $schema    JSON Schema object.
	 * @param string $post_type The post type slug.
	 */
	return apply_filters( "lax_abilities_input_schema_{$post_type}", $schema, $post_type );
}

/**
 * Build the input schema for the update ability.
 * Same fields as create, all optional, plus required `id`.
 *
 * @param  array  $config
 * @param  string $post_type
 * @return array  JSON Schema object.
 */
function lax_abilities_build_update_schema( $config, $post_type ) {
	$schema = lax_abilities_build_create_schema( $config, $post_type );

	// Prepend `id`, remove create's required constraint, require only `id`.
	$schema['properties'] = array_merge(
		array( 'id' => array( 'type' => 'integer', 'description' => __( 'ID of the item to update.', 'lax-abilities-toolkit' ) ) ),
		$schema['properties']
	);
	$schema['required'] = array( 'id' );

	return $schema;
}

// =============================================================================
// Handlers
// =============================================================================

/**
 * Execute handler: create a post.
 *
 * @param  array  $params
 * @param  string $post_type
 * @param  array  $config
 * @return array|WP_Error
 */
function lax_abilities_create_post_handler( $params, $post_type, $config ) {
	$post_data = array(
		'post_type'    => $post_type,
		'post_title'   => sanitize_text_field( $params['title'] ),
		'post_content' => wp_kses_post( isset( $params['content'] ) ? $params['content'] : '' ),
		'post_status'  => isset( $params['status'] ) ? sanitize_text_field( $params['status'] ) : 'draft',
		'post_author'  => isset( $params['author_id'] ) ? absint( $params['author_id'] ) : get_current_user_id(),
		'post_excerpt' => isset( $params['excerpt'] ) ? sanitize_text_field( $params['excerpt'] ) : '',
		'post_parent'  => isset( $params['parent_id'] ) ? absint( $params['parent_id'] ) : 0,
	);

	if ( ! empty( $config['supports_schedule'] ) && ! empty( $params['publish_date'] ) ) {
		$date = lax_abilities_parse_date( $params['publish_date'] );
		if ( null === $date ) {
			return new WP_Error(
				'invalid_date',
				__( 'publish_date could not be parsed. Use ISO 8601 or MySQL format.', 'lax-abilities-toolkit' )
			);
		}
		$post_data['post_date']     = $date;
		$post_data['post_date_gmt'] = get_gmt_from_date( $date );
		// Auto-promote to future when a future date is given without an explicit status.
		if ( ( ! isset( $params['status'] ) || 'publish' === $params['status'] ) && strtotime( $date ) > time() ) {
			$post_data['post_status'] = 'future';
		}
	}

	/**
	 * Filters the post data array before insertion.
	 *
	 * Useful for setting post meta, overriding fields, or integrating CPT plugins.
	 *
	 * @param array  $post_data  Data passed to wp_insert_post().
	 * @param array  $params     Raw input parameters.
	 * @param string $post_type  The post type slug.
	 */
	$post_data = apply_filters( "lax_abilities_post_data_{$post_type}", $post_data, $params, $post_type );

	$post_id = wp_insert_post( $post_data, true );
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	lax_abilities_sync_taxonomies( $post_id, $params, $config['taxonomies'] );
	lax_abilities_sync_template( $post_id, $params, $config );

	return array_merge(
		array( 'success' => true ),
		lax_abilities_build_post_response( get_post( $post_id ), $post_type )
	);
}

/**
 * Execute handler: update a post.
 *
 * @param  array  $params
 * @param  string $post_type
 * @param  array  $config
 * @return array|WP_Error
 */
function lax_abilities_update_post_handler( $params, $post_type, $config ) {
	$post_id = absint( $params['id'] );
	$post    = get_post( $post_id );

	if ( ! $post || $post->post_type !== $post_type ) {
		return new WP_Error(
			'not_found',
			/* translators: %s: post type label */
			sprintf( __( '%s not found.', 'lax-abilities-toolkit' ), $config['label'] )
		);
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return new WP_Error( 'insufficient_permissions', __( 'You do not have permission to edit this item.', 'lax-abilities-toolkit' ) );
	}

	$post_data = array( 'ID' => $post_id );

	if ( isset( $params['title'] ) )     $post_data['post_title']   = sanitize_text_field( $params['title'] );
	if ( isset( $params['content'] ) )   $post_data['post_content'] = wp_kses_post( $params['content'] );
	if ( isset( $params['status'] ) )    $post_data['post_status']  = sanitize_text_field( $params['status'] );
	if ( isset( $params['excerpt'] ) )   $post_data['post_excerpt'] = sanitize_text_field( $params['excerpt'] );
	if ( isset( $params['parent_id'] ) ) $post_data['post_parent']  = absint( $params['parent_id'] );

	if ( ! empty( $config['supports_schedule'] ) && ! empty( $params['publish_date'] ) ) {
		$date = lax_abilities_parse_date( $params['publish_date'] );
		if ( null === $date ) {
			return new WP_Error( 'invalid_date', __( 'publish_date could not be parsed.', 'lax-abilities-toolkit' ) );
		}
		$post_data['post_date']     = $date;
		$post_data['post_date_gmt'] = get_gmt_from_date( $date );
		if ( ! isset( $params['status'] ) && strtotime( $date ) > time() ) {
			$post_data['post_status'] = 'future';
		}
	}

	/** This filter is documented in includes/post-types.php */
	$post_data = apply_filters( "lax_abilities_post_data_{$post_type}", $post_data, $params, $post_type );

	$result = wp_update_post( $post_data, true );
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	lax_abilities_sync_taxonomies( $post_id, $params, $config['taxonomies'] );
	lax_abilities_sync_template( $post_id, $params, $config );

	return array_merge(
		array( 'success' => true ),
		lax_abilities_build_post_response( get_post( $post_id ), $post_type )
	);
}

/**
 * Execute handler: list posts.
 *
 * @param  array  $params
 * @param  string $post_type
 * @param  array  $config
 * @return array|WP_Error
 */
function lax_abilities_list_posts_handler( $params, $post_type, $config ) {
	$limit  = isset( $params['limit'] ) ? min( absint( $params['limit'] ), 50 ) : 10;
	$status = isset( $params['status'] ) ? sanitize_text_field( $params['status'] ) : 'publish';

	$posts = get_posts( array(
		'post_type'      => $post_type,
		'posts_per_page' => $limit,
		'post_status'    => $status,
		'orderby'        => 'date',
		'order'          => 'DESC',
	) );

	$items = array_map( function ( WP_Post $post ) use ( $post_type, $config ) {
		$item = array(
			'id'       => $post->ID,
			'title'    => $post->post_title,
			'slug'     => $post->post_name,
			'status'   => $post->post_status,
			'date'     => $post->post_date,
			'url'      => get_permalink( $post->ID ),
			'edit_url' => get_edit_post_link( $post->ID, 'raw' ),
			'author'   => get_the_author_meta( 'display_name', $post->post_author ),
			'excerpt'  => wp_trim_words( $post->post_content, 30 ),
		);

		if ( 'future' === $post->post_status ) {
			$item['scheduled_for']     = $post->post_date;
			$item['scheduled_for_gmt'] = $post->post_date_gmt;
		}

		foreach ( $config['taxonomies'] as $taxonomy ) {
			$item[ $taxonomy ] = wp_get_object_terms( $post->ID, $taxonomy, array( 'fields' => 'names' ) );
		}

		/**
		 * Filters a single list item for a specific post type.
		 *
		 * @param array   $item      The item array.
		 * @param WP_Post $post      The post object.
		 * @param string  $post_type The post type slug.
		 */
		return apply_filters( "lax_abilities_list_item_{$post_type}", $item, $post, $post_type );
	}, $posts );

	return array( 'success' => true, 'count' => count( $items ), 'items' => $items );
}

/**
 * Execute handler: get a single post.
 *
 * @param  array  $params
 * @param  string $post_type
 * @param  array  $config
 * @return array|WP_Error
 */
function lax_abilities_get_post_handler( $params, $post_type, $config ) {
	$post_id = absint( $params['id'] );
	$post    = get_post( $post_id );

	if ( ! $post || $post->post_type !== $post_type ) {
		return new WP_Error(
			'not_found',
			sprintf( __( '%s not found.', 'lax-abilities-toolkit' ), $config['label'] )
		);
	}

	$item = array(
		'id'        => $post->ID,
		'title'     => $post->post_title,
		'content'   => $post->post_content,
		'excerpt'   => $post->post_excerpt,
		'slug'      => $post->post_name,
		'status'    => $post->post_status,
		'date'      => $post->post_date,
		'date_gmt'  => $post->post_date_gmt,
		'modified'  => $post->post_modified,
		'author_id' => (int) $post->post_author,
		'author'    => get_the_author_meta( 'display_name', $post->post_author ),
		'parent_id' => (int) $post->post_parent,
		'url'       => get_permalink( $post->ID ),
		'edit_url'  => get_edit_post_link( $post->ID, 'raw' ),
	);

	if ( 'future' === $post->post_status ) {
		$item['scheduled_for']     = $post->post_date;
		$item['scheduled_for_gmt'] = $post->post_date_gmt;
	}

	foreach ( $config['taxonomies'] as $taxonomy ) {
		$terms           = wp_get_object_terms( $post->ID, $taxonomy );
		$item[ $taxonomy ] = array_map( function ( WP_Term $t ) {
			return array( 'id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug );
		}, is_wp_error( $terms ) ? array() : $terms );
	}

	if ( ! empty( $config['supports_template'] ) ) {
		$item['template'] = get_page_template_slug( $post->ID );
	}

	/**
	 * Filters the full detail response for a specific post type.
	 *
	 * @param array   $item      The detail array.
	 * @param WP_Post $post      The post object.
	 * @param string  $post_type The post type slug.
	 */
	return apply_filters( "lax_abilities_post_detail_{$post_type}", $item, $post, $post_type );
}

// =============================================================================
// Internal utilities
// =============================================================================

/**
 * Sync taxonomy terms for a post from the input params.
 *
 * @param int      $post_id
 * @param array    $params
 * @param string[] $taxonomies
 */
function lax_abilities_sync_taxonomies( $post_id, $params, $taxonomies ) {
	foreach ( $taxonomies as $taxonomy ) {
		$key = $taxonomy . '_ids';
		if ( isset( $params[ $key ] ) && is_array( $params[ $key ] ) ) {
			wp_set_object_terms( $post_id, array_map( 'absint', $params[ $key ] ), $taxonomy );
		}
	}
}

/**
 * Save page template meta if the post type supports it and a template was provided.
 *
 * @param int   $post_id
 * @param array $params
 * @param array $config
 */
function lax_abilities_sync_template( $post_id, $params, $config ) {
	if ( ! empty( $config['supports_template'] ) && isset( $params['template'] ) ) {
		update_post_meta( $post_id, '_wp_page_template', sanitize_text_field( $params['template'] ) );
	}
}
