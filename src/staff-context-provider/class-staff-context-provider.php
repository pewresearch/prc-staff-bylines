<?php
/**
 * Staff Context Provider Block
 *
 * @package PRC\Platform\Staff_Bylines
 */

namespace PRC\Platform\Staff_Bylines;

use WP_Block;

/**
 * Block Name:        Staff Context Provider
 * Description:       Provides information about a Staff member via termId and passes that information via block context to its innerblocks.
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Pew Research Center
 *
 * @package           prc-staff-bylines
 */
class Staff_Context_Provider {
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
	 * @param mixed $loader Loader.
	 */
	public function init( $loader = null ) {
		if ( null !== $loader ) {
			$loader->add_action( 'init', $this, 'block_init' );
		}
	}

	/**
	 * Render the block
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content Block content.
	 * @param array  $block WP_Block object.
	 * @return string
	 */
	public function render_block_callback( $attributes, $content, $block ) {
		$staff_id = false;
		$term_id  = false;
		// check if staffSlug in attributes exists, if so get the staff post id by slug...
		if ( array_key_exists( 'staffSlug', $attributes ) ) {
			$staff = get_page_by_path( $attributes['staffSlug'], OBJECT, 'staff' );
			if ( $staff ) {
				$staff_id = $staff->ID;
			}
		} else {
			$context = $block->context;
			if ( array_key_exists( 'postType', $context ) && array_key_exists( 'postId', $context ) && 'staff' === $context['postType'] ) {
				$staff_id = $context['postId'];
			} else {
				$queried_object = get_queried_object();
				// check if taxonomy exists on the queried object, if so get the term id...
				if ( ! is_a( $queried_object, 'WP_Term' ) ) {
					return $context;
				}
				$taxonomy = $queried_object->taxonomy;
				if ( 'bylines' !== $taxonomy ) {
					return $context;
				}
				$term_id = get_queried_object_id();
			}
		}

		$staff = new Staff( $staff_id, $term_id );
		if ( is_wp_error( $staff ) ) {
			return '<!-- Staff not found -->';
		}

		$block_instance              = $block->parsed_block;
		$block_instance['blockName'] = 'core/null';
		$content                     = (
			new WP_Block(
				$block_instance,
				array( 'staffId' => $staff->ID )
			)
		)->render( array( 'dynamic' => false ) );

		return wp_sprintf(
			'<div %1$s>%2$s</div>',
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			get_block_wrapper_attributes(),
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$content
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
			PRC_STAFF_BYLINES_DIR . '/build/staff-context-provider',
			array(
				'render_callback' => array( $this, 'render_block_callback' ),
			)
		);
	}
}
