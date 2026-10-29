<?php
/**
 * ORCID iD support for staff posts.
 *
 * @package PRC\Platform\Staff_Bylines
 */

declare(strict_types=1);

namespace PRC\Platform\Staff_Bylines;

/**
 * Parses, renders, and authenticates staff ORCID iDs.
 *
 * Authentication runs through admin-post.php instead of REST because the
 * ORCID redirect back to the site carries only the auth cookie, not a REST nonce.
 */
class Orcid {

	/**
	 * Post meta key holding the iD that ORCID OAuth confirmed.
	 *
	 * @var string
	 */
	public const AUTHENTICATED_META_KEY = '_orcid_authenticated';

	/**
	 * Nonce action for the authorize request.
	 *
	 * @var string
	 */
	public const NONCE_ACTION = 'prc_orcid_authorize';

	/**
	 * Transient key prefix for OAuth state.
	 *
	 * @var string
	 */
	private const STATE_TRANSIENT_PREFIX = 'prc_orcid_state_';

	/**
	 * Script handle of the staff inspector sidebar panel.
	 *
	 * @var string
	 */
	private const EDITOR_SCRIPT_HANDLE = 'prc-staff-bylines-staff-inspector-sidebar-panel';

	/**
	 * BroadcastChannel name shared by the callback page and the editor.
	 *
	 * @var string
	 */
	public const BROADCAST_CHANNEL = 'prc-orcid-auth';

	/**
	 * Constructor.
	 *
	 * @param Loader $loader The loader.
	 */
	public function __construct( Loader $loader ) {
		$loader->add_action( 'admin_post_prc_orcid_authorize', $this, 'handle_authorize' );
		$loader->add_action( 'admin_post_prc_orcid_callback', $this, 'handle_callback' );
		$loader->add_action( 'enqueue_block_editor_assets', $this, 'localize_editor_script', 10 );
	}

	/**
	 * Parse a raw ORCID iD or ORCID profile URL into canonical form.
	 *
	 * @param mixed $raw Raw input.
	 * @return string|null Canonical `XXXX-XXXX-XXXX-XXXX` iD, or null when invalid.
	 */
	public static function parse( mixed $raw ): ?string {
		if ( ! is_string( $raw ) ) {
			return null;
		}

		$value = trim( $raw );
		$value = (string) preg_replace( '#^https?://(?:www\.|sandbox\.)?orcid\.org/#i', '', $value );
		$value = rtrim( $value, '/' );
		$value = strtoupper( (string) preg_replace( '/[\s-]/', '', $value ) );

		if ( 1 !== preg_match( '/^\d{15}[\dX]$/', $value ) ) {
			return null;
		}

		$total = 0;
		for ( $i = 0; $i < 15; $i++ ) {
			$total = ( $total + (int) $value[ $i ] ) * 2;
		}
		$result         = ( 12 - ( $total % 11 ) ) % 11;
		$expected_check = 10 === $result ? 'X' : (string) $result;
		if ( $expected_check !== $value[15] ) {
			return null;
		}

		return implode( '-', str_split( $value, 4 ) );
	}

	/**
	 * Sanitize callback for the `orcid` post meta.
	 *
	 * @param mixed $value Raw meta value.
	 * @return string Canonical iD, or '' when invalid.
	 */
	public static function sanitize_meta( mixed $value ): string {
		return self::parse( $value ) ?? '';
	}

	/**
	 * Whether the staff post's stored iD matches the iD ORCID confirmed.
	 *
	 * @param int $post_id Staff post ID.
	 * @return bool
	 */
	public static function is_authenticated( int $post_id ): bool {
		$orcid = (string) get_post_meta( $post_id, 'orcid', true );
		return '' !== $orcid && get_post_meta( $post_id, self::AUTHENTICATED_META_KEY, true ) === $orcid;
	}

	/**
	 * ORCID base URL for the configured environment.
	 *
	 * @return string
	 */
	public static function get_base_url(): string {
		if ( defined( 'PRC_PLATFORM_ORCID_ENVIRONMENT' ) && 'sandbox' === constant( 'PRC_PLATFORM_ORCID_ENVIRONMENT' ) ) {
			return 'https://sandbox.orcid.org';
		}
		return 'https://orcid.org';
	}

	/**
	 * Public ORCID profile URL for an iD.
	 *
	 * @param string $id Canonical iD.
	 * @return string
	 */
	public static function url( string $id ): string {
		return self::get_base_url() . '/' . $id;
	}

