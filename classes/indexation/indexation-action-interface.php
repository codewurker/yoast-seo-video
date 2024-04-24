<?php
/**
 * Yoast SEO Video plugin file.
 *
 * @package    WordPress SEO
 * @subpackage WordPress SEO Video
 */

/**
 * Interface definition of reindexing action for indexables.
 */
interface WPSEO_Video_Indexation_Action_Interface {

	/**
	 * Returns the name of the represented indexation action.
	 *
	 * @return string The name of the represented indexation action.
	 */
	public function get_name();

	/**
	 * Returns the total number of objects.
	 *
	 * @return int The total number of objects.
	 */
	public function get_total();

	/**
	 * Indexes a number of objects.
	 *
	 * @param int  $limit   The limit per query.
	 * @param int  $offset  The offset of the query.
	 * @param bool $reindex Whether to force reindex.
	 */
	public function index( $limit, $offset, $reindex );
}
