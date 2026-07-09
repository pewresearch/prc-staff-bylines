/**
 * Shared field manifest for prc-platform/staff-info bindings.
 *
 * Labels mirror existing variation inserter titles (flat panel list).
 */
import { __ } from '@wordpress/i18n';

/** @type {Array<{label: string, type: string, args: Record<string, unknown>}>} */
export const STAFF_INFO_BINDING_FIELDS = [
	{
		label: __('Name', 'prc-staff-bylines'),
		type: 'string',
		args: { valueToFetch: 'name' },
	},
	{
		label: __('Name (linked)', 'prc-staff-bylines'),
		type: 'string',
		args: { valueToFetch: 'name', outputLink: true },
	},
	{
		label: __('Job Title', 'prc-staff-bylines'),
		type: 'string',
		args: { valueToFetch: 'job_title' },
	},
	{
		label: __('Job Title Extended', 'prc-staff-bylines'),
		type: 'string',
		args: { valueToFetch: 'job_title_extended' },
	},
	{
		label: __('Mini Bio', 'prc-staff-bylines'),
		type: 'string',
		args: { valueToFetch: 'mini_bio' },
	},
	{
		label: __('Bio', 'prc-staff-bylines'),
		type: 'string',
		args: { valueToFetch: 'bio' },
	},
	{
		label: __('Expertise', 'prc-staff-bylines'),
		type: 'string',
		args: { valueToFetch: 'expertise' },
	},
	{
		label: __('Name and Job Title', 'prc-staff-bylines'),
		type: 'string',
		args: { valueToFetch: 'name_and_job_title' },
	},
	{
		label: __('Photo URL', 'prc-staff-bylines'),
		type: 'string',
		args: { valueToFetch: 'photo' },
	},
	{
		label: __('Photo Title', 'prc-staff-bylines'),
		type: 'string',
		args: { valueToFetch: 'photo' },
	},
	{
		label: __('Photo Alt', 'prc-staff-bylines'),
		type: 'string',
		args: { valueToFetch: 'photo' },
	},
	{
		label: __('Download Photo Text', 'prc-staff-bylines'),
		type: 'string',
		args: { valueToFetch: 'photo-full-download-text' },
	},
	{
		label: __('Download Photo URL', 'prc-staff-bylines'),
		type: 'string',
		args: { valueToFetch: 'photo-full' },
	},
];

/**
 * PHP `fields` metadata mirror (label / type / args triples).
 *
 * @return {Array<{label: string, type: string, args: Record<string, unknown>}>}
 */
export function getStaffInfoBindingFieldsForPhp() {
	return STAFF_INFO_BINDING_FIELDS.map((field) => ({
		label: field.label,
		type: field.type,
		args: field.args,
	}));
}
