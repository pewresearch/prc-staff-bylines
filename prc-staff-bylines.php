<?php
/**
 * PRC Staff Bylines
 *
 * @package           PRC_Staff_Bylines
 * @author            Seth Rubenstein
 * @copyright         2024 Pew Research Center
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       PRC Staff Bylines
 * Plugin URI:        https://github.com/pewresearch/prc-platform/tree/trunk/plugins/prc-staff-bylines
 * Description:       A comprehensive staff and bylines management system for WordPress that creates synchronized staff profiles and byline taxonomies. Includes editor blocks and UI for managing staff information, providing an enhanced multi-author experience.
 * Version:           1.1.0
 * Requires at least: 6.8
 * Requires PHP:      8.2
 * Author:            Seth Rubenstein
 * Author URI:        https://pewresearch.org
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       prc-staff-bylines
 * Requires Plugins:  prc-scripts, prc-post-publish-pipeline
 */

declare(strict_types=1);

namespace PRC\Platform\Staff_Bylines;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'DEFAULT_TECHNICAL_CONTACT' ) ) {
	define( 'DEFAULT_TECHNICAL_CONTACT', 'webdev@pewresearch.org' );
}

// Load the Jetpack Autoloader so runtime version-selection can pick the
// highest version across all plugins that ship the same library dep
// (matches the prc-platform-core pattern).
$prc_staff_bylines_autoloader = __DIR__ . '/vendor/autoload_packages.php';
if ( file_exists( $prc_staff_bylines_autoloader ) ) {
	require_once $prc_staff_bylines_autoloader;
}
unset( $prc_staff_bylines_autoloader );

define( 'PRC_STAFF_BYLINES_FILE', __FILE__ );
define( 'PRC_STAFF_BYLINES_DIR', __DIR__ );
define( 'PRC_STAFF_BYLINES_VERSION', '1.1.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-plugin-activator.php
 */
function activate(): void {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-plugin-activator.php';
	Plugin_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-plugin-deactivator.php
 */
function deactivate(): void {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-plugin-deactivator.php';
	Plugin_Deactivator::deactivate();
}

register_activation_hook( __FILE__, '\PRC\Platform\Staff_Bylines\activate' );
register_deactivation_hook( __FILE__, '\PRC\Platform\Staff_Bylines\deactivate' );

/**
 * Helper utilities
 */
require plugin_dir_path( __FILE__ ) . 'includes/utils.php';

/**
 * The core bootstrap class that is used to define the hooks that initialize the various components.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-bootstrap.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_prc_staff_bylines(): void {
	$plugin = new Bootstrap();
	$plugin->run();
}
run_prc_staff_bylines();
