<?php
/**
 * All functionality for fetching video data and creating an XML video sitemap with it.
 *
 * @link https://codex.wordpress.org/oEmbed OEmbed Codex Article.
 * @link http://oembed.com/                 OEmbed Homepage.
 *
 * @package    WordPress SEO
 * @subpackage WordPress SEO Video
 */

use Yoast\WP\SEO\Values\Open_Graph\Images;

/**
 * Wpseo_video_Video_Sitemap class.
 *
 * @package WordPress SEO Video
 * @since   0.1
 */
class WPSEO_Video_Sitemap {

	/**
	 * The maximum number of entries per sitemap page.
	 *
	 * @var int
	 */
	private $max_entries = 5;

	/**
	 * Name of the metabox tab.
	 *
	 * @var string
	 */
	private $metabox_tab;

	/**
	 * Youtube video ID regex pattern.
	 *
	 * @var string
	 */
	public static $youtube_id_pattern = '[0-9a-zA-Z_-]+';

	/**
	 * Video extension list for use in regex pattern.
	 *
	 * @var string
	 *
	 * @todo - shouldn't this be a class constant ?
	 */
	public static $video_ext_pattern = 'mpg|mpeg|mp4|m4v|mov|ogv|wmv|asf|avi|ra|ram|rm|flv|swf';

	/**
	 * Image extension list for use in regex pattern.
	 *
	 * @var string
	 *
	 * @todo - shouldn't this be a class constant ?
	 */
	public static $image_ext_pattern = 'jpg|jpeg|jpe|gif|png';

	/**
	 * The date helper.
	 *
	 * @var WPSEO_Date_Helper
	 */
	protected $date;

	/**
	 * Constructor for the WPSEO_Video_Sitemap class.
	 *
	 * @todo  Deal with upgrade from license constant WPSEO_VIDEO_LICENSE
	 * @since 0.1
	 */
	public function __construct() {
		// Initialize the options.
		WPSEO_Option_Video::register_option();

		// Run upgrade routine.
		$this->upgrade();

		add_filter( 'wpseo_tax_meta_special_term_id_validation__video', [ $this, 'validate_video_tax_meta' ] );

		// Set content_width based on theme content_width or our option value if either is available.
		$content_width = $this->get_content_width();
		if ( $content_width !== false ) {
			$GLOBALS['content_width'] = $content_width;
		}
		unset( $content_width );

		add_action( 'setup_theme', [ $this, 'init' ] );
		add_action( 'admin_init', [ $this, 'init' ] );
		add_action( 'init', [ $this, 'register_sitemap' ], 20 ); // Register sitemap after cpts have been added.
		add_action( 'admin_bar_menu', [ $this, 'add_admin_bar_item' ], 97 );
		add_filter( 'oembed_providers', [ $this, 'sync_oembed_providers' ] );

		if ( is_admin() ) {

			add_filter( 'wpseo_submenu_pages', [ $this, 'add_submenu_pages' ] );

			// Check if we are in our Elementor AJAX request.
			$post_action = false;
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reason: We are not processing form information.
			if ( isset( $_POST['action'] ) && is_string( $_POST['action'] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reason: We are not processing form information.
				$post_action = sanitize_text_field( wp_unslash( $_POST['action'] ) );
			}
			$doing_ajax                 = \wp_doing_ajax();
			$is_elementor_ajax_save     = $doing_ajax && $post_action === 'elementor_ajax';
			$is_our_elementor_ajax_save = $doing_ajax && $post_action === 'wpseo_elementor_save';

			// Update video post meta in Elementor save, after our WordPress SEO save.
			if ( $is_our_elementor_ajax_save ) {
				\add_action( 'wpseo_saved_postdata', [ $this, 'update_video_post_meta' ], 10 );
				\add_action( 'wpseo_saved_postdata', [ $this, 'invalidate_sitemap' ], 12 );
			}
			// Update video meta on normal save. But prevent updates in Elementor's own save request, as we have our own.
			elseif ( ! $is_elementor_ajax_save ) {
				\add_action( 'wp_insert_post', [ $this, 'update_video_post_meta' ], 12, 3 );
				\add_action( 'wp_insert_post', [ $this, 'invalidate_sitemap' ], 13 );
			}

			$valid_pages = [
				'edit.php',
				'post.php',
				'post-new.php',
			];
			if ( in_array( $GLOBALS['pagenow'], $valid_pages, true )
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Using a YoastSEO Free hook.
				|| apply_filters( 'wpseo_always_register_metaboxes_on_admin', false )
				|| $doing_ajax
			) {
				$this->metabox_tab = new WPSEO_Video_Metabox();
				$this->metabox_tab->register_hooks();
			}

			add_action( 'admin_enqueue_scripts', [ $this, 'admin_video_enqueue_scripts' ] );

			add_action( 'admin_init', [ $this, 'admin_video_enqueue_styles' ] );

			add_action( 'wp_ajax_index_posts', [ $this, 'index_posts_callback' ] );

			// Maybe show 'Recommend re-index' admin notice.
			if ( get_transient( 'video_seo_recommend_reindex' ) === '1' ) {
				add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts_ignore' ] );
				add_action( 'all_admin_notices', [ $this, 'recommend_force_index' ] );
				add_action( 'wp_ajax_videoseo_set_ignore', [ $this, 'set_ignore' ] );
			}
		}
		else {

			// OpenGraph.
			add_action( 'wpseo_add_opengraph_additional_images', [ $this, 'opengraph_image' ], 15, 1 );
			add_filter( 'wpseo_html_namespaces', [ $this, 'add_video_namespaces' ] );

			// XML Sitemap Index addition.
			add_filter( 'wpseo_sitemap_index', [ $this, 'add_to_index' ] );

			if ( WPSEO_Options::get( 'video_fitvids' ) === true ) {
				// Fitvids scripting.
				add_action( 'wp_head', [ $this, 'fitvids' ] );
			}

			if ( WPSEO_Options::get( 'video_disable_rss' ) !== true ) {
				// MRSS.
				add_action( 'rss2_ns', [ $this, 'mrss_namespace' ] );
				add_action( 'rss2_item', [ $this, 'mrss_item' ], 10, 1 );
				add_filter( 'mrss_media', [ $this, 'mrss_add_video' ] );
			}
		}

		$this->date = new WPSEO_Date_Helper();
	}

	/**
	 * Retrieve a value to use for content_width.
	 *
	 * @since 3.8.0
	 *
	 * @param int $default_value Optional. Default value to use if value could not be determined.
	 *
	 * @return int|false Integer content width value or false if it could not be determined
	 *                   and no default was provided.
	 */
	public function get_content_width( $default_value = 0 ) {
		// If the theme or WP has set it, use what's already available.
		if ( ! empty( $GLOBALS['content_width'] ) ) {
			return (int) $GLOBALS['content_width'];
		}

		// If the user has set it in options, use that.
		$option_content_width = (int) WPSEO_Options::get( 'video_content_width' );
		if ( $option_content_width > 0 ) {
			return $option_content_width;
		}

		// Otherwise fall back to an arbitrary default if provided.
		// WP itself uses 500 for embeds, 640 for playlists and video shortcodes.
		if ( $default_value > 0 ) {
			return $default_value;
		}

		return false;
	}

	/**
	 * Method to invalidate the sitemap
	 *
	 * @param int $post_id Post ID.
	 */
	public function invalidate_sitemap( $post_id ) {
		// If this is just a revision, don't invalidate the sitemap cache yet.
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Bail if this is a multisite installation and the site has been switched.
		if ( is_multisite() && ms_is_switched() ) {
			return;
		}

		if ( ! WPSEO_Video_Utils::is_videoseo_active_for_posttype( get_post_type( $post_id ) ) ) {
			return;
		}

		WPSEO_Video_Wrappers::invalidate_sitemap( self::get_video_sitemap_basename() );
	}

	/**
	 * When sitemap is coming out of the cache there is no stylesheet. Normally it will take the default stylesheet.
	 *
	 * This method is called by a filter that will set the video stylesheet.
	 *
	 * @param object $target_object Target object.
	 *
	 * @return object
	 */
	public function set_stylesheet_cache( $target_object ) {
		if ( property_exists( $target_object, 'renderer' ) ) {
			$target_object->renderer->set_stylesheet( $this->get_stylesheet_line() );
		}

		return $target_object;
	}

	/**
	 * Getter for stylesheet url
	 *
	 * @return string
	 */
	public function get_stylesheet_line() {
		$stylesheet_url = "\n" . '<?xml-stylesheet type="text/xsl" href="' . esc_url( $this->get_xsl_url() ) . '"?>';

		return $stylesheet_url;
	}

	/**
	 * Adds the fitvids JavaScript to the output if there's a video on the page that's supported by this script.
	 * Prevents fitvids being added when the JWPlayer plugin is active as they are incompatible.
	 *
	 * @todo  - check if we can remove the JW6. The JWP plugin does some checking and deactivating
	 * themselves, so if we can rely on that, all the better.
	 *
	 * @since 1.5.4
	 */
	public function fitvids() {
		if ( ! is_singular() || defined( 'JWP6' ) ) {
			return;
		}

		global $post;

		if ( WPSEO_Video_Utils::is_videoseo_active_for_posttype( $post->post_type ) === false ) {
			return;
		}

		$video = WPSEO_Meta::get_value( 'video_meta', $post->ID );

		if ( ! is_array( $video ) || $video === [] ) {
			return;
		}

		// Check if the current post contains a YouTube, Vimeo, Blip.tv or Viddler video, if it does, add the fitvids code.
		if ( in_array( $video['type'], [ 'youtube', 'vimeo', 'blip.tv', 'viddler', 'wistia' ], true ) ) {
			$file = 'js/jquery.fitvids.min.js';
			if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
				$file = 'js/jquery.fitvids.js';
			}

			wp_enqueue_script(
				'fitvids',
				plugins_url( $file, WPSEO_VIDEO_FILE ),
				[ 'jquery' ],
				WPSEO_VIDEO_VERSION,
				true // Load in footer.
			);
		}

		add_action( 'wp_footer', [ $this, 'fitvids_footer' ] );
	}

