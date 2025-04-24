<?php
/**
 * Maelstrom class.
 *
 * @package PRC\Platform\Staff_Bylines
 */

namespace PRC\Platform\Staff_Bylines;

/**
 * Maelstrom class.
 */
class Maelstrom {
	/**
	 * Constructor.
	 *
	 * @param Loader $loader The loader.
	 */
	public function __construct( $loader ) {
		$loader->add_action( 'prc_platform_on_publish', $this, 'enforce', 10, 1 );
	}

	/**
	 * Check if the byline is protected.
	 *
	 * @param int   $byline_term_id The byline term ID.
	 * @param array $regions_countries The regions and countries.
	 * @return bool
	 */
	private function is_byline_protected( $byline_term_id, $regions_countries = array() ) {
		$staff_post_id = get_term_meta( $byline_term_id, 'tds_post_id', true );
		if ( empty( $staff_post_id ) || false === $staff_post_id ) {
			return false;
		}
		$maelstrom = get_post_meta( $staff_post_id, '_maelstrom', true );
		if ( ! $maelstrom || ! is_array( $maelstrom ) ) {
			$maelstrom = array(
				'enabled' => false,
			);
		}
		// We're going to reset the enabled flag here.
		$maelstrom['enabled'] = false;
		// if it is an array, get the 'restricted' and see if any match $regions_countries if so set $maelstrom['enabled'] to true otherwise false...
		if ( is_array( $maelstrom ) && array_key_exists( 'restricted', $maelstrom ) && is_array( $maelstrom['restricted'] ) && ! empty( $maelstrom['restricted'] ) ) {
			$restricted = $maelstrom['restricted'];
			foreach ( $regions_countries as $r ) {
				if ( in_array( $r, $restricted ) ) {
					$maelstrom['enabled'] = true;
				}
			}
		}
		return $maelstrom;
	}

	/**
	 * Enforce the Maelstrom protection.
	 *
	 * @hook prc_platform_on_publish
	 * @param WP_Post $post The post.
	 */
	public function enforce( $post ) {
		// Does this post have any bylines?
		$bylines = get_post_meta( $post->ID, 'bylines', true );
		if ( ! is_array( $bylines ) ) {
			return;
		}

		// Check this post's regions and countries taxonomies...
		$regions_countries = wp_get_post_terms( $post->ID, 'regions-countries', array( 'fields' => 'names' ) );

		// Determine if there are any maestrom enabled bylines.
		$maelstrom_bylines = array_filter(
			$bylines,
			function ( $byline ) use ( $regions_countries ) {
				$maelstrom = $this->is_byline_protected( $byline['termId'], $regions_countries );
				return $maelstrom['enabled'];
			}
		);

		// If there are any maelstrom bylines, remove them from the bylines array.
		if ( ! empty( $maelstrom_bylines ) ) {
			$bylines = array_filter(
				$bylines,
				function ( $byline ) use ( $maelstrom_bylines ) {
					return ! in_array( $byline, $maelstrom_bylines );
				}
			);
			update_post_meta( $post->ID, 'bylines', $bylines );
			// Also remove the given byline terms from the post.
			wp_remove_object_terms( $post->ID, array_column( $maelstrom_bylines, 'termId' ), 'bylines' );
		}
	}
}
