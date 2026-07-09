<?php
declare(strict_types=1);
/**
 * Staff inline block bits.
 *
 * @package PRC\Platform\Staff_Bylines
 */

namespace PRC\Platform\Staff_Bylines\Bits;

use WP_Block;

use PRC\Platform\Staff_Bylines\Staff_Field_Resolver;

use function PRC\Platform\Block_Bits\register_block_bit;

/**
 * Registers prc-staff-bylines/staff-* inline bits.
 */
class Staff_Bits {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register' ), 11 );
	}

	/**
	 * Register staff bits with the platform block-bits registry.
	 *
	 * @hook init 11
	 */
	public function register(): void {
		if ( ! function_exists( '\PRC\Platform\Block_Bits\register_block_bit' ) ) {
			return;
		}

		$allowed = array( 'core/paragraph', 'core/heading', 'core/list-item' );

		register_block_bit(
			'prc-staff-bylines/staff-name',
			array(
				'label'               => __( 'Staff Name', 'prc-staff-bylines' ),
				'category'            => __( 'Staff', 'prc-staff-bylines' ),
				'allowed_block_types' => $allowed,
				'default_text'        => __( 'Staff Name', 'prc-staff-bylines' ),
				'render_strategy'     => 'callback',
				'render_callback'     => static fn( $attrs, $parsed, $block ) => self::render_field( 'name', $parsed, $block ),
			)
		);

		register_block_bit(
			'prc-staff-bylines/staff-job-title',
			array(
				'label'               => __( 'Staff Job Title', 'prc-staff-bylines' ),
				'category'            => __( 'Staff', 'prc-staff-bylines' ),
				'allowed_block_types' => $allowed,
				'default_text'        => __( 'Job Title', 'prc-staff-bylines' ),
				'render_strategy'     => 'callback',
				'render_callback'     => static fn( $attrs, $parsed, $block ) => self::render_field( 'job_title', $parsed, $block ),
			)
		);

		register_block_bit(
			'prc-staff-bylines/staff-mini-bio',
			array(
				'label'               => __( 'Staff Mini Bio', 'prc-staff-bylines' ),
				'category'            => __( 'Staff', 'prc-staff-bylines' ),
				'allowed_block_types' => $allowed,
				'default_text'        => __( 'Staff mini bio…', 'prc-staff-bylines' ),
				'render_strategy'     => 'callback',
				'render_callback'     => static fn( $attrs, $parsed, $block ) => self::render_field( 'mini_bio', $parsed, $block ),
			)
		);
	}

	/**
	 * Render a staff bit field from available block context.
	 *
	 * @param string        $field        Field key.
	 * @param array         $parsed_block Parsed parent block.
	 * @param WP_Block|null $block        Block instance.
	 * @return string
	 */
	private static function render_field( string $field, array $parsed_block, ?WP_Block $block ): string {
		unset( $parsed_block );

		$staff_id = self::get_staff_id_from_block( $block );
		if ( false === $staff_id ) {
			return '';
		}

		$staff_record = Staff_Field_Resolver::resolve_staff_record( $staff_id );
		if ( null === $staff_record ) {
			return '';
		}

		$value = Staff_Field_Resolver::resolve_bit_field( $staff_record, $field );
		return null !== $value ? esc_html( $value ) : '';
	}

	/**
	 * Resolve staffId from block instance context (including ancestor injection).
	 *
	 * @param WP_Block|null $block Block instance.
	 * @return int|string|false
	 */
	private static function get_staff_id_from_block( ?WP_Block $block ): int|string|false {
		if ( ! $block instanceof WP_Block ) {
			return false;
		}

		if ( class_exists( 'WP_Block_Context_Extractor' ) ) {
			$available_context = \WP_Block_Context_Extractor::get_available_context( $block );
			if ( array_key_exists( 'staffId', $available_context ) && ! empty( $available_context['staffId'] ) ) {
				return $available_context['staffId'];
			}
		}

		if ( array_key_exists( 'staffId', $block->context ) && ! empty( $block->context['staffId'] ) ) {
			return $block->context['staffId'];
		}

		return false;
	}
}
