<?php
declare(strict_types=1);
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
	 * @param Loader $loader The loader.
	 */
	public function __construct( Loader $loader ) {
		$this->init( $loader );
	}

	/**
	 * Initialize the block
	 *
	 * @param Loader|null $loader The loader.
	 */
	public function init( ?Loader $loader = null ): void {
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
	public function render_callback( array $attributes, string $content, object $block ): string {
		if ( isset( $block->context['postId'] ) ) {
			$object_id = (int) $block->context['postId'];
		} else {
			$maybe_id  = get_the_ID();
			$object_id = $maybe_id ? (int) $maybe_id : 0;
			if ( $object_id <= 0 && isset( $GLOBALS['post'] ) && $GLOBALS['post'] instanceof \WP_Post ) {
				$object_id = (int) $GLOBALS['post']->ID;
			}
		}

		if ( $object_id <= 0 ) {
			return '';
		}

		$block_wrapper_attrs = get_block_wrapper_attributes();
		$prefix              = isset( $attributes['prefix'] ) ? $attributes['prefix'] : 'By';
		$bylines             = new Bylines( $object_id );
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
	public function block_init(): void {
		register_block_type_from_metadata(
			PRC_STAFF_BYLINES_DIR . '/build/bylines-display',
			array(
				'render_callback' => array( $this, 'render_callback' ),
			)
		);
	}
}
