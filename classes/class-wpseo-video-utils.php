<?php
/**
 * Utilities for fetching video data.
 *
 * @package    WordPress SEO
 * @subpackage WordPress SEO Video
 *
 * @since 11.1
 */

/**
 * Utility functions to get data about videos.
 *
 * @package WordPress SEO Video
 * @since   11.1
 */
class WPSEO_Video_Utils {

	/**
	 * Check whether VideoSEO is active for a specific post type.
	 *
	 * @since 11.1
	 *
	 * @param string $post_type The post type to check for.
	 *
	 * @return bool True if active, false if inactive.
	 */
	public static function is_videoseo_active_for_posttype( $post_type ) {
		$sitemap_post_types = WPSEO_Options::get( 'videositemap_posttypes', [] );
		if ( ! is_array( $sitemap_post_types ) || $sitemap_post_types === [] ) {
			return false;
		}

		return in_array( $post_type, $sitemap_post_types, true );
	}

	/**
	 * Retrieves the video meta data for a post.
	 *
	 * @param WP_Post $post The post for which to retrieve the video meta data.
	 *
	 * @return array|false The video metadata, or `false` if not meta data could be retrieved.
	 */
	public static function get_video_for_post( $post ) {
		if ( self::is_videoseo_active_for_posttype( $post->post_type ) === false ) {
			return false;
		}

		$disable = WPSEO_Meta::get_value( 'videositemap-disable', $post->ID );
		if ( $disable === 'on' ) {
			return false;
		}

		$video = WPSEO_Meta::get_value( 'video_meta', $post->ID );

		// For Youtube, refresh the video data every 30 days.
		$thirty_days = ( DAY_IN_SECONDS * 30 );

		if ( is_array( $video ) && $video !== [] ) {
			$video             = self::get_video_image( $post->ID, $video );
			$video['duration'] = self::get_video_duration( $video, $post->ID );

			$needs_refresh = isset( $video['last_fetched'] ) ? ( $video['last_fetched'] < ( time() - $thirty_days ) ) : true;
			if ( $video['type'] === 'youtube' && $needs_refresh ) {
				$video_details = new WPSEO_Video_Details_Youtube( $video );
				$video         = $video_details->get_details();
				WPSEO_Meta::set_value( 'video_meta', $video, $post->ID );
			}
		}

		return $video;
	}

	/**
	 * Retrieves the video meta data for a term page.
	 *
	 * @param WP_Term $term The term for which to retrieve the video meta data.
	 *
	 * @return array|false The video metadata, or `false` if not meta data could be retrieved.
	 */
	public static function get_video_for_term( $term ) {
		$video              = false;
		$sitemap_taxonomies = WPSEO_Options::get( 'videositemap_taxonomies', [] );
		if ( is_array( $sitemap_taxonomies ) && in_array( $term->taxonomy, $sitemap_taxonomies, true ) ) {
			$video = [];

			$tax_meta = get_option( 'wpseo_taxonomy_meta' );
			if ( isset( $tax_meta[ $term->taxonomy ]['_video'][ $term->term_id ] ) ) {
				$video = $tax_meta[ $term->taxonomy ]['_video'][ $term->term_id ];
			}

			// For Youtube, refresh the video data every 30 days.
			$thirty_days   = ( DAY_IN_SECONDS * 30 );
			$needs_refresh = isset( $video['last_fetched'] ) ? ( $video['last_fetched'] < ( time() - $thirty_days ) ) : true;
			if ( $video['type'] === 'youtube' && $needs_refresh ) {
				$video_details = new WPSEO_Video_Details_Youtube( $video );
				$video         = $video_details->get_details();
				$tax_meta[ $term->taxonomy ]['_video'][ $term->term_id ] = $video;
				update_option( 'wpseo_taxonomy_meta', $tax_meta );
			}

			$video['duration'] = self::get_video_duration( $video );
		}

		return $video;
	}

