<?php
declare(strict_types=1);
/**
 * Bylines class.
 *
 * @package PRC\Platform\Staff_Bylines
 */

namespace PRC\Platform\Staff_Bylines;

use WP_Error;

/**
 * Bylines class.
 *
 * @package PRC\Platform\Staff_Bylines
 */
class Bylines {
	/**
	 * The post ID.
	 *
	 * @var int
	 */
	public int $post_id;

	/**
	 * The bylines.
	 *
	 * @var array|WP_Error
	 */
	public array|WP_Error $bylines;

	/**
	 * Whether the bylines should be displayed.
	 *
	 * @var bool
	 */
	public bool $should_display = false;

	/**
	 * Constructor.
	 *
	 * @param int $post_id The post ID.
	 */
	public function __construct( int $post_id ) {
		$parent_post_id = wp_get_post_parent_id( $post_id );
		// wp_get_post_parent_id() returns int|false; false must not be assigned to int $post_id.
		$this->post_id        = ( is_int( $parent_post_id ) && $parent_post_id > 0 )
			? $parent_post_id
			: $post_id;
		$this->should_display = $this->determine_bylines_display();
		$this->bylines        = $this->get();
	}

	/**
	 * Translates the {key, termId} array to {termId, postId, name, link, jobTitle}
	 *
	 * @param array $bylines The bylines.
	 * @return array The staff objects.
	 */
	private function get_staff_objects( array $bylines = array() ): array {
		$to_return = array();
		foreach ( $bylines as $byline ) {
			// If the byline is empty, malformed, or has a null/non-integer termId, skip it.
			if ( ! array_key_exists( 'termId', $byline ) || ! is_int( $byline['termId'] ) ) {
				continue;
			}
			$staff = new Staff( false, $byline['termId'] );
			if ( $staff->is_resolved() ) {
				$to_return[ $byline['termId'] ] = get_object_vars( $staff );
			}
		}
		return $to_return;
	}

	/**
	 * Gets the bylines.
	 *
	 * @return array|WP_Error The bylines.
	 */
	public function get(): array|WP_Error {
		$bylines = get_post_meta( $this->post_id, 'bylines', true );
		if ( ! is_array( $bylines ) ) {
			return new WP_Error( '404', 'Bylines not found, no bylines found for this post ' . $this->post_id );
		}
		return $this->get_staff_objects( $bylines );
	}

	/**
	 * Determines whether the bylines should be displayed.
	 *
	 * `displayBylines` is registered with `'default' => true`, but `get_post_meta()`
	 * only honors that default when the meta key is registered for the current
	 * post type — and even then, an explicitly-saved `false` is persisted as the
	 * empty string `''` (MySQL stores boolean false via `%s` as ''). We must
	 * distinguish "no row yet" (use default of true) from "row exists with falsy
	 * value" (user toggled it off), otherwise the editor toggle is a no-op.
	 *
	 * @return bool Whether the bylines should be displayed.
	 */
	private function determine_bylines_display(): bool {
		if ( ! metadata_exists( 'post', $this->post_id, 'displayBylines' ) ) {
			return true;
		}
		return rest_sanitize_boolean( get_post_meta( $this->post_id, 'displayBylines', true ) );
	}

	/**
	 * Formats the bylines as a string.
	 *
	 * @param bool $return_html Whether to return HTML.
	 * @return string The formatted bylines.
	 */
	private function format_string( bool $return_html = false ): string {
		if ( ! is_array( $this->bylines ) ) {
			return '';
		}
		$output = '';
		$total  = count( $this->bylines );
		$and    = 'and';
		$i      = 1;
		foreach ( $this->bylines as $term_id => $d ) {
			if ( 1 < $total && $i === $total ) {
				if ( false === $return_html ) {
					$output .= ' ' . $and . ' ';
				} else {
					$output .= ' <span class="prc-platform-staff-bylines__and-separator">' . $and . '</span> ';
				}
			} elseif ( 1 < $total && 1 !== $i ) {
				if ( false === $return_html ) {
					$output .= ', ';
				} else {
					$output .= '<span class="prc-platform-staff-bylines__separator">, </span>';
				}
			}
			if ( false === $return_html ) {
				$output .= $d['name'];
			} else {
				$output .= wp_sprintf(
					'<%1$s %2$s>%3$s</%1$s>',
					false !== $d['link'] ? 'a' : 'span',
					false !== $d['link'] ? 'rel="author" href="' . $d['link'] . '" aria-label="View author archive for ' . $d['name'] . '"' : '',
					$d['name']
				);
			}
			++$i;
		}
		return $output;
	}

	/**
	 * Formats the bylines.
	 *
	 * @param string $type The type of format.
	 * @return mixed The formatted bylines.
	 */
	public function format( string $type = 'array' ): mixed {
		if ( 'array' === $type ) {
			return $this->bylines;
		}
		if ( 'string' === $type ) {
			return $this->format_string();
		}
		if ( 'html' === $type ) {
			return $this->format_string( true );
		}
		return $this->bylines;
	}
}
