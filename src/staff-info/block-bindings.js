/**
 * Client-side registration for prc-platform/staff-info bindings panel discovery.
 */
import { defineBindingSource } from '@prc/functions';
import { __, sprintf } from '@wordpress/i18n';
import { store as coreStore } from '@wordpress/core-data';

import { STAFF_INFO_BINDING_FIELDS } from './fields';
import { STAFF_PHOTO_PLACEHOLDER_DATA_URI } from './placeholders';

const PLACEHOLDERS = {
	name: __('Staff Name', 'prc-staff-bylines'),
	job_title: __('Job Title', 'prc-staff-bylines'),
	job_title_extended: __(
		'a research analyst focusing on social and demographic research at Pew Research Center',
		'prc-staff-bylines'
	),
	mini_bio: __(
		'Staff mini bio placeholder for editor preview.',
		'prc-staff-bylines'
	),
	bio: __('Staff bio placeholder for editor preview.', 'prc-staff-bylines'),
	expertise: __(
		'<span class="wp-block-prc-block-staff-context-provider__expertise-label">Expertise:</span> <a class="wp-block-prc-block-staff-context-provider__expertise-link" href="#">Expertise</a>',
		'prc-staff-bylines'
	),
	name_and_job_title: __(
		'<a href="#">Jane Doe</a>, Senior Researcher',
		'prc-staff-bylines'
	),
	photo: STAFF_PHOTO_PLACEHOLDER_DATA_URI,
	'photo-full': '#',
	'photo-full-download-text': __('Download photo', 'prc-staff-bylines'),
};

/**
 * Format expertise terms as HTML for editor preview.
 *
 * @param {Array<{url?: string, label?: string}>} expertise Expertise terms.
 * @return {string}
 */
function formatExpertiseHtml(expertise) {
	if (!Array.isArray(expertise) || expertise.length === 0) {
		return '';
	}

	const label = `<span class="wp-block-prc-block-staff-context-provider__expertise-label">${__(
		'Expertise:',
		'prc-staff-bylines'
	)}</span>`;
	const links = expertise
		.map((term, index) => {
			const sep = index < expertise.length - 1 ? ', ' : '';
			const url = term?.url ?? '#';
			const termLabel = term?.label ?? '';
			return `<a class="wp-block-prc-block-staff-context-provider__expertise-link" href="${url}">${termLabel}</a>${sep}`;
		})
		.join('');

	return `${label} ${links}`;
}

/**
 * Format name and job title as HTML for editor preview.
 *
 * @param {Record<string, unknown>} staffInfo Staff REST staffInfo object.
 * @return {string|null}
 */
function formatNameAndJobTitle(staffInfo) {
	const name = staffInfo?.staffName;
	const jobTitle = staffInfo?.staffJobTitle;
	if (!name || !jobTitle) {
		return null;
	}

	const link = staffInfo?.staffLink;
	if (link) {
		return `<strong><a href="${link}">${name}</a></strong>, ${jobTitle}`;
	}

	return `<strong>${name}</strong>, ${jobTitle}`;
}

/**
 * Resolve a live editor value from a staff REST record.
 *
 * @param {Record<string, unknown>} staffInfo Staff REST staffInfo object.
 * @param {Record<string, unknown>} sourceArgs Binding source args.
 * @param {string} attributeName Bound block attribute name.
 * @return {string|null}
 */
