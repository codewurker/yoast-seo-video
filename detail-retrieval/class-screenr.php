<?php
/**
 * Yoast SEO Video plugin file.
 *
 * @package    Internals
 * @since      1.7.0
 * @version    1.7.0
 */

// Avoid direct calls to this file.
if ( ! class_exists( 'WPSEO_Video_Sitemap' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}


/**
 * Screenr Video SEO Details
 *
 * JSON response format [2014/7/22]:
 * {
 *    "version":"1.0",
 *    "type":"video",
 *    "provider_name":"screenr",
 *    "provider_url":"https://www.screenr.com",
 *    "width":560,
 *    "height":345,
 *    "title":"Demonstration of a way to add new custom field types to Easy Content Types in an upcoming version",
 *    "description":"Demonstration of a way to add new custom field types to Easy Content Types in an upcoming version",
 *    "author_name":"pippinspages",
 *    "author_url":"https://www.screenr.com/user/pippinspages",
 *    "html":"<iframe src=\"https://www.screenr.com/embed/3jns\" width=\"650\" height=\"396\" frameborder=\"0\"></iframe>",
 *    "thumbnail_url":"https://az21792.vo.msecnd.net/images/e9938535-f19e-4a7a-bcea-6dcf0bf33d70_thumb.jpg"
 * }
 */
if ( ! class_exists( 'WPSEO_Video_Details_Screenr' ) ) {

	/**
	 * Class WPSEO_Video_Details_Screenr
	 */
	class WPSEO_Video_Details_Screenr extends WPSEO_Video_Details_Oembed {

		/**
		 * Regular expression to retrieve a video ID from a known video URL.
		 *
		 * @var string
		 */
		protected $id_regex = '`[/\.]screenr\.com/(?:embed/)?([a-z0-9-]+)(?:$|[#\?])`i';

		/**
		 * Sprintf template to create a URL from an ID.
		 *
		 * @var string
		 */
		protected $url_template = 'http://screenr.com/%s';

		/**
		 * Information on the remote URL to use for retrieving the video details.
		 *
		 * @var string[]
		 */
		protected $remote_url = [
			'pattern'       => 'http://www.screenr.com/api/oembed.json?url=http://screenr.com/%s',
			'replace_key'   => 'id',
			'response_type' => 'json',
		];

		/**
		 * Set the player location
		 */
		protected function set_player_loc() {
			if ( ! empty( $this->vid['id'] ) ) {
				$this->vid['player_loc'] = 'http://www.screenr.com/embed/' . rawurlencode( $this->vid['id'] );
			}
		}
	}
}
