<?php
/**
 * Content Type class.
 *
 * @package PRC\Platform\Staff_Bylines
 */

namespace PRC\Platform\Staff_Bylines;

use WP_Query;

/**
 * Content Type class.
 *
 * @package PRC\Platform\Staff_Bylines
 */
class Content_Type {

	/**
	 * The name of the post object.
	 *
	 * @var string
	 */
	public static $post_object_name = 'staff';

	/**
	 * The name of the taxonomy object.
	 *
	 * @var string
	 */
	public static $taxonomy_object_name = 'bylines';

	/**
	 * The staff post type arguments.
	 *
	 * @var array
	 */
	public static $staff_post_type_args = array(
		'labels'             => array(
			'name'               => 'Staff',
			'singular_name'      => 'Staff',
			'add_new'            => 'Add New',
			'add_new_item'       => 'Add New Staff',
			'edit_item'          => 'Edit Staff',
			'new_item'           => 'New Staff',
			'all_items'          => 'All Staff',
			'view_item'          => 'View Staff',
			'search_items'       => 'Search staff',
			'not_found'          => 'No staff found',
			'not_found_in_trash' => 'No staff found in Trash',
			'parent_item_colon'  => '',
			'menu_name'          => 'Staff',
			'featured_image'     => 'Staff Photo',
			'set_featured_image' => 'Set Staff Photo',
		),
		'public'             => true,
		'publicly_queryable' => true,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'show_in_nav_menus'  => false,
		'show_in_rest'       => true,
		'menu_icon'          => 'dashicons-groups',
		'query_var'          => true,
		'rewrite'            => false,
		'capability_type'    => 'post',
		'has_archive'        => false,
		'hierarchical'       => false,
		'menu_position'      => 70,
		'taxonomies'         => array( 'areas-of-expertise', 'bylines', 'staff-type', 'research-teams' ),
		'supports'           => array( 'title', 'editor', 'thumbnail', 'revisions', 'author', 'custom-fields', 'excerpt' ),
	);

	/**
	 * The staff type taxonomy arguments.
	 *
	 * @var array
	 */
	public static $staff_type_taxonomy_args = array(
		'hierarchical'      => true,
		'labels'            => array(
			'name'                       => 'Staff Type',
			'singular_name'              => 'Staff Type',
			'search_items'               => 'Search Staff Type',
			'popular_items'              => 'Popular Staff Type',
			'all_items'                  => 'All Staff Type',
			'parent_item'                => null,
			'parent_item_colon'          => null,
			'edit_item'                  => 'Edit Staff Type',
			'update_item'                => 'Update Staff Type',
			'add_new_item'               => 'Add New Staff Type',
			'new_item_name'              => 'New Staff Type Name',
			'separate_items_with_commas' => 'Separate staff type with commas',
			'add_or_remove_items'        => 'Add or remove staff type',
			'choose_from_most_used'      => 'Choose from the most used staff types',
		),
		'show_ui'           => true,
		'query_var'         => false,
		'show_admin_column' => true,
		'show_in_rest'      => true,
	);

	/**
	 * The expertise taxonomy arguments.
	 *
	 * @var array
	 */
	public static $expertise_taxonomy_args = array(
		'hierarchical'      => true,
		'labels'            => array(
			'name'                       => 'Areas of Expertise',
			'singular_name'              => 'Expertise',
			'search_items'               => 'Search Expertise',
			'popular_items'              => 'Popular Expertise',
			'all_items'                  => 'All Expertise',
			'parent_item'                => null,
			'parent_item_colon'          => null,
			'edit_item'                  => 'Edit Expertise',
			'update_item'                => 'Update Expertise',
			'add_new_item'               => 'Add New Expertise',
			'new_item_name'              => 'New Expertise Name',
			'separate_items_with_commas' => 'Separate expertise with commas',
			'add_or_remove_items'        => 'Add or remove expertise',
			'choose_from_most_used'      => 'Choose from the most used expertises',
		),
		'show_ui'           => true,
		'query_var'         => false,
		'show_in_rest'      => true,
		'show_admin_column' => true,
		'rewrite'           => array(
			'slug'         => 'expertise',
			'with_front'   => false,
			'hierarchical' => true,
		),
	);

	/**
	 * The byline taxonomy arguments.
	 *
	 * @var array
	 */
	public static $byline_taxonomy_args = array(
		'hierarchical'      => false,
		'labels'            => array(
			'name'                       => 'Bylines',
			'singular_name'              => 'Byline',
			'search_items'               => 'Search Bylines',
			'popular_items'              => 'Popular Bylines',
			'all_items'                  => 'All Bylines',
			'parent_item'                => null,
			'parent_item_colon'          => null,
			'edit_item'                  => 'Edit Byline',
			'update_item'                => 'Update Byline',
			'add_new_item'               => 'Add New Byline',
			'new_item_name'              => 'New Byline Name',
			'separate_items_with_commas' => 'Separate bylines with commas',
			'add_or_remove_items'        => 'Add or remove bylines',
			'choose_from_most_used'      => 'Choose from the most used bylines',
		),
		'show_in_rest'      => true,
		'show_ui'           => true,
		'query_var'         => true,
		'rewrite'           => array(
			'slug'         => 'staff',
			'with_front'   => false,
			'hierarchical' => false,
		),
		'show_admin_column' => true,
	);

