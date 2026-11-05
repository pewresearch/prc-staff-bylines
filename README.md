# PRC Staff Bylines

> Canonical docs: [docs/plugins/prc-staff-bylines/](../../docs/plugins/prc-staff-bylines/)

Staff profile and byline management system for the PRC Platform. Links a `staff` custom post type to a `bylines` taxonomy via the `prc/primitives` term data store (namespace `PRC\Primitives\TDS`), enabling multi-author bylines on any post type while keeping a single source of truth for each person's data.

## What it does

- Registers the `staff` post type and `bylines` taxonomy and binds them through the `prc/primitives` term data store so each staff member is addressable by either a post ID or a term ID.
- Registers supporting taxonomies: `areas-of-expertise`, `staff-type`.
- Stores bylines, acknowledgements, and display flags as post meta on any post type that declares `prc-bylines` support.
- Adds a `staffInfo` REST field to both `bylines` terms and `staff` posts, exposing a normalized staff data object to the editor and frontend consumers.
- Redirects WordPress native author archives to 404s — the `bylines` taxonomy term archive serves as the canonical author page.
- Integrates with Yoast SEO to populate `wpseo_meta_author`, `wpseo_opengraph_author_facebook`, and `wpseo_enhanced_slack_data` from the post's bylines rather than the WordPress post author.
- Adds `noindex`/`nofollow` robots directives to byline term archives for staff members with `bylineLinkEnabled` disabled.
- Registers five Gutenberg blocks for composing author displays in the editor.
- Provides a WP-CLI command for creating guest-author byline terms.

## Post types and taxonomies

| Object    | Slug                 | Notes                                                                                                  |
| --------- | -------------------- | ------------------------------------------------------------------------------------------------------ |
| Post type | `staff`              | Public, REST-enabled, no archive. Linked to `bylines` via the `prc/primitives` term data store.        |
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

