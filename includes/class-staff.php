<?php
declare(strict_types=1);
/**
 * Staff class.
 *
 * @package PRC\Platform\Staff_Bylines
 */

namespace PRC\Platform\Staff_Bylines;

use WP_Error;
use function PRC\TDS\get_related_term;

/**
 * This is the primary means of accessing a unified Staff member. Combining both term and post type.
 *
 * @package PRC\Platform
 */
class Staff {
	/**
	 * The ID of the staff member.
	 *
	 * Defaults to 0 when construction cannot resolve a staff post or guest byline.
	 * Callers must treat empty( $staff->ID ) as "not found" — constructors cannot
	 * return WP_Error in PHP, so failed resolution leaves this sentinel.
	 *
	 * @var int|string
	 */
	public int|string $ID = 0;

	/**
	 * The name of the staff member.
	 *
	 * @var string
	 */
	public string $name = '';

	/**
	 * The slug of the staff member.
	 *
	 * @var string
	 */
	public string $slug = '';

	/**
	 * The link of the staff member.
	 *
	 * @var string|false
	 */
	public string|false $link = false;

	/**
	 * The user ID of the staff member.
	 *
	 * @var int|string|false
	 */
	public int|string|false $user_id = false;

	/**
	 * The bio of the staff member.
	 *
	 * @var string
	 */
	public string $bio = '';

	/**
	 * The mini bio of the staff member.
	 *
	 * @var string
	 */
	public string $mini_bio = '';

	/**
	 * The job title of the staff member.
	 *
	 * @var string|false
	 */
	public string|false $job_title = false;

	/**
	 * The extended job title of the staff member.
	 *
	 * @var string|false
	 */
	public string|false $job_title_extended = false;

	/**
	 * The photo of the staff member.
	 *
	 * @var array|false
	 */
	public array|false $photo = false;

	/**
	 * The expertise of the staff member.
	 *
	 * @var array|false
	 */
	public array|false $expertise = false;

	/**
	 * The social profiles of the staff member.
	 *
	 * @var array|false
	 */
	public array|false $social_profiles = false;

	/**
	 * The WordPress user of the staff member.
	 *
	 * @var \WP_User|false
	 */
	public \WP_User|false $wp_user = false;

	/**
	 * The slack handle of the staff member.
	 *
	 * @var string|false
	 */
	public string|false $slack_handle = false;

	/**
	 * Whether the staff member is currently employed.
	 *
	 * @var bool
	 */
	public bool $is_currently_employed = false;

	/**
	 * The cache TTL.
	 *
	 * @var int
	 */
	protected static int $cache_ttl = 1 * HOUR_IN_SECONDS;

	/**
	 * Constructor.
	 *
	 * On failure this leaves default property values (ID = 0). PHP constructors
	 * cannot return WP_Error; callers must check empty( $this->ID ).
	 *
	 * @param int|false $post_id The post ID.
	 * @param int|false $term_id The term ID.
	 */
	public function __construct( int|false $post_id = false, int|false $term_id = false ) {
		// Resolve staff post from byline term when only term_id is provided.
		if ( false === $post_id && false !== $term_id && is_int( $term_id ) ) {
			$resolved = $this->get_staff_post_id_from_term_id( $term_id );
			if ( is_wp_error( $resolved ) ) {
				// No linked staff post — fall back to guest byline term data.
				$term = get_term_by( 'id', $term_id, 'bylines' );
				if ( ! is_a( $term, 'WP_Term' ) ) {
					return;
				}
				$this->set_guest( $term_id );
				return;
			}
			$post_id = $resolved;
		}

		if ( false === $post_id || ! is_int( $post_id ) ) {
			return;
		}

		$this->set_staff( $post_id );

		// Linked staff post missing or wrong type — fall back to guest byline data.
		if ( ! $this->is_resolved() && false !== $term_id && is_int( $term_id ) ) {
			$term = get_term_by( 'id', $term_id, 'bylines' );
			if ( is_a( $term, 'WP_Term' ) ) {
				$this->set_guest( $term_id );
			}
		}
	}