	/**
	 * The schema for the field.
	 *
	 * @var array
	 */
	public static $field_schema = array(
		'items' => array(
			'type'       => 'object',
			'properties' => array(
				'key'    => array(
					'type' => 'string',
				),
				'termId' => array(
					'type' => 'integer',
				),
			),
		),
	);

	/**
	 * Constructor.
	 *
	 * @param object $loader The loader object.
	 */
	public function __construct( $loader ) {
		$loader->add_action( 'init', $this, 'init' );
		$loader->add_filter( 'tds_balancing_from_term', $this, 'override_term_data_store_for_guests', 10, 4 );
		$loader->add_filter( 'posts_orderby', $this, 'orderby_last_name', PHP_INT_MAX, 2 );
		$loader->add_filter( 'rest_staff_collection_params', $this, 'filter_add_rest_orderby_params', 10, 1 );
		$loader->add_action( 'pre_get_posts', $this, 'hide_former_staff', 10, 1 );
		$loader->add_filter( 'the_title', $this, 'indicate_former_staff', 10, 1 );
		$loader->add_filter( 'post_link', $this, 'modify_staff_permalink', 10, 2 );
		$loader->add_filter( 'prc_sitemap_supported_taxonomies', $this, 'opt_into_sitemap', 10, 1 );
	}

	/**
	 * Get the enabled post types.
	 *
	 * @return array The enabled post types.
	 */
	public static function get_enabled_post_types() {
		return apply_filters( 'prc_platform__bylines_enabled_post_types', array( 'post' ) );
	}

	/**
	 * Initialize the class with the hybrid post type, associated taxonomies, and meta fields.
	 *
	 * @hook init
	 */
	public function init() {
		$enabled_post_types = self::get_enabled_post_types();

		register_post_type( self::$post_object_name, self::$staff_post_type_args );

		register_taxonomy( self::$taxonomy_object_name, $enabled_post_types, self::$byline_taxonomy_args );

		register_taxonomy( 'areas-of-expertise', self::$post_object_name, self::$expertise_taxonomy_args );

		register_taxonomy( 'staff-type', self::$post_object_name, self::$staff_type_taxonomy_args );

		// Link the post object and taxonomy object into one entity.
		\TDS\add_relationship( self::$post_object_name, self::$taxonomy_object_name );

		$this->register_meta_fields( $enabled_post_types );
	}

	/**
	 * Opt into sitemap.
	 *
	 * @hook prc_sitemap_supported_taxonomies
	 *
	 * @param array $taxonomy_types The taxonomy types.
	 * @return array The taxonomy types.
	 */
	public function opt_into_sitemap( $taxonomy_types ) {
		$taxonomy_types[] = self::$taxonomy_object_name;
		return $taxonomy_types;
	}

