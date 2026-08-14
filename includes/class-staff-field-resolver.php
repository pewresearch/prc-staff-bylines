<?php
declare(strict_types=1);
/**
 * Resolves staff field values for bindings and block bits.
 *
 * @package PRC\Platform\Staff_Bylines
 */

namespace PRC\Platform\Staff_Bylines;

/**
 * Shared staff field resolution used by bindings and inline bits.
 */
class Staff_Field_Resolver {

	/**
	 * Field manifest for prc-platform/staff-info (matches JS STAFF_INFO_BINDING_FIELDS).
	 *
	 * @return array<int, array{label: string, type: string, args: array<string, mixed>}>
	 */
	public static function get_binding_fields(): array {
		return array(
			array(
				'label' => __( 'Name', 'prc-staff-bylines' ),
				'type'  => 'string',
				'args'  => array( 'valueToFetch' => 'name' ),
			),
			array(
				'label' => __( 'Name (linked)', 'prc-staff-bylines' ),
				'type'  => 'string',
				'args'  => array(
					'valueToFetch' => 'name',
					'outputLink'   => true,
				),
			),
			array(
				'label' => __( 'Job Title', 'prc-staff-bylines' ),
				'type'  => 'string',
				'args'  => array( 'valueToFetch' => 'job_title' ),
			),
			array(
				'label' => __( 'Job Title Extended', 'prc-staff-bylines' ),
				'type'  => 'string',
				'args'  => array( 'valueToFetch' => 'job_title_extended' ),
			),
			array(
				'label' => __( 'Mini Bio', 'prc-staff-bylines' ),
				'type'  => 'string',
				'args'  => array( 'valueToFetch' => 'mini_bio' ),
			),
			array(
				'label' => __( 'Bio', 'prc-staff-bylines' ),
				'type'  => 'string',
				'args'  => array( 'valueToFetch' => 'bio' ),
			),
			array(
				'label' => __( 'Expertise', 'prc-staff-bylines' ),
				'type'  => 'string',
				'args'  => array( 'valueToFetch' => 'expertise' ),
			),
			array(
				'label' => __( 'Name and Job Title', 'prc-staff-bylines' ),
				'type'  => 'string',
				'args'  => array( 'valueToFetch' => 'name_and_job_title' ),
			),
			array(
				'label' => __( 'Photo URL', 'prc-staff-bylines' ),
				'type'  => 'string',
				'args'  => array( 'valueToFetch' => 'photo' ),
			),
			array(
				'label' => __( 'Photo Title', 'prc-staff-bylines' ),
				'type'  => 'string',
				'args'  => array( 'valueToFetch' => 'photo' ),
			),
			array(
				'label' => __( 'Photo Alt', 'prc-staff-bylines' ),
				'type'  => 'string',
				'args'  => array( 'valueToFetch' => 'photo' ),
			),
			array(
				'label' => __( 'Download Photo Text', 'prc-staff-bylines' ),
				'type'  => 'string',
				'args'  => array( 'valueToFetch' => 'photo-full-download-text' ),
			),
			array(
				'label' => __( 'Download Photo URL', 'prc-staff-bylines' ),
				'type'  => 'string',
				'args'  => array( 'valueToFetch' => 'photo-full' ),
			),
		);
	}

	/**
	 * Resolve a staff member record from a staffId context value.
	 *
	 * @param int|string|false $staff_id Staff ID or guest_* term key.
	 * @return array<string, mixed>|null
	 */
	public static function resolve_staff_record( int|string|false $staff_id ): ?array {
		if ( false === $staff_id ) {
			return null;
		}

		if ( is_string( $staff_id ) && str_starts_with( $staff_id, 'guest_' ) ) {
			$term_id = (int) str_replace( 'guest_', '', $staff_id );
			$staff   = new Staff( false, $term_id );
		} elseif ( is_numeric( $staff_id ) ) {
			$staff = new Staff( (int) $staff_id );
		} else {
			return null;
		}

		if ( ! $staff->is_resolved() ) {
			return null;
		}

		return get_object_vars( $staff );
	}

