<?php
/**
 * REST API class.
 *
 * @package PRC\Platform\Staff_Bylines
 */

namespace PRC\Platform\Staff_Bylines;

/**
 * REST API class.
 */
class REST_API {

	/**
	 * Constructor.
	 *
	 * @param Loader $loader The loader.
	 */
	public function __construct( $loader ) {
		$loader->add_action( 'rest_api_init', $this, 'add_staff_info_term' );
	}

	/**
	 * Add constructed staff info to the byline term object and staff post object in the rest api.
	 *
	 * @hook rest_api_init
	 * @return void
	 */
	public function add_staff_info_term() {
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
	 * Get staff info for the byline term.
	 *
	 * @param mixed $object The object.
	 * @return array The staff info.
	 */
	public function get_staff_info_for_byline_term( $object ) {
		return $this->get_staff_info_for_api( $object, Content_Type::$taxonomy_object_name );
	}

	/**
	 * Get staff info for the staff post.
	 *
	 * @param mixed $object The object.
	 * @return array The staff info.
	 */
	public function get_staff_info_for_staff_post( $object ) {
		return $this->get_staff_info_for_api( $object, Content_Type::$post_object_name );
	}

	/**
	 * Get staff info for the rest api.
	 *
	 * @param mixed  $object The object.
	 * @param string $type The type.
	 * @return array The staff info.
	 */
	private function get_staff_info_for_api( $object, $type ) {
		$byline_term_id = false;
		$staff_post_id  = false;
		if ( $type && Content_Type::$post_object_name === $type ) {
			$staff_post_id = $object['id'];
		} else {
			$byline_term_id = $object['id'];
		}

		$staff = new Staff( $staff_post_id, $byline_term_id );
		if ( is_wp_error( $staff ) ) {
			return $object;
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
