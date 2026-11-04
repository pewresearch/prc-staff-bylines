<?php
/**
 * Optional ORCID step in the wp-admin welcome splash.
 *
 * @package PRC\Platform\Staff_Bylines
 */

declare(strict_types=1);

namespace PRC\Platform\Staff_Bylines;

use PRC\Platform\Wp_Admin_Tours\Setup_Fields;
use WP_User;

/**
 * Registers an `orcid` setup field for users linked to a staff record. The
 * iD stays on the staff post; the splash only starts the OAuth popup.
 */
class Orcid_Setup_Field {

	public const FIELD_ID = 'orcid';

	/**
	 * Value the browser submits when the user has no ORCID iD.
	 *
	 * @var string
	 */
	public const DECLINED = 'declined';

	/**
	 * Constructor.
	 *
	 * @param Loader $loader The loader.
	 */
	public function __construct( Loader $loader ) {
		$loader->add_filter( 'prc_wp_admin_tours_setup_fields', $this, 'register' );
	}

	/**
	 * Add the ORCID field when ORCID credentials are configured.
	 *
	 * @hook prc_wp_admin_tours_setup_fields
	 *
	 * @param mixed $fields Registered fields.
	 * @return mixed
	 */
	public function register( $fields ) {
		if ( ! is_array( $fields ) || ! Orcid::is_configured() ) {
			return $fields;
		}
		$fields[] = array(
			'id'          => self::FIELD_ID,
			'label'       => __( 'ORCID iD', 'prc-staff-bylines' ),
			'description' => __( 'Sign in to ORCID to confirm your iD. It appears as verified on your staff profile.', 'prc-staff-bylines' ),
			'type'        => 'popup_connect',
			'is_complete' => array( self::class, 'is_complete' ),
			'save'        => array( self::class, 'save' ),
			'value'       => array( self::class, 'value' ),
		);
		return $fields;
	}

	/**
	 * Complete for users with no staff record, for an authenticated record,
	 * and for users who said they have no iD.
	 *
	 * @param WP_User $user User.
	 */
	public static function is_complete( WP_User $user ): bool {
		$staff_id = Staff_User_Link::staff_id_for_user( $user->ID );
		if ( null === $staff_id || Setup_Fields::is_acknowledged( $user, self::FIELD_ID ) ) {
			return true;
		}
		return self::on_primary_site( static fn(): bool => Orcid::is_authenticated( $staff_id ) );
	}

	/**
	 * Popup settings for the `popup_connect` control.
	 *
	 * @param WP_User $user User.
	 * @return array{url:string,channel:string,buttonLabel:string,declineLabel:string}
	 */
	public static function value( WP_User $user ): array {
		return array(
			'url'          => Orcid::get_authorize_url_for( (int) Staff_User_Link::staff_id_for_user( $user->ID ) ),
			'channel'      => Orcid::BROADCAST_CHANNEL,
			'buttonLabel'  => __( 'Connect ORCID', 'prc-staff-bylines' ),
			'declineLabel' => __( "I don't have an ORCID iD", 'prc-staff-bylines' ),
		);
	}

	/**
	 * Record a decline. A successful sign-in is stored by the OAuth callback.
	 *
	 * @param WP_User $user  User.
	 * @param mixed   $value Submitted value.
	 * @return true
	 */
	public static function save( WP_User $user, $value ): bool {
		if ( self::DECLINED === $value ) {
			Setup_Fields::acknowledge( $user, self::FIELD_ID );
		}
		return true;
	}

	/**
	 * Run a callback on the primary site, where staff posts live.
	 *
	 * @param callable $callback Callback.
	 * @return mixed
	 */
	private static function on_primary_site( callable $callback ) {
		$site_id  = Staff_User_Link::primary_site_id();
		$switched = is_multisite() && get_current_blog_id() !== $site_id;
		if ( $switched ) {
			switch_to_blog( $site_id );
		}
		$result = $callback();
		if ( $switched ) {
			restore_current_blog();
		}
		return $result;
	}
}
