<?php
/**
 * Yoast SEO Video plugin file.
 *
 * @package Yoast\VideoSEO
 */

use Yoast\WP\SEO\Presenters\Abstract_Indexable_Tag_Presenter;

/**
 * The abstract tag presenter for the video meta tags.
 */
abstract class WPSEO_Video_Abstract_Tag_Presenter extends Abstract_Indexable_Tag_Presenter {

	/**
	 * The video to present.
	 *
	 * @var array
	 */
	protected $video;

	/**
	 * The tag format including placeholders.
	 *
	 * @var string
	 */
	protected $tag_format = self::META_PROPERTY_CONTENT;

	/**
	 * WPSEO_Video_Abstract_Tag_Presenter constructor.
	 *
	 * @param array $video The video from which to present a property.
	 */
	public function __construct( $video ) {
		$this->video = $video;
	}
}
