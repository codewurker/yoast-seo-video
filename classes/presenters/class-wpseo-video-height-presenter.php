<?php
/**
 * Yoast SEO Video plugin file.
 *
 * @package Yoast\VideoSEO
 */

/**
 * Presenter for presenting the video's height as an opengraph meta tag.
 */
class WPSEO_Video_Height_Presenter extends WPSEO_Video_Abstract_Tag_Presenter {

	/**
	 * The tag key name.
	 *
	 * @var string
	 */
	protected $key = 'og:video:height';

	/**
	 * Gets the raw height value.
	 *
	 * @return string The raw value.
	 */
	public function get() {
		if ( ! isset( $this->video['height'] ) ) {
			return '';
		}
		return (string) $this->video['height'];
	}
}
