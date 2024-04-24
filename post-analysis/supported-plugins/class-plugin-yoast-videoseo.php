<?php
/**
 * Yoast SEO Video plugin file.
 *
 * @package    Internals
 * @since      1.8.0
 * @version    1.8.0
 */

// Avoid direct calls to this file.
if ( ! class_exists( 'WPSEO_Video_Sitemap' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

/**
 * Add support for the VideoSEO (==this==) plugin
 *
 * @link https://yoast.com/wordpress/plugins/video-seo/
 *
 * {@internal Last update: August 2014 based upon v 1.8/2.0.}
 */
if ( ! class_exists( 'WPSEO_Video_Plugin_Yoast_Videoseo' ) ) {

	/**
	 * Class WPSEO_Video_Plugin_Yoast_Videoseo
	 */
	class WPSEO_Video_Plugin_Yoast_Videoseo extends WPSEO_Video_Supported_Plugin {

		/**
		 * Conditionally add plugin features to analyse for video content
		 */
		public function __construct() {
			// No need to check that the plugin is really loaded as it's this plugin.
			$meta_keys = [];

			$custom_fields = WPSEO_Options::get( 'video_custom_fields', '' );
			if ( ! empty( $custom_fields ) && is_string( $custom_fields ) ) {
				$meta_keys = (array) explode( ',', $custom_fields );
				$meta_keys = array_map( 'trim', $meta_keys );
				$meta_keys = array_filter( $meta_keys );
			}
			if ( is_array( $meta_keys ) && $meta_keys !== [] ) {
				$this->meta_keys = $meta_keys;
			}

			// OEmbed url (well, without the protocol or {format} tags) as specified in plugin => VideoSEO service name.
			$this->video_oembeds = [
				'//fast.wistia.com/oembed'           => 'wistia',
				'//www.screenr.com/api/oembed'       => 'screenr',
				'//lab.viddler.com/services/oembed/' => 'viddler',
			];

			$evs_location = get_option( 'evs_location' );
			if ( $evs_location && ! empty( $evs_location ) ) {
				$this->video_oembeds[ $evs_location . '/oembed.php' ] = 'evs';
			}
		}

		/**
		 * Analyse a specific post meta field for usable video information
		 *
		 * @param string $meta_value The value to analyse.
		 * @param string $meta_key   The associated meta key.
		 * @param int    $post_id    The id of the post this meta value applies to.
		 *
		 * @return array An array with the usable information found or else an empty array.
		 */
		public function get_info_from_post_meta( $meta_value, $meta_key, $post_id ) {
			$vid = [];

			if ( preg_match( '`^[^\s]+\.(?:' . WPSEO_Video_Sitemap::$video_ext_pattern . ')$`', $meta_value ) ) {
				$vid['content_loc'] = $meta_value;
				$vid['url']         = $meta_value;
				$vid['maybe_local'] = true;
				$vid['type']        = 'custom_field';
			}
			else {
				$vid['__add_to_content'] = $meta_value;
			}

			return $vid;
		}
	}
}
