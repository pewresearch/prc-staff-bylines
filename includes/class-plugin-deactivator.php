<?php
/**
 * Plugin deactivator.
 *
 * @package PRC\Platform\Staff_Bylines
 */

namespace PRC\Platform\Staff_Bylines;

use DEFAULT_TECHNICAL_CONTACT;

/**
 * Plugin deactivator.
 *
 * @package PRC\Platform\Staff_Bylines
 */
class Plugin_Deactivator {
	/**
	 * Deactivate the plugin.
	 */
	public static function deactivate() {
		flush_rewrite_rules();

		wp_mail(
			DEFAULT_TECHNICAL_CONTACT,
			'PRC Staff Bylines Deactivated',
			'The PRC Staff Bylines plugin has been deactivated on ' . get_site_url()
		);
	}
}