	/**
	 * Whether ORCID API credentials are configured.
	 *
	 * @return bool
	 */
	public static function is_configured(): bool {
		return defined( 'PRC_PLATFORM_ORCID_CLIENT_ID' )
			&& defined( 'PRC_PLATFORM_ORCID_CLIENT_SECRET' )
			&& ! empty( constant( 'PRC_PLATFORM_ORCID_CLIENT_ID' ) )
			&& ! empty( constant( 'PRC_PLATFORM_ORCID_CLIENT_SECRET' ) );
	}

	/**
	 * Render the ORCID iD link markup.
	 *
	 * @param string $id            Canonical iD.
	 * @param bool   $authenticated Whether ORCID confirmed the iD.
	 * @return string
	 */
	public static function render_link( string $id, bool $authenticated ): string {
		return sprintf(
			'<a class="wp-block-prc-block-staff-context-provider__orcid %1$s" href="%2$s" rel="me">%3$s</a>',
			$authenticated ? 'is-authenticated' : 'is-unauthenticated',
			esc_url( self::url( $id ) ),
			esc_html( $id )
		);
	}

	/**
	 * OAuth redirect URI registered with ORCID.
	 *
	 * @return string
	 */
	private static function get_redirect_uri(): string {
		return admin_url( 'admin-post.php?action=prc_orcid_callback' );
	}

	/**
	 * Start the ORCID OAuth flow for a staff post.
	 *
	 * @hook admin_post_prc_orcid_authorize
	 * @return void
	 */
	public function handle_authorize(): void {
		$nonce    = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		$staff_id = isset( $_GET['staffId'] ) ? absint( $_GET['staffId'] ) : 0;

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			$this->render_result( 'error', __( 'This link has expired. Reload the editor and try again.', 'prc-staff-bylines' ), '', $staff_id );
		}
		if ( 0 === $staff_id || 'staff' !== get_post_type( $staff_id ) || ! current_user_can( 'edit_post', $staff_id ) ) {
			$this->render_result( 'error', __( 'You cannot edit this staff profile.', 'prc-staff-bylines' ), '', $staff_id );
		}
		if ( ! self::is_configured() ) {
			$this->render_result( 'error', __( 'ORCID API credentials are not configured.', 'prc-staff-bylines' ), '', $staff_id );
		}

		$state = wp_generate_password( 32, false );
		set_transient(
			self::STATE_TRANSIENT_PREFIX . $state,
			array(
				'staff_id' => $staff_id,
				'user_id'  => get_current_user_id(),
			),
			10 * MINUTE_IN_SECONDS
		);

