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
 * Collegehumor Video SEO Details
 *
 * JSON response format [2014/7/22]:
 * {
 *    "type":"video",
 *    "version":"1.0",
 *    "title":"Prank War 7: The Half Million Dollar Shot",
 *    "author_name":"CollegeHumor",
 *    "author_url":"http:\/\/www.collegehumor.com",
 *    "provider_name":"CollegeHumor",
 *    "provider_url":"http:\/\/www.collegehumor.com",
 *    "height":338,
 *    "width":600,
 *    "html":"<object id=\"ch3922232\" type=\"application\/x-shockwave-flash\" data=\"http:\/\/0.static.collegehumor.cvcdn.com\/moogaloop\/moogaloop.1.0.31.swf?clip_id=3922232&amp;use_node_id=true&amp;fullscreen=1\" width=\"600\" height=\"338\"><param name=\"allowfullscreen\" value=\"true\"\/><param name=\"wmode\" value=\"transparent\"\/><param name=\"allowScriptAccess\" value=\"always\"\/><param name=\"movie\" quality=\"best\" value=\"http:\/\/0.static.collegehumor.cvcdn.com\/moogaloop\/moogaloop.1.0.31.swf?clip_id=3922232&amp;use_node_id=true&amp;fullscreen=1\"\/><embed src=\"http:\/\/0.static.collegehumor.cvcdn.com\/moogaloop\/moogaloop.1.0.31.swf?clip_id=3922232&amp;use_node_id=true&amp;fullscreen=1\" type=\"application\/x-shockwave-flash\" wmode=\"transparent\" width=\"600\" height=\"338\" allowScriptAccess=\"always\"><\/embed><\/object><div style=\"padding:5px 0; text-align:center; width:600px;\"><p><a href=\"\/\/www.collegehumor.com\/videos\/most-viewed\/this-year\">CollegeHumor&#039;s Favorite Funny Videos<\/a><\/p><\/div>",
 *    "thumbnail_url":"http:\/\/0.media.collegehumor.cvcdn.com\/14\/81\/46ed8b408e8c586b0fad03ccd968aaa5.jpg",
 *    "thumbnail_width":"175",
 *    "thumbnail_height":"98"
 * }
 */
if ( ! class_exists( 'WPSEO_Video_Details_Collegehumor' ) ) {

	/**
	 * Class WPSEO_Video_Details_Collegehumor
	 */
	class WPSEO_Video_Details_Collegehumor extends WPSEO_Video_Details_Oembed {

		/**
		 * Regular expression to retrieve a video ID from a known video URL.
		 *
		 * @var string
		 */
		protected $id_regex = '`[/\.]collegehumor\.com/(?:video|embed)/([0-9]+)`i';

		/**
		 * Sprintf template to create a URL from an ID.
		 *
		 * {@internal Set to embed as it gives better retrieval results compared to video!}
		 *
		 * @var string
		 */
		protected $url_template = 'http://www.collegehumor.com/embed/%s/';

		/**
		 * Information on the remote URL to use for retrieving the video details.
		 *
		 * @var string[]
		 */
		protected $remote_url = [
			'pattern'       => 'http://www.collegehumor.com/oembed.json?url=%s',
			'replace_key'   => 'url',
			'response_type' => 'json',
		];

		/**
		 * Set the player location
		 *
		 * @todo - or should we parse the embed url from decoded_response->html ?
		 *
		 * @return void
		 */
		protected function set_player_loc() {
			if ( ! empty( $this->vid['id'] ) ) {
				$this->vid['player_loc'] = 'http://0.static.collegehumor.cvcdn.com/moogaloop/moogaloop.1.0.31.swf?clip_id=' . urlencode( $this->vid['id'] ) . '&amp;fullscreen=1';
			}
		}
	}
}
