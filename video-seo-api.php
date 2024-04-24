<?php
/**
 * Yoast SEO Video plugin file.
 *
 * @package Yoast\VideoSEO
 */

/**
 * Initializes the Video SEO module on plugins loaded.
 *
 * This way WordPress SEO should have set its constants and loaded its main classes.
 *
 * @since 0.2
 */
function yoast_wpseo_video_seo_init() {
	$bootstrap = new WPSEO_Video_Bootstrap();
	$bootstrap->add_hooks();
}

/**
 * Executes option cleanup actions on activate.
 *
 * There are a couple of things being done on activation:
 * - Cleans up the options to be sure it's set well.
 * - Activates the license, because updating the plugin results in deactivating the license.
 * - Clears the sitemap cache to rebuild the sitemap.
 */
function yoast_wpseo_video_activate() {
	WPSEO_Video_Utils::load_textdomain();

	$bootstrap = new WPSEO_Video_Bootstrap();
	if ( ! $bootstrap->is_yoast_seo_active() ) {
		return;
	}

	$option_instance = WPSEO_Option_Video::get_instance();
	$option_instance->clean();

	// Enable tracking.
	WPSEO_Options::set( 'tracking', true );

	yoast_wpseo_video_clear_sitemap_cache();
}

/**
 * Empties sitemap cache on plugin deactivate.
 *
 * @since 3.8.0
 */
function yoast_wpseo_video_deactivate() {
	yoast_wpseo_video_clear_sitemap_cache();
}

/**
 * Clears the sitemap index.
 *
 * @since 3.8.0
 */
function yoast_wpseo_video_clear_sitemap_cache() {
	$bootstrap = new WPSEO_Video_Bootstrap();
	if ( ! $bootstrap->is_yoast_seo_active() ) {
		return;
	}

	WPSEO_Video_Wrappers::invalidate_sitemap( WPSEO_Video_Sitemap::get_video_sitemap_basename() );
}
