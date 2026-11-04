<?php
/**
 * One-to-one link between a staff post and a WordPress user.
 *
 * @package PRC\Platform\Staff_Bylines
 */

declare(strict_types=1);

namespace PRC\Platform\Staff_Bylines;

use WP_Error;
use WP_Post;
use WP_REST_Request;

/**
 * Stores the link once, as `_wp_user` on the staff post, and answers the
 * reverse user-to-staff lookup from a self-verifying cache.
 */
class Staff_User_Link {

	/**
	 * Post meta key holding the linked user ID.
	 *
	 * @var string
	 */
	public const META_KEY = '_wp_user';

	/**
	 * REST field the editor reads and writes the link through.
	 *
	 * The editor resends the whole `meta` object on every save, and core runs a
	 * meta auth check for any key with no stored value. Exposing the link as
	 * REST meta would make every save by an editor without CAPABILITY fail.
	 *
	 * @var string
	 */
	public const REST_FIELD = 'wpUser';

	/**
	 * Capability required to change a link. Core user search in REST only
	 * returns every user to callers with this capability.
	 *
	 * @var string
	 */
	public const CAPABILITY = 'list_users';

	/**
	 * Object cache group for the user-to-staff lookup.
	 *
	 * @var string
	 */
	private const CACHE_GROUP = 'staff_user_link';

	/**
	 * Constructor.
	 *
	 * @param Loader $loader The loader.
	 */
	public function __construct( Loader $loader ) {
		$loader->add_action( 'init', $this, 'register_meta', 11 );
		$loader->add_action( 'rest_api_init', $this, 'register_rest_field' );
		$loader->add_action( 'enqueue_block_editor_assets', $this, 'localize_editor_script', 10 );
		$loader->add_filter( 'rest_pre_insert_' . Content_Type::$post_object_name, $this, 'validate_rest_link', 10, 2 );
		$loader->add_action( 'added_post_meta', $this, 'on_meta_written', 10, 4 );
		$loader->add_action( 'updated_post_meta', $this, 'on_meta_written', 10, 4 );
		$loader->add_action( 'deleted_post_meta', $this, 'on_meta_written', 10, 4 );
	}

	/**
	 * Register the link meta on staff posts.
	 *
	 * @hook init 11
	 */
	public function register_meta(): void {
		register_post_meta(
			Content_Type::$post_object_name,
			self::META_KEY,
			array(
				'description'       => 'ID of the WordPress user this staff record belongs to.',
				'show_in_rest'      => false,
				'single'            => true,
				'type'              => 'integer',
				'default'           => 0,
				'sanitize_callback' => 'absint',
				'auth_callback'     => static function (): bool {
					return current_user_can( self::CAPABILITY );
				},
			)
		);
	}

	/**
	 * Expose the link to the editor as `wpUser: { id, name }`.
	 *
	 * @hook rest_api_init
	 */
	public function register_rest_field(): void {
		register_rest_field(
			Content_Type::$post_object_name,
			self::REST_FIELD,
			array(
				'get_callback'    => array( $this, 'get_rest_value' ),
				'update_callback' => array( $this, 'update_from_rest' ),
				'schema'          => array(
					'description' => 'WordPress user linked to this staff record. Send { id: 0 } to unlink.',
					'type'        => 'object',
					'context'     => array( 'edit' ),
					'properties'  => array(
						'id'   => array(
							'type'     => 'integer',
							'minimum'  => 0,
							'required' => true,
						),
						'name' => array(
							'type'     => 'string',
							'readonly' => true,
						),
					),
				),
			)
		);
	}

	/**
	 * REST get: wpUser.
	 *
	 * @param array $post Prepared post.
	 * @return array{id:int,name:string}
	 */
	public function get_rest_value( array $post ): array {
		$user_id = self::user_id_for_staff( (int) ( $post['id'] ?? 0 ) );
		$user    = null === $user_id ? false : get_userdata( $user_id );
		return array(
			'id'   => false === $user ? 0 : $user->ID,
			'name' => false === $user ? '' : $user->display_name,
		);
	}

