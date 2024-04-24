<?php
/**
 * Yoast SEO Video plugin file.
 *
 * @package Yoast\VideoSEO
 */

/**
 * Presenter for presenting the video's type as an opengraph meta tag.
 */
class WPSEO_Video_Type_Presenter extends WPSEO_Video_Abstract_Tag_Presenter {

	/**
	 * The tag key name.
	 *
	 * @var string
	 */
	protected $key = 'og:video:type';

	/**
	 * Gets the raw type value.
	 *
	 * @return string The raw value.
	 */
	public function get() {
		return 'text/html';
	}
}