	/**
	 * The fitvids instantiation code.
	 *
	 * @since 1.5.4
	 */
	public function fitvids_footer() {
		global $post;

		// Try and use the post class to determine the container.
		$classes    = get_post_class( '', $post->ID );
		$class_name = 'post';
		if ( is_array( $classes ) && $classes !== [] ) {
			$class_name = $classes[0];
		}
		$script = sprintf(
			'jQuery( document ).ready( function ( $ ) { $( ".%s" ).fitVids( {customSelector: "iframe.wistia_embed"} ); } );',
			esc_attr( $class_name )
		);

		wp_add_inline_script( 'fitvids', $script );
	}

	/**
	 * Registers the Video SEO submenu.
	 *
	 * @param array $submenu_pages Currently registered submenu pages.
	 *
	 * @return array Submenu pages with our submenu added.
	 */
	public function add_submenu_pages( $submenu_pages ) {
		$submenu_pages[] = [
			'wpseo_dashboard',
			'Yoast SEO: Video SEO',
			'Video SEO',
			'wpseo_manage_options',
			'wpseo_video',
			[ $this, 'admin_panel' ],
		];

		return $submenu_pages;
	}

	/**
	 * Adds the rewrite for the video XML sitemap
	 *
	 * @since 0.1
	 */
	public function init() {
		$this->max_entries = $this->get_entries_per_page();
		$this->add_oembed();

		add_filter( 'wpseo_helpscout_beacon_settings', [ $this, 'filter_helpscout_beacon' ] );
	}

	/**
	 * Makes sure the News settings page has a HelpScout beacon.
	 *
	 * @param array $helpscout_settings The HelpScout settings.
	 *
	 * @return array $helpscout_settings The HelpScout settings with the News SEO beacon added.
	 */
	public function filter_helpscout_beacon( $helpscout_settings ) {
		$helpscout_settings['pages_ids']['wpseo_video'] = '4e7489db-f907-41b3-9e86-93a01b4df9b0';
		$helpscout_settings['products'][]               = WPSEO_Addon_Manager::VIDEO_SLUG;

		return $helpscout_settings;
	}

	/**
	 * Add VideoSeo Admin bar menu item
	 *
	 * @param object $wp_admin_bar Current admin bar.
	 */
	public function add_admin_bar_item( $wp_admin_bar ) {
		if ( $this->can_manage_options() === true ) {
			$wp_admin_bar->add_menu(
				[
					'parent' => 'wpseo-settings',
					'id'     => 'wpseo-video',
					'title'  => __( 'Video SEO', 'yoast-video-seo' ),
					'href'   => admin_url( 'admin.php?page=wpseo_video' ),
				]
			);
		}
	}

	/**
	 * Register the video sitemap in the WPSEO sitemap class
	 *
	 * @since 1.7
	 */
	public function register_sitemap() {
		$basename = self::get_video_sitemap_basename();

		// Register the sitemap.
		WPSEO_Video_Wrappers::register_sitemap( $basename, [ $this, 'build_video_sitemap' ] );
		WPSEO_Video_Wrappers::register_xsl( 'video', [ $this, 'build_video_sitemap_xsl' ] );

		if ( is_admin() ) {
			// Setting action for removing the transient on update options.
			WPSEO_Video_Wrappers::register_cache_clear_option( 'wpseo_video', $basename );
		}
		else {
			// Setting stylesheet for cached sitemap.
			add_action( 'wpseo_sitemap_stylesheet_cache_' . $basename, [ $this, 'set_stylesheet_cache' ] );
		}
	}

	/**
	 * Execute upgrade actions when needed
	 */
	public function upgrade() {
		$options         = get_option( 'wpseo_video' );
		$current_version = '0';
		if ( ! empty( $options['video_dbversion'] ) ) {
			$current_version = $options['video_dbversion'];
		}

		if ( $current_version === '0' && ! empty( $options['dbversion'] ) ) {
			$current_version = $options['dbversion'];
		}

		// Early bail if dbversion is equal to current version.
		if ( version_compare( $current_version, WPSEO_VIDEO_VERSION, '==' ) ) {
			return;
		}

		// Upgrade to new option & meta classes.
		if ( version_compare( $current_version, '1.6', '<' ) ) {
			WPSEO_Option_Video::get_instance()->clean();
			// Make sure our meta values are cleaned up even if WP SEO would have been upgraded already.
			WPSEO_Meta::clean_up();
		}

		// Re-add missing durations.
		if ( $current_version === '0' || ( version_compare( $current_version, '1.7', '<' ) && version_compare( $current_version, '1.6', '>' ) ) ) {
			WPSEO_Meta_Video::re_add_durations();
		}

		// Recommend force re-index.
		if ( $current_version !== '0' && version_compare( $current_version, '4.0', '<' ) ) {
			set_transient( 'video_seo_recommend_reindex', 1 );
		}

		// Rename the option values.
		if ( $current_version !== '0' && version_compare( $current_version, '12.4-RC1', '<=' ) ) {
			$fields_to_convert = [
				'dbversion'       => 'video_dbversion',
				'cloak_sitemap'   => 'video_cloak_sitemap',
				'disable_rss'     => 'video_disable_rss',
				'custom_fields'   => 'video_custom_fields',
				'facebook_embed'  => 'video_facebook_embed',
				'fitvids'         => 'video_fitvids',
				'content_width'   => 'video_content_width',
				'wistia_domain'   => 'video_wistia_domain',
				'embedly_api_key' => 'video_embedly_api_key',
			];

			foreach ( $fields_to_convert as $current_field => $new_field ) {
				if ( ! isset( $options[ $current_field ] ) ) {
					continue;
				}

				$options[ $new_field ] = $options[ $current_field ];
			}

			update_option( 'wpseo_video', $options );
		}

		// Make sure version nr gets updated for any version without specific upgrades.
		// Re-get to make sure we have the latest version.
		if ( version_compare( $current_version, WPSEO_VIDEO_VERSION, '<' ) ) {
			WPSEO_Options::set( 'video_dbversion', WPSEO_VIDEO_VERSION );
		}
	}

	/**
	 * Recommend re-index with force index checked
	 *
	 * @since 1.8.0
	 */
	public function recommend_force_index() {
		if ( ! $this->can_manage_options() ) {
			return;
		}

		printf(
			'
	<div class="error" id="videoseo-reindex">
		<p style="float: right;"><a href="javascript:videoseo_setIgnore(\'recommend_reindex\',\'videoseo-reindex\',\'%1$s\');" class="button fixit">%2$s</a></p>
		<p>%3$s</p>
	</div>',
			esc_js( wp_create_nonce( 'videoseo-ignore' ) ), // #1.
			esc_html__( 'Ignore.', 'yoast-video-seo' ), // #2.
			sprintf(
				/* translators: 1: link open tag, 2: link close tag. */
				esc_html__( 'The VideoSEO upgrade which was just applied contains a lot of improvements. It is strongly recommended that you %1$sre-index the video content on your website%2$s with the \'force reindex\' option checked.', 'yoast-video-seo' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=wpseo_video' ) ) . '">',
				'</a>'
			) // #3.
		);
	}

	/**
	 * Function used to remove the temporary admin notices for several purposes, dies on exit.
	 */
	public function set_ignore() {
		if ( ! $this->can_manage_options() || ! isset( $_POST['option'] ) ) {
			die( '-1' );
		}

		check_ajax_referer( 'videoseo-ignore' );
		delete_transient( 'video_seo_' . sanitize_text_field( wp_unslash( $_POST['option'] ) ) );
		die( '1' );
	}

	/**
	 * Load other scripts for the admin in the Video SEO plugin
	 */
	public function admin_video_enqueue_scripts() {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Loads a page, doesn't perform any action yet.
		if ( isset( $_POST['reindex'] ) ) {
			wp_enqueue_script(
				'videoseo-admin-progress-bar',
				plugins_url( 'js/videoseo-admin-progressbar' . WPSEO_CSSJS_SUFFIX . '.js', WPSEO_VIDEO_FILE ),
				[ 'jquery' ],
				WPSEO_VIDEO_VERSION,
				true
			);
		}
	}

	/**
	 * Load styles for the admin in Video SEO
	 */
	public function admin_video_enqueue_styles() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Loads a page, doesn't perform any action yet.
		if ( isset( $_POST['reindex'] ) ) {
			wp_enqueue_style(
				'videoseo-admin-progress-bar-css',
				plugins_url( 'css/dist/videoseo-admin-progressbar.css', WPSEO_VIDEO_FILE ),
				[],
				WPSEO_VIDEO_VERSION
			);
		}
	}

