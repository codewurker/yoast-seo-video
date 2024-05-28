<?php
/**
 * Yoast SEO Video plugin file.
 *
 * @package Yoast\VideoSEO
 */

/**
 * Class WPSEO_Video_Wrappers
 *
 * @since 2.0.3
 */
class WPSEO_Video_Wrappers {

	/**
	 * Fallback function for WP SEO functionality, Validate INT
	 *
	 * @since 2.0.3
	 *
	 * @param int $integer Number to validate.
	 *
	 * @return int|bool Int or false in case of failure to convert to int.
	 */
	public static function yoast_wpseo_video_validate_int( $integer ) {
		// WPSEO 1.8+.
		if ( method_exists( 'WPSEO_Utils', 'validate_int' ) ) {
			return WPSEO_Utils::validate_int( $integer );
		}

		return WPSEO_Option::validate_int( $integer );
	}

	/**
	 * Fallback function for WP SEO functionality, is_url_relative
	 *
	 * @since 2.0.3
	 *
	 * @param string $url URL to check.
	 *
	 * @return bool
	 */
	public static function yoast_wpseo_video_is_url_relative( $url ) {
		// WPSEO 1.6.1+.
		if ( method_exists( 'WPSEO_Utils', 'is_url_relative' ) ) {
			return WPSEO_Utils::is_url_relative( $url );
		}

		return wpseo_is_url_relative( $url );
	}

	/**
	 * Fallback for WP SEO functionality, sanitize_url
	 *
	 * @since 2.0.3
	 *
	 * @param string $url URL to check.
	 *
	 * @return string
	 */
	public static function yoast_wpseo_video_sanitize_url( $url ) {
		// WPSEO 1.8+.
		if ( method_exists( 'WPSEO_Utils', 'sanitize_url' ) ) {
			return WPSEO_Utils::sanitize_url( $url, [ 'http', 'https', 'ftp', 'ftps' ] );
		}

		return WPSEO_Option::sanitize_url( $url, [ 'http', 'https', 'ftp', 'ftps' ] );
	}

	/**
	 * Returns the result of validate bool from WPSEO_Utils if this class exists, otherwise it will return the result from
	 * validate_bool from WPSEO_Option_Video
	 *
	 * @since 2.0.3
	 *
	 * @param bool $bool_to_validate Validate bool.
	 *
	 * @return bool
	 */
	public static function validate_bool( $bool_to_validate ) {
		// WPSEO 1.8+.
		if ( class_exists( 'WPSEO_Utils' ) && method_exists( 'WPSEO_Utils', 'validate_bool' ) ) {
			return WPSEO_Utils::validate_bool( $bool_to_validate );
		}

		return WPSEO_Option_Video::validate_bool( $bool_to_validate );
	}

	/**
	 * Wrapper function to check if we have a valid datetime.
	 *
	 * @since 2.0.3
	 * @since 4.1   Moved from the WPSEO_Video_Sitemap class to this one.
	 *
	 * @param string $datetime Date Time.
	 *
	 * @return bool
	 */
	public static function is_valid_datetime( $datetime ) {
		// WPSEO 2.0+.
		if ( method_exists( 'WPSEO_Utils', 'is_valid_datetime' ) ) {
			return WPSEO_Utils::is_valid_datetime( $datetime );
		}

		return true;
	}

	/**
	 * Call WPSEO_Sitemaps::register_sitemap() if the method exists.
	 *
	 * @since 4.1
	 *
	 * @param string   $name     The name of the sitemap.
	 * @param callable $callback Function to build your sitemap.
	 * @param string   $rewrite  Optional. Regular expression to match your sitemap with.
	 *
	 * @return void
	 */
	public static function register_sitemap( $name, $callback, $rewrite = '' ) {
		// WPSEO 1.4.23+.
		if ( isset( $GLOBALS['wpseo_sitemaps'] ) && is_object( $GLOBALS['wpseo_sitemaps'] ) && method_exists( 'WPSEO_Sitemaps', 'register_sitemap' ) ) {
			$GLOBALS['wpseo_sitemaps']->register_sitemap( $name, $callback, $rewrite );
		}
	}

	/**
	 * Call WPSEO_Sitemaps::register_xsl() if the method exists.
	 *
	 * @since 4.1
	 *
	 * @param string   $name     The name of the XSL file.
	 * @param callable $callback Function to build your XSL file.
	 * @param string   $rewrite  Optional. Regular expression to match your sitemap with.
	 *
	 * @return void
	 */
	public static function register_xsl( $name, $callback, $rewrite = '' ) {
		// WPSEO 1.4.23+.
		if ( isset( $GLOBALS['wpseo_sitemaps'] ) && is_object( $GLOBALS['wpseo_sitemaps'] ) && method_exists( 'WPSEO_Sitemaps', 'register_xsl' ) ) {
			$GLOBALS['wpseo_sitemaps']->register_xsl( $name, $callback, $rewrite );
		}
	}

	/**
	 * Call WPSEO_Sitemaps::set_sitemap() if the method exists.
	 *
	 * @since 4.1
	 *
	 * @param string $sitemap The generated sitemap to output.
	 *
	 * @return void
	 */
	public static function set_sitemap( $sitemap ) {
		// WPSEO 1.4.23+.
		if ( isset( $GLOBALS['wpseo_sitemaps'] ) && is_object( $GLOBALS['wpseo_sitemaps'] ) && method_exists( 'WPSEO_Sitemaps', 'set_sitemap' ) ) {
			$GLOBALS['wpseo_sitemaps']->set_sitemap( $sitemap );
		}
	}

