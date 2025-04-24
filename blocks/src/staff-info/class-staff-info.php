<?php
/**
 * Staff Info Block
 *
 * @package PRC\Platform\Staff_Bylines
 */

namespace PRC\Platform\Staff_Bylines;

/**
 * Block Name:        Staff Info
 * Description:       Display staff info from a byline; supports name, job title, twitter, and expertise.
 * Version:           0.1.0
 * Requires at least: 6.1
 * Requires PHP:      8.1
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
	public $block_json;

	/**
	 * Editor script handle
	 *
	 * @var string
	 */
	public $editor_script_handle;

	/**
	 * Block bound staff
	 *
	 * @var bool
	 */
	public $block_bound_staff = false;

	/**
	 * Constructor
	 *
	 * @param mixed $loader Loader.
	 */
	public function __construct( $loader ) {
		$this->block_json = Blocks::get_block_json( 'staff-info' );
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
			$loader->add_action( 'init', $this, 'register_assets' );
			$loader->add_action( 'enqueue_block_editor_assets', $this, 'register_editor_script' );
		}
	}

	/**
	 * Register assets
	 *
	 * @hook init
	 * @return void
	 */
	public function register_assets() {
		$this->editor_script_handle = register_block_script_handle( $this->block_json, 'editorScript' );
	}

	/**
	 * Register editor script
	 *
	 * @hook enqueue_block_editor_assets
	 * @return void
	 */
	public function register_editor_script() {
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
	public function get_staff_info_for_block_binding( $source_args, $block, $attribute_name ) {
		$block_context = $block->context;
		$staff_post_id = array_key_exists( 'staffId', $block_context ) ? $block_context['staffId'] : false;
		if ( false === $staff_post_id ) {
			return null;
		}
		// First instance lets set the $this->block_bound_staff to the staff object so its available for later blocks.
		if ( false === $this->block_bound_staff || $this->block_bound_staff['ID'] !== $staff_post_id ) {
			$staff                   = new Staff( $staff_post_id );
			$this->block_bound_staff = get_object_vars( $staff );
		}

		$block_name       = $block->name;
		$value_to_replace = null;
		if ( in_array( $block_name, array( 'core/image', 'core/paragraph', 'core/heading', 'core/button' ) ) ) {
			$value_to_fetch = array_key_exists( 'valueToFetch', $source_args ) ? $source_args['valueToFetch'] : null;
			if ( null === $value_to_fetch ) {
				return null;
			}
			$output_link = array_key_exists( 'outputLink', $source_args );

			if ( 'photo-full' === $value_to_fetch && isset( $this->block_bound_staff['photo']['full'][0] ) ) {
				// If there is no photo we need to bail...
				if ( 'url' === $attribute_name ) {
					$value_to_replace = $this->block_bound_staff['photo']['full'][0];
				}
			}
			if ( 'photo-full-download-text' === $value_to_fetch ) {
				if ( ! empty( $this->block_bound_staff['photo'] ) ) {
					// If there is no photo we need to bail...
					if ( 'text' === $attribute_name ) {
						$value_to_replace = wp_sprintf(
							'Download %1$s\'s photo',
							$this->block_bound_staff['name']
						);
					}
				} elseif ( 'text' === $attribute_name ) {
						$value_to_replace = null;
				}
			}

			if ( 'photo' === $value_to_fetch && isset( $this->block_bound_staff['photo']['thumbnail'][0] ) ) {
				// If there is no photo we need to bail...
				if ( 'url' === $attribute_name ) {
					$value_to_replace = $this->block_bound_staff['photo']['thumbnail'][0];
				}
				if ( 'title' === $attribute_name ) {
					$value_to_replace = wp_sprintf(
						'Photo of %1$s',
						$this->block_bound_staff['name']
					);
				}
				if ( 'alt' === $attribute_name ) {
					$value_to_replace = wp_sprintf(
						'Download %1$s\'s photo',
						$this->block_bound_staff['name']
					);
				}
			}
			if ( 'bio' === $value_to_fetch && isset( $this->block_bound_staff['bio'] ) && ! empty( $this->block_bound_staff['bio'] ) ) {
				$value_to_replace = $this->block_bound_staff['bio'];
			}
			// If we are looking for the bio and its not set, set as the mini_bio.
			if ( 'bio' === $value_to_fetch && empty( $this->block_bound_staff['bio'] ) ) {
				$value_to_replace = $this->block_bound_staff['mini_bio'];
			}
			if ( 'mini_bio' === $value_to_fetch && isset( $this->block_bound_staff['mini_bio'] ) ) {
				$value_to_replace = $this->block_bound_staff['mini_bio'];
			}
			if ( 'name' === $value_to_fetch && isset( $this->block_bound_staff['name'] ) ) {
				$value_to_replace = $this->block_bound_staff['name'];
			}
			if ( 'job_title' === $value_to_fetch && isset( $this->block_bound_staff['job_title'] ) ) {
				$value_to_replace = $this->block_bound_staff['job_title'];
			}
			if ( 'job_title_extended' === $value_to_fetch && isset( $this->block_bound_staff['job_title_extended'] ) ) {
				$value_to_replace = $this->block_bound_staff['job_title'];
			}
			if ( true === $output_link && isset( $this->block_bound_staff['link'] ) && false !== $this->block_bound_staff['link'] ) {
				$value_to_replace = wp_sprintf(
					'<a href="%1$s">%2$s</a>',
					$this->block_bound_staff['link'],
					$value_to_replace
				);
			}
			if ( 'expertise' === $value_to_fetch && ! empty( $this->block_bound_staff['expertise'] ) ) {
				$expertise = $this->block_bound_staff['expertise'];
				$tmp       = '<span class="wp-block-prc-block-staff-context-provider__expertise-label">Expertise:</span>';
				$total     = count( $expertise );
				$sep       = $total > 1 ? ', ' : '';
				$i         = 1;
				foreach ( $expertise as $term ) {
					if ( $i === $total ) {
						$sep = '';
					}
					$tmp .= wp_sprintf(
						'<a class="wp-block-prc-block-staff-context-provider__expertise-link" href="%1$s">%2$s</a>%3$s',
						$term['url'],
						$term['label'],
						$sep
					);
					++$i;
				}
				$value_to_replace = $tmp;
			}
			if ( 'expertise' === $value_to_fetch && empty( $this->block_bound_staff['expertise'] ) ) {
				$value_to_replace = '';
			}
			if ( 'name_and_job_title' === $value_to_fetch && ! empty( $this->block_bound_staff['name'] ) && ! empty( $this->block_bound_staff['job_title'] ) ) {
				$name      = $this->block_bound_staff['name'];
				$job_title = $this->block_bound_staff['job_title'];
				$link      = $this->block_bound_staff['link'];
				if ( empty( $link ) ) {
					$value_to_replace = wp_sprintf(
						'<strong>%1$s</strong>, %2$s',
						$name,
						$job_title
					);
				} else {
					$value_to_replace = wp_sprintf(
						'<strong><a href="%2$s">%1$s</a></strong>, %3$s',
						$name,
						$link,
						$job_title
					);
				}
			}
		}
		return $value_to_replace;
	}

	/**
	 * Registers the block using the metadata loaded from the `block.json` file.
	 * Behind the scenes, it registers also all assets so they can be enqueued
	 * through the block editor in the corresponding context.
	 *
	 * @hook init
	 * @see https://developer.wordpress.org/reference/functions/register_block_type/
	 */
	public function block_init() {
		register_block_bindings_source(
			'prc-platform/staff-info',
			array(
				'label'              => __( 'Staff Info API', 'prc-platform/staff-info' ),
				'get_value_callback' => array( $this, 'get_staff_info_for_block_binding' ),
				'uses_context'       => array( 'staffId' ),
			)
		);
	}
}