	/**
	 * Resolve a bound attribute value from staff data.
	 *
	 * @param array<string, mixed> $staff_record Staff object vars.
	 * @param array<string, mixed> $source_args  Binding source args.
	 * @param string               $block_name   Block name.
	 * @param string               $attribute_name Bound attribute.
	 * @return mixed
	 */
	public static function resolve_binding_value( array $staff_record, array $source_args, string $block_name, string $attribute_name ): mixed {
		if ( ! in_array( $block_name, array( 'core/image', 'core/paragraph', 'core/heading', 'core/button' ), true ) ) {
			return null;
		}

		$value_to_fetch = array_key_exists( 'valueToFetch', $source_args ) ? $source_args['valueToFetch'] : null;
		if ( null === $value_to_fetch ) {
			return null;
		}

		$output_link      = array_key_exists( 'outputLink', $source_args );
		$value_to_replace = null;

		if ( 'photo-full' === $value_to_fetch && isset( $staff_record['photo']['full'][0] ) ) {
			if ( 'url' === $attribute_name ) {
				$value_to_replace = $staff_record['photo']['full'][0];
			}
		}
		if ( 'photo-full-download-text' === $value_to_fetch ) {
			if ( ! empty( $staff_record['photo'] ) && 'text' === $attribute_name ) {
				$value_to_replace = wp_sprintf(
					'Download %1$s\'s photo',
					$staff_record['name']
				);
			} elseif ( 'text' === $attribute_name ) {
				$value_to_replace = null;
			}
		}

		if ( 'photo' === $value_to_fetch && isset( $staff_record['photo']['thumbnail'][0] ) ) {
			if ( 'url' === $attribute_name ) {
				$value_to_replace = $staff_record['photo']['thumbnail'][0];
			}
			if ( 'title' === $attribute_name ) {
				$value_to_replace = wp_sprintf(
					'Photo of %1$s',
					$staff_record['name']
				);
			}
			if ( 'alt' === $attribute_name ) {
				$value_to_replace = wp_sprintf(
					'Download %1$s\'s photo',
					$staff_record['name']
				);
			}
		}
		if ( 'bio' === $value_to_fetch && isset( $staff_record['bio'] ) && ! empty( $staff_record['bio'] ) ) {
			$value_to_replace = $staff_record['bio'];
		}
		if ( 'bio' === $value_to_fetch && empty( $staff_record['bio'] ) ) {
			$value_to_replace = $staff_record['mini_bio'];
		}
		if ( 'mini_bio' === $value_to_fetch && isset( $staff_record['mini_bio'] ) ) {
			$value_to_replace = $staff_record['mini_bio'];
		}
		if ( 'name' === $value_to_fetch && isset( $staff_record['name'] ) ) {
			$value_to_replace = $staff_record['name'];
		}
		if ( 'job_title' === $value_to_fetch && isset( $staff_record['job_title'] ) ) {
			$value_to_replace = $staff_record['job_title'];
		}
		if ( 'job_title_extended' === $value_to_fetch && isset( $staff_record['job_title_extended'] ) ) {
			$value_to_replace = $staff_record['job_title'];
		}
		if ( true === $output_link && isset( $staff_record['link'] ) && false !== $staff_record['link'] ) {
			$value_to_replace = wp_sprintf(
				'<a href="%1$s">%2$s</a>',
				$staff_record['link'],
				$value_to_replace
			);
		}
		if ( 'expertise' === $value_to_fetch && ! empty( $staff_record['expertise'] ) ) {
			$expertise = $staff_record['expertise'];
			$tmp       = '<span class="wp-block-prc-block-staff-context-provider__expertise-label">Expertise:</span>';
			$total     = count( $expertise );
			$sep       = $total > 1 ? ', ' : '';
			$i         = 1;
			foreach ( $expertise as $term ) {
				if ( $i === $total ) {
					$sep = '';
				}
				$tmp .= wp_sprintf(
					'<a class="wp-block-prc-block-staff-context-provider__expertise-link" href="%1$s">%2$s</a>%3$s',
					$term['url'],
					$term['label'],
					$sep
				);
				++$i;
			}
			$value_to_replace = $tmp;
		}
		if ( 'expertise' === $value_to_fetch && empty( $staff_record['expertise'] ) ) {
			$value_to_replace = '';
		}
		if ( 'name_and_job_title' === $value_to_fetch && ! empty( $staff_record['name'] ) && ! empty( $staff_record['job_title'] ) ) {
			$name      = $staff_record['name'];
			$job_title = $staff_record['job_title'];
			$link      = $staff_record['link'];
			if ( empty( $link ) ) {
				$value_to_replace = wp_sprintf(
					'<strong>%1$s</strong>, %2$s',
					$name,
					$job_title
				);
			} else {
				$value_to_replace = wp_sprintf(
					'<strong><a href="%2$s">%1$s</a></strong>, %3$s',
					$name,
					$link,
					$job_title
				);
			}
		}

		return $value_to_replace;
	}

	/**
	 * Resolve a plain-text staff field for inline bits.
	 *
	 * @param array<string, mixed> $staff_record Staff object vars.
	 * @param string               $field        Field key: name, job_title, mini_bio.
	 * @return string|null
	 */
	public static function resolve_bit_field( array $staff_record, string $field ): ?string {
		if ( 'name' === $field && ! empty( $staff_record['name'] ) ) {
			return (string) $staff_record['name'];
		}
		if ( 'job_title' === $field && ! empty( $staff_record['job_title'] ) ) {
			return (string) $staff_record['job_title'];
		}
		if ( 'mini_bio' === $field && ! empty( $staff_record['mini_bio'] ) ) {
			return (string) $staff_record['mini_bio'];
		}
		return null;
	}
}
