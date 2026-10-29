const ORCID_URL_PREFIX = /^https?:\/\/(?:www\.|sandbox\.)?orcid\.org\//i;

/**
 * Parse a raw ORCID iD or profile URL into canonical form (mirrors Orcid::parse).
 *
 * @param {unknown} raw Raw input.
 * @return {string|null} Canonical `XXXX-XXXX-XXXX-XXXX` iD, or null when invalid.
 */
export function parseOrcid(raw) {
	if (typeof raw !== 'string') {
		return null;
	}

	const value = raw
		.trim()
		.replace(ORCID_URL_PREFIX, '')
		.replace(/\/+$/, '')
		.replace(/[\s-]/g, '')
		.toUpperCase();

	if (!/^\d{15}[\dX]$/.test(value)) {
		return null;
	}

	let total = 0;
	for (const digit of value.slice(0, 15)) {
		total = (total + Number(digit)) * 2;
	}
	const result = (12 - (total % 11)) % 11;
	const expectedCheck = 10 === result ? 'X' : String(result);
	if (expectedCheck !== value[15]) {
		return null;
	}

	return value.match(/.{4}/g).join('-');
}
