<?php
/**
 * Staff Info Block
 *
 * @package PRC\Platform\Staff_Bylines
 */

declare(strict_types=1);

namespace PRC\Platform\Staff_Bylines;

use WP_Block;

/**
 * Block Name:        Staff Info
 * Description:       Display staff info from a byline; supports name, job title, twitter, and expertise.
 * Version:           0.1.0
 * Requires at least: 6.1
 * Requires PHP:      8.2
 * Author:            Seth Rubenstein
 *
 * @package           prc-staff-bylines
 */
class Staff_Info {
	/**
	 * Block JSON
	 *
	 * @var array
	 */
	public array $block_json;

	/**
	 * Editor script handle
	 *
	 * @var string
	 */
	public string $editor_script_handle;

	/**
	 * Block bound staff
	 *
	 * @var array|false
	 */
	public array|false $block_bound_staff = false;

	/**
	 * Constructor
	 *
	 * @param Loader $loader Loader.
	 */
	public function __construct( Loader $loader ) {
		$this->block_json = Bootstrap::get_block_json( 'staff-info' );
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
			$loader->add_action( 'init', $this, 'register_assets' );
			$loader->add_action( 'enqueue_block_editor_assets', $this, 'register_editor_script' );
			$loader->add_filter( 'render_block_core/paragraph', $this, 'remove_empty_orcid_paragraph', 10, 2 );
			$loader->add_filter( 'render_block_core/group', $this, 'remove_empty_orcid_group', 10, 2 );
			$loader->add_filter( 'block_bindings_supported_attributes_core/icon', $this, 'enable_icon_binding' );
			$loader->add_filter( 'render_block_core/icon', $this, 'style_orcid_icon', 10, 2 );
		}
	}

	/**
	 * Drop paragraphs bound to the ORCID iD when the staff member has none.
	 *
	 * @hook render_block_core/paragraph
	 * @param string $block_content Rendered block HTML.
	 * @param array  $block         Parsed block.
	 * @return string
	 */
	public function remove_empty_orcid_paragraph( $block_content, $block ): string {
		$binding = $block['attrs']['metadata']['bindings']['content'] ?? null;
		if (
			is_array( $binding )
			&& 'prc-platform/staff-info' === ( $binding['source'] ?? null )
			&& 'orcid' === ( $binding['args']['valueToFetch'] ?? null )
			&& '' === trim( wp_strip_all_tags( (string) $block_content ) )
		) {
			return '';
		}
		return (string) $block_content;
	}

	/**
	 * Drop groups wrapping an ORCID iD paragraph when the staff member has none.
	 *
	 * @hook render_block_core/group
	 * @param string $block_content Rendered block HTML.
	 * @param array  $block         Parsed block.
	 * @return string
	 */
	public function remove_empty_orcid_group( $block_content, $block ): string {
		foreach ( $block['innerBlocks'] ?? array() as $inner_block ) {
			$binding = $inner_block['attrs']['metadata']['bindings']['content'] ?? null;
			if (
				'core/paragraph' === ( $inner_block['blockName'] ?? null )
				&& is_array( $binding )
				&& 'prc-platform/staff-info' === ( $binding['source'] ?? null )
				&& 'orcid' === ( $binding['args']['valueToFetch'] ?? null )
				&& '' === trim( wp_strip_all_tags( (string) $block_content ) )
				&& ! str_contains( (string) $block_content, '<svg' )
			) {
				return '';
			}
		}
		return (string) $block_content;
	}

	/**
	 * Allow block bindings on the `icon` attribute of `core/icon`.
	 *
	 * Core adds this in WordPress 7.2. Remove once 7.2 is the minimum version.
	 *
	 * @hook block_bindings_supported_attributes_core/icon
	 * @param array $supported_attributes Supported attributes.
	 * @return array
	 */
	public function enable_icon_binding( $supported_attributes ): array {
		$supported_attributes = (array) $supported_attributes;
		if ( ! in_array( 'icon', $supported_attributes, true ) ) {
			$supported_attributes[] = 'icon';
		}
		return $supported_attributes;
	}

	/**
	 * Color and label an icon block bound to the ORCID iD icon.
	 *
	 * The binding only fills `icon` when the staff member has an iD, so an empty
	 * render means there is nothing to style. The mark is a disc with an "iD"
	 * cut-out: the glyph color paints the disc and the background shows through
	 * the cut-out. An unauthenticated iD gets the same mark in grayscale.
	 *
	 * @hook render_block_core/icon
	 * @param string $block_content Rendered block HTML.
	 * @param array  $block         Parsed block.
	 * @return string
	 */
	public function style_orcid_icon( $block_content, $block ): string {
		$block_content = (string) $block_content;
		$binding       = $block['attrs']['metadata']['bindings']['icon'] ?? null;
		if (
			'' === $block_content
			|| ! is_array( $binding )
			|| 'prc-platform/staff-info' !== ( $binding['source'] ?? null )
			|| 'orcid-icon' !== ( $binding['args']['valueToFetch'] ?? null )
		) {
			return $block_content;
		}

		$processor = new \WP_HTML_Tag_Processor( $block_content );
		if ( ! $processor->next_tag( 'svg' ) ) {
			return $block_content;
		}

		$authenticated = is_array( $this->block_bound_staff ) && true === ( $this->block_bound_staff['orcid_authenticated'] ?? false );

		$existing = trim( (string) $processor->get_attribute( 'style' ) );
		$existing = '' === $existing ? '' : rtrim( $existing, ';' ) . ';';
		$processor->set_attribute(
			'style',
			sprintf(
				'%1$scolor:%2$s;background-color:%3$s;border-radius:50%%%4$s',
				$existing,
				Orcid::ICON_GLYPH_COLOR,
				Orcid::ICON_BACKGROUND_COLOR,
				$authenticated ? '' : ';filter:grayscale(1)'
			)
		);
		$processor->remove_attribute( 'aria-hidden' );
		$processor->remove_attribute( 'focusable' );
		$processor->set_attribute( 'role', 'img' );
		$processor->set_attribute( 'aria-label', $authenticated ? __( 'ORCID iD', 'prc-staff-bylines' ) : __( 'ORCID iD (unauthenticated)', 'prc-staff-bylines' ) );

		return $processor->get_updated_html();
	}

	/**
	 * Register assets
	 *
	 * @hook init
	 * @return void
	 */
	public function register_assets(): void {
		$this->editor_script_handle = register_block_script_handle( $this->block_json, 'editorScript' );
	}

	/**
	 * Register editor script
	 *
	 * @hook enqueue_block_editor_assets
	 * @return void
	 */
	public function register_editor_script(): void {
		wp_enqueue_script( $this->editor_script_handle );
	}

	/**
	 * Get staff info for block binding
	 *
	 * @param mixed $source_args Source args.
	 * @param mixed $block Block.
	 * @param mixed $attribute_name Attribute name.
	 * @return mixed
	 */
	public function get_staff_info_for_block_binding( mixed $source_args, mixed $block, mixed $attribute_name ): mixed {
		$staff_id = $this->resolve_staff_id_from_block( $block );
		if ( false === $staff_id || '' === $staff_id ) {
			return null;
		}

		if ( false === $this->block_bound_staff || ( $this->block_bound_staff['ID'] ?? null ) !== $staff_id ) {
			$this->block_bound_staff = Staff_Field_Resolver::resolve_staff_record( $staff_id );
			if ( null === $this->block_bound_staff ) {
				return null;
			}
		}

		return Staff_Field_Resolver::resolve_binding_value(
			$this->block_bound_staff,
			is_array( $source_args ) ? $source_args : array(),
			$block->name,
			(string) $attribute_name
		);
	}

	/**
	 * Resolve staffId from block instance context, including ancestor injection.
	 *
	 * @param mixed $block Block instance.
	 * @return int|string|false
	 */
	private function resolve_staff_id_from_block( mixed $block ): int|string|false {
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

		return Staff_Context_Provider::get_active_render_staff_id();
	}

	/**
	 * Registers the block using the metadata loaded from the `block.json` file.
	 * Behind the scenes, it registers also all assets so they can be enqueued
	 * through the block editor in the corresponding context.
	 *
	 * @hook init
	 * @see https://developer.wordpress.org/reference/functions/register_block_type/
	 */
	public function block_init(): void {
		register_block_bindings_source(
			'prc-platform/staff-info',
			array(
				'label'              => __( 'Staff Info API', 'prc-staff-bylines' ),
				'get_value_callback' => array( $this, 'get_staff_info_for_block_binding' ),
				'uses_context'       => array( 'staffId' ),
			)
		);
	}
}
