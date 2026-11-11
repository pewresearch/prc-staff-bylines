<?php
/**
 * Per-user research team.
 *
 * @package PRC\Platform\Staff_Bylines
 */

declare(strict_types=1);

namespace PRC\Platform\Staff_Bylines;

use PRC\Platform\Taxonomies\Research_Teams;
use WP_Error;
use WP_Term;

/**
 * Stores the research team a user belongs to as the `prc_research_team` user
 * meta, holding a `research-teams` term slug. Slugs are stable across the
 * network, while term IDs differ per site. Terms always come from the primary
 * site, next to the staff posts.
 *
 * - Registered with `register_meta( 'user', ... )` so it round-trips through REST.
 * - Editable on the WP user profile screen.
 * - The wp-admin welcome tour confirms it through `Research_Team_Setup_Field`.
 */
class Research_Team_User {

	/**
	 * User meta key.
	 *
	 * @var string
	 */
	public const META_KEY = 'prc_research_team';

	/**
	 * Taxonomy that lists the research teams.
	 *
	 * @var string
	 */
	public const TAXONOMY = 'research-teams';

	/**
	 * Constructor.
	 *
	 * @param Loader $loader The loader.
	 */
	public function __construct( Loader $loader ) {
		$loader->add_action( 'init', $this, 'register_meta', 11 );
		$loader->add_action( 'show_user_profile', $this, 'render_profile_field' );
		$loader->add_action( 'edit_user_profile', $this, 'render_profile_field' );
		$loader->add_action( 'personal_options_update', $this, 'save_profile_field' );
		$loader->add_action( 'edit_user_profile_update', $this, 'save_profile_field' );
	}

	/**
	 * Register the meta on the user object.
	 *
	 * @hook init 11
	 */
	public function register_meta(): void {
		register_meta(
			'user',
			self::META_KEY,
			array(
				'description'       => 'Slug of the research team this user belongs to.',
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( self::class, 'sanitize' ),
				'auth_callback'     => array( self::class, 'can_edit' ),
			)
		);
	}

	/**
	 * Permission gate for meta writes.
	 *
	 * @param bool   $allowed   Whether to allow.
	 * @param string $meta_key  Meta key (unused).
	 * @param int    $object_id User ID being modified.
	 */
	public static function can_edit( $allowed, $meta_key, $object_id ): bool {
		unset( $allowed, $meta_key );
		return current_user_can( 'edit_user', (int) $object_id );
	}

	/**
	 * Keep only a slug that names a current research team.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize( $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$slug = sanitize_title( $value );
		return in_array( $slug, self::team_slugs(), true ) ? $slug : '';
	}

	/**
	 * Research teams a user can pick, ordered by name.
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	public static function team_options(): array {
		return Staff_User_Link::on_primary_site(
			static function (): array {
				if ( ! taxonomy_exists( self::TAXONOMY ) ) {
					return array();
				}
				$terms = get_terms(
					array(
						'taxonomy'   => self::TAXONOMY,
						'hide_empty' => false,
						'orderby'    => 'name',
						'order'      => 'ASC',
					)
				);
				if ( is_wp_error( $terms ) ) {
					return array();
				}
				$options = array();
				foreach ( $terms as $term ) {
					if ( $term instanceof WP_Term && ! in_array( $term->slug, self::excluded_slugs(), true ) ) {
						$options[] = array(
							'value' => $term->slug,
							'label' => html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ),
						);
					}
				}
				return $options;
			}
		);
	}

	/**
	 * Research team slug on the user's linked staff post. The primary term
	 * wins, else the first term.
	 *
	 * @param int $user_id User ID.
	 * @return string Slug, or an empty string without a linked staff post or team.
	 */
	public static function suggested_slug_for_user( int $user_id ): string {
		$staff_id = Staff_User_Link::staff_id_for_user( $user_id );
		if ( null === $staff_id ) {
			return '';
		}
		return Staff_User_Link::on_primary_site(
			static function () use ( $staff_id ): string {
				$terms = get_the_terms( $staff_id, self::TAXONOMY );
				if ( ! is_array( $terms ) ) {
					return '';
				}
				$terms = array_values(
					array_filter(
						$terms,
						static fn( $term ): bool => $term instanceof WP_Term && ! in_array( $term->slug, self::excluded_slugs(), true )
					)
				);
				if ( array() === $terms ) {
					return '';
				}
				$primary_id = function_exists( '\PRC\Platform\Schema_SEO\Utils\get_primary_term_id' )
					? \PRC\Platform\Schema_SEO\Utils\get_primary_term_id( $staff_id, self::TAXONOMY )
					: null;
				foreach ( $terms as $term ) {
					if ( is_numeric( $primary_id ) && (int) $primary_id === $term->term_id ) {
						return $term->slug;
					}
				}
				return $terms[0]->slug;
			}
		);
	}

