<?php
/**
 * Yoast SEO Video plugin file.
 *
 * @package Yoast\VideoSEO
 */

use Yoast\WP\SEO\Context\Meta_Tags_Context;
use Yoast\WP\SEO\Presenters\Abstract_Indexable_Presenter;

/**
 * Bootstraps the Video SEO Module.
 *
 * Makes sure the environment has all the elements we need to be able to run.
 * Notifies the user when certain elements are missing.
 */
class WPSEO_Video_Bootstrap {

	/**
	 * Queued admin notices.
	 *
	 * @var string[]
	 */
	protected $admin_notices = [];

	/**
	 * Adds hooks to integrate with WordPress.
	 *
	 * @return void
	 */
	public function add_hooks() {
		// Always load the translations to make sure the notifications are translated as well.
		add_action( 'admin_init', [ 'WPSEO_Video_Utils', 'load_textdomain' ], 1 );

		// Enable Yoast usage tracking.
		add_filter( 'wpseo_enable_tracking', '__return_true' );

		$can_activate = $this->can_activate();
		if ( ! $can_activate ) {
			$this->add_admin_notices_hook();

			return;
		}

		$this->add_integration_hooks();

		// If called via WP-CLI, register additional commands.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			add_action( 'wp_loaded', [ $this, 'register_cli_commands' ] );
		}
	}

	/**
	 * Shows any queued admin notices.
	 *
	 * @return void
	 */
	public function show_admin_notices() {
		if ( empty( $this->admin_notices ) ) {
			return;
		}

		if ( $this->is_iframe_request() ) {
			return;
		}

		foreach ( $this->admin_notices as $admin_notice ) {
			$this->display_admin_notice( $admin_notice );
		}
	}

	/**
	 * Adds hooks to load the video integrations.
	 *
	 * @return void
	 */
	protected function add_integration_hooks() {
		add_action( 'plugins_loaded', [ $this, 'load_metabox_integration' ], 10 );
		add_action( 'plugins_loaded', [ $this, 'load_sitemap_integration' ], 20 );
		add_action( 'plugins_loaded', [ $this, 'load_schema_integration' ], 20 );
		add_action( 'plugins_loaded', [ $this, 'load_embed_optimization' ], 20 );
		// Add opengraph presenter.
		add_filter( 'wpseo_frontend_presenters', [ $this, 'add_frontend_presenters' ], 10, 2 );

		$editor_reactification_alert = new WPSEO_Video_Editor_Reactification_Alert();
		$editor_reactification_alert->register_hooks();
	}

	/**
	 * Adds presenters for presenting the opengraph metatags for the video metadata.
	 *
	 * @param Abstract_Indexable_Presenter[] $presenters The presenter instances.
	 * @param Meta_Tags_Context              $context    The meta tags context.
	 *
	 * @return Abstract_Indexable_Presenter[] The extended presenters.
	 */
	public function add_frontend_presenters( $presenters, $context ) {
		if ( ! is_array( $presenters ) ) {
			return $presenters;
		}

		// Bail out when opengraph video tags are switched off.
		if ( WPSEO_Options::get( 'video_facebook_embed' ) !== true ) {
			return $presenters;
		}

		// Retrieve the video metadata.
		$video = $this->get_video( $context );

		// Bail out if the video metadata is not there or malformed.
		if ( ! isset( $video['player_loc'] ) || ! is_array( $video ) ) {
			return $presenters;
		}

		// Always output location (URL) and type of the video.
		$presenters[] = new WPSEO_Video_Location_Presenter( $video );
		$presenters[] = new WPSEO_Video_Type_Presenter( $video );
		$presenters[] = new WPSEO_Video_Duration_Presenter( $video );
		$presenters[] = new WPSEO_Video_Width_Presenter( $video );
		$presenters[] = new WPSEO_Video_Height_Presenter( $video );

		/** This filter is documented in classes/class-wpseo-video-sitemap.php */
		$yandex_support_enabled = apply_filters( 'wpseo_video_yandex_support', true );

		// Add Yandex-supported metatags, if enabled. They are enabled by default.
		if ( $yandex_support_enabled ) {
			$presenters[] = new WPSEO_Video_Yandex_Adult_Presenter( $video );
			$presenters[] = new WPSEO_Video_Yandex_Upload_Date_Presenter( $video );
			$presenters[] = new WPSEO_Video_Yandex_Allow_Embed_Presenter( $video );
		}

		return $presenters;
	}

	/**
	 * Retrieves the video metadata of the given post or term.
	 *
	 * @param Meta_Tags_Context|null $context The meta tags context.
	 *
	 * @return array|false The video metadata or `false` if no metadata are available.
	 */
	protected function get_video( $context = null ) {
		// This is not executed on a REST API call.
		if ( is_a( $context, Meta_Tags_Context::class ) ) {
			// Check if we're on a singular post page.
			if ( $context->indexable->object_type === 'post' ) {
				$the_post = get_post( $context->indexable->object_id );

				return WPSEO_Video_Utils::get_video_for_post( $the_post );
			}
			// Check if we're on a singular term page.
			// Note that this code won't work until https://yoast.atlassian.net/browse/QAK-2443 is fixed.
			if ( $context->indexable->object_type === 'term' ) {
				$the_term = get_term( $context->indexable->object_id );

				return WPSEO_Video_Utils::get_video_for_term( $the_term );
			}
		}

		// Fallback for posts in REST API calls.
		global $post;
		if ( ! empty( $post ) ) {
			return WPSEO_Video_Utils::get_video_for_post( $post );
		}

		// Known issue: video meta tags for terms are not working: https://yoast.atlassian.net/browse/QAK-2443.
		// To do: once QAK-2443 is fixed, implement fallback for terms for REST API calls.
		return false;
	}

	/**
	 * Loads the metabox integration.
	 *
	 * @return void
	 */
	public function load_metabox_integration() {
		WPSEO_Meta_Video::init();
	}

	/**
	 * Loads the sitemap integration.
	 *
	 * @return void
	 */
	public function load_sitemap_integration() {
		$GLOBALS['wpseo_video_xml'] = new WPSEO_Video_Sitemap();
	}

	/**
	 * Loads the Schema integration.
	 *
	 * @return void
	 */
	public function load_schema_integration() {
		$GLOBALS['wpseo_video_schema'] = new WPSEO_Video_Schema();
	}

	/**
	 * Loads the Embed optimization.
	 *
	 * @return void
	 */
	public function load_embed_optimization() {
		if ( WPSEO_Options::get( 'video_youtube_faster_embed' ) !== true ) {
			return;
		}

		$GLOBALS['wpseo_video_embed'] = new WPSEO_Video_Embed();
	}

	/**
	 * Checks if the plugin can be activated.
	 *
	 * @return bool True if the plugin has the environment to work in.
	 */
	protected function can_activate() {
		if ( ! $this->is_spl_autoload_available() ) {
			$this->add_admin_notice(
				esc_html__(
					'The PHP SPL extension seems to be unavailable. Please ask your web host to enable it.',
					'yoast-video-seo'
				),
				true
			);
		}

		if ( ! $this->is_wordpress_up_to_date() ) {
			$this->add_admin_notice(
				esc_html__(
					'Please upgrade WordPress to the latest version to allow WordPress and the Video SEO module to work properly.',
					'yoast-video-seo'
				)
			);
		}

		if ( ! $this->is_yoast_seo_active() ) {
			$this->add_admin_notice( $this->get_wpseo_missing_error() );
		}

		// Allow beta version.
		if ( $this->is_yoast_seo_active() && ! $this->is_yoast_seo_up_to_date() ) {
			$this->add_admin_notice(
				sprintf(
					/* translators: $1$s expands to Yoast SEO. */
					esc_html__(
						'Please upgrade the %1$s plugin to the latest version to allow the Video SEO module to work.',
						'yoast-video-seo'
					),
					'Yoast SEO'
				)
			);
		}

		return empty( $this->admin_notices );
	}

	/**
	 * Retrieves the message to show to make sure Yoast SEO gets activated.
	 *
	 * @return string The message to present to the user.
	 */
	protected function get_wpseo_missing_error() {
		if ( ! $this->user_can_activate_plugins() ) {
			return $this->get_install_by_admin_message();
		}

		return $this->get_install_plugin_message();
	}

	/**
	 * Queues an admin notification.
	 *
	 * @param string $message    Message to be shown.
	 * @param bool   $use_prefix Optional. Use the default prefix or not.
	 *
	 * @return void
	 */
	protected function add_admin_notice( $message, $use_prefix = false ) {
		$prefix = '';

		if ( $use_prefix ) {
			$prefix = esc_html( $this->get_admin_notice_prefix() ) . ' ';
		}

		$this->admin_notices[] = $prefix . $message;
	}

	/**
	 * Registers the admin notices hooks to display messages.
	 *
	 * @return void
	 */
	protected function add_admin_notices_hook() {
		$hook = 'admin_notices';
		if ( $this->use_multisite_notifications() ) {
			$hook = 'network_' . $hook;
		}

		add_action( $hook, [ $this, 'show_admin_notices' ] );
	}

	/**
	 * Displays an admin notice.
	 *
	 * @param string $admin_notice Notice to display, pre-escaped.
	 *
	 * @return void
	 */
	protected function display_admin_notice( $admin_notice ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Notices are passed in with variables already escaped.
		echo '<div class="error"><p>' . $admin_notice . '</p></div>';
	}

	/**
	 * Checks if the user can install or activate plugins.
	 *
	 * @return bool True if the user can activate plugins.
	 */
	protected function user_can_activate_plugins() {
		return current_user_can( 'install_plugins' ) || current_user_can( 'activate_plugins' );
	}

	/**
	 * Checks if the current request is an iFrame request.
	 *
	 * @return bool True if this request is an iFrame request.
	 */
	protected function is_iframe_request() {
		return defined( 'IFRAME_REQUEST' ) && IFRAME_REQUEST !== false;
	}

	/**
	 * Checks whether we should use multisite notifications or not.
	 *
	 * @return bool True if we want to use multisite notifications.
	 */
	protected function use_multisite_notifications() {
		return is_multisite() && is_network_admin();
	}

	/**
	 * Retrieves the plugin page URL to use.
	 *
	 * @return string Plugin page URL to use.
	 */
	protected function get_plugin_page_url() {
		$page_slug = 'plugin-install.php';

		if ( $this->use_multisite_plugin_page() ) {
			return network_admin_url( $page_slug );
		}

		return admin_url( $page_slug );
	}

	/**
	 * Checks if we should use the multisite plugin page.
	 *
	 * @return bool True if we are on multisite and super admin.
	 */
	protected function use_multisite_plugin_page() {
		return is_multisite() === true && is_super_admin();
	}

	/**
	 * Checks if SPL Autoload is available.
	 *
	 * @return bool True if SPL Autoload is available.
	 */
	protected function is_spl_autoload_available() {
		return function_exists( 'spl_autoload_register' );
	}

	/**
	 * Checks if WordPress is at a mimimal required version.
	 *
	 * @return bool True if WordPress is at a minimal required version.
	 */
	protected function is_wordpress_up_to_date() {
		return version_compare( $GLOBALS['wp_version'], '6.1', '>=' );
	}

	/**
	 * Checks if Yoast SEO is active.
	 *
	 * @return bool True if Yoast SEO is active.
	 */
	public function is_yoast_seo_active() {
		return defined( 'WPSEO_VERSION' );
	}

	/**
	 * Checks if Yoast SEO is at a minimum required version.
	 *
	 * @return bool True if Yoast SEO is at a minimal required version.
	 */
	protected function is_yoast_seo_up_to_date() {
		return $this->is_yoast_seo_active() && version_compare( WPSEO_VERSION, '20.13-RC0', '>=' );
	}

	/**
	 * Returns the admin notice prefix string.
	 *
	 * @return string The string to prefix the admin notice with.
	 */
	protected function get_admin_notice_prefix() {
		return __( 'Activation of Video SEO failed:', 'yoast-video-seo' );
	}

	/**
	 * Generates the message to display to install Yoast SEO by proxy.
	 *
	 * @return string The message to show to inform the user to install Yoast SEO.
	 */
	protected function get_install_by_admin_message() {
		return sprintf(
			/* translators: %1$s expands to Yoast SEO. */
			esc_html__(
				'Please ask the (network) admin to install & activate %1$s and then enable its XML sitemap functionality to allow the Video SEO module to work.',
				'yoast-video-seo'
			),
			'Yoast SEO'
		);
	}

	/**
	 * Generates the message to display to install Yoast SEO.
	 *
	 * @return string The message to show to inform the user to install Yoast SEO.
	 */
	protected function get_install_plugin_message() {
		$url = add_query_arg(
			[
				'tab'                 => 'search',
				'type'                => 'term',
				's'                   => 'wordpress+seo',
				'plugin-search-input' => 'Search+Plugins',
			],
			$this->get_plugin_page_url()
		);

		return sprintf(
			/* translators: %1$s and %3$s expand to anchor tags with a link to the download page for Yoast SEO . %2$s expands to Yoast SEO. */
			esc_html__(
				'Please %1$sinstall & activate %2$s%3$s and then enable its XML sitemap functionality to allow the Video SEO module to work.',
				'yoast-video-seo'
			),
			'<a href="' . esc_url( $url ) . '">',
			'Yoast SEO',
			'</a>'
		);
	}

	/**
	 * Loads the CLI commands.
	 *
	 * @return void
	 */
	public function register_cli_commands() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$sitemap                = new WPSEO_Video_Sitemap();
			$post_indexation_action = new WPSEO_Video_Post_Indexation_Action( $sitemap );
			$term_indexation_action = new WPSEO_Video_Term_Indexation_Action( $sitemap );
			$index_command          = new WPSEO_Video_Index_Command( $post_indexation_action, $term_indexation_action );
			WP_CLI::add_command( WPSEO_Video_Index_Command::get_namespace(), $index_command );
		}
	}
}
