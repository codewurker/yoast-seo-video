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
 * Vidyard Video SEO Details
 *
 * Currently retrieves a full html page as the remote response
 *
 * @todo consider changing retrieval method to the API, though an API key is needed and not all info is in one place.
 * @link http://api.vidyard.com/docs/dashboard/1.0/api_players/show.html
 * Note: additional header must be send: Header: �Content-Type� | Value: �application/json�
 */
if ( ! class_exists( 'WPSEO_Video_Details_Vidyard' ) ) {

	/**
	 * Class WPSEO_Video_Details_Vidyard
	 */
	class WPSEO_Video_Details_Vidyard extends WPSEO_Video_Details {

		/**
		 * Regular expression to retrieve a video ID from a known video URL.
		 *
		 * @var string
		 */
		protected $id_regex = '`[/\.]vidyard\.com/(?:[a-z_]+/)*([a-z0-9_-]+)(?:\.js|\.html|\?|$)`i';

		/**
		 * Information on the remote URL to use for retrieving the video details.
		 *
		 * @var array<string, string>
		 */
		protected $remote_url = [
			'pattern'       => 'http://play.vidyard.com/%s',
			'replace_key'   => 'id',
			'response_type' => '',
		];

		/**
		 * Data array or false if data could not be found/decoded.
		 *
		 * @var array|false
		 */
		protected $chapter_data = [];

		/**
		 * Check if the response is for a valid video
		 *
		 * @return bool
		 */
		protected function is_video_response() {
			$this->get_chapter_data();
			return ( $this->chapter_data !== [] );
		}

		/**
		 * Set the content location
		 *
		 * @return void
		 */
		protected function set_content_loc() {
			if ( ! empty( $this->chapter_data['sd_unsecure_url'] ) ) {
				$this->vid['content_loc'] = $this->chapter_data['sd_unsecure_url'];
			}
		}

		/**
		 * Set the video duration
		 *
		 * @return void
		 */
		protected function set_duration() {
			if ( ! empty( $this->chapter_data['seconds'] ) ) {
				$this->vid['duration'] = $this->chapter_data['seconds'];
			}
		}

		/**
		 * Set the player location
		 *
		 * @return void
		 */
		protected function set_player_loc() {
			if ( ( is_string( $this->remote_url['pattern'] ) && $this->remote_url['pattern'] !== '' )
				&& ( is_string( $this->vid[ $this->remote_url['replace_key'] ] )
				&& $this->vid[ $this->remote_url['replace_key'] ] !== '' ) ) {

				$url                     = sprintf( $this->remote_url['pattern'], $this->vid[ $this->remote_url['replace_key'] ] );
				$this->vid['player_loc'] = $this->url_encode( $url );
			}
		}

		/**
		 * Set the thumbnail location
		 *
		 * @return void
		 */
		protected function set_thumbnail_loc() {
			if ( preg_match( '`vidyard_thumbnail_data = ({.*?});`s', $this->decoded_response, $match ) ) {
				$thumbnail_data = str_replace( '\'', '"', trim( $match[1] ) );
				$thumbnail_data = json_decode( $thumbnail_data, true );

				if ( ( is_array( $thumbnail_data ) && $thumbnail_data !== [] ) ) {
					// Get the first element.
					$thumbnail_data = reset( $thumbnail_data );
					if ( isset( $thumbnail_data['url'] ) && is_string( $thumbnail_data['url'] ) && $thumbnail_data['url'] !== '' ) {
						$image = $this->make_image_local( $thumbnail_data['url'] );
						if ( is_string( $image ) && $image !== '' ) {
							$this->vid['thumbnail_loc'] = $image;
						}
					}
				}
			}
		}

		/**
		 * Get decoded vidyard chapter data
		 *
		 * @return void
		 */
		private function get_chapter_data() {
			// Must use preg match because the data is in inline javascript.
			if ( ! empty( $this->decoded_response ) && preg_match( '`vidyard_chapter_data = (\[\s*{[^\}]*\}\s*\]);`', $this->decoded_response, $match ) ) {
				// Replace single quotes with double quotes so it can be json decoded.
				$json = str_replace( '\'', '"', trim( $match[1] ) );
				$json = json_decode( $json, true );

				if ( is_array( $json ) && $json !== [] ) {
					// Get the first element.
					$this->chapter_data = reset( $json );
				}
			}
		}
	}
}