	/**
	 * Saved research team slug for a user.
	 *
	 * @param int $user_id User ID.
	 */
	public static function get_for_user( int $user_id ): string {
		$value = get_user_meta( $user_id, self::META_KEY, true );
		return is_string( $value ) ? $value : '';
	}

	/**
	 * Store a user's research team, or clear it with an empty slug.
	 *
	 * @param int    $user_id User ID.
	 * @param string $slug    Research team slug, or ''.
	 * @return true|WP_Error
	 */
	public static function save_for_user( int $user_id, string $slug ): true|WP_Error {
		if ( '' === $slug ) {
			delete_user_meta( $user_id, self::META_KEY );
			return true;
		}
		if ( ! in_array( $slug, self::team_slugs(), true ) ) {
			return new WP_Error( 'prc_research_team_invalid', __( 'Choose a research team from the list.', 'prc-staff-bylines' ) );
		}
		update_user_meta( $user_id, self::META_KEY, $slug );
		return true;
	}

	/**
	 * Render the profile field.
	 *
	 * @hook show_user_profile
	 * @hook edit_user_profile
	 *
	 * @param \WP_User $user User being edited.
	 */
	public function render_profile_field( $user ): void {
		if ( ! is_object( $user ) || ! property_exists( $user, 'ID' ) ) {
			return;
		}
		$options = self::team_options();
		if ( array() === $options ) {
			return;
		}
		$current = self::get_for_user( (int) $user->ID );
		?>
		<h2><?php esc_html_e( 'Staff profile', 'prc-staff-bylines' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th>
					<label for="<?php echo esc_attr( self::META_KEY ); ?>">
						<?php esc_html_e( 'Research team', 'prc-staff-bylines' ); ?>
					</label>
				</th>
				<td>
					<select name="<?php echo esc_attr( self::META_KEY ); ?>" id="<?php echo esc_attr( self::META_KEY ); ?>">
						<option value=""><?php esc_html_e( 'Not on a research team', 'prc-staff-bylines' ); ?></option>
						<?php foreach ( $options as $option ) : ?>
							<option value="<?php echo esc_attr( $option['value'] ); ?>" <?php selected( $current, $option['value'] ); ?>>
								<?php echo esc_html( $option['label'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php esc_html_e( 'The research team you belong to.', 'prc-staff-bylines' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save the profile field.
	 *
	 * @hook personal_options_update
	 * @hook edit_user_profile_update
	 *
	 * @param int $user_id User ID being saved.
	 */
	public function save_profile_field( $user_id ): void {
		$user_id = (int) $user_id;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WP core verifies the user-edit nonce before these hooks fire.
		if ( $user_id <= 0 || ! isset( $_POST[ self::META_KEY ] ) || ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Same nonce check as above.
		$slug = sanitize_title( wp_unslash( (string) $_POST[ self::META_KEY ] ) );
		self::save_for_user( $user_id, $slug );
	}

	/**
	 * Slugs of the selectable research teams.
	 *
	 * @return array<int, string>
	 */
	private static function team_slugs(): array {
		return array_column( self::team_options(), 'value' );
	}

	/**
	 * Term slugs that are content sections, not teams people belong to.
	 *
	 * @return array<int, string>
	 */
	private static function excluded_slugs(): array {
		return class_exists( Research_Teams::class ) ? Research_Teams::EXCLUDED_TERM_SLUGS : array();
	}
}
