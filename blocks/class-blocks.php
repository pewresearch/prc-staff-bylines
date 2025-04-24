<?php
/**
 * The blocks class.
 *
 * @package PRC\Platform\Staff_Bylines
 */

namespace PRC\Platform\Staff_Bylines;

/**
 * The blocks class.
 */
class Blocks {
	/**
	 * The loader object.
	 *
	 * @var object
	 */
	protected $loader;

	/**
	 * Constructor.
	 *
	 * @param object $loader The loader object.
	 */
	public function __construct( $loader ) {
		$this->loader = $loader;

		require_once PRC_STAFF_BYLINES_BLOCKS_DIR . '/build/bylines-query/class-bylines-query.php';
		require_once PRC_STAFF_BYLINES_BLOCKS_DIR . '/build/bylines-display/class-bylines-display.php';
		require_once PRC_STAFF_BYLINES_BLOCKS_DIR . '/build/staff-context-provider/class-staff-context-provider.php';
		require_once PRC_STAFF_BYLINES_BLOCKS_DIR . '/build/staff-info/class-staff-info.php';
		require_once PRC_STAFF_BYLINES_BLOCKS_DIR . '/build/staff-query/class-staff-query.php';

		$this->init();
	}

	/**
	 * Initialize the class.
	 */
	public function init() {
		wp_register_block_metadata_collection(
			plugin_dir_path( __FILE__ ) . 'build',
			plugin_dir_path( __FILE__ ) . 'build/blocks-manifest.php'
		);

		new Bylines_Query( $this->loader );
		new Bylines_Display( $this->loader );
		new Staff_Context_Provider( $this->loader );
		new Staff_Info( $this->loader );
		new Staff_Query( $this->loader );
	}

	/**
	 * Get the block JSON.
	 *
	 * @param string $block_name The block name.
	 * @return array
	 */
	public static function get_block_json( $block_name ) {
		$manifest = include PRC_STAFF_BYLINES_BLOCKS_DIR . '/build/blocks-manifest.php';
		if ( ! isset( $manifest[ $block_name ] ) ) {
			return array();
		}
			$manifest = array_key_exists( $block_name, $manifest ) ? $manifest[ $block_name ] : array();
		if ( ! empty( $manifest ) ) {
			$manifest['file'] = wp_normalize_path( realpath( PRC_STAFF_BYLINES_BLOCKS_DIR . '/build/' . $block_name . '/block.json' ) );
		}
		return $manifest;
	}
}
