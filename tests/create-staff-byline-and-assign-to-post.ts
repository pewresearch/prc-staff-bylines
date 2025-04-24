import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import { faker } from '@faker-js/faker';

test.describe('Create Staff Byline and Assign to Post', () => {
	const testTitle = faker.person.fullName();
	const testContent = faker.lorem.paragraph();

	test('Ensure staff post type is properly registered', async ({
		requestUtils,
	}) => {
		const staffPosts = await requestUtils.rest({
			path: '/wp/v2/staff',
			method: 'GET',
		});
		expect(staffPosts).toBeDefined();
	});

	test('Ensure bylines taxonomy is properly registered', async ({
		requestUtils,
	}) => {
		const bylinesTerms = await requestUtils.rest({
			path: '/wp/v2/bylines',
			method: 'GET',
		});
		expect(bylinesTerms).toBeDefined();
	});

	test('Staff post created', async ({ admin, editor, requestUtils }) => {
		await admin.createNewPost({
			title: testTitle,
			content: testContent,
			postType: 'staff',
		});
		// Publish the staff
		await editor.publishPost();

		// Get the created staff via REST API
		const staffPosts = await requestUtils.rest({
			path: '/wp/v2/staff',
			method: 'GET',
		});
		// Get the first item out of the staffPosts array
		const staffPost = staffPosts?.[0];
		// Verify the staff was created with correct title and content
		expect(staffPost.title.rendered).toBe(testTitle);
		expect(staffPost.content.rendered).toContain(testContent);
	});

	test('Matching bylines term created with staff post', async ({
		requestUtils,
	}) => {
		const bylinesTerms = await requestUtils.rest({
			path: '/wp/v2/bylines',
			method: 'GET',
		});
		// Get the first item out of the bylinesTerms array
		const bylinesTerm = bylinesTerms?.[0];
		// Verify the bylines term was created with correct title and content
		expect(bylinesTerm.name).toBe(testTitle);
	});

	// test('Publish new post with bylines term', async ({
	// 	admin,
	// 	editor,
	// 	page,
	// 	requestUtils,
	// }) => {
	// 	await admin.createNewPost({
	// 		title: 'Test Post',
	// 		content: 'This is a test post',
	// 		postType: 'post',
	// 	});

	// 	// Add the byline term to the post...

	// 	// Publish the posts.
	// 	await editor.publishPost();

	// 	// Confirm the post has a bylines term in the rest api
	// 	const testPosts = await requestUtils.rest({
	// 		path: '/wp/v2/posts',
	// 		method: 'GET',
	// 	});
	// 	// Get the first item out of the testPosts array
	// 	const testPost = testPosts?.[0];
	// 	// Verify the post has a bylines term in the rest api
	// 	expect(testPost.bylines).toHaveLength(1);
	// 	// Take a screenshot of the post
	// 	const today = new Date();
	// 	// This gives 'YYYY-MM-DD' format.
	// 	const formattedDate = today.toISOString().split('T')[0];
	// 	await page.screenshot({
	// 		path: `tests/screenshots/post-${formattedDate}.png`,
	// 	});
	// });
});
