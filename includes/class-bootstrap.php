<?php
declare(strict_types=1);
/**
 * Bootstrap class.
 *
 * @package    PRC\Platform\Staff_Bylines
 */

namespace PRC\Platform\Staff_Bylines;

use WP_Error;

/**
 * Bootstrap class.
 *
 * @package    PRC\Platform\Staff_Bylines
 */
class Bootstrap {
	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected Loader $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected string $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected string $version;

	/**
	 * Define the core functionality of the platform as initialized by hooks.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->version     = PRC_STAFF_BYLINES_VERSION;
		$this->plugin_name = 'prc-staff-bylines';

		$this->load_dependencies();
		$this->define_patterns();
		$this->init_dependencies();
	}


	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies(): void {
		// Load plugin loading class.
		require_once plugin_dir_path( __DIR__ ) . '/includes/class-loader.php';

		// Initialize the loader.
		$this->loader = new Loader();

		require_once plugin_dir_path( __DIR__ ) . '/includes/class-content-type.php';
		require_once plugin_dir_path( __DIR__ ) . '/includes/class-staff.php';
		require_once plugin_dir_path( __DIR__ ) . '/includes/class-staff-field-resolver.php';
		require_once plugin_dir_path( __DIR__ ) . '/includes/bits/class-staff-bits.php';
		require_once plugin_dir_path( __DIR__ ) . '/includes/class-bylines.php';
		require_once plugin_dir_path( __DIR__ ) . '/includes/class-seo.php';
		require_once plugin_dir_path( __DIR__ ) . '/includes/class-rest-api.php';
		require_once plugin_dir_path( __DIR__ ) . '/includes/class-maelstrom.php';
		require_once plugin_dir_path( __DIR__ ) . '/includes/class-guest-author-commands.php';

		$this->load_blocks();

		// Check for blocks-manifest.php file, if it exists, register block metadata.
		if ( ! file_exists( PRC_STAFF_BYLINES_DIR . '/build/blocks-manifest.php' ) ) {
			do_action( 'qm/warning', 'PRC Staff Bylines blocks-manifest.php file is missing' );
			return;
		}
		wp_register_block_metadata_collection(
			PRC_STAFF_BYLINES_DIR . '/build',
			PRC_STAFF_BYLINES_DIR . '/build/blocks-manifest.php'
		);
	}

	/**
	 * Register block patterns from the plugin patterns directory.
	 *
	 * Queues on init priority 8 so translated category labels run after WP 6.7+
	 * allows just-in-time textdomain loading, and before the platform pattern
	 * loader registers categories (init/9) and pattern files (init/10). Safe vs
	 * CPT (init/5), block_init (init/10), meta (init/11), and rest_api_init —
	 * register_plugin_patterns() only appends to the global queue.
	 */
	private function define_patterns(): void {
		$this->loader->add_action( 'init', $this, 'register_patterns', 8 );
	}

	/**
	 * Load staff binding companion patterns via the platform pattern loader.
	 *
	 * @hook init 8
	 */
	public function register_patterns(): void {
		if ( ! function_exists( '\PRC\Platform\Core\Patterns\register_plugin_patterns' ) ) {
			return;
		}

		\PRC\Platform\Core\Patterns\register_plugin_patterns(
			'prc-staff-bylines',
			PRC_STAFF_BYLINES_DIR . '/patterns',
			array(
				'category_label' => __( 'Staff Bylines', 'prc-staff-bylines' ),
				'text_domain'    => 'prc-staff-bylines',
			)
		);
	}

	/**
	 * Initialize the dependencies.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function init_dependencies(): void {
		// Core.
		new Content_Type( $this->get_loader() );
		new SEO( $this->get_loader() );
		new REST_API( $this->get_loader() );
		new Maelstrom( $this->get_loader() );

		if ( class_exists( 'PRC\Platform\Markdown_For_Agents\LLMs_Txt' ) ) {
			require_once plugin_dir_path( __DIR__ ) . '/includes/class-llms-txt-section.php';
			new Llms_Txt_Section( $this->get_loader() );
		}

		// Blocks.
		new Bylines_Query( $this->get_loader() );
		new Bylines_Display( $this->get_loader() );
		new Staff_Context_Provider( $this->get_loader() );
		new Staff_Info( $this->get_loader() );
		new Staff_Query( $this->get_loader() );

		new Bits\Staff_Bits();

		$this->loader->add_action( 'admin_bar_menu', $this, 'modify_admin_bar_edit_link', 100 );
		$this->loader->add_action( 'enqueue_block_editor_assets', $this, 'enqueue_editor_assets', 9 );

		// Disable WordPress author archives - bylines taxonomy handles author pages instead.
		$this->loader->add_action( 'template_redirect', $this, 'disable_author_archives' );
	}

	/**
	 * Disable WordPress author archives.
	 *
	 * PRC uses the bylines taxonomy for author pages instead of WordPress's
	 * built-in author archives. This prevents duplicate content and ensures
	 * all author traffic goes through the bylines system.
	 *
	 * @hook template_redirect
	 * @return void
	 */
	public function disable_author_archives(): void {
		if ( is_author() ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
		}
	}