		$authorize_url = add_query_arg(
			array(
				'client_id'     => rawurlencode( (string) constant( 'PRC_PLATFORM_ORCID_CLIENT_ID' ) ),
				'response_type' => 'code',
				'scope'         => rawurlencode( '/authenticate' ),
				'redirect_uri'  => rawurlencode( self::get_redirect_uri() ),
				'state'         => rawurlencode( $state ),
			),
			self::get_base_url() . '/oauth/authorize'
		);

		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- ORCID is an external host.
		wp_redirect( $authorize_url );
		exit;
	}

	/**
	 * Finish the ORCID OAuth flow and store the confirmed iD.
	 *
	 * The single-use `state` transient is the CSRF guard for this request.
	 *
	 * @hook admin_post_prc_orcid_callback
	 * @return void
	 */
	public function handle_callback(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- OAuth state is verified below.
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		$error = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : '';
		$code  = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$stored = '' !== $state ? get_transient( self::STATE_TRANSIENT_PREFIX . $state ) : false;
		if ( '' !== $state ) {
			delete_transient( self::STATE_TRANSIENT_PREFIX . $state );
		}

		$staff_id = is_array( $stored ) ? (int) ( $stored['staff_id'] ?? 0 ) : 0;
		$user_id  = is_array( $stored ) ? (int) ( $stored['user_id'] ?? 0 ) : 0;

		if ( 0 === $staff_id || get_current_user_id() !== $user_id || ! current_user_can( 'edit_post', $staff_id ) ) {
			$this->render_result( 'error', __( 'This ORCID sign-in request is invalid or has expired. Try again from the staff editor.', 'prc-staff-bylines' ), '', $staff_id );
		}
		if ( '' !== $error ) {
			$this->render_result( 'error', __( 'ORCID sign-in was cancelled or denied.', 'prc-staff-bylines' ), '', $staff_id );
		}
		if ( '' === $code ) {
			$this->render_result( 'error', __( 'ORCID did not return an authorization code.', 'prc-staff-bylines' ), '', $staff_id );
		}

		$response = wp_remote_post(
			self::get_base_url() . '/oauth/token',
			array(
				'timeout' => 5, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout -- One user-initiated admin request.
				'headers' => array( 'Accept' => 'application/json' ),
				'body'    => array(
					'client_id'     => (string) constant( 'PRC_PLATFORM_ORCID_CLIENT_ID' ),
					'client_secret' => (string) constant( 'PRC_PLATFORM_ORCID_CLIENT_SECRET' ),
					'grant_type'    => 'authorization_code',
					'code'          => $code,
					'redirect_uri'  => self::get_redirect_uri(),
				),
			)
		);

		$body  = is_wp_error( $response ) ? null : json_decode( wp_remote_retrieve_body( $response ), true );
		$orcid = is_array( $body ) ? self::parse( $body['orcid'] ?? null ) : null;
		if ( null === $orcid ) {
			$this->render_result( 'error', __( 'ORCID did not confirm an iD. Try again.', 'prc-staff-bylines' ), '', $staff_id );
		}

		update_post_meta( $staff_id, 'orcid', $orcid );
		update_post_meta( $staff_id, self::AUTHENTICATED_META_KEY, $orcid );
		$this->clear_staff_cache( $staff_id );

		$this->render_result(
			'success',
			/* translators: %s: ORCID iD. */
			sprintf( __( 'ORCID iD %s is confirmed. You can close this window.', 'prc-staff-bylines' ), $orcid ),
			$orcid,
			$staff_id
		);
	}

	/**
	 * Clear staff and byline caches for a staff post.
	 *
	 * @param int $staff_id Staff post ID.
	 * @return void
	 */
	private function clear_staff_cache( int $staff_id ): void {
		$term_id = null;
		if ( function_exists( '\\PRC\\Primitives\\TDS\\get_related_term' ) ) {
			$term = \PRC\Primitives\TDS\get_related_term( $staff_id );
			if ( is_object( $term ) && ! empty( $term->term_id ) ) {
				$term_id = (int) $term->term_id;
			}
		}
		Staff::clear_cache( $staff_id, $term_id );
	}

	/**
	 * Render the popup result page, notify the editor, and stop.
	 *
	 * The editor listens on a BroadcastChannel because the ORCID round trip
	 * can sever `window.opener`.
	 *
	 * @param string $status   `success` or `error`.
	 * @param string $message  Message for the editor and the fallback page.
	 * @param string $orcid    Confirmed iD, or ''.
	 * @param int    $staff_id Staff post ID, or 0.
	 * @return never
	 */
	private function render_result( string $status, string $message, string $orcid, int $staff_id ): never {
		$payload   = array(
			'staffId' => $staff_id,
			'status'  => $status,
			'orcid'   => $orcid,
			'message' => $message,
		);
		$edit_link = $staff_id > 0 ? get_edit_post_link( $staff_id, 'raw' ) : null;

		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title><?php echo esc_html__( 'ORCID authentication', 'prc-staff-bylines' ); ?></title>
</head>
<body>
<p><?php echo esc_html( $message ); ?></p>
		<?php if ( $edit_link ) : ?>
<p><a href="<?php echo esc_url( $edit_link ); ?>"><?php echo esc_html__( 'Return to the staff profile', 'prc-staff-bylines' ); ?></a></p>
		<?php endif; ?>
<script>
( function () {
	var channel = new BroadcastChannel( <?php echo wp_json_encode( self::BROADCAST_CHANNEL ); ?> );
	channel.postMessage( <?php echo wp_json_encode( $payload, JSON_HEX_TAG | JSON_HEX_AMP ); ?> );
	channel.close();
	window.close();
} )();
</script>
</body>
</html>
		<?php
		exit;
	}

	/**
	 * Pass ORCID settings to the staff inspector sidebar panel.
	 *
	 * Runs after Bootstrap::enqueue_editor_assets (priority 9) enqueues the panel.
	 *
	 * @hook enqueue_block_editor_assets
	 * @return void
	 */
	public function localize_editor_script(): void {
		if ( ! wp_script_is( self::EDITOR_SCRIPT_HANDLE, 'enqueued' ) ) {
			return;
		}

		wp_add_inline_script(
			self::EDITOR_SCRIPT_HANDLE,
			'window.prcStaffOrcid = ' . wp_json_encode(
				array(
					'configured'   => self::is_configured(),
					'authorizeUrl' => admin_url( 'admin-post.php?action=prc_orcid_authorize' ),
					'nonce'        => wp_create_nonce( self::NONCE_ACTION ),
					'baseUrl'      => self::get_base_url(),
					'channel'      => self::BROADCAST_CHANNEL,
				)
			) . ';',
			'before'
		);
	}
}
