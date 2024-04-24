<?php
/**
 * Yoast SEO Video plugin file.
 *
 * @package Yoast\VideoSEO
 */

/**
 * Presenter for presenting the video's location as an opengraph meta tag.
 */
class WPSEO_Video_Location_Presenter extends WPSEO_Video_Abstract_Tag_Presenter {

	/**
	 * The tag key name.
	 *
	 * @var string
	 */
	protected $key = 'og:video';

	/**
	 * Gets the raw player location value.
	 *
	 * @return string The raw value.
	 */
	public function get() {
		return $this->video['player_loc'];
	}
}
