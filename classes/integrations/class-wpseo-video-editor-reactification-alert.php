<?php
/**
 * Yoast SEO Video plugin file.
 *
 * @package Yoast\VideoSEO
 */

use Yoast\WP\SEO\Integrations\Alerts\Abstract_Dismissable_Alert;

/**
 * Class WPSEO_Video_Editor_Reactification_Alert.
 */
class WPSEO_Video_Editor_Reactification_Alert extends Abstract_Dismissable_Alert {

	/**
	 * Holds the alert identifier.
	 *
	 * @var string
	 */
	public $alert_identifier = 'video-editor-reactification-alert';
}
