<?php
declare(strict_types=1);
/**
 * Plugin activator.
 *
 * @package PRC\Platform\Staff_Bylines
 */

namespace PRC\Platform\Staff_Bylines;

use DEFAULT_TECHNICAL_CONTACT;

/**
 * Plugin activator.
 *
 * @package PRC\Platform\Staff_Bylines
 */
class Plugin_Activator {
	/**
	 * Activate the plugin.
	 */
	public static function activate(): void {
		flush_rewrite_rules();

		wp_mail(
			DEFAULT_TECHNICAL_CONTACT,
			'PRC Staff Bylines Activated',
			'The PRC Staff Bylines plugin has been activated on ' . get_site_url()
		);
	}
}
