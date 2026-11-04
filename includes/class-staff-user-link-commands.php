<?php
/**
 * WP-CLI command to bulk link staff records to WordPress users.
 *
 * @package PRC\Platform\Staff_Bylines
 */

declare(strict_types=1);

namespace PRC\Platform\Staff_Bylines;

use WPCOM_VIP_CLI_Command;
use WP_CLI;
use WP_CLI\Utils;

if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'WPCOM_VIP_CLI_Command' ) ) {
	/**
	 * Link staff records to WordPress users.
	 */
	class Staff_User_Link_Commands extends WPCOM_VIP_CLI_Command {

		private const BATCH_SIZE = 200;

		/**
		 * Link current, unlinked staff records to unlinked users by name.
		 *
		 * Matches on the legacy `user_id` staff meta first, then the user's
		 * first and last name, then the user's display name. Prints every
		 * match, ambiguous case, and miss. Only exact, unique matches are
		 * written.
		 *
		 * ## OPTIONS
		 *
		 * [--dry-run]
		 * : Print the matches without writing them.
		 *
		 * ## EXAMPLES
		 *
		 *     wp prc-staff link-users --dry-run
		 *
		 * @subcommand link-users
		 *
		 * @param array $args       Positional arguments.
		 * @param array $assoc_args Associative arguments.
		 */
		public function link_users( array $args, array $assoc_args ): void {
			$dry_run = (bool) Utils\get_flag_value( $assoc_args, 'dry-run', false );

			$site_id  = Staff_User_Link::primary_site_id();
			$switched = is_multisite() && get_current_blog_id() !== $site_id;
			if ( $switched ) {
				switch_to_blog( $site_id );
			}

			$linked_users = array();
			$staff        = array();
			foreach ( $this->staff_ids() as $staff_id ) {
				$user_id = Staff_User_Link::user_id_for_staff( $staff_id );
				if ( null !== $user_id ) {
					$linked_users[ $user_id ] = true;
					continue;
				}
				if ( has_term( 'former-staff', 'staff-type', $staff_id ) ) {
					continue;
				}
				$staff[] = array(
					'id'             => $staff_id,
					'name'           => get_the_title( $staff_id ),
					'legacy_user_id' => (int) get_post_meta( $staff_id, 'user_id', true ),
				);
			}

			$users = array_values(
				array_filter(
					$this->users( $site_id ),
					static fn( array $user ): bool => ! isset( $linked_users[ $user['id'] ] )
				)
			);

			$rows = Staff_User_Matcher::match( $staff, $users );

			$logins = array_column( $users, 'login', 'id' );
			$table  = array();
			$counts = array_fill_keys( array( Staff_User_Matcher::MATCH, Staff_User_Matcher::AMBIGUOUS, Staff_User_Matcher::MISS ), 0 );
			foreach ( $rows as $row ) {
				++$counts[ $row['status'] ];
				$table[] = array(
					'staff_id' => $row['staff_id'],
					'staff'    => $row['staff_name'],
					'result'   => $row['status'],
					'users'    => implode( ', ', array_map( static fn( int $id ): string => $logins[ $id ] . " ($id)", $row['user_ids'] ) ),
					'source'   => $row['source'],
				);
			}
			Utils\format_items( 'table', $table, array( 'staff_id', 'staff', 'result', 'users', 'source' ) );

			$written = 0;
			if ( ! $dry_run ) {
				foreach ( $rows as $row ) {
					if ( Staff_User_Matcher::MATCH !== $row['status'] ) {
						continue;
					}
					$result = Staff_User_Link::link( $row['staff_id'], $row['user_ids'][0] );
					if ( is_wp_error( $result ) ) {
						WP_CLI::warning( sprintf( '%s (%d): %s', $row['staff_name'], $row['staff_id'], $result->get_error_message() ) );
						continue;
					}
					++$written;
				}
			}

			if ( $switched ) {
				restore_current_blog();
			}

			WP_CLI::success(
				sprintf(
					'%d matched, %d ambiguous, %d missed. %s',
					$counts[ Staff_User_Matcher::MATCH ],
					$counts[ Staff_User_Matcher::AMBIGUOUS ],
					$counts[ Staff_User_Matcher::MISS ],
					$dry_run ? 'Dry run: nothing written.' : sprintf( '%d linked.', $written )
				)
			);
		}

		/**
		 * IDs of every staff post on the current site.
		 *
		 * @return \Generator<int>
		 */
		private function staff_ids(): \Generator {
			$paged = 1;
			do {
				$ids = get_posts(
					array(
						'post_type'        => Content_Type::$post_object_name,
						'post_status'      => 'any',
						'fields'           => 'ids',
						'posts_per_page'   => self::BATCH_SIZE,
						'paged'            => $paged,
						'orderby'          => 'ID',
						'order'            => 'ASC',
						'no_found_rows'    => true,
						'suppress_filters' => false,
						'ep_integrate'     => false,
					)
				);
				foreach ( $ids as $id ) {
					yield (int) $id;
				}
				$fetched = count( $ids );
				++$paged;
				self::vip_inmemory_cleanup();
			} while ( self::BATCH_SIZE === $fetched );
		}

		/**
		 * Users of a site, with the fields the matcher reads.
		 *
		 * @param int $site_id Site ID.
		 * @return array<int,array{id:int,login:string,first:string,last:string,display:string}>
		 */
		private function users( int $site_id ): array {
			$users = array();
			$paged = 1;
			do {
				$batch = get_users(
					array(
						'blog_id' => $site_id,
						'fields'  => 'all_with_meta',
						'number'  => self::BATCH_SIZE,
						'paged'   => $paged,
						'orderby' => 'ID',
					)
				);
				foreach ( $batch as $user ) {
					$users[] = array(
						'id'      => (int) $user->ID,
						'login'   => (string) $user->user_login,
						'first'   => (string) $user->first_name,
						'last'    => (string) $user->last_name,
						'display' => (string) $user->display_name,
					);
				}
				$fetched = count( $batch );
				++$paged;
				self::vip_inmemory_cleanup();
			} while ( self::BATCH_SIZE === $fetched );
			return $users;
		}
	}

	WP_CLI::add_command( 'prc-staff', Staff_User_Link_Commands::class );
}