| Key                    | Type      | Description                                                                                                     |
| ---------------------- | --------- | --------------------------------------------------------------------------------------------------------------- |
| `jobTitle`             | `string`  | Staff member's job title. Prefixed with "Former" for inactive staff.                                            |
| `jobTitleExtended`     | `string`  | Mini-biography sentence fragment, e.g. "is a Senior Researcher…".                                               |
| `orcid`                | `string`  | ORCID iD in `XXXX-XXXX-XXXX-XXXX` form, or `''`. Saving any other value stores `''`. See [ORCID iD](#orcid-id). |
| `_orcid_authenticated` | `string`  | Protected, not in REST. The iD that ORCID OAuth confirmed. Only the OAuth callback writes it.                   |
| `_wp_user`             | `integer` | Protected. ID of the linked WordPress user, or unset. See [Linked WordPress user](#linked-wordpress-user).      |
| `bylineLinkEnabled`    | `boolean` | Controls whether a public byline link and archive page are exposed.                                             |
| `socialProfiles`       | `array`   | Array of `{key, url}` social profile objects.                                                                   |

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
  "staffLink": "https://example.org/staff/jane-smith",
  "staffOrcid": "0000-0002-1825-0097",
  "staffOrcidAuthenticated": true
}
```

`wpUser` is a REST field on `staff` posts in the `edit` context. It holds the linked WordPress user as `{ "id": 42, "name": "Jane Smith" }`, or `{ "id": 0, "name": "" }` when there is no link. Send `{ "wpUser": { "id": 42 } }` to link and `{ "wpUser": { "id": 0 } }` to unlink. Writes need `list_users`. A user that does not exist returns `400 prc_staff_user_missing`. A user linked to another staff record returns `409 prc_staff_user_taken`.

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
| `tds_balancing_from_term`                  | Filter    | Overrides the `prc/primitives` term data store balancing for guest-author terms to prevent post creation.                                             |
| `posts_orderby`                            | Filter    | Enables `orderby=last_name` for WP_Query on the `staff` post type.                                                                                    |
| `rest_staff_collection_params`             | Filter    | Adds `last_name` to the allowed `orderby` enum on the `staff` REST collection.                                                                        |
| `pre_get_posts`                            | Action    | Excludes former staff (not in `staff`, `executive-team`, `managing-directors` staff-types) from `areas-of-expertise` and `bylines` taxonomy archives. |
| `the_title`                                | Filter    | Prefixes "FORMER: " to staff post titles in the admin only.                                                                                           |
| `prc_sitemap_supported_taxonomies`         | Filter    | Opts `bylines` into the platform XML sitemap.                                                                                                         |
| `prc_platform_pub_listing_default_args`    | Filter    | Includes `staff` post type in pub-listing queries when a search term is present.                                                                      |
| `wp_robots`                                | Filter    | Adds `noindex`/`nofollow` to byline term archives for staff with `bylineLinkEnabled = false`.                                                         |
| `wpseo_meta_author`                        | Filter    | Replaces Yoast author meta with the post's bylines string.                                                                                            |
| `wpseo_opengraph_author_facebook`          | Filter    | Same as above for Open Graph author tag.                                                                                                              |
| `wpseo_enhanced_slack_data`                | Filter    | Appends "Written by" label with bylines string to Yoast Slack sharing data.                                                                           |
| `template_redirect`                        | Action    | Sets 404 on WordPress native author archives.                                                                                                         |
| `admin_bar_menu`                           | Action    | Replaces the default "Edit Post" admin bar link with "Edit Staff" on byline term archive pages.                                                       |
| `enqueue_block_editor_assets`              | Action    | Enqueues the bylines and staff inspector sidebar panel scripts.                                                                                       |
| `admin_post_prc_orcid_authorize`           | Action    | Starts ORCID OAuth for a staff post. See [ORCID iD](#orcid-id).                                                                                       |
| `admin_post_prc_orcid_callback`            | Action    | Finishes ORCID OAuth and stores the confirmed iD.                                                                                                     |
| `render_block_core/icon`                   | Filter    | Colors and labels an `core/icon` block bound to the ORCID iD icon.                                                                                    |
| `render_block_core/paragraph`              | Filter    | Removes a paragraph bound to the ORCID iD when the staff member has no iD.                                                                            |

## WP-CLI commands

```
wp prc bylines guest-authors create --first=<first> --last=<last> [--middle=<middle>] [--dry-run]
```

Creates a `bylines` term for an external contributor who has no `staff` post. Dry-run is enabled by default; pass `--dry-run=false` to execute. Checks for an existing term before creating.

```
wp prc-staff link-users [--dry-run]
```

Links current staff records that have no user to users of the primary site that have no staff record. It matches the legacy `user_id` staff meta first, then the user's first and last name, then the display name. Names compare without case and with collapsed whitespace. It prints a table of every match, ambiguous case, and miss, and writes only exact, unique matches. A user that matches two staff records is ambiguous for both. Former staff are skipped. Run it with `--dry-run` first.

## Linked WordPress user

A staff record can link to one WordPress user, and a user can link to one staff record. The link lives once, in the `_wp_user` meta on the staff post. Staff posts live on the primary site (`PRC_PRIMARY_SITE_ID`), so every lookup switches to that site.

- `Staff_User_Link::user_id_for_staff( $staff_id )` returns the user ID or `null`.
- `Staff_User_Link::staff_id_for_user( $user_id )` returns the staff post ID or `null`. It caches the answer in the `staff_user_link` object-cache group. A cached ID is checked against the staff post's meta on read, and writes to `_wp_user` clear the entry.
- `Staff_User_Link::link( $staff_id, $user_id )` validates and writes the link. Pass `0` to unlink.
- `$staff->get_wp_user()` returns the linked `WP_User` or `false`.

Editors set the link in the **WordPress User** panel of the staff sidebar. Only users with `list_users` can change it. The user search uses the core users endpoint, so it finds only members of the primary site.

The link is a REST field (`wpUser`), not REST meta. The staff sidebar panels send every registered meta key on each save, and core re-checks the auth callback for a meta key whenever the value it receives differs from the stored value. As REST meta, the link would make saves fail for editors who cannot list users.

## ORCID iD

Staff posts store an ORCID iD in the `orcid` meta. `Orcid::parse()` accepts a bare iD, a compact iD, or an `orcid.org` profile URL. It checks the ISO 7064 Mod 11-2 checksum and stores the hyphenated form. An invalid value is stored as `''`.

An iD is authenticated when `orcid` equals `_orcid_authenticated`. Nothing stores a separate flag. When an editor changes the iD by hand, it becomes unauthenticated.

To authenticate, an editor clicks **Authenticate with ORCID** in the staff sidebar. A popup opens `admin-post.php?action=prc_orcid_authorize`, which redirects to ORCID with the `/authenticate` scope. The staff member signs in to ORCID. ORCID then redirects to `admin-post.php?action=prc_orcid_callback`. The callback checks the single-use state, exchanges the code for the iD, and writes both meta keys. It does not store the access token. The popup sends the result to the editor on the `prc-orcid-auth` BroadcastChannel and closes. It does not use `window.opener`, because the ORCID round trip can sever it.

`Orcid::can_authenticate( $staff_id )` decides who can start and finish the flow. It allows users who can edit the staff post and the WordPress user linked to it. A linked user who cannot edit the post does not see the edit link on the result page.

The flow always runs on the primary site, so the authorize URL and the redirect URI use the primary site admin URL. In the ORCID developer tools, register `admin-post.php?action=prc_orcid_callback` under the primary site admin URL as the redirect URI.

### ORCID in the welcome splash

When ORCID is configured, the plugin adds an optional `orcid` step to the `prc-wp-admin-tours` welcome splash through `prc_wp_admin_tours_setup_fields`. The step uses the `popup_connect` field type. It shows a **Connect ORCID** button that opens the same OAuth popup for the user's linked staff record, and an **I don't have an ORCID iD** link.

The step is complete when the user has no linked staff record, when the linked iD is authenticated, or when the user chose **I don't have an ORCID iD**. That choice is stored as a setup acknowledgment, so the splash does not ask again. `includes/class-orcid-setup-field.php` owns the step.

| Environment variable               | Description                                                           |
| ---------------------------------- | --------------------------------------------------------------------- |
| `PRC_PLATFORM_ORCID_CLIENT_ID`     | ORCID public API client ID. The button is disabled when empty.        |
| `PRC_PLATFORM_ORCID_CLIENT_SECRET` | ORCID public API client secret. The button is disabled when empty.    |
| `PRC_PLATFORM_ORCID_ENVIRONMENT`   | `sandbox` uses `sandbox.orcid.org`. Any other value uses `orcid.org`. |

The **ORCID iD icon** binding field (`valueToFetch: orcid-icon`) fills the `icon` attribute of a `core/icon` block with `brands/orcid` when the staff member has an iD. It is grayscale when the iD is unauthenticated, and resolves to nothing when there is no iD. A `render_block_core/icon` filter colors the mark (glyph `#a6ce39`, background `#ffffff`, round) and sets `role="img"` and `aria-label="ORCID iD"`. The plugin enables bindings on `core/icon` `icon` through `block_bindings_supported_attributes_core/icon`, because WordPress 7.1 does not.

The **ORCID iD** binding field (`valueToFetch: orcid`) renders the iD as a link. The link has the class `wp-block-prc-block-staff-context-provider__orcid` plus `is-authenticated` or `is-unauthenticated`. The `prc-staff-bylines/staff-orcid` pattern and the `taxonomy-bylines` theme template use this field.

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
$staff->orcid;           // string|false — canonical ORCID iD
$staff->orcid_authenticated; // bool — true when ORCID confirmed the stored iD
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

| File                                          | Purpose                                                                                                           |
| --------------------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| `prc-staff-bylines.php`                       | Plugin entry point. Defines constants, registers activation/deactivation hooks, boots Bootstrap.                  |
| `includes/class-bootstrap.php`                | Wires all classes and blocks to the loader, manages editor asset enqueueing.                                      |
| `includes/class-content-type.php`             | Registers the `staff` CPT, `bylines`, `areas-of-expertise`, and `staff-type` taxonomies. Registers all post meta. |
| `includes/class-staff.php`                    | `Staff` data object. Resolves staff members from post ID or term ID, with object cache support.                   |
| `includes/class-bylines.php`                  | `Bylines` data object. Fetches and formats bylines for a given post.                                              |
| `includes/class-rest-api.php`                 | Registers the `staffInfo` field on `bylines` terms and `staff` posts.                                             |
| `includes/class-orcid.php`                    | Parses and renders ORCID iDs and runs the ORCID OAuth flow.                                                       |
| `includes/class-orcid-setup-field.php`        | Adds the optional ORCID step to the welcome splash.                                                               |
| `includes/class-staff-user-link.php`          | Stores, validates, and looks up the link between a staff record and a WordPress user.                             |
| `includes/class-staff-user-matcher.php`       | Pure name matcher behind `wp prc-staff link-users`.                                                               |
| `includes/class-staff-user-link-commands.php` | WP-CLI command `wp prc-staff link-users`.                                                                         |
| `includes/class-seo.php`                      | Integrates with Yoast SEO for author meta and robots directives.                                                  |
| `includes/class-guest-author-commands.php`    | WP-CLI command for creating guest-author byline terms.                                                            |
| `includes/bylines-inspector-sidebar-panel/`   | Editor panel for managing bylines on posts.                                                                       |
| `includes/staff-inspector-sidebar-panel/`     | Editor panel for managing staff-specific meta on `staff` posts.                                                   |
| `src/bylines-query/`                          | Block: `prc-block/bylines-query`.                                                                                 |
| `src/bylines-display/`                        | Block: `prc-block/bylines-display`.                                                                               |
| `src/staff-context-provider/`                 | Block: `prc-block/staff-context-provider`.                                                                        |
| `src/staff-query/`                            | Block: `prc-block/staff-query`.                                                                                   |
| `src/staff-info/`                             | Block: `prc-block/staff-info`.                                                                                    |

## Dependencies

- `prc-platform-core` (required plugin) — provides `prc_platform_on_publish`, platform utility functions, and script-loading helpers.
- `prc/primitives` term data store (`PRC\Primitives\TDS` namespace) — used to create and maintain the `staff` post ↔ `bylines` term relationship.
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
