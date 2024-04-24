<?php
/**
 * Yoast SEO Video plugin file.
 *
 * @package Yoast\VideoSEO
 */

/**
 * Presenter for presenting whether the video contains adult material, as a Yandex meta tag.
 */
class WPSEO_Video_Yandex_Adult_Presenter extends WPSEO_Video_Abstract_Tag_Presenter {

	/**
	 * The tag key name.
	 *
	 * @var string
	 */
	protected $key = 'ya:ovs:adult';

	/**
	 * Gets the raw family friendly as a stringified boolean.
	 *
	 * @return string The raw value.
	 */
	public function get() {
		$post = $this->presentation->source;
		if ( ! $post instanceof WP_Post ) {
			return 'false';
		}
		if ( WPSEO_Video_Utils::is_video_family_friendly( $post->ID ) === false ) {
			return 'true';
		}
		return 'false';
	}
}
