<?php
/**
 * Match staff records to WordPress users for bulk linking.
 *
 * @package PRC\Platform\Staff_Bylines
 */

declare(strict_types=1);

namespace PRC\Platform\Staff_Bylines;

/**
 * Pure matcher: takes plain arrays, returns one row per staff record.
 */
final class Staff_User_Matcher {

	public const MATCH     = 'match';
	public const AMBIGUOUS = 'ambiguous';
	public const MISS      = 'miss';

	/**
	 * Match each staff record to at most one user.
	 *
	 * Sources, in order: the legacy `user_id` staff meta, the user's first and
	 * last name, then the user's display name. A user that two staff records
	 * would claim is ambiguous for both.
	 *
	 * @param array<int,array{id:int,name:string,legacy_user_id:int}>                       $staff Unlinked staff records.
	 * @param array<int,array{id:int,login:string,first:string,last:string,display:string}> $users Unlinked users.
	 * @return array<int,array{staff_id:int,staff_name:string,status:string,user_ids:int[],source:string}>
	 */
	public static function match( array $staff, array $users ): array {
		$by_id      = array();
		$by_name    = array();
		$by_display = array();
		foreach ( $users as $user ) {
			$by_id[ $user['id'] ] = true;
			$full                 = self::normalize( $user['first'] . ' ' . $user['last'] );
			if ( '' !== self::normalize( $user['first'] ) && '' !== self::normalize( $user['last'] ) ) {
				$by_name[ $full ][] = $user['id'];
			}
			$display = self::normalize( $user['display'] );
			if ( '' !== $display ) {
				$by_display[ $display ][] = $user['id'];
			}
		}

		$rows = array();
		foreach ( $staff as $record ) {
			$name = self::normalize( $record['name'] );
			if ( $record['legacy_user_id'] > 0 && isset( $by_id[ $record['legacy_user_id'] ] ) ) {
				$candidates = array( $record['legacy_user_id'] );
				$source     = 'legacy';
			} elseif ( isset( $by_name[ $name ] ) ) {
				$candidates = $by_name[ $name ];
				$source     = 'name';
			} elseif ( isset( $by_display[ $name ] ) ) {
				$candidates = $by_display[ $name ];
				$source     = 'display_name';
			} else {
				$candidates = array();
				$source     = '';
			}

			$candidates = array_values( array_unique( $candidates ) );
			$rows[]     = array(
				'staff_id'   => $record['id'],
				'staff_name' => $record['name'],
				'status'     => match ( count( $candidates ) ) {
					0       => self::MISS,
					1       => self::MATCH,
					default => self::AMBIGUOUS,
				},
				'user_ids'   => $candidates,
				'source'     => $source,
			);
		}

		$claims = array();
		foreach ( $rows as $row ) {
			if ( self::MATCH === $row['status'] ) {
				$claims[ $row['user_ids'][0] ] = ( $claims[ $row['user_ids'][0] ] ?? 0 ) + 1;
			}
		}
		foreach ( $rows as &$row ) {
			if ( self::MATCH === $row['status'] && $claims[ $row['user_ids'][0] ] > 1 ) {
				$row['status'] = self::AMBIGUOUS;
			}
		}
		unset( $row );

		return $rows;
	}

	/**
	 * Case- and whitespace-insensitive name key.
	 *
	 * @param string $name Name.
	 */
	private static function normalize( string $name ): string {
		return mb_strtolower( trim( (string) preg_replace( '/\s+/u', ' ', $name ) ) );
	}
}
