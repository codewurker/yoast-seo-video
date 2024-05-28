<?php
/**
 * Yoast SEO Video plugin file.
 *
 * @package    WordPress SEO
 * @subpackage WordPress SEO Video
 */

/**
 * Index post videos.
 *
 * Post as in, a post under any post type.
 * Not to be confused with the specific post type "post".
 */
class WPSEO_Video_Post_Indexation_Action implements WPSEO_Video_Indexation_Action_Interface {

	/**
	 * Holds the WPSEO_Video_Sitemap.
	 *
	 * @var WPSEO_Video_Sitemap
	 */
	private $sitemap;

	/**
	 * Constructs WPSEO_Video_Post_Indexation_Action.
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
		return 'posts';
	}

	/**
	 * Returns the total number of posts.
	 *
	 * @return int The total number of posts.
	 */
	public function get_total() {
		$total      = 0;
		$post_types = $this->get_post_types();
		foreach ( $post_types as $post_type ) {
			$total += (int) wp_count_posts( $post_type )->publish;
		}

		return $total;
	}

	/**
	 * Index the video info from posts.
	 *
	 * @param int  $limit   The limit per query.
	 * @param int  $offset  The offset of the query.
	 * @param bool $reindex Whether to force reindex.
	 *
	 * @return void
	 */
	public function index( $limit, $offset, $reindex ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$post_types = $this->get_post_types();
		if ( $post_types === [] ) {
			return;
		}

		$query = [
			'post_type'   => $post_types,
			'post_status' => 'publish',
			'numberposts' => $limit,
			'offset'      => $offset,
		];
		if ( ! $reindex ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- no other way to do this.
			$query['meta_query'] = [
				'key'     => '_yoast_wpseo_video_meta',
				'compare' => 'NOT EXISTS',
			];
		}

		$results = get_posts( $query );
		if ( is_array( $results ) && count( $results ) > 0 ) {
			if ( $reindex ) {
				// Do this ugly thing instead of refactoring `update_video_post_meta`.
				$_POST['force'] = $reindex;
			}

			foreach ( $results as $post ) {
				if ( $post instanceof WP_Post ) {
					$this->sitemap->update_video_post_meta( $post->ID, $post );
				}
			}
		}
	}

	/**
	 * Retrieves the post types to include in the sitemap.
	 *
	 * @return array The post types to include in the sitemap.
	 */
	private function get_post_types() {
		return (array) WPSEO_Options::get( 'videositemap_posttypes', [] );
	}
}
