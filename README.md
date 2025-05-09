# PRC Staff Bylines

PRC Staff Bylines is a comprehensive staff and bylines management system for WordPress, designed for the PRC Platform. It creates synchronized staff profiles and byline taxonomies, provides custom editor blocks, and offers an enhanced multi-author experience.

## Features

- **Custom Staff Post Type**: Manage staff profiles with job titles, extended bios, photos, and social profiles.
- **Bylines Taxonomy**: Assign multiple staff bylines to posts, supporting true multi-author content.
- **Areas of Expertise & Staff Types**: Categorize staff by expertise and type (e.g., executive, researcher).
- **REST API Integration**: Exposes staff and byline data for headless and decoupled applications.
- **Editor UI**: Custom sidebar panels for managing bylines, acknowledgements, and staff info in the block editor.
- **Blocks**:
  - **Staff Query**: Display staff filtered by type or research area.
  - **Bylines Display**: Show a post's bylines in a customizable format.
  - **Bylines Query**: Query and display bylines for the current post.
  - **Staff Info**: Display detailed staff information.
  - **Staff Context Provider**: Pass staff context to inner blocks for advanced layouts.
- **SEO & Permalinks**: Integrates with Yoast SEO and customizes staff archive links.
- **Security & Privacy**: Includes features to protect sensitive staff ("maelstrom" safety net for regional/country-based restrictions).

## Installation

1. Ensure you have [prc-platform-core](../prc-platform-core) installed and activated.
2. Copy or symlink this plugin to your WordPress `plugins` or `mu-plugins` directory.
3. Activate **PRC Staff Bylines** from the WordPress admin.
4. Run `npm install` and `npm run build` in this directory to build block assets.

## Usage

- **Managing Staff**: Add and edit staff profiles under the "Staff" menu in the WordPress admin.
- **Assigning Bylines**: In the post editor, use the "Bylines" panel to assign staff bylines and acknowledgements to posts.
- **Custom Blocks**: Insert the provided blocks into posts or templates to display staff lists, bylines, or detailed staff info.
- **REST API**: Staff and byline data are available via the WordPress REST API for integration with decoupled frontends.

## Available Blocks

- **Staff Query** (`prc-block/staff-query`): Query and display staff by type or research area.
- **Bylines Display** (`prc-block/bylines-display`): Display a post's bylines with a customizable prefix (e.g., "By").
- **Bylines Query** (`prc-block/bylines-query`): Query and display bylines for the current post.
- **Staff Info** (`prc-block/staff-info`): Show detailed information for a staff member.
- **Staff Context Provider** (`prc-block/staff-context-provider`): Provide staff context to nested blocks for advanced layouts.

## Development

- **Requirements**: Node.js, npm, and Composer (for advanced use).
- **Scripts**:
  - `npm run build`: Build block assets.
  - `npm test`: Run Playwright tests.
  - `npm run test:env:start`: Start the local WordPress environment.
  - `npm run test:env:stop`: Stop the local environment.
  - `npm run test:env:clean`: Clean and restart the environment.
  - `npm run test:env:destroy`: Destroy the environment.
- **Local Environment**: Uses `.wp-env.json` for local development with required plugins and theme.

## License

GPL-2.0-or-later. See the license header in `prc-staff-bylines.php`.

## Credits

Developed by Seth Rubenstein for Pew Research Center.