	/**
	 * REST update: wpUser. Permission and uniqueness already passed in validate_rest_link().
	 *
	 * @param array   $value New value.
	 * @param WP_Post $post  Saved post.
	 * @return true|WP_Error
	 */
	public function update_from_rest( array $value, WP_Post $post ): true|WP_Error {
		return self::link( $post->ID, (int) $value['id'] );
	}

	/**
	 * Tell the staff panel whether the current user may change the link.
	 *
	 * @hook enqueue_block_editor_assets
	 */
	public function localize_editor_script(): void {
		if ( ! wp_script_is( Orcid::EDITOR_SCRIPT_HANDLE, 'enqueued' ) ) {
			return;
		}
		wp_add_inline_script(
			Orcid::EDITOR_SCRIPT_HANDLE,
			'window.prcStaffUserLink = ' . wp_json_encode( array( 'canLink' => current_user_can( self::CAPABILITY ) ) ) . ';',
			'before'
		);
	}

	/**
	 * Primary site ID, where staff posts live.
	 */
	public static function primary_site_id(): int {
		return defined( 'PRC_PRIMARY_SITE_ID' ) ? (int) PRC_PRIMARY_SITE_ID : (int) get_main_site_id();
	}

	/**
	 * Linked user ID for a staff post on the current site.
	 *
	 * @param int $staff_id Staff post ID.
	 * @return int|null User ID, or null when the post is not linked to an existing user.
	 */
	public static function user_id_for_staff( int $staff_id ): ?int {
		if ( ! self::is_staff( $staff_id ) ) {
			return null;
		}
		$user_id = (int) get_post_meta( $staff_id, self::META_KEY, true );
		if ( $user_id <= 0 || false === get_userdata( $user_id ) ) {
			return null;
		}
		return $user_id;
	}

	/**
	 * Staff post on the primary site linked to a user.
	 *
	 * The cached post ID is re-checked against its meta on read, so an unlink
	 * or relink needs no cache bookkeeping for the old user.
	 *
	 * @param int $user_id User ID.
	 * @return int|null Staff post ID on the primary site, or null.
	 */
	public static function staff_id_for_user( int $user_id ): ?int {
		if ( $user_id <= 0 ) {
			return null;
		}

		$site_id  = self::primary_site_id();
		$switched = is_multisite() && get_current_blog_id() !== $site_id;
		if ( $switched ) {
			switch_to_blog( $site_id );
		}

		$cached = wp_cache_get( $user_id, self::CACHE_GROUP );
		if ( is_int( $cached ) && ( 0 === $cached || self::points_at( $cached, $user_id ) ) ) {
			$staff_id = $cached;
		} else {
			$staff_id = self::query_staff_id( $user_id );
			wp_cache_set( $user_id, $staff_id, self::CACHE_GROUP, DAY_IN_SECONDS );
		}

		if ( $switched ) {
			restore_current_blog();
		}

		return $staff_id > 0 ? $staff_id : null;
	}

	/**
	 * Why a staff post cannot link to a user, or true when it can.
	 *
	 * @param int $staff_id Staff post ID, or 0 for a post not yet created.
	 * @param int $user_id  User ID, or 0 to unlink.
	 * @return true|WP_Error
	 */
	public static function validate( int $staff_id, int $user_id ): true|WP_Error {
		if ( 0 === $user_id ) {
			return true;
		}
		$user = get_userdata( $user_id );
		if ( false === $user ) {
			return new WP_Error(
				'prc_staff_user_missing',
				__( 'That WordPress user does not exist.', 'prc-staff-bylines' ),
				array( 'status' => 400 )
			);
		}

		$other = self::staff_id_for_user( $user_id );
		if ( null !== $other && $other !== $staff_id ) {
			return new WP_Error(
				'prc_staff_user_taken',
				sprintf(
					/* translators: 1: user display name, 2: staff name. */
					__( '%1$s is already linked to the staff record for %2$s.', 'prc-staff-bylines' ),
					$user->display_name,
					get_the_title( $other )
				),
				array( 'status' => 409 )
			);
		}

		return true;
	}