	/**
	 * Load a small js file to facilitate ignoring admin messages
	 */
	public function admin_enqueue_scripts_ignore() {
		if ( ! $this->can_manage_options() ) {
			return;
		}

		wp_enqueue_script( 'videoseo-admin-global-script', plugins_url( 'js/videoseo-admin-global' . WPSEO_CSSJS_SUFFIX . '.js', WPSEO_VIDEO_FILE ), [ 'jquery' ], WPSEO_VIDEO_VERSION, true );
	}

	/**
	 * AJAX request handler for reindex posts
	 */
	public function index_posts_callback() {
		if ( isset( $_POST['nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'videoseo-ajax-nonce-for-reindex' ) ) {
			if ( isset( $_POST['type'] ) && $_POST['type'] === 'total_posts' ) {
				$total = 0;

				$sitemap_post_types = (array) WPSEO_Options::get( 'videositemap_posttypes', [] );
				foreach ( $sitemap_post_types as $post_type ) {
					$total += wp_count_posts( $post_type )->publish;
				}
				echo (int) $total;
			}
			elseif ( isset( $_POST['type'] ) && $_POST['type'] === 'index' ) {
				$start_time = time();

				$post_defaults = [
					'portion' => 5,
					'start'   => 0,
					'total'   => 0,
				];

				foreach ( $post_defaults as $key => $default ) {
					if ( isset( $_POST[ $key ] ) && is_numeric( $_POST[ $key ] ) ) {
						${$key} = (int) $_POST[ $key ];
					}
					else {
						${$key} = $default;
					}
				}

				$this->reindex( $portion, $start, $total );

				$end_time = time();

				// Return time in seconds that we've needed to index.
				echo (int) ( ( $end_time - $start_time ) + 1 );
			}
		}

		exit;
	}

	/**
	 * Returns the basename of the video-sitemap, the first portion of the name of the sitemap "file".
	 *
	 * Retrieves the video sitemap basename.
	 *
	 * @since 1.5.3
	 *
	 * @return string
	 */
	public function video_sitemap_basename() {
		return self::get_video_sitemap_basename();
	}

	/**
	 * Defaults to video, but it's possible to override it by using the YOAST_VIDEO_SITEMAP_BASENAME constant.
	 *
	 * @return string The sitemap basename.
	 */
	public static function get_video_sitemap_basename() {
		$basename = 'video';

		if ( post_type_exists( 'video' ) ) {
			$basename = 'yoast-video';
		}

		if ( defined( 'YOAST_VIDEO_SITEMAP_BASENAME' ) ) {
			$basename = YOAST_VIDEO_SITEMAP_BASENAME;
		}

		return $basename;
	}

	/**
	 * Return the Video Sitemap URL
	 *
	 * @since 1.2.1
	 * @since 3.8.0 The $extra parameter was added.
	 *
	 * @param string $extra Optionally suffix to add to the filename part of the sitemap url.
	 *
	 * @return string The URL to the video Sitemap.
	 */
	public function sitemap_url( $extra = '' ) {
		$sitemap = self::get_video_sitemap_basename() . '-sitemap' . $extra . '.xml';

		return WPSEO_Video_Wrappers::xml_sitemaps_base_url( $sitemap );
	}

	/**
	 * Adds the video XML sitemap to the Index Sitemap.
	 *
	 * @since  0.1
	 *
	 * @param string $str String with the filtered additions to the index sitemap in it.
	 *
	 * @return string String with the Video XML sitemap additions to the index sitemap in it.
	 */
	public function add_to_index( $str ) {
		$base = $GLOBALS['wp_rewrite']->using_index_permalinks() ? 'index.php/' : '';

		$sitemap_post_types = WPSEO_Options::get( 'videositemap_posttypes', [] );
		if ( is_array( $sitemap_post_types ) && $sitemap_post_types !== [] ) {
			// Use fields => ids to limit the overhead of fetching entire post objects, fetch only an array of ids instead to count.
            // phpcs:disable WordPress.DB.SlowDBQuery -- no other way.
			$args = [
				'post_type'      => $sitemap_post_types,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_key'       => '_yoast_wpseo_video_meta',
				'meta_compare'   => '!=',
				'meta_value'     => 'none',
				'fields'         => 'ids',
			];
			// phpcs:enable WordPress.DB.SlowDBQuery -- no other way.
			// Copy these args to be used and modify later.
			$date_args = $args;

			$video_ids = get_posts( $args );
			$count     = count( $video_ids );

			if ( $count > 0 ) {
				$n = ( $count > $this->max_entries ) ? (int) ceil( $count / $this->max_entries ) : 1;
				for ( $i = 0; $i < $n; $i++ ) {
					$count = ( $n > 1 ) ? ( $i + 1 ) : '';

					if ( empty( $count ) || $count === $n ) {
						$date_args['fields']         = 'all';
						$date_args['posts_per_page'] = 1;
						$date_args['offset']         = 0;
						$date_args['order']          = 'DESC';
						$date_args['orderby']        = 'modified';
					}
					else {
						$date_args['fields']         = 'all';
						$date_args['posts_per_page'] = 1;
						$date_args['offset']         = ( ( $this->max_entries * ( $i + 1 ) ) - 1 );
						$date_args['order']          = 'ASC';
						$date_args['orderby']        = 'modified';
					}

					$posts = get_posts( $date_args );
					$date  = $this->date->format( $posts[0]->post_modified_gmt );

					$text = ( $count > 1 ) ? $count : '';
					$str .= '<sitemap>' . "\n";
					$str .= '<loc>' . $this->sitemap_url( $text ) . '</loc>' . "\n";
					$str .= '<lastmod>' . $date . '</lastmod>' . "\n";
					$str .= '</sitemap>' . "\n";
				}
			}
		}

		return $str;
	}

	/**
	 * Adds oembed endpoints for supported video platforms that are not supported by core.
	 *
	 * @since 1.3.5
	 */
	public function add_oembed() {
		// @todo - check with official plugin.
		// Wistia.
		$wistia_regex  = '`(?:http[s]?:)?//[^/]*(wistia\.(com|net)|wi\.st#CUSTOM_URL#)/(medias|embed)/.*`i';
		$wistia_domain = WPSEO_Options::get( 'video_wistia_domain', '' );
		if ( $wistia_domain !== '' ) {
			$wistia_regex = str_replace( '#CUSTOM_URL#', '|' . preg_quote( $wistia_domain, '`' ), $wistia_regex );
		}
		else {
			$wistia_regex = str_replace( '#CUSTOM_URL#', '', $wistia_regex );
		}
		wp_oembed_add_provider( $wistia_regex, 'http://fast.wistia.com/oembed', true );

		// Viddler - WP native support removed in WP 4.0.
		wp_oembed_add_provider( '`http[s]?://(?:www\.)?viddler\.com/.*`i', 'http://lab.viddler.com/services/oembed/', true );

		// Screenr.
		wp_oembed_add_provider( '`http[s]?://(?:www\.)?screenr\.com/.*`i', 'http://www.screenr.com/api/oembed.{format}', true );

		// EVS.
		$evs_location = get_option( 'evs_location' );
		if ( $evs_location && ! empty( $evs_location ) ) {
			wp_oembed_add_provider( $evs_location . '/*', $evs_location . '/oembed.php', false );
		}
	}

	/**
	 * Synchronize the WP native oembed providers list for various WP versions.
	 *
	 * If VideoSEO users choose to stay on a lower WP version, they will still get the benefit of improved
	 * oembed regexes and provider compatibility this way.
	 *
	 * @param string[] $providers Providers.
	 *
	 * @return string[]
	 */
	public function sync_oembed_providers( $providers ) {

		// Support SSL urls for flick shortdomain (natively added in WP4.0).
		if ( isset( $providers['http://flic.kr/*'] ) ) {
			unset( $providers['http://flic.kr/*'] );
			$providers['#https?://flic\.kr/.*#i'] = [ 'https://www.flickr.com/services/oembed/', true ];
		}

		// Change to SSL for oembed provider domain (natively changed in WP4.0).
		if ( isset( $providers['#https?://(www\.)?flickr\.com/.*#i'] ) && strpos( $providers['#https?://(www\.)?flickr\.com/.*#i'][0], 'https' ) !== 0 ) {
			$providers['#https?://(www\.)?flickr\.com/.*#i'] = [ 'https://www.flickr.com/services/oembed/', true ];
		}

		// Allow any vimeo subdomain (natively changed in WP3.9).
		if ( isset( $providers['#https?://(www\.)?vimeo\.com/.*#i'] ) ) {
			unset( $providers['#https?://(www\.)?vimeo\.com/.*#i'] );
			$providers['#https?://(.+\.)?vimeo\.com/.*#i'] = [ 'http://vimeo.com/api/oembed.{format}', true ];
		}

		// Support SSL urls for wordpress.tv (natively added in WP4.0).
		if ( isset( $providers['http://wordpress.tv/*'] ) ) {
			unset( $providers['http://wordpress.tv/*'] );
			$providers['#https?://wordpress.tv/.*#i'] = [ 'http://wordpress.tv/oembed/', true ];
		}

		return $providers;
	}

	/**
	 * Add the MRSS namespace to the RSS feed.
	 *
	 * @since 0.1
	 */
	public function mrss_namespace() {
		echo ' xmlns:media="http://search.yahoo.com/mrss/" ';
	}

	/**
	 * Add the MRSS info to the feed
	 *
	 * Based upon the MRSS plugin {@link https://wordpress.org/plugins/mrss/} developed by Andy Skelton
	 *
	 * @since     0.1
	 * @copyright Andy Skelton
	 */
	public function mrss_item() {
		global $mrss_gallery_lookup;
		$media  = [];
		$lookup = [];

		// Honor the feed settings. Don't include any media that isn't in the feed.
		if ( get_option( 'rss_use_excerpt' ) || ! strlen( get_the_content() ) ) {
			ob_start();
			the_excerpt_rss();
			$content = ob_get_clean();
		}
		else {
			// If any galleries are processed, we need to capture the attachment IDs.
			add_filter( 'wp_get_attachment_link', [ $this, 'mrss_gallery_lookup' ], 10, 5 );
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Using a WP Core hook.
			$content = apply_filters( 'the_content', get_the_content() );
			remove_filter( 'wp_get_attachment_link', [ $this, 'mrss_gallery_lookup' ], 10, 5 );
			$lookup = $mrss_gallery_lookup;
			unset( $mrss_gallery_lookup );
		}

		$images = 0;
		if ( preg_match_all( '`<img ([^>]+)>`', $content, $matches ) ) {
			foreach ( $matches[1] as $attrs ) {
				$item = [];
				$img  = [];
				// Construct $img array from <img> attributes.
				$attributes = wp_kses_hair( $attrs, [ 'http' ] );
				foreach ( $attributes as $attr ) {
					$img[ $attr['name'] ] = $attr['value'];
				}
				unset( $attributes );

				// Skip emoticons and images without source attribute.
				if ( ! isset( $img['src'] ) || ( isset( $img['class'] ) && strpos( $img['class'], 'wp-smiley' ) !== false ) ) {
					continue;
				}

				$img['src'] = $this->mrss_url( $img['src'] );

				$id = false;
				if ( isset( $lookup[ $img['src'] ] ) ) {
					$id = $lookup[ $img['src'] ];
				}
				elseif ( isset( $img['class'] ) && preg_match( '`wp-image-(\d+)`', $img['class'], $match ) ) {
					$id = $match[1];
				}
				if ( $id ) {
					// It's an attachment, so we will get the URLs, title, and description from functions.
					$attachment = get_post( $id );
					$src        = wp_get_attachment_image_src( $id, 'full' );
					if ( ! empty( $src[0] ) ) {
						$img['src'] = $src[0];
					}
					$thumbnail = wp_get_attachment_image_src( $id, 'thumbnail' );
					if ( ! empty( $thumbnail[0] ) && $thumbnail[0] !== $img['src'] ) {
						$img['thumbnail'] = $thumbnail[0];
					}
					$title = get_the_title( $id );
					if ( ! empty( $title ) ) {
						$img['title'] = trim( $title );
					}
					if ( ! empty( $attachment->post_excerpt ) ) {
						$img['description'] = trim( $attachment->post_excerpt );
					}
				}
				// If this is the first image in the markup, make it the post thumbnail.
				if ( ++$images === 1 ) {
					if ( isset( $img['thumbnail'] ) ) {
						$media[]['thumbnail']['attr']['url'] = $img['thumbnail'];
					}
					else {
						$media[]['thumbnail']['attr']['url'] = $img['src'];
					}
				}

				$item['content']['attr']['url']    = $img['src'];
				$item['content']['attr']['medium'] = 'image';
				if ( ! empty( $img['title'] ) ) {
					$item['content']['children']['title']['attr']['type'] = 'html';
					$item['content']['children']['title']['children'][]   = $img['title'];
				}
				elseif ( ! empty( $img['alt'] ) ) {
					$item['content']['children']['title']['attr']['type'] = 'html';
					$item['content']['children']['title']['children'][]   = $img['alt'];
				}
				if ( ! empty( $img['description'] ) ) {
					$item['content']['children']['description']['attr']['type'] = 'html';
					$item['content']['children']['description']['children'][]   = $img['description'];
				}
				if ( ! empty( $img['thumbnail'] ) ) {
					$item['content']['children']['thumbnail']['attr']['url'] = $img['thumbnail'];
				}
				$media[] = $item;
			}
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Using a hook from the MediaRSS plugin.
		$media = apply_filters( 'mrss_media', $media );
		$this->mrss_print( $media );
	}

	/**
	 * Create an absolute URL for use in the MRSS info.
	 *
	 * @param string $url Variable to evaluate for URL.
	 *
	 * @return string
	 */
	public function mrss_url( $url ) {
		if ( preg_match( '`^(?:http[s]?:)//`', $url ) ) {
			return $url;
		}
		else {
			return home_url( $url );
		}
	}

	/**
	 * Add attachments to the MRSS gallery lookup array.
	 *
	 * @param string     $link Link tag.
	 * @param string|int $id   ID to lookup.
	 *
	 * @return string
	 */
	public function mrss_gallery_lookup( $link, $id ) {
		if ( preg_match( '` src="([^"]+)"`', $link, $matches ) ) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- not our global var.
			$GLOBALS['mrss_gallery_lookup'][ $matches[1] ] = $id;
		}

		return $link;
	}

	/**
	 * Print an MRSS item.
	 *
	 * @param array $media Media.
	 */
	public function mrss_print( $media ) {
		if ( ! empty( $media ) ) {
			foreach ( (array) $media as $element ) {
				$this->mrss_print_element( $element );
			}
		}
		echo "\n";
	}

	/**
	 * Print an MRSS element.
	 *
	 * @param array $element Element.
	 * @param int   $indent  Ident.
	 */
	public function mrss_print_element( $element, $indent = 2 ) {
		echo "\n";
		foreach ( (array) $element as $name => $data ) {
			// phpcs:ignore WordPress.Security.EscapeOutput -- This str_repeat() is safe.
			echo str_repeat( "\t", $indent );
			echo '<media:' . esc_attr( $name );

			if ( ! empty( $data['attr'] ) && is_array( $data['attr'] ) ) {
				foreach ( $data['attr'] as $attr => $value ) {
					echo ' ' . esc_attr( $attr ) . '="' . esc_attr( ent2ncr( $value ) ) . '"';
				}
			}
			if ( ! empty( $data['children'] ) && is_array( $data['children'] ) ) {
				$nl = false;
				echo '>';
				foreach ( $data['children'] as $_name => $_data ) {
					if ( is_int( $_name ) ) {
						echo ent2ncr( esc_html( $_data ) );
					}
					else {
						$nl = true;
						$this->mrss_print_element( [ $_name => $_data ], ( $indent + 1 ) );
					}
				}
				if ( $nl ) {
					// phpcs:ignore WordPress.Security.EscapeOutput -- This str_repeat() is safe.
					echo "\n" . str_repeat( "\t", $indent );
				}
				echo '</media:' . esc_attr( $name ) . '>';
			}
			else {
				echo ' />';
			}
		}
	}

	/**
	 * Add the video output to the MRSS feed.
	 *
	 * @since 0.1
	 *
	 * @param array $media Media.
	 *
	 * @return array
	 */
	public function mrss_add_video( $media ) {
		global $post;

		if ( WPSEO_Video_Utils::is_videoseo_active_for_posttype( $post->post_type ) === false ) {
			return $media;
		}

		$video = WPSEO_Meta::get_value( 'video_meta', $post->ID );

		if ( ! is_array( $video ) || $video === [] ) {
			return $media;
		}

		$video_duration = WPSEO_Meta::get_value( 'videositemap-duration', $post->ID );
		if ( $video_duration === '0' && isset( $video['duration'] ) ) {
			$video_duration = $video['duration'];
		}

		$item                                = [];
		$item['content']['attr']['url']      = $video['player_loc'];
		$item['content']['attr']['duration'] = $video_duration;
		$item['content']['children']['player']['attr']['url']       = $video['player_loc'];
		$item['content']['children']['title']['attr']['type']       = 'html';
		$item['content']['children']['title']['children'][]         = esc_html( $video['title'] );
		$item['content']['children']['description']['attr']['type'] = 'html';
		$item['content']['children']['description']['children'][]   = esc_html( $video['description'] );
		$item['content']['children']['thumbnail']['attr']['url']    = $video['thumbnail_loc'];

		if ( array_key_exists( 'tag', $video ) ) {
			$item['content']['children']['keywords']['children'][] = is_array( $video['tag'] ) ? implode( ',', $video['tag'] ) : $video['tag'];
		}
		else {
			$item['content']['children']['keywords']['children'][] = '';
		}

		array_unshift( $media, $item );

		return $media;
	}

	/**
	 * Parse the content of a post or term description.
	 *
	 * @since 1.3
	 * @see   WPSEO_Video_Analyse_Post
	 *
	 * @param string          $content The content to parse for videos.
	 * @param array           $vid     The video array to update.
	 * @param array           $old_vid The former video array.
	 * @param object|int|null $post    The post object or the post id of the post to analyse.
	 *
	 * @return array
	 */
	public function index_content( $content, $vid, $old_vid = [], $post = null ) {
		$index = new WPSEO_Video_Analyse_Post( $content, $vid, $old_vid, $post );

		return $index->get_vid_info();
	}

	/**
	 * Check and, if applicable, update video details for a term description
	 *
	 * @since 1.3
	 *
	 * @param object $term           The term to check the description and possibly update the video details for.
	 * @param bool   $send_to_screen Whether or not to echo the performed actions.
	 *
	 * @return array|string|false The video array that was just stored, or "none" if nothing
	 *                            was stored or false if not applicable.
	 */
	public function update_video_term_meta( $term, $send_to_screen = false ) {
		$sitemap_taxonomies = WPSEO_Options::get( 'videositemap_taxonomies', [] );
		if ( ! is_array( $sitemap_taxonomies ) || $sitemap_taxonomies === [] ) {
			return false;
		}

		if ( ! in_array( $term->taxonomy, $sitemap_taxonomies, true ) ) {
			return false;
		}

		$tax_meta = get_option( 'wpseo_taxonomy_meta' );
		$old_vid  = [];
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Check done elsewhere.
		if ( ! isset( $_POST['force'] ) ) {
			if ( isset( $tax_meta[ $term->taxonomy ]['_video'][ $term->term_id ] ) ) {
				$old_vid = $tax_meta[ $term->taxonomy ]['_video'][ $term->term_id ];
			}
		}

		$vid = [];

		$title = WPSEO_Taxonomy_Meta::get_term_meta( $term->term_id, $term->taxonomy, 'wpseo_title' );
		if ( empty( $title ) ) {
			$default_title = WPSEO_Options::get( 'title-' . $term->taxonomy, '' );
			if ( $default_title !== '' ) {
				$title = wpseo_replace_vars( $default_title, (array) $term );
			}
		}
		if ( empty( $title ) ) {
			$title = $term->name;
		}
		$vid['title'] = htmlspecialchars( $title, ENT_COMPAT, get_bloginfo( 'charset' ), true );

		$vid['description'] = WPSEO_Taxonomy_Meta::get_term_meta( $term->term_id, $term->taxonomy, 'wpseo_metadesc' );
		if ( ! $vid['description'] ) {
			$vid['description'] = esc_attr( preg_replace( '`\s+`', ' ', wp_html_excerpt( strip_shortcodes( get_term_field( 'description', $term->term_id, $term->taxonomy ) ), 300 ) ) );
		}

		$vid['publication_date'] = $this->date->format_timestamp( time() );

		// Concatenate genesis intro text and term description to index the videos for both.
		$genesis_term_meta = get_option( 'genesis-term-meta' );

		$content = '';
		if ( isset( $genesis_term_meta[ $term->term_id ]['intro_text'] ) && $genesis_term_meta[ $term->term_id ]['intro_text'] ) {
			$content .= $genesis_term_meta[ $term->term_id ]['intro_text'];
		}

		$content .= "\n" . $term->description;
		$content  = stripslashes( $content );

		$vid = $this->index_content( $content, $vid, $old_vid, null );

		if ( $vid !== 'none' ) {
			$tax_meta[ $term->taxonomy ]['_video'][ $term->term_id ] = $vid;
			// Don't bother with the complete tax meta validation.
			$tax_meta['wpseo_already_validated'] = true;
			update_option( 'wpseo_taxonomy_meta', $tax_meta );

			if ( $send_to_screen ) {
				$link = get_term_link( $term );
				if ( ! is_wp_error( $link ) ) {
					echo 'Updated <a href="' . esc_url( $link ) . '">' . esc_html( $vid['title'] ) . '</a> - ' . esc_html( $vid['type'] ) . '<br/>';
				}
			}
		}

		return $vid;
	}

	/**
	 * (Don't) validate the _video taxonomy metadata array
	 * Doesn't actually validate it atm, but having this function hooked in *does* make sure that the
	 * _video taxonomy metadata is not removed as it otherwise would be (by the normal taxonomy meta validation).
	 *
	 * @since 1.6
	 *
	 * @param array $tax_meta_data Received _video tax metadata.
	 *
	 * @return array Validated _video tax metadata
	 */
	public function validate_video_tax_meta( $tax_meta_data ) {
		return $tax_meta_data;
	}

	/**
	 * Check and, if applicable, update video details for a post
	 *
	 * @since 0.1
	 * @since 3.8  The $echo parameter was removed and the $post and $update parameters
	 *             added to be in line with the parameters received from the hook this
	 *             method is tied to.
	 * @since 11.x Removed the $update parameter as it was never used.
	 *
	 * @param int           $post_id The post ID to check and possibly update the video details for.
	 * @param \WP_Post|null $post    The post object.
	 *
	 * @return array|string|false The video array that was just stored, string "none" if nothing
	 *                            was stored or false if not applicable.
	 */
	public function update_video_post_meta( $post_id, $post = null ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Check done elsewhere.

		global $wp_query;

		// Bail if this is a multisite installation and the site has been switched.
		if ( is_multisite() && ms_is_switched() ) {
			return false;
		}

		if ( ! is_numeric( $post_id ) ) {
			// Get post ID from the request. Added this for our Elementor save hook.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Reason: Nonce has been verified earlier in the pipeline, We are not processing form information, We are casting to an integer.
			$post_id = isset( $_POST['post'] ) && is_string( $_POST['post'] ) ? (int) wp_unslash( $_POST['post'] ) : 0;
		}

		if ( ( ! isset( $post ) || ! ( $post instanceof WP_Post ) ) && is_numeric( $post_id ) ) {
			$post = get_post( $post_id );
		}

		if ( isset( $post ) && ( ! ( $post instanceof WP_Post ) || ! isset( $post->ID ) ) ) {
			return false;
		}

		if ( WPSEO_Video_Utils::is_videoseo_active_for_posttype( $post->post_type ) === false ) {
			return false;
		}

		$old_vid = [];
		if ( ! isset( $_POST['force'] ) ) {
			$old_vid = WPSEO_Meta::get_value( 'video_meta', $post->ID );
		}

		$title = WPSEO_Meta::get_value( 'title', $post->ID );
		if ( ! is_string( $title ) || $title === '' ) {
			$default_title = WPSEO_Options::get( 'title-' . $post->post_type, '' );
			if ( $default_title !== '' ) {
				$title = wpseo_replace_vars( $default_title, (array) $post );
			}
			else {
				$title = wpseo_replace_vars( '%%title%% - %%sitename%%', (array) $post );
			}
		}

		if ( ! is_string( $title ) || $title === '' ) {
			$title = $post->post_title;
		}

		$vid = [];

		// @todo [JRF->Yoast] Verify if this is really what we want. What about non-hierarchical custom post types ? and are we adjusting the main query output now ? could this cause bugs for others ?
		if ( $post->post_type === 'post' ) {
			$wp_query->is_single = true;
			$wp_query->is_page   = false;
		}
		else {
			$wp_query->is_single = false;
			$wp_query->is_page   = true;
		}

		$vid['post_id'] = $post->ID;
		$vid['title']   = htmlspecialchars( $title, ENT_COMPAT, get_bloginfo( 'charset' ), true );

		$vid['publication_date'] = $this->date->format( $post->post_date_gmt );

		$vid['description'] = WPSEO_Meta::get_value( 'metadesc', $post->ID );
		if ( ! is_string( $vid['description'] ) || $vid['description'] === '' ) {
			$default_description = WPSEO_Options::get( 'metadesc-' . $post->post_type, '' );
			if ( $default_description !== '' ) {
				$vid['description'] = wpseo_replace_vars( $default_description, (array) $post );
			}
			else {
				$vid['description'] = esc_attr( preg_replace( '`\s+`', ' ', wp_html_excerpt( strip_shortcodes( $post->post_content ), 300 ) ) );
			}
		}

		$vid = $this->index_content( $post->post_content, $vid, $old_vid, $post );

		if ( $vid !== 'none' ) {
			// Shouldn't be needed, but just in case.
			if ( isset( $vid['__add_to_content'] ) ) {
				unset( $vid['__add_to_content'] );
			}

			if ( ! isset( $vid['thumbnail_loc'] ) || empty( $vid['thumbnail_loc'] ) ) {
				$img = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'single-post-thumbnail' );
				if ( strpos( $img[0], 'http' ) !== 0 ) {
					$vid['thumbnail_loc'] = get_site_url( null, $img[0] );
				}
				else {
					$vid['thumbnail_loc'] = $img[0];
				}
			}

			// Grab the metadata from the post.
			$tags = wp_get_object_terms( $post->ID, 'post_tag', [ 'fields' => 'names' ] );

			if ( isset( $_POST['yoast_wpseo_videositemap-tags'] ) && ! empty( $_POST['yoast_wpseo_videositemap-tags'] ) ) {
				$extra_tags = explode( ',', sanitize_text_field( wp_unslash( $_POST['yoast_wpseo_videositemap-tags'] ) ) );
				$tags       = array_merge( $extra_tags, $tags );
			}

			$tag = [];
			if ( is_array( $tags ) ) {
				foreach ( $tags as $t ) {
					$tag[] = $t;
				}
			}
			elseif ( isset( $cats[0] ) ) {
				$tag[] = $cats[0]->name;
			}

			$focuskw = WPSEO_Meta::get_value( 'focuskw', $post->ID );
			if ( ! empty( $focuskw ) ) {
				$tag[] = $focuskw;
			}
			$vid['tag'] = $tag;

			if ( WPSEO_Video_Wrappers::is_development_mode() ) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- we're in development mode.
				error_log( 'Updated [' . esc_html( $post->post_title ) . '](' . esc_url( add_query_arg( [ 'p' => $post->ID ], home_url() ) ) . ') - ' . esc_html( $vid['type'] ) );
			}
		}

		WPSEO_Meta::set_value( 'video_meta', $vid, $post->ID );

		// phpcs:enable WordPress.Security.NonceVerification.Missing -- Check done elsewhere.

		return $vid;
	}

