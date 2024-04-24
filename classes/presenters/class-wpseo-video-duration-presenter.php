<?php
/**
 * Yoast SEO Video plugin file.
 *
 * @package Yoast\VideoSEO
 */

/**
 * Presenter for presenting the video's duration as an opengraph meta tag.
 */
class WPSEO_Video_Duration_Presenter extends WPSEO_Video_Abstract_Tag_Presenter {

	/**
	 * The tag key name.
	 *
	 * @var string
	 */
	protected $key = 'og:video:duration';

	/**
	 * Gets the raw duration value.
	 *
	 * @return string The raw value.
	 */
	public function get() {
		if ( $this->video['duration'] === 0 ) {
			return '';
		}
		return (string) $this->video['duration'];
	}
}