	/**
	 * Link a staff post to a user, or unlink it with user ID 0.
	 *
	 * @param int $staff_id Staff post ID.
	 * @param int $user_id  User ID, or 0.
	 * @return true|WP_Error
	 */
	public static function link( int $staff_id, int $user_id ): true|WP_Error {
		if ( ! self::is_staff( $staff_id ) ) {
			return new WP_Error( 'prc_staff_missing', __( 'That staff record does not exist.', 'prc-staff-bylines' ) );
		}
		$valid = self::validate( $staff_id, $user_id );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		if ( 0 === $user_id ) {
			delete_post_meta( $staff_id, self::META_KEY );
		} else {
			update_post_meta( $staff_id, self::META_KEY, $user_id );
		}
		return true;
	}

	/**
	 * Reject a REST save that changes the link without permission, or that
	 * would link a user who already has a staff record, before the post saves.
	 *
	 * @hook rest_pre_insert_staff
	 *
	 * @param \stdClass|WP_Error $prepared_post Post about to be saved.
	 * @param WP_REST_Request    $request       Request.
	 * @return \stdClass|WP_Error
	 */
	public function validate_rest_link( $prepared_post, WP_REST_Request $request ) {
		$value = $request->get_param( self::REST_FIELD );
		if ( is_wp_error( $prepared_post ) || ! is_array( $value ) ) {
			return $prepared_post;
		}

		$staff_id = (int) $request->get_param( 'id' );
		$user_id  = absint( $value['id'] ?? 0 );
		if ( (int) self::user_id_for_staff( $staff_id ) === $user_id ) {
			return $prepared_post;
		}
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Sorry, you are not allowed to change which WordPress user this staff record belongs to.', 'prc-staff-bylines' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		$valid = self::validate( $staff_id, $user_id );
		return is_wp_error( $valid ) ? $valid : $prepared_post;
	}

	/**
	 * Drop caches that a link change makes stale.
	 *
	 * @hook added_post_meta
	 * @hook updated_post_meta
	 * @hook deleted_post_meta
	 *
	 * @param int|array $meta_id    Meta ID, or IDs on delete.
	 * @param int       $object_id  Post ID.
	 * @param string    $meta_key   Meta key.
	 * @param mixed     $meta_value New value.
	 */
	public function on_meta_written( $meta_id, $object_id, $meta_key, $meta_value ): void {
		if ( self::META_KEY !== $meta_key ) {
			return;
		}
		$user_id = absint( $meta_value );
		if ( $user_id > 0 ) {
			wp_cache_delete( $user_id, self::CACHE_GROUP );
		}
		Staff::clear_cache( (int) $object_id );
	}

	/**
	 * Whether a staff post still links to a user.
	 *
	 * @param int $staff_id Staff post ID.
	 * @param int $user_id  User ID.
	 */
	private static function points_at( int $staff_id, int $user_id ): bool {
		return self::is_staff( $staff_id )
			&& (int) get_post_meta( $staff_id, self::META_KEY, true ) === $user_id;
	}

	/**
	 * Whether an ID belongs to a staff post on the current site.
	 *
	 * @param int $post_id Post ID.
	 */
	private static function is_staff( int $post_id ): bool {
		return $post_id > 0 && get_post_type( $post_id ) === Content_Type::$post_object_name;
	}

	/**
	 * Look the link up in the database on the current site.
	 *
	 * @param int $user_id User ID.
	 * @return int Staff post ID, or 0.
	 */
	private static function query_staff_id( int $user_id ): int {
		$ids = get_posts(
			array(
				'post_type'        => Content_Type::$post_object_name,
				'post_status'      => array_values( get_post_stati() ),
				'fields'           => 'ids',
				'posts_per_page'   => 1,
				'no_found_rows'    => true,
				'suppress_filters' => false,
				'ep_integrate'     => false,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- One small indexed lookup per user, cached for a day.
				'meta_query'       => array(
					array(
						'key'   => self::META_KEY,
						'value' => $user_id,
						'type'  => 'NUMERIC',
					),
				),
			)
		);
		return isset( $ids[0] ) ? (int) $ids[0] : 0;
	}
}
