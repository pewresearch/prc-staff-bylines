<?php
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
	public $post_id;

	/**
	 * The bylines.
	 *
	 * @var array
	 */
	public $bylines;

	/**
	 * Whether the bylines should be displayed.
	 *
	 * @var bool
	 */
	public $should_display = false;

	/**
	 * Constructor.
	 *
	 * @param int $post_id The post ID.
	 */
	public function __construct( $post_id ) {
		if ( ! is_int( $post_id ) ) {
			$this->bylines = new WP_Error( '404', 'Bylines not found, no post id provided.' );
		} else {
			$parent_post_id       = wp_get_post_parent_id( $post_id );
			$this->post_id        = 0 !== $parent_post_id ? $parent_post_id : $post_id;
			$this->should_display = $this->determine_bylines_display();
			$this->bylines        = $this->get();
		}
	}

	/**
	 * Translates the {key, termId} array to {termId, postId, name, link, jobTitle}
	 *
	 * @param array $bylines The bylines.
	 * @return array The staff objects.
	 */
	private function get_staff_objects( $bylines = array() ) {
		$to_return = array();
		foreach ( $bylines as $byline ) {
			// If the byline is empty or malformed, skip it.
			if ( ! array_key_exists( 'termId', $byline ) ) {
				continue;
			}
			$staff = new Staff( false, $byline['termId'] );
			if ( ! is_wp_error( $staff ) ) {
				$to_return[ $byline['termId'] ] = get_object_vars( $staff );
			}
		}
		return $to_return;
	}

	/**
	 * Gets the bylines.
	 *
	 * @return array The bylines.
	 */
	public function get() {
		$bylines = array();
		$bylines = get_post_meta( $this->post_id, 'bylines', true );
		if ( ! is_array( $bylines ) ) {
			return new WP_Error( '404', 'Bylines not found, no bylines found for this post ' . $this->post_id );
		}
		return $this->get_staff_objects( $bylines );
	}

	/**
	 * Determines whether the bylines should be displayed.
	 *
	 * @return bool Whether the bylines should be displayed.
	 */
	private function determine_bylines_display() {
		$should_display = get_post_meta( $this->post_id, 'displayBylines', true );
		return rest_sanitize_boolean( $should_display );
	}

	/**
	 * Formats the bylines as a string.
	 *
	 * @param bool $return_html Whether to return HTML.
	 * @return string The formatted bylines.
	 */
	private function format_string( $return_html = false ) {
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
	public function format( $type = 'array' ) {
		if ( 'array' === $type ) {
			return $this->bylines;
		}
		if ( 'string' === $type ) {
			return $this->format_string();
		}
		if ( 'html' === $type ) {
			return $this->format_string( true );
		}
	}
}
