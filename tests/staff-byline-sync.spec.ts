import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import { faker } from '@faker-js/faker';

test.describe('Create Staff and Verify Byline Sync', () => {
	const testTitle = faker.person.fullName();
	const testContent = faker.lorem.paragraph();

	test('Staff post can be created and published', async ({
		admin,
		editor,
		requestUtils,
	}) => {
		await admin.createNewPost({
			title: testTitle,
			content: testContent,
			postType: 'staff',
		});
		// Publish the staff post.
		await editor.publishPost();

		// Verify the staff was created via REST API.
		const staffPosts = await requestUtils.rest({
			path: '/wp/v2/staff',
			method: 'GET',
		});
		const staffPost = staffPosts?.find(
			(post: any) => post.title.rendered === testTitle
		);
		expect(staffPost).toBeDefined();
		expect(staffPost.title.rendered).toBe(testTitle);
		expect(staffPost.content.rendered).toContain(testContent);
		expect(staffPost.status).toBe('publish');
	});

	test('Matching bylines term is created when staff post is published', async ({
		requestUtils,
	}) => {
		const bylinesTerms = await requestUtils.rest({
			path: '/wp/v2/bylines',
			method: 'GET',
		});
		const bylinesTerm = bylinesTerms?.find(
			(term: any) => term.name === testTitle
		);
		expect(bylinesTerm).toBeDefined();
		expect(bylinesTerm.name).toBe(testTitle);
	});

	test('Staff post has staffInfo REST field', async ({ requestUtils }) => {
		const staffPosts = await requestUtils.rest({
			path: '/wp/v2/staff',
			method: 'GET',
		});
		const staffPost = staffPosts?.find(
			(post: any) => post.title.rendered === testTitle
		);
		expect(staffPost).toBeDefined();
		expect(staffPost.staffInfo).toBeDefined();
		expect(staffPost.staffInfo.staffName).toBe(testTitle);
	});
});
