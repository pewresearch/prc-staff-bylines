/**
 * Editor-side overlay for prc-staff-bylines inline staff bits.
 *
 * PHP registration lives in includes/bits/class-staff-bits.php.
 */

import { __ } from '@wordpress/i18n';
import { paragraph, people, text } from '@wordpress/icons';
import { registerBlockBit } from '@prc/block-bits';

export default function registerBlockBits() {
	registerBlockBit('prc-staff-bylines/staff-name', {
		title: __('Staff Name', 'prc-staff-bylines'),
		icon: people,
	});

	registerBlockBit('prc-staff-bylines/staff-job-title', {
		title: __('Staff Job Title', 'prc-staff-bylines'),
		icon: text,
	});

	registerBlockBit('prc-staff-bylines/staff-mini-bio', {
		title: __('Staff Mini Bio', 'prc-staff-bylines'),
		icon: paragraph,
	});
}
