# PRC Staff Bylines

> Canonical docs: [docs/plugins/prc-staff-bylines/](../../docs/plugins/prc-staff-bylines/)

Staff profile and byline management system for the PRC Platform. Links a `staff` custom post type to a `bylines` taxonomy via `[prc/term-data-store](https://github.com/pewresearch/term-data-store)` (namespace `PRC\TDS`), enabling multi-author bylines on any post type while keeping a single source of truth for each person's data.

## What it does

- Registers the `staff` post type and `bylines` taxonomy and binds them through `prc/term-data-store` so each staff member is addressable by either a post ID or a term ID.
- Registers supporting taxonomies: `areas-of-expertise`, `staff-type`.
- Stores bylines, acknowledgements, and display flags as post meta on any post type that declares `prc-bylines` support.
- Adds a `staffInfo` REST field to both `bylines` terms and `staff` posts, exposing a normalized staff data object to the editor and frontend consumers.
- Redirects WordPress native author archives to 404s — the `bylines` taxonomy term archive serves as the canonical author page.
- Applies Maelstrom protection: on publish, strips bylines for staff whose `_maelstrom` meta contains restricted region/country terms matching the post's taxonomy assignments.
- Integrates with Yoast SEO to populate `wpseo_meta_author`, `wpseo_opengraph_author_facebook`, and `wpseo_enhanced_slack_data` from the post's bylines rather than the WordPress post author.
- Adds `noindex`/`nofollow` robots directives to byline term archives for staff members with `bylineLinkEnabled` disabled.
- Registers five Gutenberg blocks for composing author displays in the editor.
- Provides a WP-CLI command for creating guest-author byline terms.

## Post types and taxonomies

| Object    | Slug                 | Notes                                                                                                  |
| --------- | -------------------- | ------------------------------------------------------------------------------------------------------ |
| Post type | `staff`              | Public, REST-enabled, no archive. Linked to `bylines` via `prc/term-data-store`.                       |
| Taxonomy  | `bylines`            | Non-hierarchical. Slug rewrite: `/staff/{slug}`. Applied to all post types with `prc-bylines` support. |
| Taxonomy  | `areas-of-expertise` | Hierarchical. Slug rewrite: `/expertise/{slug}`. Staff only.                                           |
| Taxonomy  | `staff-type`         | Hierarchical. Used to distinguish current staff from former staff. Staff only.                         |

### Enabling bylines on a post type

The `bylines` taxonomy and its meta fields are automatically applied to any post type that supports `prc-bylines`. `post` is opted in by default.

```php
// Add bylines support to a custom post type.
add_post_type_support( 'my-post-type', 'prc-bylines' );

// Legacy filter (still honored, prefer add_post_type_support).
add_filter( 'prc_platform__bylines_enabled_post_types', function( $types ) {
    $types[] = 'my-post-type';
    return $types;
} );
```

## Post meta

### On `staff` posts

