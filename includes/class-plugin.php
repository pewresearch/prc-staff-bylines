<?php
/**
 * Plugin class.
 *
 * @package    PRC\Platform\Staff_Bylines
 */

namespace PRC\Platform\Staff_Bylines;

use WP_Error;

/**
 * Plugin class.
 *
 * @package    PRC\Platform\Staff_Bylines
 */
class Plugin {
	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the platform as initialized by hooks.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->version     = '1.0.0';
		$this->plugin_name = 'prc-staff-bylines';

		$this->load_dependencies();
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
	private function load_dependencies() {
		// Load plugin loading class.
		require_once plugin_dir_path( __DIR__ ) . '/includes/class-loader.php';

		// Initialize the loader.
		$this->loader = new Loader();

		require_once plugin_dir_path( __DIR__ ) . '/includes/class-content-type.php';
		require_once plugin_dir_path( __DIR__ ) . '/includes/class-staff.php';
		require_once plugin_dir_path( __DIR__ ) . '/includes/class-bylines.php';
		require_once plugin_dir_path( __DIR__ ) . '/includes/class-seo.php';
		require_once plugin_dir_path( __DIR__ ) . '/includes/class-rest-api.php';
		require_once plugin_dir_path( __DIR__ ) . '/includes/class-maelstrom.php';
		require_once plugin_dir_path( __DIR__ ) . '/includes/class-guest-author-commands.php';
		require_once plugin_dir_path( __DIR__ ) . '/blocks/class-blocks.php';
	}

	/**
	 * Initialize the dependencies.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function init_dependencies() {
		new Content_Type( $this->get_loader() );
		new SEO( $this->get_loader() );
		new REST_API( $this->get_loader() );
		new Maelstrom( $this->get_loader() );
		new Blocks( $this->get_loader() );

		$this->loader->add_action( 'admin_bar_menu', $this, 'modify_admin_bar_edit_link', 100 );
		$this->loader->add_action( 'enqueue_block_editor_assets', $this, 'enqueue_editor_assets' );
	}

	/**
	 * Add an edit link to the admin bar for the current staff post.
	 *
	 * @hook admin_bar_menu
	 *
	 * @param mixed $admin_bar The admin bar.
	 * @return void
	 */
	public function modify_admin_bar_edit_link( $admin_bar ) {
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
	public function register_editor_asset( $folder_name ) {
		$asset_file = include plugin_dir_path( __FILE__ ) . $folder_name . '/build/index.asset.php';
		$asset_slug = $this->plugin_name . '-' . $folder_name;
		$script_src = plugin_dir_url( __FILE__ ) . $folder_name . '/build/index.js';

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
	 * @param string $folder_name The folder name.
	 * @param string $post_type The post type.
	 * @return void
	 */
	public function enqueue_editor_asset( $folder_name, $post_type = false ) {
		$this->register_editor_asset( $folder_name );
		$enabled_post_types = false !== $post_type ? array( $post_type ) : Content_Type::get_enabled_post_types();
		$registered         = wp_script_is( $this->plugin_name . '-' . $folder_name, 'registered' );
		if ( is_admin() && $registered && in_array( \PRC\Platform\get_wp_admin_current_post_type(), $enabled_post_types, true ) ) {
			wp_enqueue_script( $this->plugin_name . '-' . $folder_name );
		}
	}

	/**
	 * Enqueue the editor assets
	 *
	 * @hook enqueue_block_editor_assets
	 */
	public function enqueue_editor_assets() {
		$this->enqueue_editor_asset( 'bylines-inspector-sidebar-panel' );
		$this->enqueue_editor_asset( 'staff-inspector-sidebar-panel', 'staff' );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    PRC\Platform\Staff_Bylines\Loader
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}
}