	/**
	 * Check to see if the video thumbnail was manually set, if so, update the $video array.
	 *
	 * @since 11.1
	 *
	 * @param int   $post_id The post to check for.
	 * @param array $video   The video array.
	 *
	 * @return array
	 */
	public static function get_video_image( $post_id, $video ) {
		// Allow for the video's thumbnail to be overridden by the meta box input.
		$videoimg = WPSEO_Meta::get_value( 'videositemap-thumbnail', $post_id );
		if ( ( $video !== 'none' ) && ( $videoimg !== '' ) ) {
			$video['thumbnail_loc'] = $videoimg;
		}

		return $video;
	}

	/**
	 * Retrieve the duration of a video.
	 *
	 * Use a user provided duration if available, fall back to the available video data
	 * as previously retrieved through an API call.
	 *
	 * @since 11.1
	 *
	 * @param array    $video   Data about the video being evaluated.
	 * @param int|null $post_id Optional. Post ID.
	 *
	 * @return int Duration in seconds or 0 if no duration could be determined.
	 */
	public static function get_video_duration( $video, $post_id = null ) {
		$video_duration = 0;

		if ( isset( $post_id ) ) {
			$video_duration = (int) WPSEO_Meta::get_value( 'videositemap-duration', $post_id );
		}

		if ( $video_duration === 0 && isset( $video['duration'] ) ) {
			$video_duration = (int) $video['duration'];
		}

		return $video_duration;
	}

	/**
	 * Converts the duration in seconds to an ISO 8601 compatible output. Assumes the length is not over 24 hours.
	 *
	 * @link https://en.wikipedia.org/wiki/ISO_8601
	 *
	 * @param int $duration The duration in seconds.
	 *
	 * @return string ISO 8601 compatible output.
	 */
	public static function iso_8601_duration( $duration ) {
		if ( $duration <= 0 ) {
			return '';
		}

		$out = 'PT';
		if ( $duration > HOUR_IN_SECONDS ) {
			$hours    = floor( $duration / HOUR_IN_SECONDS );
			$out     .= $hours . 'H';
			$duration = ( $duration - ( $hours * HOUR_IN_SECONDS ) );
		}
		if ( $duration > MINUTE_IN_SECONDS ) {
			$minutes  = floor( $duration / MINUTE_IN_SECONDS );
			$out     .= $minutes . 'M';
			$duration = ( $duration - ( $minutes * MINUTE_IN_SECONDS ) );
		}
		if ( $duration > 0 ) {
			$out .= $duration . 'S';
		}

		return $out;
	}

	/**
	 * Determine whether a video is family friendly or not.
	 *
	 * @since 11.1
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return bool True if family friendly, false if not.
	 */
	public static function is_video_family_friendly( $post_id ) {
		$family_friendly = true;

		// We store this inverted which is incredibly annoying.
		$not_family_friendly = WPSEO_Meta::get_value( 'videositemap-not-family-friendly', $post_id );
		if ( is_string( $not_family_friendly ) && $not_family_friendly === 'on' ) {
			$family_friendly = false;
		}

		/**
		 * Filter: 'wpseo_video_family_friendly' - Allow changing the family friendly setting for a video.
		 *
		 * @param bool $family_friendly Set to `false` to mark a video as _not_ family friendly.
		 * @param int  $post_id         Post ID.
		 */
		$filter_return = apply_filters( 'wpseo_video_family_friendly', $family_friendly, $post_id );

		// For legacy reasons, this filter used to be quite ugly.
		if ( is_string( $filter_return ) ) {
			if ( $filter_return === 'on' ) {
				return true;
			}
			return false;
		}

		if ( is_bool( $filter_return ) ) {
			return $filter_return;
		}

		return $family_friendly;
	}

	/**
	 * Return the plugin file
	 *
	 * @since 11.1
	 *
	 * @return string
	 */
	public static function get_plugin_file() {
		return WPSEO_VIDEO_FILE;
	}

	/**
	 * Load translations
	 *
	 * @since 11.1
	 *
	 * @return void
	 */
	public static function load_textdomain() {
		load_plugin_textdomain( 'yoast-video-seo', false, dirname( plugin_basename( WPSEO_VIDEO_FILE ) ) . '/languages/' );
	}
}