	/**
	 * Whether this instance resolved a staff post or guest byline.
	 *
	 * @return bool
	 */
	public function is_resolved(): bool {
		return ! empty( $this->ID );
	}

	/**
	 * Get the staff post ID from the term ID.
	 *
	 * @param int $term_id The term ID.
	 * @return int|WP_Error The staff post ID.
	 */
	public function get_staff_post_id_from_term_id( int $term_id ): int|WP_Error {
		$staff_post_id = get_term_meta( $term_id, 'tds_post_id', true );
		if ( empty( $staff_post_id ) || false === $staff_post_id ) {
			return new WP_Error( '404', 'Staff post not found, no post id found for term id.' );
		}
		return (int) $staff_post_id;
	}

	/**
	 * Get the staff link.
	 *
	 * @param int|false $staff_post_id The staff post ID.
	 * @return string|false The staff link.
	 */
	public function get_staff_link( int|false $staff_post_id = false ): string|false {
		if ( false === $staff_post_id ) {
			$staff_post_id = ( is_int( $this->ID ) && $this->ID > 0 ) ? $this->ID : false;
		}
		if ( false === $staff_post_id ) {
			return false;
		}

		$display_byline_link = rest_sanitize_boolean( get_post_meta( $staff_post_id, 'bylineLinkEnabled', true ) );
		if ( true !== $display_byline_link ) {
			return false;
		}

		$term = get_related_term( $staff_post_id );
		if ( ! is_a( $term, 'WP_Term' ) ) {
			return false;
		}
		$link = get_term_link( $term, 'bylines' );
		if ( is_wp_error( $link ) ) {
			return false;
		}
		return $link;
	}

	/**
	 * Get the cache.
	 *
	 * @param int $post_id The post ID.
	 * @return bool True if the cache is set, false otherwise.
	 */
	public function get_cache( int $post_id ): bool {
		$cache = wp_cache_get( $post_id, 'staff_data' );
		if ( false !== $cache && is_array( $cache ) && array_key_exists( 'ID', $cache ) && ! is_user_logged_in() ) {
			foreach ( $cache as $key => $value ) {
				$this->$key = $value;
			}
			return true;
		}
		return false;
	}

	/**
	 * Set the cache.
	 */
	public function set_cache(): void {
		if ( ! is_preview() && $this->is_resolved() ) {
			wp_cache_set(
				$this->ID,
				get_object_vars( $this ),
				'staff_data',
				self::$cache_ttl,
			);
		}
	}

	/**
	 * Set the staff object properties based on the staff/byline hybrid.
	 *
	 * @param int $post_id The post ID.
	 */
	public function set_staff( int $post_id ): void {
		if ( true === $this->get_cache( $post_id ) ) {
			return;
		}
		$staff_post = get_post( $post_id );
		if ( ! $staff_post instanceof \WP_Post || 'staff' !== $staff_post->post_type ) {
			return;
		}

		$staff_post_id = $staff_post->ID;
		$this->ID      = (int) $staff_post_id;

		$this->name                  = $staff_post->post_title;
		$this->slug                  = $staff_post->post_name;
		$this->link                  = $this->get_staff_link( $staff_post_id );
		$this->user_id               = get_post_meta( $staff_post_id, 'user_id', true );
		$this->is_currently_employed = $this->check_employment_status( $staff_post_id );
		$this->bio                   = apply_filters( 'the_content', $staff_post->post_content );
		$this->job_title             = $this->get_job_title( $staff_post_id );
		$this->job_title_extended    = $this->get_job_title_extended( $staff_post_id );
		$this->mini_bio              = ! empty( $this->job_title_extended ) ? wp_sprintf(
			'<a href="%1$s">%2$s</a> <span>is %3$s</span>.',
			$this->link,
			$this->name,
			$this->job_title_extended
		) : '';
		$this->photo                 = $this->get_staff_photo( $staff_post_id );
		$this->social_profiles       = $this->get_social_profiles( $staff_post_id );
		$this->expertise             = $this->get_expertise( $staff_post_id );
		$this->wp_user               = $this->get_wp_user( $staff_post_id );
		$this->slack_handle          = $this->get_slack_handle( $staff_post_id );
		$this->set_cache();
	}

