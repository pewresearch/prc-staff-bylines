<?php
/**
 * Staff Query Block
 *
 * @package PRC\Platform\Staff_Bylines
 */

namespace PRC\Platform\Staff_Bylines;

use WP_Query;
use WP_Block;
use WP_Error;

/**
 * Block Name:        Staff Query
 * Description:       Query the Staff by Staff Type and Research Area.
 * Requires at least: 6.4
 * Requires PHP:      8.l1
 * Author:            Seth Rubenstein
 *
 * @package           prc-staff-bylines
 */
class Staff_Query {
	/**
	 * Constructor
	 *
	 * @param mixed $loader Loader.
	 */
	public function __construct( $loader ) {
		$this->init( $loader );
	}

	/**
	 * Initialize the block
	 *
	 * @hook init
	 *
	 * @param mixed $loader Loader.
	 */
	public function init( $loader = null ) {
		if ( null !== $loader ) {
			$loader->add_action( 'init', $this, 'block_init' );
		}
	}

	/**
	 * Get expertise
	 *
	 * @param mixed $post_id Post ID.
	 * @return array
	 */
	public function get_expertise( $post_id ) {
		$terms     = get_the_terms( $post_id, 'areas-of-expertise' );
		$expertise = array();
		if ( $terms ) {
			foreach ( $terms as $term ) {
				// if $term is wp error and or is not a term object then skip it.
				if ( is_wp_error( $term ) || ! is_object( $term ) ) {
					continue;
				}
				$link        = get_term_link( $term, 'areas-of-expertise' );
				$expertise[] = array(
					'url'   => $link,
					'label' => $term->name,
					'slug'  => $term->slug,
				);
			}
		}
		return $expertise;
	}

	/**
	 * Query staff posts
	 *
	 * @param array $attributes Attributes.
	 * @return array
	 */
	public function query_staff_posts( $attributes = array() ) {
		$staff_type    = array_key_exists( 'staffType', $attributes ) ? $attributes['staffType'] : false;
		$research_area = array_key_exists( 'researchArea', $attributes ) ? $attributes['researchArea'] : false;
		$tax_query     = array();
		if ( $staff_type ) {
			$staff_type  = $staff_type['slug'];
			$tax_query[] = array(
				'taxonomy' => 'staff-type',
				'field'    => 'slug',
				'terms'    => $staff_type,
			);
		}
		if ( $research_area ) {
			$research_area = $research_area['slug'];
			$tax_query[]   = array(
				'taxonomy' => 'research-teams',
				'field'    => 'slug',
				'terms'    => $research_area,
			);
		}
		if ( count( $tax_query ) > 1 ) {
			$tax_query['relation'] = 'AND';
		}

		$query_args = array(
			'post_type'      => 'staff',
			'posts_per_page' => 200,
			'orderby'        => 'last_name',
			'order'          => 'ASC',
		);
		if ( count( $tax_query ) > 0 ) {
			$query_args['tax_query'] = $tax_query;
		}

		$staff_posts = array();

		switch_to_blog( 20 );

		$staff_query = new WP_Query( $query_args );

		if ( $staff_query->have_posts() ) {
			while ( $staff_query->have_posts() ) {
				$staff_query->the_post();
				$staff = new Staff( get_the_ID(), false );
				if ( is_wp_error( $staff ) ) {
					continue;
				}
				if ( ! $staff->is_currently_employed ) {
					continue;
				}

				$staff_posts[] = array(
					'staffId' => $staff->ID,
				);
			}
		}

		wp_reset_postdata();

		restore_current_blog();

		return $staff_posts;
	}
	/**
	 * Render block callback
	 *
	 * @param array    $attributes Attributes.
	 * @param string   $content Content.
	 * @param WP_Block $block Block.
	 * @return string
	 */
	public function render_block_callback( $attributes, $content, $block ) {
		$staff_posts = $this->query_staff_posts( $attributes );

		$block_content = '';

		if ( empty( $staff_posts ) ) {
			$block_content = '<p>No staff found.</p>';
		}

		$block_attrs = get_block_wrapper_attributes();

		$block_instance = $block->parsed_block;

		// Set the block name to one that does not correspond to an existing registered block.
		// This ensures that for the inner instances of the Staff Query block, we do not render any block supports.
		$block_instance['blockName'] = 'core/null';

		foreach ( $staff_posts as $staff_post_context ) {
			// Render the inner blocks of the Staff Query block with `dynamic` set to `false` to prevent calling
			// `render_callback` and ensure that no wrapper markup is included.
			$block_content .= (
				new WP_Block(
					$block_instance,
					$staff_post_context
				)
			)->render( array( 'dynamic' => false ) );
		}

		return wp_sprintf(
			'<div %1$s>%2$s</div>',
			$block_attrs, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$block_content // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}

	/**
	 * Registers the block using the metadata loaded from the `block.json` file.
	 * Behind the scenes, it registers also all assets so they can be enqueued
	 * through the block editor in the corresponding context.
	 *
	 * @hook init
	 *
	 * @see https://developer.wordpress.org/reference/functions/register_block_type/
	 */
	public function block_init() {
		register_block_type_from_metadata(
			PRC_STAFF_BYLINES_BLOCKS_DIR . '/build/staff-query',
			array(
				'render_callback' => array( $this, 'render_block_callback' ),
			)
		);
	}
}
