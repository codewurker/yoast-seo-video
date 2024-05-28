<?php
/**
 * Yoast SEO Video plugin file.
 *
 * @package    Internals
 * @since      x.x.x
 * @version    x.x.x
 */

// Avoid direct calls to this file
if ( ! class_exists( 'WPSEO_Video_Sitemap' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

if ( ! class_exists( 'WPSEO_Video_Details_[SERVICENAME]' ) ) {

	/**
	 * Class WPSEO_Video_Details_[SERVICENAME]
	 */
	class WPSEO_Video_Details_[ SERVICENAME ] extends WPSEO_Video_Details {
	//class WPSEO_Video_Details_[SERVICENAME] extends WPSEO_Video_Details_Oembed {

		/**
		 * Regular expression to retrieve a video ID from a known video URL.
		 *
		 * @var string
		 */
		//protected $id_regex = '``i';

		/**
		 * Sprintf template to create a URL from an ID.
		 *
		 * @var string
		 */
		//protected $url_template = '.../%s/';

		/**
		 * Information on the remote URL to use for retrieving the video details.
		 *
		 * @var string[]
		 */
		protected $remote_url = [
			'pattern'       => '.../%s',
			'replace_key'   => 'url|id',
			'response_type' => 'json|serial|simplexml',
		];

		/**
		 * Set the player location
		 *
		 * @return void
		 */
		protected function set_player_loc() {
		}

		/**
		 * Set the thumbnail location
		 *
		 * @return void
		 */
		protected function set_thumbnail_loc() {
		}

		/*
		protected function set_content_loc() {}
		protected function set_duration() {}
		protected function set_height() {}
		protected function set_id() {}
		protected function set_view_count() {}
		protected function set_width() {}

		protected function set_type() {} -> normally not needed
		*/
	}
}
