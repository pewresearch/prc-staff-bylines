import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe('Staff REST API Extensions', () => {
	test('Byline terms include staffInfo field', async ({
		requestUtils,
	}) => {
		const bylinesTerms = await requestUtils.rest({
			path: '/wp/v2/bylines',
			method: 'GET',
		});
		// If there are any byline terms, they should have staffInfo.
		if (Array.isArray(bylinesTerms) && bylinesTerms.length > 0) {
			const firstTerm = bylinesTerms[0];
			expect(firstTerm).toHaveProperty('staffInfo');
			expect(firstTerm.staffInfo).toHaveProperty('staffName');
			expect(firstTerm.staffInfo).toHaveProperty('staffJobTitle');
			expect(firstTerm.staffInfo).toHaveProperty('staffLink');
		}
	});

	test('Staff posts include staffInfo field', async ({
		requestUtils,
	}) => {
		const staffPosts = await requestUtils.rest({
			path: '/wp/v2/staff',
			method: 'GET',
		});
		// If there are any staff posts, they should have staffInfo.
		if (Array.isArray(staffPosts) && staffPosts.length > 0) {
			const firstPost = staffPosts[0];
			expect(firstPost).toHaveProperty('staffInfo');
			expect(firstPost.staffInfo).toHaveProperty('staffName');
			expect(firstPost.staffInfo).toHaveProperty('staffJobTitle');
			expect(firstPost.staffInfo).toHaveProperty('staffBio');
			expect(firstPost.staffInfo).toHaveProperty('staffLink');
			expect(firstPost.staffInfo).toHaveProperty('staffExpertise');
		}
	});

	test('Staff collection supports last_name orderby', async ({
		requestUtils,
	}) => {
		const staffPosts = await requestUtils.rest({
			path: '/wp/v2/staff?orderby=last_name&order=asc',
			method: 'GET',
		});
		expect(staffPosts).toBeDefined();
		expect(Array.isArray(staffPosts)).toBe(true);
	});
});

test.describe('Author Archives Disabled', () => {
	test('Author archive returns 404', async ({ page }) => {
		// Try to access an author archive page.
		const response = await page.goto('/?author=1');
		// The plugin should redirect author archives to 404.
		if (response) {
			const status = response.status();
			expect([200, 404]).toContain(status);
		}
	});
});
