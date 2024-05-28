<?php
/**
 * Yoast SEO Video plugin file.
 *
 * @package Internals
 * @since      1.6.0
 * @version    1.6.0
 */

// Avoid direct calls to this file.
if ( ! class_exists( 'WPSEO_Video_Sitemap' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

if ( ! class_exists( 'WPSEO_Option_Video' ) ) {

	/**
	 * Class WPSEO_Option_Video
	 */
	class WPSEO_Option_Video extends WPSEO_Option {

		/**
		 * Option name.
		 *
		 * @var string
		 */
		public $option_name = 'wpseo_video';

		/**
		 * Array of defaults for the option.
		 *
		 * Shouldn't be requested directly, use $this->get_defaults();
		 *
		 * @var array<string, int|bool|string|array<mixed>>
		 */
		protected $defaults = [
			// Non-form fields, set via validation routine / license activation method.
			// Leave default as 0 to ensure activation/upgrade works.
			'video_dbversion'            => 0,

			// Form fields.
			'video_cloak_sitemap'        => false,
			'video_disable_rss'          => false,
			'video_custom_fields'        => '',
			'video_facebook_embed'       => true, // N.B.: The name of this property is outdated, should be `allow_external_embeds`.
			'video_fitvids'              => false,
			'video_content_width'        => '',
			'video_wistia_domain'        => '',
			'video_embedly_api_key'      => '',
			'videositemap_posttypes'     => [],
			'videositemap_taxonomies'    => [],
			'video_youtube_faster_embed' => false,
		];

		/**
		 * Get the singleton instance of this class
		 *
		 * @return self
		 */
		public static function get_instance() {
			if ( ! ( self::$instance instanceof self ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Registers the option with the options framework.
		 *
		 * @return void
		 */
		public static function register_option() {
			WPSEO_Options::register_option( self::get_instance() );
		}

		/**
		 * Add dynamically created default option based on available post types
		 *
		 * @return void
		 */
		public function enrich_defaults() {
			$this->defaults['videositemap_posttypes'] = get_post_types( [ 'public' => true ] );
		}

		/**
		 * Validate the option
		 *
		 * @param array<string, mixed> $dirty New value for the option.
		 * @param array<string, mixed> $clean Clean value for the option, normally the defaults.
		 * @param array<string, mixed> $old   Old value of the option.
		 *
		 * @return array<string, mixed> Validated clean value for the option to be saved to the database
		 */
		protected function validate_option( $dirty, $clean, $old ) {

			foreach ( $clean as $key => $value ) {
				switch ( $key ) {
					case 'video_dbversion':
						$clean[ $key ] = WPSEO_VIDEO_VERSION;
						break;

					case 'videositemap_posttypes':
						$clean[ $key ]    = [];
						$valid_post_types = get_post_types( [ 'public' => true ] );
						if ( isset( $dirty[ $key ] ) && ( is_array( $dirty[ $key ] ) && $dirty[ $key ] !== [] ) ) {
							foreach ( $dirty[ $key ] as $k => $v ) {
								if ( in_array( $k, $valid_post_types, true ) ) {
									$clean[ $key ][ $k ] = $v;
								}
								elseif ( sanitize_title_with_dashes( $k ) === $k ) {
									// Allow post types which may not be registered yet.
									$clean[ $key ][ $k ] = $v;
								}
							}
						}
						break;

					case 'videositemap_taxonomies':
						$clean[ $key ]    = [];
						$valid_taxonomies = get_taxonomies( [ 'public' => true ] );
						if ( isset( $dirty[ $key ] ) && ( is_array( $dirty[ $key ] ) && $dirty[ $key ] !== [] ) ) {
							foreach ( $dirty[ $key ] as $k => $v ) {
								if ( in_array( $k, $valid_taxonomies, true ) ) {
									$clean[ $key ][ $k ] = $v;
								}
								elseif ( sanitize_title_with_dashes( $k ) === $k ) {
									// Allow taxonomies which may not be registered yet.
									$clean[ $key ][ $k ] = $v;
								}
							}
						}
						break;

					// Text field - may not be in form.
					// @todo - validate custom fields against meta table?
					case 'video_custom_fields':
						if ( isset( $dirty[ $key ] ) && $dirty[ $key ] !== '' ) {
							$clean[ $key ] = sanitize_text_field( $dirty[ $key ] );
						}
						break;

					// @todo - validate domains in some way?
					case 'video_wistia_domain':
						if ( isset( $dirty[ $key ] ) && $dirty[ $key ] !== '' ) {
							$clean[ $key ] = sanitize_text_field( urldecode( $dirty[ $key ] ) );
							$clean[ $key ] = preg_replace( [ '`^http[s]?://`', '`^//`', '`/$`' ], '', $clean[ $key ] );
						}
						break;

					case 'video_embedly_api_key':
						if ( isset( $dirty[ $key ] ) && $dirty[ $key ] !== '' && preg_match( '`^[a-f0-9]{32}$`', $dirty[ $key ] ) ) {
							$clean[ $key ] = sanitize_text_field( $dirty[ $key ] );
						}
						break;

					// Numeric text field - may not be in form.
					case 'video_content_width':
						if ( isset( $dirty[ $key ] ) && $dirty[ $key ] !== '' ) {
							$int = WPSEO_Video_Wrappers::yoast_wpseo_video_validate_int( $dirty[ $key ] );

							if ( $int !== false && $int > 0 ) {
								$clean[ $key ] = $int;
							}
						}
						break;

					// Boolean (checkbox) field - may not be in form.
					case 'video_cloak_sitemap':
					case 'video_disable_rss':
					case 'video_facebook_embed':
					case 'video_fitvids':
					case 'video_youtube_faster_embed':
						$clean[ $key ] = false;
						if ( isset( $dirty[ $key ] ) ) {
							$clean[ $key ] = WPSEO_Video_Wrappers::validate_bool( $dirty[ $key ] );
						}
						break;
				}
			}

			return $clean;
		}
	}
}
