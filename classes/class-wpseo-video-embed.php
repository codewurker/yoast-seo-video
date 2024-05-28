<?php
/**
 * All functionality for speeding up YouTube embeds by not loading them immediately.
 *
 * @package    WordPress SEO
 * @subpackage WordPress SEO Video
 */

/**
 * WPSEO_Video_Embed class.
 *
 * @package WordPress SEO Video
 */
class WPSEO_Video_Embed {

	/**
	 * Constructor for the WPSEO_Video_Embed class.
	 */
	public function __construct() {
		add_filter( 'render_block', [ $this, 'replace_youtube_block_html' ], 10, 2 );
		add_action( 'wp_head', [ $this, 'set_high_res_default_image' ] );
	}

	/**
	 * Replaces the YouTube embed HTML with our custom HTML.
	 *
	 * @return void
	 */
	public function set_high_res_default_image() {
		/*
		 * Light YouTube Embeds by @labnol.
		 * Web: https://www.labnol.org/internet/light-youtube-embeds/27941/
		 * Adapted by Yoast to use the max res default image when available,
		 * load the play image locally, and make it keyboard-accessible.
		 */
		echo '<script>
			document.addEventListener( "DOMContentLoaded", function() {
				var div, i,
					youtubePlayers = document.getElementsByClassName( "video-seo-youtube-player" );
				for ( i = 0; i < youtubePlayers.length; i++ ) {
					div = document.createElement( "div" );
					div.className = "video-seo-youtube-embed-loader";
					div.setAttribute( "data-id", youtubePlayers[ i ].dataset.id );
					div.setAttribute( "tabindex", "0" );
					div.setAttribute( "role", "button" );
					div.setAttribute(
						"aria-label", "'
						/* translators: Hidden accessibility text. */
						. esc_attr__( 'Load YouTube video', 'yoast-video-seo' )
						. '"
					);
					div.innerHTML = videoSEOGenerateYouTubeThumbnail( youtubePlayers[ i ].dataset.id );
					div.addEventListener( "click", videoSEOGenerateYouTubeIframe );
					div.addEventListener( "keydown", videoSEOYouTubeThumbnailHandleKeydown );
					div.addEventListener( "keyup", videoSEOYouTubeThumbnailHandleKeyup );
					youtubePlayers[ i ].appendChild( div );
				}
			} );

			function videoSEOGenerateYouTubeThumbnail( id ) {
				var thumbnail = \'<picture class="video-seo-youtube-picture">\n\' +
					\'<source class="video-seo-source-to-maybe-replace" media="(min-width: 801px)" srcset="https://i.ytimg.com/vi/\' + id + \'/maxresdefault.jpg" >\n\' +
					\'<source class="video-seo-source-hq" media="(max-width: 800px)" srcset="https://i.ytimg.com/vi/\' + id + \'/hqdefault.jpg">\n\' +
					\'<img onload="videoSEOMaybeReplaceMaxResSourceWithHqSource( event );" src="https://i.ytimg.com/vi/\' + id + \'/hqdefault.jpg" width="480" height="360" loading="eager" alt="">\n\' +
					\'</picture>\n\',
					play = \'<div class="video-seo-youtube-player-play"></div>\';
				return thumbnail.replace( "ID", id ) + play;
			}

			function videoSEOMaybeReplaceMaxResSourceWithHqSource( event ) {
				var sourceMaxRes,
					sourceHighQuality,
					loadedThumbnail = event.target,
					parent = loadedThumbnail.parentNode;

				if ( loadedThumbnail.naturalWidth < 150 ) {
					sourceMaxRes = parent.querySelector(".video-seo-source-to-maybe-replace");
					sourceHighQuality = parent.querySelector(".video-seo-source-hq");
					sourceMaxRes.srcset = sourceHighQuality.srcset;
					parent.className = "video-seo-youtube-picture video-seo-youtube-picture-replaced-srcset";
				}
			}

			function videoSEOYouTubeThumbnailHandleKeydown( event ) {
				if ( event.keyCode !== 13 && event.keyCode !== 32 ) {
					return;
				}

				if ( event.keyCode === 13 ) {
					videoSEOGenerateYouTubeIframe( event );
				}

				if ( event.keyCode === 32 ) {
					event.preventDefault();
				}
			}

			function videoSEOYouTubeThumbnailHandleKeyup( event ) {
				if ( event.keyCode !== 32 ) {
					return;
				}

				videoSEOGenerateYouTubeIframe( event );
			}

			function videoSEOGenerateYouTubeIframe( event ) {
				var el = ( event.type === "click" ) ? this : event.target,
					iframe = document.createElement( "iframe" );

				iframe.setAttribute( "src", "https://www.youtube.com/embed/" + el.dataset.id + "?autoplay=1&enablejsapi=1&origin=' . rawurlencode( home_url() ) . '" );
				iframe.setAttribute( "frameborder", "0" );
				iframe.setAttribute( "allowfullscreen", "1" );
				iframe.setAttribute( "allow", "accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" );
				el.parentNode.replaceChild( iframe, el );
			}
		</script>';
	}

	/**
	 * Replaces the YouTube block HTML with our custom HTML.
	 *
	 * @param string $block_content The block content.
	 * @param array  $block         The full block, including name and attributes.
	 *
	 * @return string The filtered block content.
	 */
	public function replace_youtube_block_html( $block_content, $block ) {
		/*
		 * In WordPress 5.6 the YouTube block name changed from `core-embed/youtube`
		 * to `core/embed` as the embed block was refactored to use only one block
		 * type with variations. The old name `core-embed/youtube` is used here
		 * for backwards compatibility.
		 */
		if (
			( $block['blockName'] === 'core/embed' || $block['blockName'] === 'core-embed/youtube' )
			&& $block['attrs']['providerNameSlug'] === 'youtube'
		) {
			wp_enqueue_style(
				'videoseo-youtube-embed-css',
				plugins_url( 'css/dist/videoseo-youtube-embed.css', WPSEO_VIDEO_FILE ),
				[],
				WPSEO_VIDEO_VERSION
			);

			if ( strpos( $block['attrs']['url'], 'youtu.be' ) ) {
				$data_id = str_replace( 'https://youtu.be/', '', $block['attrs']['url'] );
			}
			else {
				$data_id = str_replace( 'https://www.youtube.com/watch?v=', '', $block['attrs']['url'] );
			}

			$class = '';
			if ( isset( $block['attrs']['align'] ) ) {
				$class = 'align' . $block['attrs']['align'] . ' ';
			}

			if ( isset( $block['attrs']['className'] ) ) {
				$class .= $block['attrs']['className'] . ' ';
			}

			$block_content  = '<figure class="wp-block-embed ' . $class . ' is-type-video is-provider-youtube wp-block-embed-youtube">';
			$block_content .= '<div class="wp-block-embed__wrapper video-seo-youtube-embed-wrapper">';
			$block_content .= '<div class="video-seo-youtube-player" data-id="' . $data_id . '"></div>';
			$block_content .= '</div></figure>';

			return $block_content;
		}

		return $block_content;
	}
}