| Key                 | Type      | Description                                                                              |
| ------------------- | --------- | ---------------------------------------------------------------------------------------- |
| `jobTitle`          | `string`  | Staff member's job title. Prefixed with "Former" for inactive staff.                     |
| `jobTitleExtended`  | `string`  | Mini-biography sentence fragment, e.g. "is a Senior Researcher…".                        |
| `bylineLinkEnabled` | `boolean` | Controls whether a public byline link and archive page are exposed.                      |
| `socialProfiles`    | `array`   | Array of `{key, url}` social profile objects.                                            |
| `_maelstrom`        | `object`  | `{enabled: bool, restricted: string[]}` — staff safety net. See [Maelstrom](#maelstrom). |

### On bylines-enabled post types

| Key                | Type      | Description                                                              |
| ------------------ | --------- | ------------------------------------------------------------------------ |
| `bylines`          | `array`   | Ordered array of `{key, termId}` objects referencing `bylines` term IDs. |
| `acknowledgements` | `array`   | Same shape as `bylines`. Tracks acknowledgement credits separately.      |
| `displayBylines`   | `boolean` | Defaults `true`. When `false`, bylines blocks suppress output.           |

## REST API

### Extended fields

`staffInfo` is registered as a REST field on both the `bylines` taxonomy and the `staff` post type via `register_rest_field`.

**Endpoints:**

- `GET /wp-json/wp/v2/bylines/<id>` — includes `staffInfo`
- `GET /wp-json/wp/v2/staff/<id>` — includes `staffInfo`

`**staffInfo` shape:\*\*

```json
{
  "staffName": "Jane Smith",
  "staffJobTitle": "Senior Researcher",
  "staffJobTitleExtended": "a Senior Researcher focusing on...",
  "staffImage": { "full": [...], "thumbnail": [...] },
  "staffExpertise": [{ "url": "...", "label": "...", "slug": "..." }],
  "staffBio": "<p>...</p>",
  "staffBioShort": "<a href=\"...\">Jane Smith</a> is a Senior Researcher...",
  "staffLink": "https://pewresearch.org/staff/jane-smith"
}
```

**Collection ordering:**

The `staff` REST collection accepts `orderby=last_name` in addition to the standard WP params. This is handled by `Content_Type::filter_add_rest_orderby_params` and `Content_Type::orderby_last_name`.

## Blocks

| Block name                         | Title                  | Description                                                                                        |
| ---------------------------------- | ---------------------- | -------------------------------------------------------------------------------------------------- |
| `prc-block/bylines-query`          | Bylines Query          | InnerBlocks container that queries a post's bylines. Accepts `postId` context.                     |
| `prc-block/bylines-display`        | Bylines Display        | Renders the byline list as "By Author 1, Author 2, and Author 3." Configurable prefix.             |
| `prc-block/staff-context-provider` | Staff Context Provider | Wraps inner blocks, resolving a staff member by `staffSlug` and passing data via block context.    |
| `prc-block/staff-query`            | Staff Query            | Queries the `staff` post type filtered by `staffType` and `researchArea`. For staff listing pages. |
| `prc-block/staff-info`             | Staff Info             | Displays resolved staff member data. Intended as an inner block of Staff Context Provider.         |

### Editor sidebar panels

Two scripts are enqueued via `enqueue_block_editor_assets`:

- `**bylines-inspector-sidebar-panel`\*\* — injected on all `prc-bylines`-enabled post types. Provides the bylines picker UI.
- `**staff-inspector-sidebar-panel`\*\* — injected on the `staff` post type only. Provides staff-specific meta fields.

## Filters / hooks

| Hook                                       | Direction | Description                                                                                                                                           |
| ------------------------------------------ | --------- | ----------------------------------------------------------------------------------------------------------------------------------------------------- |
| `prc_platform__bylines_enabled_post_types` | Filter    | Append post type slugs to opt them into the bylines system. Prefer `add_post_type_support( $pt, 'prc-bylines' )` instead.                             |
| `tds_balancing_from_term`                  | Filter    | Overrides `prc/term-data-store` balancing for guest-author terms to prevent post creation.                                                            |
| `posts_orderby`                            | Filter    | Enables `orderby=last_name` for WP_Query on the `staff` post type.                                                                                    |
| `rest_staff_collection_params`             | Filter    | Adds `last_name` to the allowed `orderby` enum on the `staff` REST collection.                                                                        |
| `pre_get_posts`                            | Action    | Excludes former staff (not in `staff`, `executive-team`, `managing-directors` staff-types) from `areas-of-expertise` and `bylines` taxonomy archives. |
| `the_title`                                | Filter    | Prefixes "FORMER: " to staff post titles in the admin only.                                                                                           |
| `prc_sitemap_supported_taxonomies`         | Filter    | Opts `bylines` into the platform XML sitemap.                                                                                                         |
| `prc_platform_pub_listing_default_args`    | Filter    | Includes `staff` post type in pub-listing queries when a search term is present.                                                                      |
| `prc_platform_on_publish`                  | Action    | Triggers Maelstrom enforcement on post publish.                                                                                                       |
| `wp_robots`                                | Filter    | Adds `noindex`/`nofollow` to byline term archives for staff with `bylineLinkEnabled = false`.                                                         |
| `wpseo_meta_author`                        | Filter    | Replaces Yoast author meta with the post's bylines string.                                                                                            |
| `wpseo_opengraph_author_facebook`          | Filter    | Same as above for Open Graph author tag.                                                                                                              |
| `wpseo_enhanced_slack_data`                | Filter    | Appends "Written by" label with bylines string to Yoast Slack sharing data.                                                                           |
| `template_redirect`                        | Action    | Sets 404 on WordPress native author archives.                                                                                                         |
| `admin_bar_menu`                           | Action    | Replaces the default "Edit Post" admin bar link with "Edit Staff" on byline term archive pages.                                                       |
| `enqueue_block_editor_assets`              | Action    | Enqueues the bylines and staff inspector sidebar panel scripts.                                                                                       |

## WP-CLI commands

```
wp prc bylines guest-authors create --first=<first> --last=<last> [--middle=<middle>] [--dry-run]
```

Creates a `bylines` term for an external contributor who has no `staff` post. Dry-run is enabled by default; pass `--dry-run=false` to execute. Checks for an existing term before creating.

## Maelstrom

`_maelstrom` is a staff-level safety net that automatically removes a staff member's byline from a post at publish time if the post is tagged with regions or countries the staff member has marked as restricted. This is intentionally obfuscated in the codebase to reduce exposure of the feature's purpose.

Configure via the `_maelstrom` post meta on the `staff` post:

```json
{
	"enabled": false,
	"restricted": ["Middle East & North Africa", "China"]
}
```

The `enabled` flag is computed at publish time based on whether the post's `regions-countries` terms overlap with `restricted`. Do not rely on the stored `enabled` value at rest.

## The `Staff` object

`Staff` is the primary data accessor, resolving a unified staff member from either a post ID or a bylines term ID. It caches hydrated data in the object cache (`staff_data` group, 1-hour TTL) for unauthenticated requests.

```php
// From a staff post ID.
$staff = new \PRC\Platform\Staff_Bylines\Staff( $post_id );

// From a bylines term ID.
$staff = new \PRC\Platform\Staff_Bylines\Staff( false, $term_id );

$staff->name;            // string
$staff->job_title;       // string — prepended "Former" if not currently employed
$staff->link;            // string|false — false if bylineLinkEnabled is off
$staff->is_currently_employed; // bool
$staff->photo;           // ['full' => [...], 'thumbnail' => [...]] | false
$staff->expertise;       // [['url'=>'...','label'=>'...','slug'=>'...']] | false
$staff->social_profiles; // array
$staff->bio;             // string (filtered post content)
$staff->mini_bio;        // string (linked name + job_title_extended sentence)
```

For guest contributors (byline terms with no linked `staff` post), `set_guest()` is called instead, populating the same interface with defaults.

## The `Bylines` object

```php
$bylines = new \PRC\Platform\Staff_Bylines\Bylines( $post_id );

$bylines->should_display; // bool — reads displayBylines post meta
$bylines->bylines;        // array keyed by term ID, or WP_Error if none set

// Format options:
$bylines->format( 'array' );  // raw keyed array of staff data
$bylines->format( 'string' ); // "Jane Smith, John Doe, and Alex Lee"
$bylines->format( 'html' );   // same with <a> or <span> per-author, comma/and separators
```

## Key files

| File                                        | Purpose                                                                                                           |
| ------------------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| `prc-staff-bylines.php`                     | Plugin entry point. Defines constants, registers activation/deactivation hooks, boots Bootstrap.                  |
| `includes/class-bootstrap.php`              | Wires all classes and blocks to the loader, manages editor asset enqueueing.                                      |
| `includes/class-content-type.php`           | Registers the `staff` CPT, `bylines`, `areas-of-expertise`, and `staff-type` taxonomies. Registers all post meta. |
| `includes/class-staff.php`                  | `Staff` data object. Resolves staff members from post ID or term ID, with object cache support.                   |
| `includes/class-bylines.php`                | `Bylines` data object. Fetches and formats bylines for a given post.                                              |
| `includes/class-rest-api.php`               | Registers the `staffInfo` field on `bylines` terms and `staff` posts.                                             |
| `includes/class-seo.php`                    | Integrates with Yoast SEO for author meta and robots directives.                                                  |
| `includes/class-maelstrom.php`              | Enforces staff safety net on publish via `prc_platform_on_publish`.                                               |
| `includes/class-guest-author-commands.php`  | WP-CLI command for creating guest-author byline terms.                                                            |
| `includes/bylines-inspector-sidebar-panel/` | Editor panel for managing bylines on posts.                                                                       |
| `includes/staff-inspector-sidebar-panel/`   | Editor panel for managing staff-specific meta on `staff` posts.                                                   |
| `src/bylines-query/`                        | Block: `prc-block/bylines-query`.                                                                                 |
| `src/bylines-display/`                      | Block: `prc-block/bylines-display`.                                                                               |
| `src/staff-context-provider/`               | Block: `prc-block/staff-context-provider`.                                                                        |
| `src/staff-query/`                          | Block: `prc-block/staff-query`.                                                                                   |
| `src/staff-info/`                           | Block: `prc-block/staff-info`.                                                                                    |

## Dependencies

- `prc-platform-core` (required plugin) — provides `prc_platform_on_publish`, platform utility functions, and script-loading helpers.
- `[prc/term-data-store](https://github.com/pewresearch/term-data-store)` (`PRC\TDS` namespace) — used to create and maintain the `staff` post ↔ `bylines` term relationship.
- Yoast SEO — SEO class hooks are conditional on Yoast being active.

## Development

```bash
# Build all blocks
npm run build -w @prc/staff-bylines

# Watch mode
npm run start -w @prc/staff-bylines

# Run Playwright e2e tests (from monorepo root; VIP dev-env + Playwright are centralized)
npm run vip:start
npm test -- tests/prc-staff-bylines/e2e/
```

Specs live at `tests/prc-staff-bylines/e2e/` (at the monorepo root) and cover content-type registration, REST API, staff meta fields, byline sync, and editor integration.