	/**
	 * Call WPSEO_Sitemaps::set_stylesheet() if the method exists.
	 *
	 * @since 4.1
	 *
	 * @param string $stylesheet Full xml-stylesheet declaration.
	 *
	 * @return void
	 */
	public static function set_stylesheet( $stylesheet ) {
		if ( isset( $GLOBALS['wpseo_sitemaps'] ) && is_object( $GLOBALS['wpseo_sitemaps'] ) ) {

			// WPSEO 3.2+.
			if ( method_exists( 'WPSEO_Sitemaps_Renderer', 'set_stylesheet' ) && property_exists( $GLOBALS['wpseo_sitemaps'], 'renderer' ) && ( $GLOBALS['wpseo_sitemaps']->renderer instanceof WPSEO_Sitemaps_Renderer ) ) {
				$GLOBALS['wpseo_sitemaps']->renderer->set_stylesheet( $stylesheet );
				return;
			}

			// WPSEO 1.4.23+.
			if ( method_exists( $GLOBALS['wpseo_sitemaps'], 'set_stylesheet' ) ) {
				$GLOBALS['wpseo_sitemaps']->set_stylesheet( $stylesheet );
				return;
			}
		}
	}

	/**
	 * Returns the result of WPSEO_Utils::is_development_mode() if the method exists.
	 *
	 * @since 4.1
	 *
	 * @return bool
	 */
	public static function is_development_mode() {
		// WPSEO 3.0+.
		if ( method_exists( 'WPSEO_Utils', 'is_development_mode' ) ) {
			return WPSEO_Utils::is_development_mode();
		}

		return false;
	}

	/**
	 * Returns the result of get_base_url from WPSEO_Sitemaps_Router if the method exists,
	 * otherwise it will return the result from the deprecated wpseo_xml_sitemaps_base_url() function.
	 *
	 * @since 4.1
	 *
	 * @param string $sitemap Sitemap file name.
	 *
	 * @return string
	 */
	public static function xml_sitemaps_base_url( $sitemap ) {
		// WPSEO 3.2+.
		if ( method_exists( 'WPSEO_Sitemaps_Router', 'get_base_url' ) ) {
			return WPSEO_Sitemaps_Router::get_base_url( $sitemap );
		}

		if ( function_exists( 'wpseo_xml_sitemaps_base_url' ) ) {
			return wpseo_xml_sitemaps_base_url( $sitemap );
		}
	}

	/**
	 * Call WPSEO_Sitemaps::ping_search_engines() if the method exists,
	 * otherwise it will call the deprecated wpseo_ping_search_engines() function.
	 *
	 * @since 4.1
	 *
	 * @param string|null $sitemapurl Sitemap URL.
	 *
	 * @return void
	 */
	public static function ping_search_engines( $sitemapurl = null ) {
		// WPSEO 19.2+.
		if ( method_exists( 'WPSEO_Sitemaps_Admin', 'ping_search_engines' ) ) {
			$admin = new WPSEO_Sitemaps_Admin();
			$admin->ping_search_engines();
			return;
		}

		// WPSEO 3.2+.
		if ( method_exists( 'WPSEO_Sitemaps', 'ping_search_engines' ) ) {
			WPSEO_Sitemaps::ping_search_engines( $sitemapurl );
			return;
		}

		if ( function_exists( 'wpseo_ping_search_engines' ) ) {
			wpseo_ping_search_engines( $sitemapurl );
			return;
		}
	}

	/**
	 * Wrapper function to invalidate a cached sitemap.
	 *
	 * @since 4.1
	 *
	 * @param string|null $type The type to get the key for. Null for all caches.
	 *
	 * @return void
	 */
	public static function invalidate_cache_storage( $type = null ) {
		// WPSEO 3.2+.
		if ( method_exists( 'WPSEO_Sitemaps_Cache_Validator', 'invalidate_storage' ) ) {
			WPSEO_Sitemaps_Cache_Validator::invalidate_storage( $type );
			return;
		}

		// WPSEO 1.8.0+.
		if ( method_exists( 'WPSEO_Utils', 'clear_sitemap_cache' ) ) {
			WPSEO_Utils::clear_sitemap_cache( $type );
			return;
		}
	}

	/**
	 * Wrapper function to invalidate a sitemap type.
	 *
	 * @since 4.1
	 *
	 * @param string $type Sitemap type to invalidate.
	 *
	 * @return void
	 */
	public static function invalidate_sitemap( $type ) {
		// WPSEO 3.2+.
		if ( method_exists( 'WPSEO_Sitemaps_Cache', 'invalidate' ) ) {
			WPSEO_Sitemaps_Cache::invalidate( $type );
			return;
		}

		// WPSEO 1.5.4+.
		if ( function_exists( 'wpseo_invalidate_sitemap_cache' ) ) {
			wpseo_invalidate_sitemap_cache( $type );
			return;
		}
	}

	/**
	 * Call WPSEO_Sitemaps_Cache::register_clear_on_option_update() if the method exists,
	 * otherwise it will call the deprecated WPSEO_Utils::register_cache_clear_option() function.
	 *
	 * @since 4.1
	 *
	 * @param string $option Option name.
	 * @param string $type   Sitemap type.
	 *
	 * @return void
	 */
	public static function register_cache_clear_option( $option, $type = '' ) {
		// WPSEO 3.2+.
		if ( method_exists( 'WPSEO_Sitemaps_Cache', 'register_clear_on_option_update' ) ) {
			WPSEO_Sitemaps_Cache::register_clear_on_option_update( $option, $type );
			return;
		}

		// WPSEO 2.2+.
		if ( method_exists( 'WPSEO_Utils', 'register_cache_clear_option' ) ) {
			WPSEO_Utils::register_cache_clear_option( $option, $type );
			return;
		}
	}
}