	/**
	 * Get the block JSON.
	 *
	 * @param string $block_name The block name.
	 * @return array
	 */
	public static function get_block_json( string $block_name ): array {
		$manifest = include PRC_STAFF_BYLINES_DIR . '/build/blocks-manifest.php';
		if ( ! isset( $manifest[ $block_name ] ) ) {
			return array();
		}
		$block_manifest         = $manifest[ $block_name ];
		$block_manifest['file'] = wp_normalize_path(
			realpath( PRC_STAFF_BYLINES_DIR . '/build/' . $block_name . '/block.json' )
		);
		return $block_manifest;
	}

	/**
	 * Include a file from the plugin's includes directory.
	 *
	 * @param string $block_file_name The block file name.
	 * @return WP_Error|null
	 */
	private function include_block( string $block_file_name ): WP_Error|null {
		$dir             = 'local' === wp_get_environment_type() ? 'src' : 'build';
		$block_file_path = $dir . '/' . $block_file_name . '/class-' . $block_file_name . '.php';
		if ( file_exists( plugin_dir_path( __DIR__ ) . $block_file_path ) ) {
			require_once plugin_dir_path( __DIR__ ) . $block_file_path;
		} else {
			do_action( 'qm/debug', 'BLOCK_MISSING: ' . $block_file_path );
			error_log( 'BLOCK_MISSING: ' . $block_file_path );
			return new WP_Error( 'prc_staff_bylines_block_missing', __( 'Block missing.', 'prc-staff-bylines' ) );
		}
		return null;
	}

	/**
	 * Include all blocks from the plugin's /blocks directory.
	 *
	 * @return void
	 */
	private function load_blocks(): void {
		$block_files = glob( PRC_STAFF_BYLINES_DIR . '/src/*', GLOB_ONLYDIR );
		foreach ( $block_files as $block ) {
			$block  = basename( $block );
			$loaded = $this->include_block( $block );
			if ( is_wp_error( $loaded ) ) {
				error_log( $loaded->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}
	}

	/**
	 * Add an edit link to the admin bar for the current staff post.
	 *
	 * @hook admin_bar_menu
	 *
	 * @param mixed $admin_bar The admin bar.
	 * @return void
	 */
	public function modify_admin_bar_edit_link( $admin_bar ): void {
		if ( ! is_tax( Content_Type::$taxonomy_object_name ) ) {
			return;
		}

		$admin_bar->remove_menu( 'edit' );

		$staff = new Staff( false, get_queried_object()->term_id );
		if ( is_wp_error( $staff ) ) {
			return;
		}

		$link = get_edit_post_link( $staff->ID );
		$admin_bar->add_menu(
			array(
				'parent' => false,
				'id'     => 'edit_staff',
				'title'  => __( 'Edit Staff' ),
				'href'   => $link,
				'meta'   => array(
					'title' => __( 'Edit Staff' ),
				),
			)
		);
	}


	/**
	 * Register the editor asset
	 *
	 * @param string $folder_name The folder name.
	 * @return bool|WP_Error True if the asset is registered, WP_Error if it fails.
	 */
	public function register_editor_asset( string $folder_name ): bool|WP_Error {
		$asset_file = include PRC_STAFF_BYLINES_DIR . '/includes/' . $folder_name . '/build/index.asset.php';
		$asset_slug = $this->plugin_name . '-' . $folder_name;
		$script_src = plugins_url( 'includes/' . $folder_name . '/build/index.js', PRC_STAFF_BYLINES_FILE );

		$script = wp_register_script(
			$asset_slug,
			$script_src,
			$asset_file['dependencies'],
			$asset_file['version'],
			true
		);

		if ( ! $script ) {
			return new WP_Error( $this->plugin_name . '-' . $folder_name, 'Failed to register all assets' );
		}

		return true;
	}

	/**
	 * Enqueue the editor asset
	 *
	 * @param string      $folder_name The folder name.
	 * @param string|bool $post_type The post type.
	 * @return void
	 */
	public function enqueue_editor_asset( string $folder_name, string|bool $post_type = false ): void {
		$this->register_editor_asset( $folder_name );
		$enabled_post_types = false !== $post_type ? array( $post_type ) : Content_Type::get_enabled_post_types();
		$registered         = wp_script_is( $this->plugin_name . '-' . $folder_name, 'registered' );
		if ( is_admin() && $registered && in_array( \PRC\BlockUtils\get_wp_admin_current_post_type(), $enabled_post_types, true ) ) {
			wp_enqueue_script( $this->plugin_name . '-' . $folder_name );
		}
	}

	/**
	 * Enqueue the editor assets
	 *
	 * @hook enqueue_block_editor_assets
	 * @return void
	 */
	public function enqueue_editor_assets(): void {
		$this->enqueue_editor_asset( 'bylines-inspector-sidebar-panel' );
		$this->enqueue_editor_asset( 'staff-inspector-sidebar-panel', 'staff' );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run(): void {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name(): string {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Loader
	 */
	public function get_loader(): Loader {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version(): string {
		return $this->version;
	}
}