	/**
	 * Register the meta fields.
	 *
	 * @param array $enabled_post_types The enabled post types.
	 */
	public function register_meta_fields( $enabled_post_types ) {
		register_post_meta(
			self::$post_object_name,
			'jobTitle',
			array(
				'description'   => 'This staff member\'s job title.',
				'show_in_rest'  => true,
				'single'        => true,
				'type'          => 'string',
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		register_post_meta(
			self::$post_object_name,
			'jobTitleExtended',
			array(
				'description'   => 'This staff member\'s extended job title, "mini biography"; e.g. ... "is a Senior Researcher focusing on Internet and Technology at the Pew Research Center."',
				'show_in_rest'  => true,
				'single'        => true,
				'type'          => 'string',
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		register_post_meta(
			self::$post_object_name,
			'bylineLinkEnabled',
			array(
				'description'   => 'Allow this staff member to have a byline link?',
				'show_in_rest'  => true,
				'single'        => true,
				'type'          => 'boolean',
				'default'       => false,
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		/**
		 * MAELSTROM
		 * This is a misnomer!!! As this field is publicly accessible we want to obfuscate what this is for. This is the "safety net" for staff members, this will allow us to hide staff members from certain posts based on that post's region and country taxonomy terms. This is a safety net for staff members and their families back home to ensure they are not targeted by bad actors by working on posts that are sensitive to their home country.
		 */
		register_post_meta(
			self::$post_object_name,
			'_maelstrom',
			array(
				'description'   => '',
				'show_in_rest'  => array(
					'schema' => array(
						'properties' => array(
							'enabled'    => array(
								'type'    => 'boolean',
								'default' => false,
							),
							'restricted' => array(
								'type'  => 'array',
								'items' => array(
									'type'    => 'string',
									'default' => array(),
								),
							),
						),
					),
				),
				'single'        => true,
				'type'          => 'object',
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		register_post_meta(
			self::$post_object_name,
			'socialProfiles',
			array(
				'description'   => 'Social profiles for this staff member.',
				'show_in_rest'  => array(
					'schema' => array(
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'key' => array(
									'type' => 'string',
								),
								'url' => array(
									'type' => 'string',
								),
							),
						),
					),
				),
				'single'        => true,
				'type'          => 'array',
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		// Register bylines, acknowledgements, and displayBylines toggle meta for posts.
		foreach ( $enabled_post_types as $post_type ) {
			register_post_meta(
				$post_type,
				'bylines',
				array(
					'single'        => true,
					'type'          => 'array',
					'show_in_rest'  => array(
						'schema' => self::$field_schema,
					),
					'auth_callback' => function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);

			register_post_meta(
				$post_type,
				'acknowledgements',
				array(
					'single'        => true,
					'type'          => 'array',
					'show_in_rest'  => array(
						'schema' => self::$field_schema,
					),
					'auth_callback' => function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);

			/**
			 * This handles whether ALL bylines should display on a given post.
			 */
			register_post_meta(
				$post_type,
				'displayBylines',
				array(
					'show_in_rest'  => true,
					'single'        => true,
					'type'          => 'boolean',
					'default'       => true,
					'auth_callback' => function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}

	/**
	 * Override the term data store for guests, don't try to update it or manage it in the term data store.
	 *
	 * @hook tds_balancing_from_term
	 *
	 * @param boolean $allow Whether to allow the term data store.
	 * @param string  $taxonomy The taxonomy.
	 * @param string  $post_type The post type.
	 * @param integer $term_id The term ID.
	 * @return boolean
	 */
	public function override_term_data_store_for_guests( $allow, $taxonomy, $post_type, $term_id ) {
		if ( self::$taxonomy_object_name === $taxonomy ) {
			$term_meta = get_term_meta( $term_id, 'is_guest_author', true );
			if ( $term_meta ) {
				return true;
			}
		}
		return $allow;
	}

	/**
	 * Order staff posts by last name
	 *
	 * @hook posts_orderby
	 *
	 * @param mixed    $orderby The orderby.
	 * @param WP_Query $query The query.
	 * @return mixed The orderby.
	 */
	public function orderby_last_name( $orderby, WP_Query $query ) {
		$order = $query->get( 'order' );
		global $wpdb;
		if ( 'last_name' === $query->get( 'orderby' ) && $order ) {
			if ( in_array( strtoupper( $order ), array( 'ASC', 'DESC' ) ) ) {
				// Order by last name.
				$orderby = "RIGHT($wpdb->posts.post_title, LOCATE(' ', REVERSE($wpdb->posts.post_title)) - 1) " . 'ASC';
			}
			// If Michael Dimock is present, make sure he is always first.
			$orderby = "CASE WHEN $wpdb->posts.post_title = 'Michael Dimock' THEN 1 ELSE 2 END, $orderby";
		}
		return $orderby;
	}

	/**
	 * Add last_name to the list of permitted orderby values
	 *
	 * @hook rest_staff_collection_params
	 *
	 * @param array $params The parameters.
	 * @return array The parameters.
	 */
	public function filter_add_rest_orderby_params( $params ) {
		$params['orderby']['enum'][] = 'last_name';
		return $params;
	}

	/**
	 * Hide former staff from the staff archive and staff taxonomy archive
	 *
	 * @hook pre_get_posts
	 *
	 * @param mixed $query The query.
	 */
	public function hide_former_staff( $query ) {
		if ( true === $query->get( 'isPubListingQuery' ) ) {
			return $query;
		}
		if ( $query->is_main_query() && ( is_tax( 'areas-of-expertise' ) || is_tax( 'bylines' ) ) ) {
			$tax_query = $query->get( 'tax_query' );
			if ( ! is_array( $tax_query ) ) {
				$tax_query = array();
			}
			$tax_query[] = array(
				'taxonomy' => 'staff-type',
				'field'    => 'slug',
				'terms'    => array( 'staff', 'executive-team', 'managing-directors' ),
			);
			$query->set( 'tax_query', $tax_query );
		}
	}

	/**
	 * Modifies the staff title to indicate former staff.
	 *
	 * @hook the_title
	 *
	 * @param mixed $title The title.
	 * @return mixed The title.
	 */
	public function indicate_former_staff( $title ) {
		if ( ! is_admin() ) {
			return $title;
		}

		global $post;
		if ( get_post_type( $post ) !== self::$post_object_name ) {
			return $title;
		}

		$staff = new Staff( $post->ID );
		if ( true !== $staff->is_currently_employed ) {
			$title = 'FORMER: ' . $title;
		}
		return $title;
	}

	/**
	 * Modifies the staff permalink to point to the bylines term archive permalink.
	 *
	 * @hook post_link
	 *
	 * @param string  $url The URL.
	 * @param WP_Post $post The post.
	 * @return string The URL.
	 */
	public function modify_staff_permalink( $url, $post ) {
		if ( 'publish' !== $post->post_status ) {
			return $url;
		}
		if ( self::$post_object_name === $post->post_type ) {
			$staff       = new Staff( $post->ID );
			$matched_url = $staff->link;
			if ( ! is_wp_error( $matched_url ) ) {
				return $matched_url;
			}
		}
		return $url;
	}
}
