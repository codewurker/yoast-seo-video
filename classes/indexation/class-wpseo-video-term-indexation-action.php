<?php
/**
 * Yoast SEO Video plugin file.
 *
 * @package    WordPress SEO
 * @subpackage WordPress SEO Video
 */

/**
 * Index term videos.
 */
class WPSEO_Video_Term_Indexation_Action implements WPSEO_Video_Indexation_Action_Interface {

	/**
	 * Holds the WPSEO_Video_Sitemap.
	 *
	 * @var WPSEO_Video_Sitemap
	 */
	private $sitemap;

	/**
	 * Constructs WPSEO_Video_Term_Indexation_Action.
	 *
	 * @param WPSEO_Video_Sitemap $sitemap The WPSEO_Video_Sitemap.
	 */
	public function __construct( WPSEO_Video_Sitemap $sitemap ) {
		$this->sitemap = $sitemap;
	}

	/**
	 * Returns the name of the represented indexation action.
	 *
	 * @return string The name of the represented indexation action.
	 */
	public function get_name() {
		return 'terms';
	}

	/**
	 * Returns the total number of terms.
	 *
	 * @return int The total number of terms.
	 */
	public function get_total() {
		// Get all the non-empty terms.
		add_filter( 'terms_clauses', [ $this->sitemap, 'filter_terms_clauses' ] );
		$taxonomies = $this->get_taxonomies();
		$total      = 0;
		if ( $taxonomies !== [] ) {
			$count = wp_count_terms(
				[
					'taxonomy' => array_values( $taxonomies ),
				]
			);
			if ( is_string( $count ) ) {
				$total = (int) $count;
			}
		}
		remove_filter( 'terms_clauses', [ $this->sitemap, 'filter_terms_clauses' ] );

		return $total;
	}

	/**
	 * Index the video info from terms.
	 *
	 * @param int  $limit   The limit per query.
	 * @param int  $offset  The offset of the query.
	 * @param bool $reindex Whether to force reindex.
	 *
	 * @return void
	 */
	public function index( $limit, $offset, $reindex ) {
		// Get all the non-empty terms.
		add_filter( 'terms_clauses', [ $this->sitemap, 'filter_terms_clauses' ] );
		$terms      = [];
		$taxonomies = $this->get_taxonomies();
		if ( $taxonomies !== [] ) {
			$new_terms = get_terms(
				[
					'taxonomy' => array_values( $taxonomies ),
					'number'   => $limit,
					'offset'   => $offset,
				]
			);
			if ( is_array( $new_terms ) ) {
				$terms = $new_terms;
			}
		}
		remove_filter( 'terms_clauses', [ $this->sitemap, 'filter_terms_clauses' ] );

		if ( count( $terms ) > 0 ) {
			if ( $reindex ) {
				// Do this ugly thing instead of refactoring `update_video_term_meta`.
				$_POST['force'] = $reindex;
			}

			foreach ( $terms as $term ) {
				$this->sitemap->update_video_term_meta( $term, false );
				flush();
			}
		}
	}

	/**
	 * Retrieves the taxonomies to include in the sitemap.
	 *
	 * @return array The taxonomies to include in the sitemap.
	 */
	private function get_taxonomies() {
		return (array) WPSEO_Options::get( 'videositemap_taxonomies', [] );
	}
}
