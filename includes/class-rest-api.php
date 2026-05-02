<?php
declare(strict_types=1);
/**
 * REST API class.
 *
 * @package PRC\Platform\Staff_Bylines
 */

namespace PRC\Platform\Staff_Bylines;

use WP_Error;
use WP_Post;

/**
 * REST API class.
 */
class REST_API {

	/**
	 * Constructor.
	 *
	 * @param Loader $loader The loader.
	 */
	public function __construct( Loader $loader ) {
		$loader->add_action( 'rest_api_init', $this, 'add_staff_info_term' );
		// Late: Content_Type::init runs at init:5 so post types exist before core rest_api_init.
		$loader->add_action( 'rest_api_init', $this, 'register_ordered_bylines_rest_fields', 99 );
	}

	/**
	 * Add constructed staff info to the byline term object and staff post object in the rest api.
	 *
	 * @hook rest_api_init
	 * @return void
	 */
	public function add_staff_info_term(): void {
		register_rest_field(
			Content_Type::$taxonomy_object_name,
			'staffInfo',
			array(
				'get_callback' => array( $this, 'get_staff_info_for_byline_term' ),
			)
		);
		// Currently this is only used on the mini staff block.
		register_rest_field(
			Content_Type::$post_object_name,
			'staffInfo',
			array(
				'get_callback' => array( $this, 'get_staff_info_for_staff_post' ),
			)
		);
	}

	/**
	 * Register ordered bylines / acknowledgements REST fields (editor uses these with a single editPost).
	 *
	 * @hook rest_api_init
	 * @return void
	 */
	public function register_ordered_bylines_rest_fields(): void {
		$schema = array(
			'description' => 'Ordered byline rows { key, termId } for display and RTC.',
			'type'        => 'array',
			'items'       => Content_Type::$field_schema['items'],
		);

		foreach ( Content_Type::get_enabled_post_types() as $post_type ) {
			register_rest_field(
				$post_type,
				'bylinesOrdered',
				array(
					'get_callback'    => array( $this, 'get_bylines_ordered_for_rest' ),
					'update_callback' => array( $this, 'update_bylines_ordered_from_rest' ),
					'schema'          => $schema,
				)
			);

			register_rest_field(
				$post_type,
				'acknowledgementsOrdered',
				array(
					'get_callback'    => array( $this, 'get_acknowledgements_ordered_for_rest' ),
					'update_callback' => array( $this, 'update_acknowledgements_ordered_from_rest' ),
					'schema'          => $schema,
				)
			);
		}
	}

	/**
	 * Get post ID from REST object (array or WP_Post).
	 *
	 * @param mixed $object The object.
	 * @return int Post ID or 0.
	 */
	private function get_post_id_from_rest_object( mixed $object ): int {
		if ( $object instanceof WP_Post ) {
			return (int) $object->ID;
		}
		if ( is_array( $object ) && isset( $object['id'] ) ) {
			return (int) $object['id'];
		}
		return 0;
	}

	/**
	 * Sanitize ordered { key, termId }[] from REST.
	 *
	 * @param mixed $value Raw value.
	 * @return array<int,array{key:string,termId:int}>
	 */
	private function sanitize_ordered_bylines_array( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$out = array();
		foreach ( $value as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$key     = isset( $row['key'] ) ? (string) $row['key'] : '';
			$term_id = isset( $row['termId'] ) ? (int) $row['termId'] : 0;
			if ( $term_id <= 0 ) {
				continue;
			}
			$out[] = array(
				'key'    => $key,
				'termId' => $term_id,
			);
		}
		return $out;
	}

	/**
	 * Sync the bylines taxonomy terms from post meta (bylines + acknowledgements order preserved in meta only).
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function sync_bylines_taxonomy_for_post( int $post_id ): void {
		$bylines = get_post_meta( $post_id, 'bylines', true );
		$acks    = get_post_meta( $post_id, 'acknowledgements', true );
		if ( ! is_array( $bylines ) ) {
			$bylines = array();
		}
		if ( ! is_array( $acks ) ) {
			$acks = array();
		}
		$term_ids = array();
		foreach ( $bylines as $row ) {
			if ( is_array( $row ) && isset( $row['termId'] ) ) {
				$tid = (int) $row['termId'];
				if ( $tid > 0 ) {
					$term_ids[] = $tid;
				}
			}
		}
		foreach ( $acks as $row ) {
			if ( is_array( $row ) && isset( $row['termId'] ) ) {
				$tid = (int) $row['termId'];
				if ( $tid > 0 ) {
					$term_ids[] = $tid;
				}
			}
		}
		$term_ids = array_values( array_unique( $term_ids ) );
		wp_set_object_terms( $post_id, $term_ids, Content_Type::$taxonomy_object_name, false );
	}

	/**
	 * REST get: bylinesOrdered.
	 *
	 * @param mixed $object Prepared post.
	 * @return array<int,array{key:string,termId:int}>
	 */
	public function get_bylines_ordered_for_rest( mixed $object ): array {
		$post_id = $this->get_post_id_from_rest_object( $object );
		if ( $post_id <= 0 ) {
			return array();
		}
		$raw = get_post_meta( $post_id, 'bylines', true );
		return is_array( $raw ) ? $this->sanitize_ordered_bylines_array( $raw ) : array();
	}

