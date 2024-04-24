<?php
/**
 * Yoast SEO Video plugin file.
 *
 * @package    WordPress SEO
 * @subpackage WordPress SEO Video
 */

use WP_CLI\Utils;
use Yoast\WP\SEO\Commands\Command_Interface;
use Yoast\WP\SEO\Main;

/**
 * Command to reindex posts.
 */
class WPSEO_Video_Index_Command implements Command_Interface {

	/**
	 * Holds the indexation actions.
	 *
	 * @var WPSEO_Video_Indexation_Action_Interface[]
	 */
	private $indexation_actions;

	/**
	 * Holds the plugin basename.
	 *
	 * Used to check network installed.
	 *
	 * @var string
	 */
	private $plugin_basename;

	/**
	 * WPSEO_Index_Command constructor.
	 *
	 * @param WPSEO_Video_Indexation_Action_Interface ...$indexation_actions The indexation actions.
	 */
	public function __construct( WPSEO_Video_Indexation_Action_Interface ...$indexation_actions ) {
		$this->indexation_actions = $indexation_actions;
		$this->plugin_basename    = plugin_basename( WPSEO_VIDEO_FILE );
	}

	/**
	 * Gets the namespace.
	 *
	 * @return string
	 */
	public static function get_namespace() {
		return Main::WP_CLI_NAMESPACE . ' video';
	}

	/**
	 * Indexation of videos in your content.
	 *
	 * ## OPTIONS
	 *
	 * [--reindex]
	 * : Force reindex of already indexed videos.
	 *
	 * [--limit=<limit>]
	 * : The number of database records to per SQL query.
	 * ---
	 * default: 25
	 * ---
	 *
	 * [--interval=<interval>]
	 * : The number of microseconds (millionths of a second) to wait between index actions.
	 * ---
	 * default: 50000
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp yoast video index
	 *
	 * @when after_wp_load
	 *
	 * @param array|null $args       The arguments.
	 * @param array|null $assoc_args The associative arguments.
	 *
	 * @return void
	 */
	public function index( $args = null, $assoc_args = null ) {
		$reindex = isset( $assoc_args['reindex'] );
		$limit   = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 25;
		if ( $limit < 1 ) {
			WP_CLI::error( 'The value for \'limit\' must be a positive integer, larger than 0.' );
		}
		$interval = isset( $assoc_args['interval'] ) ? (int) $assoc_args['interval'] : 50000;
		if ( $interval < 0 ) {
			WP_CLI::error( 'The value for \'interval\' must be a positive integer.' );
		}

		$this->index_current_site( $reindex, $limit, $interval );

		WP_CLI::success( 'Done!' );
	}

	/**
	 * Performs the indexation for the current site.
	 *
	 * @param bool $reindex  Whether to force reindex.
	 * @param int  $limit    The limit per query.
	 * @param int  $interval The number of microseconds to sleep.
	 */
	private function index_current_site( $reindex, $limit, $interval ) {
		if ( ! is_plugin_active( $this->plugin_basename ) ) {
			WP_CLI::warning( sprintf( 'Skipping %1$s. Yoast SEO Video is not active on this site.', site_url() ) );

			return;
		}

		foreach ( $this->indexation_actions as $indexation_action ) {
			$this->run_indexation_action( $indexation_action, $limit, $interval, $reindex );
		}

		$this->index_complete();
	}

	/**
	 * Runs an indexation action.
	 *
	 * @param WPSEO_Video_Indexation_Action_Interface $indexation_action The indexation action.
	 * @param int                                     $limit             The limit per query.
	 * @param int                                     $interval          Number of microseconds to sleep.
	 * @param bool                                    $reindex           Whether to force reindex.
	 *
	 * @return void
	 */
	private function run_indexation_action(
		WPSEO_Video_Indexation_Action_Interface $indexation_action,
		$limit,
		$interval,
		$reindex
	) {
		$total = $indexation_action->get_total();
		if ( $total <= 0 ) {
			return;
		}
		$offset   = 0;
		$progress = Utils\make_progress_bar( 'Indexing ' . $indexation_action->get_name(), $total );
		do {
			$indexation_action->index( $limit, $offset, $reindex );
			$progress->tick( $limit );
			$offset += $limit;
			\usleep( $interval );
			Utils\wp_clear_object_cache();
		} while ( $offset <= $total );
		$progress->finish();
	}

	/**
	 * Cleans up after completing indexation on a site.
	 *
	 * @return void
	 */
	private function index_complete() {
		// As this is used from within a CLI command, we don't queue the cache clearing, but do a hard reset.
		WPSEO_Video_Wrappers::invalidate_cache_storage( WPSEO_Video_Sitemap::get_video_sitemap_basename() );

		// Ping the search engines with our updated XML sitemap, we ping with the index sitemap because
		// we don't know which video sitemap, or sitemaps, have been updated / added.
		WPSEO_Video_Wrappers::ping_search_engines();

		// Remove the admin notice.
		delete_transient( 'video_seo_recommend_reindex' );
	}
}
