import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe('Staff Post Type and Taxonomy Registration', () => {
	test('Staff post type is properly registered', async ({
		requestUtils,
	}) => {
		const staffPosts = await requestUtils.rest({
			path: '/wp/v2/staff',
			method: 'GET',
		});
		expect(staffPosts).toBeDefined();
		expect(Array.isArray(staffPosts)).toBe(true);
	});

	test('Bylines taxonomy is properly registered', async ({
		requestUtils,
	}) => {
		const bylinesTerms = await requestUtils.rest({
			path: '/wp/v2/bylines',
			method: 'GET',
		});
		expect(bylinesTerms).toBeDefined();
		expect(Array.isArray(bylinesTerms)).toBe(true);
	});

	test('Staff-type taxonomy is properly registered', async ({
		requestUtils,
	}) => {
		const staffTypeTerms = await requestUtils.rest({
			path: '/wp/v2/staff-type',
			method: 'GET',
		});
		expect(staffTypeTerms).toBeDefined();
		expect(Array.isArray(staffTypeTerms)).toBe(true);
	});

	test('Areas-of-expertise taxonomy is properly registered', async ({
		requestUtils,
	}) => {
		const expertiseTerms = await requestUtils.rest({
			path: '/wp/v2/areas-of-expertise',
			method: 'GET',
		});
		expect(expertiseTerms).toBeDefined();
		expect(Array.isArray(expertiseTerms)).toBe(true);
	});
});
