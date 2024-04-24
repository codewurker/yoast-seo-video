<?php
/**
 * Yoast SEO Video Plugin.
 *
 * @package   Yoast\VideoSEO
 * @copyright Copyright (C) 2012-2022 Yoast BV - support@yoast.com
 * @license   http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 or higher
 *
 * @wordpress-plugin
 * Plugin Name: Yoast SEO: Video
 * Version:     14.8
 * Plugin URI:  https://yoa.st/4fh
 * Description: The Yoast Video SEO plugin makes sure your videos are recognized by search engines and social platforms, so they look good when found on these social platforms and in the search results.
 * Author:      Team Yoast
 * Author URI:  https://yoa.st/team-yoast-video
 * Text Domain: yoast-video-seo
 * Domain Path: /languages/
 * Requires at least: 6.1
 * Requires PHP: 7.2.5
 * Depends:     Yoast SEO
 * License:     GPL v2
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

if ( ! function_exists( 'add_filter' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require __DIR__ . '/vendor/autoload.php';
}

define( 'WPSEO_VIDEO_VERSION', '14.8' );
define( 'WPSEO_VIDEO_FILE', __FILE__ );

require_once __DIR__ . '/video-seo-api.php';

if ( ! wp_installing() ) {
	add_action( 'plugins_loaded', 'yoast_wpseo_video_seo_init', 5 );
}

register_activation_hook( __FILE__, 'yoast_wpseo_video_activate' );

register_deactivation_hook( __FILE__, 'yoast_wpseo_video_deactivate' );