function resolveLiveValue(staffInfo, sourceArgs, attributeName) {
	if (!staffInfo) {
		return null;
	}

	const valueToFetch = sourceArgs?.valueToFetch;
	if (!valueToFetch || typeof valueToFetch !== 'string') {
		return null;
	}

	const photo =
		staffInfo.staffImage && typeof staffInfo.staffImage === 'object'
			? staffInfo.staffImage
			: null;

	if ('photo' === valueToFetch) {
		if ('url' === attributeName && photo?.thumbnail?.[0]) {
			return photo.thumbnail[0];
		}
		if ('title' === attributeName && staffInfo.staffName) {
			return sprintf(
				__('Photo of %s', 'prc-staff-bylines'),
				staffInfo.staffName
			);
		}
		if ('alt' === attributeName && staffInfo.staffName) {
			return sprintf(
				__("Download %s's photo", 'prc-staff-bylines'),
				staffInfo.staffName
			);
		}
		return null;
	}

	if ('photo-full' === valueToFetch && 'url' === attributeName) {
		return photo?.full?.[0] ?? null;
	}

	if (
		'photo-full-download-text' === valueToFetch &&
		'text' === attributeName &&
		staffInfo.staffName
	) {
		return sprintf(
			__("Download %s's photo", 'prc-staff-bylines'),
			staffInfo.staffName
		);
	}

	if ('content' !== attributeName) {
		return null;
	}

	if ('name' === valueToFetch && staffInfo.staffName) {
		if (sourceArgs.outputLink && staffInfo.staffLink) {
			return `<a href="${staffInfo.staffLink}">${staffInfo.staffName}</a>`;
		}
		return staffInfo.staffName;
	}

	if ('job_title' === valueToFetch && staffInfo.staffJobTitle) {
		return staffInfo.staffJobTitle;
	}

	if (
		'job_title_extended' === valueToFetch &&
		staffInfo.staffJobTitleExtended
	) {
		return staffInfo.staffJobTitleExtended;
	}

	if ('mini_bio' === valueToFetch && staffInfo.staffMiniBio) {
		return staffInfo.staffMiniBio;
	}

	if ('bio' === valueToFetch && staffInfo.staffBio) {
		return staffInfo.staffBio;
	}

	if ('expertise' === valueToFetch) {
		const expertiseHtml = formatExpertiseHtml(staffInfo.staffExpertise);
		return expertiseHtml || null;
	}

	if ('name_and_job_title' === valueToFetch) {
		return formatNameAndJobTitle(staffInfo);
	}

	return null;
}

/**
 * Resolve editor preview values for a bound attribute.
 *
 * @param {Record<string, unknown>} sourceArgs Binding source args.
 * @param {string} attributeName Bound block attribute name.
 * @return {string|null}
 */
function resolvePreviewValue(sourceArgs, attributeName) {
	const valueToFetch = sourceArgs?.valueToFetch;
	if (!valueToFetch || typeof valueToFetch !== 'string') {
		return null;
	}

	if ('photo' === valueToFetch) {
		if ('url' === attributeName) {
			return PLACEHOLDERS.photo;
		}
		if ('title' === attributeName) {
			return __('Photo of Staff Name', 'prc-staff-bylines');
		}
		if ('alt' === attributeName) {
			return __('Download Staff Name photo', 'prc-staff-bylines');
		}
		return null;
	}

	if ('photo-full' === valueToFetch && 'url' === attributeName) {
		return PLACEHOLDERS['photo-full'];
	}

	if (
		'photo-full-download-text' === valueToFetch &&
		'text' === attributeName
	) {
		return PLACEHOLDERS['photo-full-download-text'];
	}

	if ('content' !== attributeName) {
		return null;
	}

	const base = PLACEHOLDERS[valueToFetch] ?? PLACEHOLDERS.name;

	if (sourceArgs.outputLink && 'name' === valueToFetch) {
		return `<a href="#">${PLACEHOLDERS.name}</a>`;
	}

	return base;
}

defineBindingSource({
	name: 'prc-platform/staff-info',
	label: __('Staff Info API', 'prc-staff-bylines'),
	usesContext: ['staffId'],
	fields: STAFF_INFO_BINDING_FIELDS,
	getValues({ select, context, bindings }) {
		const staffId = context?.staffId;
		let staffInfo = null;

		if (staffId && select) {
			const record = select(coreStore).getEntityRecord(
				'postType',
				'staff',
				staffId
			);
			staffInfo = record?.staffInfo ?? null;
		}

		const values = {};
		for (const [attributeName, binding] of Object.entries(bindings ?? {})) {
			const sourceArgs = binding?.args ?? {};
			const live = staffInfo
				? resolveLiveValue(staffInfo, sourceArgs, attributeName)
				: null;
			const preview =
				live ?? resolvePreviewValue(sourceArgs, attributeName);

			if (null !== preview) {
				values[attributeName] = preview;
			}
		}

		return values;
	},
	canUserEditValue() {
		return false;
	},
});
