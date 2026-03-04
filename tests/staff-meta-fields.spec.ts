import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import { faker } from '@faker-js/faker';

test.describe('Staff Meta Fields', () => {
	const staffName = faker.person.fullName();

	test('Staff post meta fields can be set and retrieved', async ({
		admin,
		editor,
		requestUtils,
	}) => {
		// Create a staff post.
		await admin.createNewPost({
			title: staffName,
			content: 'Test bio content for staff member.',
			postType: 'staff',
		});
		await editor.publishPost();

		// Get the staff post ID.
		const staffPosts = await requestUtils.rest({
			path: '/wp/v2/staff',
			method: 'GET',
		});
		const staffPost = staffPosts?.find(
			(post: any) => post.title.rendered === staffName
		);
		expect(staffPost).toBeDefined();

		const staffPostId = staffPost.id;

		// Update staff meta via REST API.
		const updatedPost = await requestUtils.rest({
			path: `/wp/v2/staff/${staffPostId}`,
			method: 'POST',
			data: {
				meta: {
					jobTitle: 'Senior Researcher',
					jobTitleExtended:
						'a senior researcher focusing on technology at Pew Research Center',
					bylineLinkEnabled: true,
				},
			},
		});

		expect(updatedPost.meta.jobTitle).toBe('Senior Researcher');
		expect(updatedPost.meta.jobTitleExtended).toBe(
			'a senior researcher focusing on technology at Pew Research Center'
		);
		expect(updatedPost.meta.bylineLinkEnabled).toBe(true);
	});
});

test.describe('Post Bylines Meta', () => {
	test('Bylines meta field is registered on posts', async ({
		admin,
		editor,
		requestUtils,
	}) => {
		// Create a regular post.
		await admin.createNewPost({
			title: 'Bylines Meta Test Post',
			content: 'Testing bylines meta field registration.',
			postType: 'post',
		});
		await editor.publishPost();

		// Get the post.
		const posts = await requestUtils.rest({
			path: '/wp/v2/posts',
			method: 'GET',
		});
		const testPost = posts?.find(
			(post: any) => post.title.rendered === 'Bylines Meta Test Post'
		);
		expect(testPost).toBeDefined();

		// The meta field should exist (even if empty).
		expect(testPost.meta).toBeDefined();
		expect(testPost.meta).toHaveProperty('bylines');
		expect(testPost.meta).toHaveProperty('displayBylines');
	});

	test('displayBylines defaults to true', async ({
		admin,
		editor,
		requestUtils,
	}) => {
		await admin.createNewPost({
			title: 'Display Bylines Default Test',
			content: 'Testing displayBylines default value.',
			postType: 'post',
		});
		await editor.publishPost();

		const posts = await requestUtils.rest({
			path: '/wp/v2/posts',
			method: 'GET',
		});
		const testPost = posts?.find(
			(post: any) =>
				post.title.rendered === 'Display Bylines Default Test'
		);
		expect(testPost).toBeDefined();
		expect(testPost.meta.displayBylines).toBe(true);
	});
});
