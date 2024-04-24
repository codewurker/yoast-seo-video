<?php
/**
 * Yoast SEO Video plugin file.
 *
 * @package Yoast\VideoSEO
 */

/**
 * Presenter for presenting the `allow_embed` property, as a Yandex meta tag.
 */
class WPSEO_Video_Yandex_Allow_Embed_Presenter extends WPSEO_Video_Abstract_Tag_Presenter {

	/**
	 * The tag key name.
	 *
	 * @var string
	 */
	protected $key = 'ya:ovs:allow_embed';

	/**
	 * Gets the raw allow embed value.
	 *
	 * @return string The raw value.
	 */
	public function get() {
		return 'true';
	}
}
