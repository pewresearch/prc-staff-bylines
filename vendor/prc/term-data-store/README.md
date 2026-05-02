# prc/term-data-store

![Tests](https://github.com/pewresearch/term-data-store/actions/workflows/test.yml/badge.svg)

Establishes 1:1 synced relationships between WordPress taxonomy terms and custom post type posts. Forked from [10up/term-data-store](https://github.com/10up/term-data-store) (original author: John P. Bloch), modified to enforce stronger ID-based relationships via post and term meta and to optionally rewrite post permalinks to the related term archive.

## Install

```bash
composer require prc/term-data-store
```

The library is loaded via Composer's `autoload.files`, so the `PRC\TDS\*` functions are available as soon as the consuming plugin or theme loads `vendor/autoload.php` (or [Jetpack Autoloader](https://github.com/Automattic/jetpack-autoloader)).

## What it does

When a relationship is registered between a post type and a taxonomy:

- Creating a **term** automatically creates a matching **post** of the related post type.
- Creating a **post** automatically creates a matching **term** in the related taxonomy.
- Each entity stores the other's ID in meta (`tds_post_id` on the term, `tds_term_id` on the post), guaranteeing a persistent, queryable link even when names or slugs change.
- Hierarchical relationships are mirrored: a child term gets a child post (and vice versa).
- Optionally rewrites post permalinks to point to the related term archive.

## Core functions

```php
use function PRC\TDS\add_relationship;
use function PRC\TDS\get_related_post;
use function PRC\TDS\get_related_term;

add_relationship( 'my-post-type', 'my-taxonomy' );

$post = get_related_post( $term_id, 'my-taxonomy' );

$term = get_related_term( $post_id );
```

## Usage example

```php
use function PRC\TDS\add_relationship;

// Call after both the post type and taxonomy are registered.
add_action( 'init', function () {
    add_relationship( 'research-team', 'researcher' );
}, 20 );
```

Each `researcher` term will then have a corresponding `research-team` post, and vice versa.

To register the relationship without permalink rewrites, pass `false` as the third argument:

```php
add_relationship( 'research-team', 'researcher', false );
```

## Migrating from `TDS\`

This fork moved the public namespace from `TDS\` (upstream 10up) to `PRC\TDS\`. Update call sites and `use` statements:

```diff
- use TDS;
- TDS\add_relationship( 'research-team', 'researcher' );
+ use function PRC\TDS\add_relationship;
+ add_relationship( 'research-team', 'researcher' );
```

The function names, signatures, hooks (`tds_balancing_*` filters), and meta keys (`tds_post_id`, `tds_term_id`) are unchanged.

## Notes

- `add_relationship()` must be called **after** both the post type and taxonomy are registered.
- Throws `Invalid_Input_Exception` if either the post type or taxonomy does not exist at call time, or if either is already part of another relationship.
- Permalink rewriting is enabled by default; pass `false` as the third argument to `add_relationship()` to disable it.
- Deleting a post deletes the related term and vice versa, via `before_delete_post` and `pre_delete_term` hooks.

## Development & tests

Tests run inside a [`@wordpress/env`](https://www.npmjs.com/package/@wordpress/env) container so the integration suite has a real WordPress to work against.

```bash
composer install
npm install
npm run env:start
npm run env:install-tests   # one-time
npm test                     # unit + integration
```

## License

GPL-2.0-or-later. Original 10up code distributed under MIT (preserved in [`LICENSE`](LICENSE)).