	/**
	 * REST get: acknowledgementsOrdered.
	 *
	 * @param mixed $object Prepared post.
	 * @return array<int,array{key:string,termId:int}>
	 */
	public function get_acknowledgements_ordered_for_rest( mixed $object ): array {
		$post_id = $this->get_post_id_from_rest_object( $object );
		if ( $post_id <= 0 ) {
			return array();
		}
		$raw = get_post_meta( $post_id, 'acknowledgements', true );
		return is_array( $raw ) ? $this->sanitize_ordered_bylines_array( $raw ) : array();
	}

	/**
	 * REST update: bylinesOrdered — persist meta and sync taxonomy.
	 *
	 * @param mixed $value  New value.
	 * @param mixed $object Post object.
	 * @return bool|WP_Error
	 */
	public function update_bylines_ordered_from_rest( mixed $value, mixed $object ): bool|WP_Error {
		$post_id = $this->get_post_id_from_rest_object( $object );
		if ( $post_id <= 0 ) {
			return new WP_Error( 'invalid_post', 'Invalid post for bylinesOrdered.' );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'rest_forbidden', 'Sorry, you are not allowed to edit this post.' );
		}
		$sanitized = $this->sanitize_ordered_bylines_array( $value );
		update_post_meta( $post_id, 'bylines', $sanitized );
		$this->sync_bylines_taxonomy_for_post( $post_id );
		return true;
	}

	/**
	 * REST update: acknowledgementsOrdered — persist meta and sync taxonomy.
	 *
	 * @param mixed $value  New value.
	 * @param mixed $object Post object.
	 * @return bool|WP_Error
	 */
	public function update_acknowledgements_ordered_from_rest( mixed $value, mixed $object ): bool|WP_Error {
		$post_id = $this->get_post_id_from_rest_object( $object );
		if ( $post_id <= 0 ) {
			return new WP_Error( 'invalid_post', 'Invalid post for acknowledgementsOrdered.' );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'rest_forbidden', 'Sorry, you are not allowed to edit this post.' );
		}
		$sanitized = $this->sanitize_ordered_bylines_array( $value );
		update_post_meta( $post_id, 'acknowledgements', $sanitized );
		$this->sync_bylines_taxonomy_for_post( $post_id );
		return true;
	}

	/**
	 * Get staff info for the byline term.
	 *
	 * @param mixed $object The object.
	 * @return array The staff info.
	 */
	public function get_staff_info_for_byline_term( mixed $object ): array {
		return $this->get_staff_info_for_api( $object, Content_Type::$taxonomy_object_name );
	}

	/**
	 * Get staff info for the staff post.
	 *
	 * @param mixed $object The object.
	 * @return array The staff info.
	 */
	public function get_staff_info_for_staff_post( mixed $object ): array {
		return $this->get_staff_info_for_api( $object, Content_Type::$post_object_name );
	}

	/**
	 * Get staff info for the rest api.
	 *
	 * @param mixed  $object The object.
	 * @param string $type The type.
	 * @return array The staff info.
	 */
	private function get_staff_info_for_api( mixed $object, string $type ): array {
		$byline_term_id = false;
		$staff_post_id  = false;
		if ( $type && Content_Type::$post_object_name === $type ) {
			$staff_post_id = $object['id'];
		} else {
			$byline_term_id = $object['id'];
		}

		$staff = new Staff( $staff_post_id, $byline_term_id );
		if ( is_wp_error( $staff ) ) {
			return (array) $object;
		}
		$staff_data = get_object_vars( $staff );

		$staff_link         = $staff_data['link'];
		$staff_name_as_link = wp_sprintf(
			'<a href="%1$s">%2$s</a>&nbsp;',
			$staff_link,
			$staff_data['name']
		);

		$data = array(
			'staffName'             => $staff_data['name'],
			'staffJobTitle'         => $staff_data['job_title'],
			'staffImage'            => $staff_data['photo'],
			'staffTwitter'          => null,
			'staffExpertise'        => $staff_data['expertise'],
			'staffBio'              => $staff_data['bio'],
			'staffBioShort'         => $staff_name_as_link . ' is ' . $staff_data['job_title_extended'],
			'staffJobTitleExtended' => $staff_data['job_title_extended'],
			'staffLink'             => $staff_data['link'],
		);

		return $data;
	}
}
