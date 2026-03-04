/**
 * WordPress Dependencies
 */
import apiFetch from '@wordpress/api-fetch';

interface StaffInfo {
	staffName: string;
	staffJobTitle: string;
	staffImage: false | Record<string, unknown>;
	staffTwitter: string | null;
	staffExpertise: Array<Record<string, unknown>>;
	staffBio: string;
	staffMiniBio: string;
	staffLink: string | false;
	staffJobTitleExtended: string;
	staffBioShort: string;
	[key: string]: unknown;
}

/**
 * Gets a single staff member by ID from PRC primary site.
 */
export function fetchStaff(
	staffId: number,
	valueToFetch: string
): Promise<unknown> {
	const endpointUrl = `${window.location.origin}/wp-json/wp/v2/staff/${staffId}`;

	return new Promise((resolve) => {
		apiFetch({
			url: endpointUrl,
		}).then((post: any) => {
			const { staffInfo } = post as { staffInfo: StaffInfo };
			const value = staffInfo[valueToFetch];
			return resolve(value);
		});
	});
}

/**
 * Gets a single value from a byline term by termId from the current PRC site.
 */
export function fetchByline(
	termId: number,
	valueToFetch: string
): Promise<unknown> {
	return new Promise((resolve) => {
		apiFetch({
			path: `/wp/v2/bylines/${termId}`,
		}).then((byline: any) => {
			const { staffInfo } = byline as { staffInfo: StaffInfo };
			const value = staffInfo[valueToFetch];
			return resolve(value);
		});
	});
}
