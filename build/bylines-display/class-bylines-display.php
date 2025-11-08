<?php
/**
 * Bylines Display Block
 *
 * @package PRC\Platform\Staff_Bylines
 */

namespace PRC\Platform\Staff_Bylines;

/**
 * Bylines Display Block
 *
 * @package PRC\Platform\Staff_Bylines
 */
class Bylines_Display {
	/**
	 * Constructor
	 *
	 * @param object $loader The loader.
	 */
	public function __construct( $loader ) {
		$this->init( $loader );
	}

	/**
	 * Initialize the block
	 *
	 * @param object $loader The loader.
	 */
	public function init( $loader = null ) {
		if ( null !== $loader ) {
			$loader->add_action( 'init', $this, 'block_init' );
		}
	}

	/**
	 * Render callback for the block
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content    Block content.
	 * @param object $block      Block object.
	 * @return string
	 */
	public function render_callback( $attributes, $content, $block ) {
		if ( isset( $block->context['postId'] ) ) {
			$object_id = $block->context['postId'];
		} else {
			$object_id = get_the_ID();
		}

		$block_wrapper_attrs = get_block_wrapper_attributes();
		$prefix              = isset( $attributes['prefix'] ) ? $attributes['prefix'] : 'By';
		$bylines             = new Bylines( (int) $object_id );
		if ( is_wp_error( $bylines->bylines ) ) {
			return '';
		}
		if ( false === $bylines->should_display ) {
			return '';
		}

		$bylines_output = $bylines->format( 'html' );

		if ( 2 >= strlen( $bylines_output ) ) {
			return '';
		}

		return wp_sprintf(
			'<div %1$s class="wp-block-prc-block-bylines-display__bylines"><span class="wp-block-prc-block-bylines-display__prefix">%2$s</span> %3$s</div>',
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$block_wrapper_attrs,
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$prefix,
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$bylines_output
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
			PRC_STAFF_BYLINES_DIR . '/build/bylines-display',
			array(
				'render_callback' => array( $this, 'render_callback' ),
			)
		);
	}
}
