<?php
/**
 * Research team step in the wp-admin welcome splash.
 *
 * @package PRC\Platform\Staff_Bylines
 */

declare(strict_types=1);

namespace PRC\Platform\Staff_Bylines;

use PRC\Platform\Wp_Admin_Tours\Setup_Fields;
use WP_Error;
use WP_User;

/**
 * Registers a `research_team` setup field for users linked to a staff record.
 * It preselects the research team found on the staff post. Saving the step
 * confirms the team and writes it to the user's `prc_research_team` meta.
 */
class Research_Team_Setup_Field {

	public const FIELD_ID = 'research_team';

	/**
	 * Constructor.
	 *
	 * @param Loader $loader The loader.
	 */
	public function __construct( Loader $loader ) {
		$loader->add_filter( 'prc_wp_admin_tours_setup_fields', $this, 'register' );
	}

	/**
	 * Add the research team field.
	 *
	 * @hook prc_wp_admin_tours_setup_fields
	 *
	 * @param mixed $fields Registered fields.
	 * @return mixed
	 */
	public function register( $fields ) {
		if ( ! is_array( $fields ) ) {
			return $fields;
		}
		$fields[] = array(
			'id'            => self::FIELD_ID,
			'label'         => __( 'Research team', 'prc-staff-bylines' ),
			'description'   => __( 'Choose the research team you belong to.', 'prc-staff-bylines' ),
			'type'          => 'select_confirm',
			'is_applicable' => array( self::class, 'is_applicable' ),
			'is_complete'   => array( self::class, 'is_complete' ),
			'save'          => array( self::class, 'save' ),
			'value'         => array( self::class, 'value' ),
		);
		return $fields;
	}

	/**
	 * Only users linked to a staff record, on a network with research teams.
	 *
	 * @param WP_User $user User.
	 */
	public static function is_applicable( WP_User $user ): bool {
		return null !== Staff_User_Link::staff_id_for_user( $user->ID ) && array() !== Research_Team_User::team_options();
	}

	/**
	 * Complete once the user has a team saved or answered the step, including
	 * a choice of no team.
	 *
	 * @param WP_User $user User.
	 */
	public static function is_complete( WP_User $user ): bool {
		return '' !== Research_Team_User::get_for_user( $user->ID ) || Setup_Fields::is_acknowledged( $user, self::FIELD_ID );
	}

	/**
	 * Options, the saved team, and the team found on the staff post. `selected`
	 * is null until the user answers, so an empty string means "no team".
	 *
	 * @param WP_User $user User.
	 * @return array{options: array<int, array{value: string, label: string}>, selected: string|null, suggested: string}
	 */
	public static function value( WP_User $user ): array {
		$saved = Research_Team_User::get_for_user( $user->ID );
		return array(
			'options'   => array_merge(
				array(
					array(
						'value' => '',
						'label' => __( 'Not on a research team', 'prc-staff-bylines' ),
					),
				),
				Research_Team_User::team_options()
			),
			'selected'  => '' !== $saved || Setup_Fields::is_acknowledged( $user, self::FIELD_ID ) ? $saved : null,
			'suggested' => Research_Team_User::suggested_slug_for_user( $user->ID ),
		);
	}

	/**
	 * Store the confirmed team. An empty value clears it and still counts as an answer.
	 *
	 * @param WP_User $user  User.
	 * @param mixed   $value Submitted team slug.
	 * @return true|WP_Error
	 */
	public static function save( WP_User $user, $value ): true|WP_Error {
		if ( ! is_string( $value ) ) {
			return new WP_Error( 'prc_research_team_invalid', __( 'Choose a research team from the list.', 'prc-staff-bylines' ) );
		}
		$saved = Research_Team_User::save_for_user( $user->ID, sanitize_title( $value ) );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		Setup_Fields::acknowledge( $user, self::FIELD_ID );
		return true;
	}
}