	/**
	 * Set the guest object properties based on the term.
	 *
	 * @param int $term_id The term ID.
	 */
	public function set_guest( int $term_id ): void {
		$term = get_term( $term_id );
		if ( ! $term instanceof \WP_Term ) {
			return;
		}

		$this->ID                    = 'guest_' . $term_id;
		$is_guest_author             = get_post_meta( $term_id, 'is_guest_author', true );
		$name                        = $term->name;
		$this->name                  = $name;
		$this->slug                  = $term->slug;
		$guest_link                  = $is_guest_author ? get_term_link( $term_id, 'bylines' ) : false;
		$this->link                  = is_string( $guest_link ) ? $guest_link : false;
		$this->user_id               = false;
		$this->is_currently_employed = (bool) $is_guest_author;
		$this->bio                   = '';
		$this->job_title             = $is_guest_author ? 'Guest Author' : 'Guest Contributor';
		$this->job_title_extended    = '';
		$this->mini_bio              = '';
		$this->photo                 = false;
		$this->social_profiles       = array();
		$this->expertise             = array();
		$this->wp_user               = false;
		$this->slack_handle          = false;
		$this->set_cache();
	}

	/**
	 * Check the employment status of the staff member.
	 *
	 * @param int|false $staff_post_id The staff post ID.
	 * @return bool True if the staff member is currently employed, false otherwise.
	 */
	public function check_employment_status( int|false $staff_post_id = false ): bool {
		if ( false === $staff_post_id ) {
			$staff_post_id = ( is_int( $this->ID ) && $this->ID > 0 ) ? $this->ID : false;
		}
		if ( false === $staff_post_id ) {
			return false;
		}
		if ( has_term( 'former-staff', 'staff-type', $staff_post_id ) ) {
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Returns the job title for the staff member.
	 *
	 * @param int|false $staff_post_id The staff post ID.
	 * @return string|false The job title.
	 */
	public function get_job_title( int|false $staff_post_id = false ): string|false {
		if ( false === $staff_post_id ) {
			$staff_post_id = ( is_int( $this->ID ) && $this->ID > 0 ) ? $this->ID : false;
		}
		if ( false === $staff_post_id ) {
			return false;
		}
		$job_title = get_post_meta( $staff_post_id, 'jobTitle', true );
		if ( false === $this->is_currently_employed ) {
			$job_title = 'Former ' . $job_title;
		}
		return $job_title;
	}

	/**
	 * Returns the extended job title for the staff member.
	 *
	 * @param int|false $staff_post_id The staff post ID.
	 * @return string|false The extended job title.
	 */
	public function get_job_title_extended( int|false $staff_post_id = false ): string|false {
		if ( false === $staff_post_id ) {
			$staff_post_id = ( is_int( $this->ID ) && $this->ID > 0 ) ? $this->ID : false;
		}
		if ( false === $staff_post_id ) {
			return false;
		}
		$job_title_extended = get_post_meta( $staff_post_id, 'jobTitleExtended', true );
		if ( false === $this->is_currently_employed ) {
			$job_title_extended = preg_replace( '/\b(a|an) /', 'a former ', $job_title_extended );
		}
		return $job_title_extended;
	}

	/**
	 * Returns an array of expertise terms for the staff member.
	 *
	 * @param int|false $staff_post_id The staff post ID.
	 * @return array|false The expertise terms.
	 */
	public function get_expertise( int|false $staff_post_id = false ): array|false {
		if ( false === $staff_post_id ) {
			$staff_post_id = ( is_int( $this->ID ) && $this->ID > 0 ) ? $this->ID : false;
		}
		if ( false === $staff_post_id ) {
			return false;
		}
		$terms = get_the_terms( $staff_post_id, 'areas-of-expertise' );

		$expertise = array();
		if ( $terms ) {
			foreach ( $terms as $term ) {
				// if $term is wp error and or is not a term object then skip it.
				if ( is_wp_error( $term ) || ! is_object( $term ) ) {
					continue;
				}
				$link        = get_term_link( $term, 'areas-of-expertise' );
				$expertise[] = array(
					'url'   => $link,
					'label' => $term->name,
					'slug'  => $term->slug,
				);
			}
		}
		return $expertise;
	}

	/**
	 * Returns the staff photo.
	 *
	 * @param int|false $staff_post_id The staff post ID.
	 * @return array|false The staff photo.
	 */
	public function get_staff_photo( int|false $staff_post_id = false ): array|false {
		$staff_photo_data = false;
		$staff_photo_id   = get_post_thumbnail_id( $staff_post_id );
		$staff_photo      = wp_get_attachment_image_src( $staff_photo_id, 'full' );
		$staff_portrait   = wp_get_attachment_image_src( $staff_photo_id, '320-portrait' );
		if ( false !== $staff_photo || false !== $staff_portrait ) {
			$staff_photo_data = array();
		}
		if ( false !== $staff_photo ) {
			$staff_photo_data['full'] = $staff_photo;
		}
		if ( false !== $staff_portrait ) {
			$staff_photo_data['thumbnail'] = $staff_portrait;
		}
		return $staff_photo_data;
	}

	/**
	 * Returns the social profiles for the staff member.
	 *
	 * @param int|false $staff_post_id The staff post ID.
	 * @return array|false The social profiles.
	 */
	public function get_social_profiles( int|false $staff_post_id = false ): array|false {
		if ( false === $staff_post_id ) {
			$staff_post_id = ( is_int( $this->ID ) && $this->ID > 0 ) ? $this->ID : false;
		}
		if ( false === $staff_post_id ) {
			return false;
		}
		return array();
	}

	/**
	 * Returns the WordPress user for the staff member.
	 *
	 * @param int|false $staff_post_id The staff post ID.
	 * @return \WP_User|false The WordPress user.
	 */
	public function get_wp_user( int|false $staff_post_id = false ): \WP_User|false {
		if ( false === $staff_post_id ) {
			$staff_post_id = ( is_int( $this->ID ) && $this->ID > 0 ) ? $this->ID : false;
		}
		if ( false === $staff_post_id ) {
			return false;
		}
		$assigned_user = get_post_meta( $staff_post_id, '_wp_user', true );
		if ( ! empty( $assigned_user ) ) {
			return false;
		}
		$user = get_userdata( $assigned_user );
		if ( false === $user ) {
			return false;
		}
		return $user;
	}

	/**
	 * Returns the Slack handle for the staff member.
	 *
	 * @param int|false $staff_post_id The staff post ID.
	 * @return string|false The Slack handle.
	 */
	public function get_slack_handle( int|false $staff_post_id = false ): string|false {
		if ( false === $staff_post_id ) {
			$staff_post_id = ( is_int( $this->ID ) && $this->ID > 0 ) ? $this->ID : false;
		}
		if ( false === $staff_post_id ) {
			return false;
		}
		// Get the user.
		$wp_user = $this->get_wp_user( $staff_post_id );
		if ( false === $wp_user ) {
			return false;
		}
		$slack_handle = get_user_meta( $wp_user->ID, 'slack_handle', true );
		if ( empty( $slack_handle ) ) {
			return false;
		}
		return '@' . $slack_handle;
	}
}