	/**
	 * Check whether the current visitor is really Google or Bing's bot by doing a reverse DNS lookup
	 *
	 * @since 1.2.2
	 *
	 * @return bool
	 */
	public function is_valid_bot() {
		if ( isset( $_SERVER['HTTP_USER_AGENT'] ) && isset( $_SERVER['REMOTE_ADDR'] ) && preg_match( '`(Google|bing)bot`', sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), $match ) ) {
			$hostname = gethostbyaddr( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) );

			if (
				( $match[1] === 'Google' && preg_match( '`googlebot\.com$`', $hostname ) && gethostbyname( $hostname ) === $_SERVER['REMOTE_ADDR'] )
				|| ( $match[1] === 'bing' && preg_match( '`search\.msn\.com$`', $hostname ) && gethostbyname( $hostname ) === $_SERVER['REMOTE_ADDR'] )
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the server protocol.
	 *
	 * @since 4.1.0
	 *
	 * @return string
	 */
	protected function get_server_protocol() {
		$protocol = 'HTTP/1.1';
		if ( isset( $_SERVER['SERVER_PROTOCOL'] ) && $_SERVER['SERVER_PROTOCOL'] !== '' ) {
			$protocol = sanitize_text_field( wp_unslash( $_SERVER['SERVER_PROTOCOL'] ) );
		}

		return $protocol;
	}

	/**
	 * Outputs the XSL file
	 */
	public function build_video_sitemap_xsl() {
		$protocol = $this->get_server_protocol();

		// Force a 200 header and replace other status codes.
		header( $protocol . ' 200 OK', true, 200 );

		// Set the right content / mime type.
		header( 'Content-Type: text/xml' );

		// Prevent the search engines from indexing the XML Sitemap.
		header( 'X-Robots-Tag: noindex, follow', true );

		// Make the browser cache this file properly.
		header( 'Pragma: public' );
		header( 'Cache-Control: maxage=' . YEAR_IN_SECONDS );
		header( 'Expires: ' . $this->date->format_timestamp( ( time() + YEAR_IN_SECONDS ), 'D, d M Y H:i:s' ) . ' GMT' );

		global $wp_filesystem;
		require_once ABSPATH . '/wp-admin/includes/file.php';
		WP_Filesystem();

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- This file is straight from this plugin.
		echo $wp_filesystem->get_contents( plugin_dir_path( WPSEO_VIDEO_FILE ) . 'xml-video-sitemap.xsl' );

		die();
	}

	/**
	 * The main function of this class: it generates the XML sitemap's contents.
	 *
	 * @since 0.1
	 */
	public function build_video_sitemap() {
		$protocol = $this->get_server_protocol();

		// Restrict access to the video sitemap to admins and valid bots.
		if ( WPSEO_Options::get( 'video_cloak_sitemap' ) === true && ( ! $this->can_manage_options() && ! $this->is_valid_bot() ) ) {
			header( $protocol . ' 403 Forbidden', true, 403 );
			wp_die( "We're sorry, access to our video sitemap is restricted to site admins and valid Google & Bing bots." );
		}

		// Force a 200 header and replace other status codes.
		header( $protocol . ' 200 OK', true, 200 );

		$output = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">' . "\n";

		$printed_post_ids = [];

		$steps  = $this->max_entries;
		$n      = (int) get_query_var( 'sitemap_n' );
		$offset = ( $n > 1 ) ? ( ( $n - 1 ) * $this->max_entries ) : 0;
		$total  = ( $offset + $this->max_entries );

		$sitemap_post_types = WPSEO_Options::get( 'videositemap_posttypes', [] );
		if ( is_array( $sitemap_post_types ) && $sitemap_post_types !== [] ) {
			// Set the initial args array to get videos in chunks.
            // phpcs:disable WordPress.DB.SlowDBQuery -- Ain't no other way.
			$args = [
				'post_type'      => $sitemap_post_types,
				'post_status'    => 'publish',
				'posts_per_page' => $steps,
				'offset'         => $offset,
				'meta_key'       => '_yoast_wpseo_video_meta',
				'meta_compare'   => '!=',
				'meta_value'     => 'none',
				'order'          => 'ASC',
				'orderby'        => 'post_modified',
			];
			// phpcs:enable WordPress.DB.SlowDBQuery -- Ain't no other way.

			/*
			 * @TODO: add support to tax video to honor pages
			 *        add a bool to the while loop to see if tax has been processed
			 *        if $items is empty the posts are done so move on to tax
			 *
			 *        do some math between $printed_post_ids and $this-max_entries to figure out
			 *        how many from tax to add to this pagination
			 */

			// Add entries to the sitemap until the total is hit (rounded up by nearest $steps).
			$items = get_posts( $args );
			while ( ( $total > $offset ) && $items ) {

				if ( is_array( $items ) && $items !== [] ) {
					foreach ( $items as $item ) {
						if ( ! is_object( $item ) || in_array( $item->ID, $printed_post_ids, true ) ) {
							continue;
						}
						else {
							$printed_post_ids[] = $item->ID;
						}

						if ( WPSEO_Meta::get_value( 'meta-robots-noindex', $item->ID ) === '1' ) {
							continue;
						}

						$disable = WPSEO_Meta::get_value( 'videositemap-disable', $item->ID );
						if ( $disable === 'on' ) {
							continue;
						}

						$video = WPSEO_Meta::get_value( 'video_meta', $item->ID );

						$video = WPSEO_Video_Utils::get_video_image( $item->ID, $video );

						// When we don't have a thumbnail and either a player_loc or a content_loc, skip this video.
						if ( ! isset( $video['thumbnail_loc'] )
							|| ( ! isset( $video['player_loc'] ) && ! isset( $video['content_loc'] ) )
						) {
							continue;
						}

						$video_duration = WPSEO_Meta::get_value( 'videositemap-duration', $item->ID );
						if ( $video_duration > 0 ) {
							$video['duration'] = $video_duration;
						}

						$video['permalink'] = get_permalink( $item );

						/**
						 * Filter: 'wpseo_video_rating' - Allow changing the rating for a video on output.
						 *
						 * @api float $rating A rating between 0 and 5.
						 *
						 * @param int $post_id The ID of the post the video is in.
						 */
						$rating = apply_filters( 'wpseo_video_rating', WPSEO_Meta::get_value( 'videositemap-rating', $item->ID ) );
						if ( $rating && WPSEO_Meta_Video::sanitize_rating( null, $rating, WPSEO_Meta_Video::$meta_fields['video']['videositemap-rating'] ) ) {
							$video['rating'] = number_format( $rating, 1 );
						}

						$video['family_friendly'] = 'yes';
						if ( WPSEO_Video_Utils::is_video_family_friendly( $item->ID ) === false ) {
							$video['family_friendly'] = 'no';
						}

						$video['author'] = $item->post_author;

						$output .= $this->print_sitemap_line( $video, $item );
					}
				}

				// Update these args for the next iteration.
				$offset          = ( $offset + $steps );
				$args['offset'] += $steps;
				$items           = get_posts( $args );
			}
		}

		$tax_meta = get_option( 'wpseo_taxonomy_meta' );
		$terms    = [];

		$sitemap_taxonomies = WPSEO_Options::get( 'videositemap_taxonomies', [] );
		if ( is_array( $sitemap_taxonomies ) && $sitemap_taxonomies !== [] ) {
			$terms = get_terms(
				[
					'taxonomy' => array_values( $sitemap_taxonomies ),
				]
			);
		}

		if ( is_array( $terms ) && $terms !== [] ) {
			foreach ( $terms as $term ) {
				if ( is_object( $term ) && isset( $tax_meta[ $term->taxonomy ]['_video'][ $term->term_id ] ) ) {
					$video = $tax_meta[ $term->taxonomy ]['_video'][ $term->term_id ];
					if ( is_array( $video ) ) {
						$video['permalink'] = get_term_link( $term, $term->taxonomy );
						$video['tag']       = $term->name;
						$output            .= $this->print_sitemap_line( $video, $term );
					}
				}
			}
		}

		$output .= '</urlset>';

		WPSEO_Video_Wrappers::set_sitemap( $output );
		WPSEO_Video_Wrappers::set_stylesheet( $this->get_stylesheet_line() );
	}

	/**
	 * Print a full <url> line in the sitemap.
	 *
	 * @since 1.3
	 *
	 * @param array  $video              The video object to print out.
	 * @param object $post_or_tax_object The post/tax object this video relates to.
	 *
	 * @return string The output generated
	 */
	public function print_sitemap_line( $video, $post_or_tax_object ) {
		if ( ! is_array( $video ) || $video === [] ) {
			return '';
		}

		$output  = "\t<url>\n";
		$output .= "\t\t<loc>" . esc_url( $video['permalink'] ) . '</loc>' . "\n";
		$output .= "\t\t<video:video>\n";


		if ( empty( $video['publication_date'] ) || WPSEO_Video_Wrappers::is_valid_datetime( $video['publication_date'] ) === false ) {
			$post = $post_or_tax_object;
			if ( is_object( $post ) && $post->post_date_gmt !== '0000-00-00 00:00:00' && WPSEO_Video_Wrappers::is_valid_datetime( $post->post_date_gmt ) ) {
				$video['publication_date'] = $this->date->format( $post->post_date_gmt );
			}
			elseif ( is_object( $post ) && $post->post_date !== '0000-00-00 00:00:00' && WPSEO_Video_Wrappers::is_valid_datetime( $post->post_date ) ) {
				$video['publication_date'] = $this->date->format( get_gmt_from_date( $post->post_date ) );
			}
			else {
				return '<!-- Post with ID ' . $video['post_id'] . 'skipped, because there\'s no valid date in the DB for it. -->';
			} // If we have no valid date for the post, skip the video and don't print it in the XML Video Sitemap.
		}

		// @todo - We should really switch to whitelist format, rather than blacklist
		$video_keys_to_skip = [
			'id',
			'url',
			'type',
			'permalink',
			'post_id',
			'hd',
			'maybe_local',
			'attachment_id',
			'file_path',
			'file_url',
			'last_fetched',
		];

		foreach ( $video as $key => $val ) {
			if ( in_array( $key, $video_keys_to_skip, true ) ) {
				continue;
			}

			if ( $key === 'author' ) {
				$output .= "\t\t\t<video:uploader info='" . get_author_posts_url( $val ) . "'>" . ent2ncr( esc_html( get_the_author_meta( 'display_name', $val ) ) ) . "</video:uploader>\n";
				continue;
			}

			if ( $key === 'description' && empty( $val ) ) {
				$val = $video['title'];
			}

			if ( is_scalar( $val ) && ! empty( $val ) ) {
				$prepare_sitemap_line = $this->get_single_sitemap_line( $val, $key, '', $post_or_tax_object );

				if ( ! is_null( $prepare_sitemap_line ) ) {
					$output .= $prepare_sitemap_line;
				}
			}
			elseif ( is_array( $val ) && $val !== [] ) {
				$i = 1;
				foreach ( $val as $v ) {
					// Only 32 tags are allowed.
					if ( $key === 'tag' && $i > 32 ) {
						break;
					}
					$prepare_sitemap_line = $this->get_single_sitemap_line( $v, $key, '', $post_or_tax_object );

					if ( ! is_null( $prepare_sitemap_line ) ) {
						$output .= $prepare_sitemap_line;
					}

					++$i;
				}
			}
		}

		// Allow custom implementations with extra tags here.
		$output .= apply_filters( 'wpseo_video_item', '', isset( $video['post_id'] ) ? $video['post_id'] : 0 );

		$output .= "\t\t</video:video>\n";

		$output .= "\t</url>\n";

		return $output;
	}

	/**
	 * Cleans a string for XML display purposes.
	 *
	 * @since 1.2.1
	 *
	 * @link http://php.net/html-entity-decode#98697 Modified for WP from here.
	 *
	 * @param string   $in     The string to clean.
	 * @param int|null $offset Offset of the string to start the cleaning at.
	 *
	 * @return string Cleaned string.
	 */
	public function clean_string( $in, $offset = 0 ) {
		$out = trim( $in );
		$out = strip_shortcodes( $out );
		$out = html_entity_decode( $out, ENT_QUOTES, 'ISO-8859-15' );
		$out = html_entity_decode( $out, ENT_QUOTES, get_bloginfo( 'charset' ) );
		if ( ! empty( $out ) ) {
			$entity_start = strpos( $out, '&', $offset );
			if ( $entity_start === false ) {
				return _wp_specialchars( $out );
			}
			else {
				$entity_end = strpos( $out, ';', $entity_start );
				if ( $entity_end === false ) {
					return _wp_specialchars( $out );
				}
				elseif ( $entity_end > ( $entity_start + 7 ) ) {
					$out = $this->clean_string( $out, ( $entity_start + 1 ) );
				}
				else {
					$clean  = substr( $out, 0, $entity_start );
					$subst  = substr( $out, ( $entity_start + 1 ), 1 );
					$clean .= ( $subst !== '#' ) ? $subst : '_';
					$clean .= substr( $out, ( $entity_end + 1 ) );
					$out    = $this->clean_string( $clean, ( $entity_start + 1 ) );
				}
			}
		}

		return _wp_specialchars( $out );
	}

	/**
	 * Roughly calculate the length of an FLV video.
	 *
	 * @since 1.3.1
	 *
	 * @param string $file The path to the video file to calculate the length for.
	 *
	 * @return int Duration of the video
	 */
	public function get_flv_duration( $file ) {
        // phpcs:disable WordPress.WP.AlternativeFunctions -- rewriting this as WP filesystem isn't worth it.
		if ( is_file( $file ) && is_readable( $file ) ) {
			$flv = fopen( $file, 'rb' );
			if ( is_resource( $flv ) ) {
				fseek( $flv, -4, SEEK_END );
				$arr             = unpack( 'N', fread( $flv, 4 ) );
				$last_tag_offset = $arr[1];
				fseek( $flv, -( $last_tag_offset + 4 ), SEEK_END );
				fseek( $flv, 4, SEEK_CUR );
				$t0                    = fread( $flv, 3 );
				$t1                    = fread( $flv, 1 );
				$arr                   = unpack( 'N', $t1 . $t0 );
				$milliseconds_duration = $arr[1];

				return $milliseconds_duration;
			}
		}
		// phpcs:enable WordPress.WP.AlternativeFunctions -- rewriting this as WP filesystem isn't worth it.
		return 0;
	}

	/**
	 * Outputs the admin panel for the Video Sitemaps on the XML Sitemaps page with the WP SEO admin
	 *
	 * @since 0.1
	 */
	public function admin_panel() {
		$sitemap_url        = null;
		$sitemap_post_types = WPSEO_Options::get( 'videositemap_posttypes', [] );
		if ( is_array( $sitemap_post_types ) && $sitemap_post_types !== [] ) {
			// Use fields => ids to limit the overhead of fetching entire post objects, fetch only an array of ids instead to count.
			// phpcs:disable WordPress.DB.SlowDBQuery -- no other way to do this.
			$args = [
				'post_type'      => $sitemap_post_types,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_key'       => '_yoast_wpseo_video_meta',
				'meta_compare'   => '!=',
				'meta_value'     => 'none',
				'fields'         => 'ids',
			];
			// phpcs:enable WordPress.DB.SlowDBQuery -- no other way to do this.
			$video_ids   = get_posts( $args );
			$count       = count( $video_ids );
			$n           = ( $count > $this->max_entries ) ? (int) ceil( $count / $this->max_entries ) : '';
			$sitemap_url = $this->sitemap_url( $n );
		}

		$admin_page = new WPSEO_Video_Admin_Page();
		$admin_page->display( $sitemap_url );
	}

	/**
	 * A better strip tags that leaves spaces intact (and rips out more code)
	 *
	 * @since 1.3.4
	 *
	 * @link http://php.net/strip-tags#110280
	 *
	 * @param string $text Text string to strip tags from.
	 *
	 * @return string
	 */
	public function strip_tags( $text ) {

		// ----- remove HTML TAGs -----
		$text = preg_replace( '/<[^>]*>/', ' ', $text );

		// ----- remove control characters -----
		$text = str_replace( "\r", '', $text ); // --- replace with empty space
		$text = str_replace( "\n", ' ', $text ); // --- replace with space
		$text = str_replace( "\t", ' ', $text ); // --- replace with space

		// ----- remove multiple spaces -----
		$text = trim( preg_replace( '/ {2,}/', ' ', $text ) );

		return $text;
	}

	/**
	 * Add the video and yandex namespaces to the namespaces in the html prefix attribute.
	 *
	 * @since 4.1.0
	 *
	 * @link http://ogp.me/#type_video
	 * @link https://yandex.com/support/webmaster/video/open-graph.xml
	 *
	 * @param string[] $namespaces Currently registered namespaces.
	 *
	 * @return string[]
	 */
	public function add_video_namespaces( $namespaces ) {
		$namespaces[] = 'video: http://ogp.me/ns/video#';

		/**
		 * Allow for turning off Yandex support.
		 *
		 * @since 4.1.0
		 *
		 * @param bool Whether or not to support (add) Yandex specific video SEO
		 *             meta tags. Defaults to `true`.
		 *             Return `false` to disable Yandex support.
		 */
		if ( apply_filters( 'wpseo_video_yandex_support', true ) === true ) {
			$namespaces[] = 'ya: http://webmaster.yandex.ru/vocabularies/';
		}

		return $namespaces;
	}

	/**
	 * Switch the Twitter card type to player if needed.
	 *
	 * {@internal [JRF] This method does not seem to be hooked in anywhere.}
	 *
	 * @param string $type The Twitter card type.
	 *
	 * @return string
	 */
	public function card_type( $type ) {
		return $this->type_filter( $type, 'player' );
	}

	/**
	 * Helper function for Twitter and OpenGraph card types
	 *
	 * @param string $type         The card type.
	 * @param string $video_output Output.
	 *
	 * @return string
	 */
	public function type_filter( $type, $video_output ) {
		global $post;

		if ( is_singular() ) {
			if ( is_object( $post ) ) {
				if ( WPSEO_Video_Utils::is_videoseo_active_for_posttype( $post->post_type ) === false ) {
					return $type;
				}

				$video = WPSEO_Meta::get_value( 'video_meta', $post->ID );
				if ( ! is_array( $video ) || $video === [] ) {
					return $type;
				}

				$disable = WPSEO_Meta::get_value( 'videositemap-disable', $post->ID );
				if ( $disable === 'on' ) {
					return $type;
				}

				return $video_output;
			}
		}
		elseif ( is_tax() || is_category() || is_tag() ) {
			$term = get_queried_object();

			$sitemap_taxonomies = WPSEO_Options::get( 'videositemap_taxonomies', [] );
			if ( is_array( $sitemap_taxonomies ) && in_array( $term->taxonomy, $sitemap_taxonomies, true ) ) {
				$tax_meta = get_option( 'wpseo_taxonomy_meta' );
				if ( isset( $tax_meta[ $term->taxonomy ]['_video'][ $term->term_id ] ) ) {
					return $video_output;
				}
			}
		}

		return $type;
	}

	/**
	 * Filter the OpenGraph image for the post and sets it to the video thumbnail
	 *
	 * @param Images $image_container The WPSEO OpenGraph image object.
	 *
	 * @return void
	 */
	public function opengraph_image( Images $image_container ) {
		if ( is_singular() ) {
			$post = get_queried_object();

			if ( is_object( $post ) ) {
				// If there are images already, the video still is probably not going the be the best image, so bail.
				if ( $image_container->get_images() !== [] ) {
					return;
				}

				if ( WPSEO_Video_Utils::is_videoseo_active_for_posttype( $post->post_type ) === false ) {
					return;
				}

				$disable = WPSEO_Meta::get_value( 'videositemap-disable', $post->ID );
				if ( $disable === 'on' ) {
					return;
				}

				$video = WPSEO_Meta::get_value( 'video_meta', $post->ID );
				if ( ! is_array( $video ) || $video === [] ) {
					return;
				}

				$image_container->add_image_by_url( $video['thumbnail_loc'] );

				return;
			}

			return;
		}

		if ( is_tax() || is_category() || is_tag() ) {
			$term = get_queried_object();

			$sitemap_taxonomies = WPSEO_Options::get( 'videositemap_taxonomies', [] );
			if ( is_array( $sitemap_taxonomies ) && in_array( $term->taxonomy, $sitemap_taxonomies, true ) ) {
				$tax_meta = get_option( 'wpseo_taxonomy_meta' );
				if ( isset( $tax_meta[ $term->taxonomy ]['_video'][ $term->term_id ] ) ) {
					$video = $tax_meta[ $term->taxonomy ]['_video'][ $term->term_id ];
					$image_container->add_image_by_url( $video['thumbnail_loc'] );
				}
			}
		}
	}

	/**
	 * Make the get_terms query only return terms with a non-empty description.
	 *
	 * @since 1.3
	 *
	 * @param array $pieces The separate pieces of the terms query to filter.
	 *
	 * @return string[]
	 */
	public function filter_terms_clauses( $pieces ) {
		$pieces['where'] .= " AND tt.description != ''";

		return $pieces;
	}

	/**
	 * Get a single sitemap line to output in the xml sitemap
	 *
	 * @param string $val                Value.
	 * @param string $key                Key.
	 * @param string $xtra               Extra.
	 * @param object $post_or_tax_object The post/tax object this value relates to.
	 *
	 * @return string|null
	 */
	private function get_single_sitemap_line( $val, $key, $xtra, $post_or_tax_object ) {
		$val = $this->clean_string( $val );
		if ( in_array( $key, [ 'description', 'category', 'tag', 'title' ], true ) ) {
			$val = ent2ncr( esc_html( $val ) );
		}
		if ( ! empty( $val ) ) {
			$val = wpseo_replace_vars( $val, $post_or_tax_object );
			$val = _wp_specialchars( html_entity_decode( $val, ENT_QUOTES, 'UTF-8' ) );

			if ( in_array( $key, [ 'description', 'category', 'tag', 'title' ], true ) ) {
				$val = '<![CDATA[' . $val . ']]>';
			}

			return "\t\t\t<video:" . $key . $xtra . '>' . $val . '</video:' . $key . ">\n";
		}

		return null;
	}

	/**
	 * Reindex the video info from posts
	 *
	 * @since 0.1
	 * @since 3.8 $total parameter was added.
	 *
	 * @param int $portion Number of posts.
	 * @param int $start   Offset.
	 * @param int $total   Total number of posts which will be re-indexed.
	 */
	private function reindex( $portion, $start, $total ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$sitemap_post_types = WPSEO_Options::get( 'videositemap_posttypes', [] );
		if ( is_array( $sitemap_post_types ) && $sitemap_post_types !== [] ) {
			$args = [
				'post_type'   => $sitemap_post_types,
				'post_status' => 'publish',
				'numberposts' => $portion,
				'offset'      => $start,
			];

            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- we don't have to verify this with a nonce, we have to verify the overall action.
			if ( ! isset( $_POST['force'] ) ) {
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- no other way to do this.
				$args['meta_query'] = [
					'key'     => '_yoast_wpseo_video_meta',
					'compare' => 'NOT EXISTS',
				];
			}


			$results      = get_posts( $args );
			$result_count = count( $results );

			if ( is_array( $results ) && $result_count > 0 ) {
				foreach ( $results as $post ) {
					if ( $post instanceof WP_Post ) {
						$this->update_video_post_meta( $post->ID, $post );
					}
					elseif ( is_numeric( $post ) ) {
						$this->update_video_post_meta( $post );
					}
					flush(); // Clear system output buffer if any exist.
				}
			}
		}

		if ( ( $start + $portion ) >= $total ) {
			// Get all the non-empty terms.
			add_filter( 'terms_clauses', [ $this, 'filter_terms_clauses' ] );
			$terms              = [];
			$sitemap_taxonomies = WPSEO_Options::get( 'videositemap_taxonomies', [] );
			if ( is_array( $sitemap_taxonomies ) && $sitemap_taxonomies !== [] ) {
				foreach ( $sitemap_taxonomies as $val ) {
					$new_terms = get_terms( $val );
					if ( is_array( $new_terms ) ) {
						$terms = array_merge( $terms, $new_terms );
					}
				}
			}
			remove_filter( 'terms_clauses', [ $this, 'filter_terms_clauses' ] );

			if ( count( $terms ) > 0 ) {

				foreach ( $terms as $term ) {
					$this->update_video_term_meta( $term, false );
					flush();
				}
			}

			// As this is used from within an AJAX call, we don't queue the cache clearing,
			// but do a hard reset.
			WPSEO_Video_Wrappers::invalidate_cache_storage( self::get_video_sitemap_basename() );

			// Ping the search engines with our updated XML sitemap, we ping with the index sitemap because
			// we don't know which video sitemap, or sitemaps, have been updated / added.
			WPSEO_Video_Wrappers::ping_search_engines();

			// Remove the admin notice.
			delete_transient( 'video_seo_recommend_reindex' );
		}
	}

	/**
	 * Retrieves the XSL URL that should be used in the current environment
	 *
	 * When home_url and site_url are not the same, the home_url should be used.
	 * This is because the XSL needs to be served from the same domain, protocol and port
	 * as the XML file that is loading it.
	 *
	 * @return string The XSL URL that needs to be used.
	 */
	protected function get_xsl_url() {
		if ( home_url() !== site_url() ) {
			return home_url( 'video-sitemap.xsl' );
		}

		return plugin_dir_url( WPSEO_VIDEO_FILE ) . 'xml-video-sitemap.xsl';
	}

	/**
	 * Checks if the user can manage options.
	 *
	 * @since 5.6.0
	 *
	 * @return bool True if the user can manage options.
	 */
	protected function can_manage_options() {
		if ( class_exists( 'WPSEO_Capability_Utils' ) ) {
			return WPSEO_Capability_Utils::current_user_can( 'wpseo_manage_options' );
		}

		return false;
	}

	/**
	 * Retrieves the maximum number of entries per XML sitemap.
	 *
	 * @return int The maximum number of entries.
	 */
	protected function get_entries_per_page() {
		/**
		 * Filter the maximum number of entries per XML sitemap.
		 *
		 * @param int $entries The maximum number of entries per XML sitemap.
		 */
		return (int) apply_filters( 'wpseo_sitemap_entries_per_page', 1000 ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Using a YoastSEO Free hook.
	}
}
