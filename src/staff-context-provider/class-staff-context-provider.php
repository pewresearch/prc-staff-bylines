<?php
declare(strict_types=1);
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
 * Requires PHP:      8.2
 * Author:            Pew Research Center
 *
 * @package           prc-staff-bylines
 */
class Staff_Context_Provider {
	/**
	 * Staff ID being rendered inside an active staff context provider pass.
	 *
	 * @var int|string|false|null
	 */
	private static int|string|false|null $active_render_staff_id = null;

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
			$loader->add_filter( 'render_block_context', $this, 'filter_render_block_context', 10, 3 );
		}
	}

	/**
	 * Resolve a staff post ID from provider attributes and block context.
	 *
	 * @param array<string, mixed> $attributes   Provider block attributes.
	 * @param array<string, mixed> $block_context Provider block context.
	 * @return int|false Staff post ID, or false when unresolved.
	 */
	public static function resolve_staff_id( array $attributes, array $block_context ): int|false {
		if ( ! empty( $attributes['staffId'] ) && is_numeric( $attributes['staffId'] ) ) {
			return (int) $attributes['staffId'];
		}

		if ( ! empty( $attributes['staffSlug'] ) ) {
			$staff = get_page_by_path( (string) $attributes['staffSlug'], OBJECT, 'staff' );
			if ( $staff ) {
				return $staff->ID;
			}
		}

		if (
			array_key_exists( 'postType', $block_context )
			&& array_key_exists( 'postId', $block_context )
			&& 'staff' === $block_context['postType']
			&& is_numeric( $block_context['postId'] )
		) {
			return (int) $block_context['postId'];
		}

		if ( is_singular( Content_Type::$post_object_name ) ) {
			$queried_id = get_queried_object_id();
			if ( $queried_id > 0 ) {
				return $queried_id;
			}
		}

		return false;
	}

	/**
	 * Staff ID for the staff context provider currently being rendered.
	 *
	 * @return int|string|false
	 */
	public static function get_active_render_staff_id(): int|string|false {
		if ( null === self::$active_render_staff_id || false === self::$active_render_staff_id ) {
			return false;
		}

		return self::$active_render_staff_id;
	}

	/**
	 * Resolve a byline term ID when the provider is rendered on a byline archive.
	 *
	 * @return int|false
	 */
	private static function resolve_byline_term_id(): int|false {
		$queried_object = get_queried_object();
		if ( ! is_a( $queried_object, 'WP_Term' ) || 'bylines' !== $queried_object->taxonomy ) {
			return false;
		}

		return get_queried_object_id();
	}

	/**
	 * Inject staffId into inner block context for descendants of the provider.
	 *
	 * @hook render_block_context
	 *
	 * @param array<string, mixed> $context      Current block context.
	 * @param array<string, mixed> $parsed_block Parsed block being rendered.
	 * @param WP_Block|null        $parent_block Parent block instance.
	 * @return array<string, mixed>
	 */
	public function filter_render_block_context( array $context, array $parsed_block, ?WP_Block $parent_block ): array {
		unset( $parsed_block );

		$active_staff_id = self::get_active_render_staff_id();
		if ( false !== $active_staff_id ) {
			$context['staffId'] = $active_staff_id;
			return $context;
		}

		if ( null === $parent_block || 'prc-block/staff-context-provider' !== $parent_block->name ) {
			return $context;
		}

		$staff_id = self::resolve_staff_id( $parent_block->attributes, $parent_block->context );
		if ( false !== $staff_id ) {
			$context['staffId'] = $staff_id;
		}

		return $context;
	}

	/**
	 * Render the block
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Block content.
	 * @param WP_Block             $block      WP_Block object.
	 * @return string
	 */
	public function render_block_callback( array $attributes, string $content, WP_Block $block ): string {
		$staff_id = self::resolve_staff_id( $attributes, $block->context );
		$term_id  = false;

		if ( false === $staff_id ) {
			$term_id = self::resolve_byline_term_id();
			if ( false === $term_id ) {
				return '';
			}
		}

		$staff = new Staff( $staff_id, $term_id );
		// Constructors cannot return WP_Error; failed resolution leaves ID = 0.
		if ( ! $staff->is_resolved() ) {
			return '<!-- Staff not found -->';
		}

		self::$active_render_staff_id = $staff->ID;

		$block_instance              = $block->parsed_block;
		$block_instance['blockName'] = 'core/null';
		$content                     = (
			new WP_Block(
				$block_instance,
				array( 'staffId' => $staff->ID )
			)
		)->render( array( 'dynamic' => false ) );

		self::$active_render_staff_id = null;

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
	public function block_init(): void {
		register_block_type_from_metadata(
			PRC_STAFF_BYLINES_DIR . '/build/staff-context-provider',
			array(
				'render_callback' => array( $this, 'render_block_callback' ),
			)
		);
	}
}
