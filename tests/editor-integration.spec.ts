import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe('Editor Integration', () => {
	test('Staff post type appears in admin menu', async ({
		admin,
		page,
	}) => {
		await admin.visitAdminPage('/');
		// Look for the Staff menu item in the admin sidebar.
		const staffMenuItem = page.locator(
			'#adminmenu a[href*="edit.php?post_type=staff"]'
		);
		await expect(staffMenuItem).toBeVisible();
	});

	test('Staff editor loads without errors', async ({ admin, page }) => {
		await admin.createNewPost({ postType: 'staff' });
		// Verify the editor loaded.
		const editorContent = page.locator(
			'.editor-visual-editor, .block-editor'
		);
		await expect(editorContent).toBeVisible();
	});

	test('Bylines display block is available in block inserter', async ({
		admin,
		page,
	}) => {
		await admin.createNewPost({ postType: 'post' });

		// Open the block inserter.
		await page
			.getByRole('button', { name: 'Toggle block inserter' })
			.click();

		// Search for the bylines display block.
		await page
			.getByRole('searchbox', { name: 'Search' })
			.fill('Bylines Display');

		// The block should appear in search results.
		const blockResult = page.getByRole('option', {
			name: 'Bylines Display',
		});
		await expect(blockResult).toBeVisible();
	});
});
