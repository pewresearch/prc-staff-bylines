/**
 * Inline editor placeholders for staff binding previews (no network requests).
 */

/** @type {string} 200×200 neutral gray SVG data URI. */
export const STAFF_PHOTO_PLACEHOLDER_DATA_URI =
	'data:image/svg+xml;charset=utf-8,' +
	encodeURIComponent(
		'<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200">' +
			'<rect width="200" height="200" fill="#e0e0e0"/>' +
			'<text x="100" y="105" text-anchor="middle" fill="#757575" font-family="sans-serif" font-size="14">Photo</text>' +
			'</svg>'
	);
