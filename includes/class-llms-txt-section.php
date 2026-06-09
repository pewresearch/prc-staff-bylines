<?php
/**
 * Registers Researchers in /llms.txt.
 *
 * @package PRC\Platform\Staff_Bylines
 */

declare( strict_types=1 );

namespace PRC\Platform\Staff_Bylines;

use PRC\Platform\Markdown_For_Agents\LLMs_Txt;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contributes the Researchers section from the staff CPT.
 */
class Llms_Txt_Section {

	/**
	 * @param Loader $loader Loader instance.
	 */
	public function __construct( Loader $loader ) {
		unset( $loader );
		add_post_type_support( Content_Type::$post_object_name, 'prc-markdown-for-agents-llms-txt' );
		add_filter( 'prc_markdown_for_agents_llms_txt_sections', array( $this, 'register_section' ) );
	}

	/**
	 * @param array<int, array<string, mixed>> $sections Existing sections.
	 * @return array<int, array<string, mixed>>
	 */
	public function register_section( array $sections ): array {
		$links = $this->get_researcher_links();
		if ( empty( $links ) ) {
			return $sections;
		}

		$sections[] = array(
			'slug'        => 'researchers',
			'title'       => __( 'Researchers', 'prc-staff-bylines' ),
			'description' => __( 'Pew Research Center experts and researchers.', 'prc-staff-bylines' ),
			'links'       => $links,
		);

		return $sections;
	}

	/**
	 * @return array<int, array<string, string>>
	 */
	private function get_researcher_links(): array {
		$total  = 0;
		$links  = array();
		$paged  = 1;
		$batch  = 100;
		$has_more = true;

		while ( $has_more ) {
			$query = new \WP_Query(
				array(
					'post_type'              => Content_Type::$post_object_name,
					'post_status'            => 'publish',
					'posts_per_page'         => $batch,
					'paged'                  => $paged,
					'orderby'                => 'title',
					'order'                  => 'ASC',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => true,
					'fields'                 => 'ids',
				)
			);

			if ( empty( $query->posts ) ) {
				break;
			}

			foreach ( $query->posts as $post_id ) {
				$staff = new Staff( (int) $post_id );
				if ( ! $staff->is_currently_employed ) {
					continue;
				}

				++$total;

				if ( count( $links ) >= LLMs_Txt::SECTION_CAP ) {
					continue;
				}

				$post = get_post( (int) $post_id );
				if ( ! $post instanceof \WP_Post ) {
					continue;
				}

				$permalink = get_permalink( $post );
				if ( ! $permalink ) {
					continue;
				}

				$job_title = $staff->job_title;
				$link      = array(
					'title' => html_entity_decode( get_the_title( $post ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
					'url'   => $permalink,
				);
				if ( is_string( $job_title ) && '' !== trim( $job_title ) ) {
					$link['description'] = trim( $job_title );
				}
				$links[] = $link;
			}

			$has_more = count( $query->posts ) === $batch;
			++$paged;
		}

		$archive_url = home_url( '/staff/' );

		return LLMs_Txt::maybe_append_see_all_link( $links, $total, $archive_url );
	}
}
