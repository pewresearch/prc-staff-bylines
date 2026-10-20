<?php
/**
 * Byline meta to taxonomy sync helpers.
 *
 * @package PRC\Platform\Staff_Bylines
 */

declare(strict_types=1);

namespace PRC\Platform\Staff_Bylines;

/**
 * Public helpers that keep the `bylines` / `acknowledgements` post meta and the
 * `bylines` taxonomy in step. Post meta is the source of truth; the taxonomy
 * powers staff archive pages.
 */
class Byline_Sync {

	const META_BYLINES          = 'bylines';
	const META_ACKNOWLEDGEMENTS = 'acknowledgements';

	/**
	 * Read the stored { key, termId } rows for one meta key.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Either META_BYLINES or META_ACKNOWLEDGEMENTS.
	 * @return array<int,array{key:string,termId:int}>
	 */
	public static function get_rows( int $post_id, string $meta_key ): array {
		$raw = get_post_meta( $post_id, $meta_key, true );
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$rows = array();
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['termId'] ) ) {
				continue;
			}
			$term_id = (int) $row['termId'];
			if ( $term_id <= 0 ) {
				continue;
			}
			$rows[] = array(
				'key'    => isset( $row['key'] ) ? (string) $row['key'] : '',
				'termId' => $term_id,
			);
		}
		return $rows;
	}

	/**
	 * Whether a byline or acknowledgement row already names this term.
	 *
	 * @param int $post_id Post ID.
	 * @param int $term_id Bylines term ID.
	 */
	public static function has_meta_row( int $post_id, int $term_id ): bool {
		foreach ( array( self::META_BYLINES, self::META_ACKNOWLEDGEMENTS ) as $meta_key ) {
			foreach ( self::get_rows( $post_id, $meta_key ) as $row ) {
				if ( $term_id === $row['termId'] ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Whether the post is fully attributed to the term: a meta row exists and
	 * the term is attached in the bylines taxonomy.
	 *
	 * @param int $post_id Post ID.
	 * @param int $term_id Bylines term ID.
	 */
	public static function is_attributed( int $post_id, int $term_id ): bool {
		return self::has_meta_row( $post_id, $term_id )
			&& has_term( $term_id, Content_Type::$taxonomy_object_name, $post_id );
	}

	/**
	 * Copy the term IDs in `bylines` and `acknowledgements` meta onto the post
	 * as `bylines` taxonomy terms.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function sync_taxonomy_from_meta( int $post_id ): void {
		$term_ids = array();
		foreach ( array( self::META_BYLINES, self::META_ACKNOWLEDGEMENTS ) as $meta_key ) {
			foreach ( self::get_rows( $post_id, $meta_key ) as $row ) {
				$term_ids[] = $row['termId'];
			}
		}
		wp_set_object_terms(
			$post_id,
			array_values( array_unique( $term_ids ) ),
			Content_Type::$taxonomy_object_name,
			false
		);
	}

	/**
	 * Attribute a post to one byline term without disturbing existing rows.
	 *
	 * Appends a row to the chosen meta key unless a byline or acknowledgement
	 * already names the term, then syncs the taxonomy. Safe to call repeatedly.
	 *
	 * @param int    $post_id  Post ID.
	 * @param int    $term_id  Bylines term ID.
	 * @param string $meta_key META_ACKNOWLEDGEMENTS (staff page only) or META_BYLINES (printed byline).
	 * @return bool Whether anything changed.
	 */
	public static function add_term_row( int $post_id, int $term_id, string $meta_key = self::META_ACKNOWLEDGEMENTS ): bool {
		if ( $post_id <= 0 || $term_id <= 0 ) {
			return false;
		}
		if ( ! in_array( $meta_key, array( self::META_BYLINES, self::META_ACKNOWLEDGEMENTS ), true ) ) {
			return false;
		}
		if ( self::is_attributed( $post_id, $term_id ) ) {
			return false;
		}

		if ( ! self::has_meta_row( $post_id, $term_id ) ) {
			$rows   = self::get_rows( $post_id, $meta_key );
			$rows[] = array(
				'key'    => 'enforced-' . $term_id,
				'termId' => $term_id,
			);
			update_post_meta( $post_id, $meta_key, $rows );
		}

		self::sync_taxonomy_from_meta( $post_id );
		return true;
	}
}
