<?php
declare(strict_types=1);
/**
 * Bylines Query Block
 *
 * @package PRC\Platform\Staff_Bylines
 */

namespace PRC\Platform\Staff_Bylines;

use WP_Block;

/**
 * Block Name:        Bylines Query
 * Description:       Query the current post for bylines and display them.
 * Requires at least: 6.4
 * Requires PHP:      8.2
 * Author:            Seth Rubenstein
 *
 * @package           prc-staff-bylines
 */
class Bylines_Query {
	/**
	 * Constructor
	 *
	 * @param Loader $loader Loader.
	 */
	public function __construct( Loader $loader ) {
		$this->init( $loader );
	}

	/**
	 * Initialize the block
	 *
	 * @param Loader|null $loader Loader.
	 */
	public function init( ?Loader $loader = null ): void {
		if ( null !== $loader ) {
			$loader->add_action( 'init', $this, 'block_init' );
		}
	}

	/**
	 * Query bylines
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public function query_bylines( int $post_id ): array {
		$byline_terms = get_post_meta( $post_id, 'bylines', true );
		$bylines      = array();
		if ( $byline_terms ) {
			foreach ( $byline_terms as $byline_term ) {
				$byline_term_id = $byline_term['termId'] ?? null;
				if ( ! is_int( $byline_term_id ) && ! is_numeric( $byline_term_id ) ) {
					continue;
				}
				$byline_term_id = (int) $byline_term_id;
				$staff          = new Staff( false, $byline_term_id );
				if ( is_wp_error( $staff ) || empty( $staff->ID ) ) {
					continue;
				}
				$bylines[] = array(
					'staffId' => $staff->ID,
				);
			}
		}
		return $bylines;
	}

	/**
	 * Render block callback
	 *
	 * @param array    $attributes Attributes.
	 * @param string   $content Content.
	 * @param WP_Block $block Block.
	 * @return string
	 */
	public function render_block_callback( array $attributes, string $content, WP_Block $block ): string {
		$bylines = $this->query_bylines( (int) get_the_ID() );

		$block_attrs = get_block_wrapper_attributes();

		$block_content = '';

		$block_instance = $block->parsed_block;

		// Set the block name to one that does not correspond to an existing registered block.
		// This ensures that for the inner instances of the Staff Query block, we do not render any block supports.
		$block_instance['blockName'] = 'core/null';

		foreach ( $bylines as $byline_context ) {
			// Render the inner blocks of the Bylines Query block with `dynamic` set to `false` to prevent calling
			// `render_callback` and ensure that no wrapper markup is included.
			$block_content .= (
				new WP_Block(
					$block_instance,
					$byline_context
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
	public function block_init(): void {
		register_block_type_from_metadata(
			PRC_STAFF_BYLINES_DIR . '/build/bylines-query',
			array(
				'render_callback' => array( $this, 'render_block_callback' ),
			)
		);
	}
}
