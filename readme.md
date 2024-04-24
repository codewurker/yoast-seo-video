[![Coverage Status](https://coveralls.io/repos/github/Yoast/wpseo-video/badge.svg?branch=trunk&t=Vi74c9)](https://coveralls.io/github/Yoast/wpseo-video?branch=trunk)

Video SEO
=========
Requires at least: 6.1
Tested up to: 6.3
Stable tag: 13.9
Requires PHP: 7.2.5
Depends: Yoast SEO

Video SEO adds Video SEO capabilities to WordPress SEO.

Description
------------

This plugin adds Video XML Sitemaps as well as the necessary OpenGraph markup, Schema.org videoObject markup and mediaRSS for your videos.

This repository uses [the Yoast grunt tasks plugin](https://github.com/Yoast/plugin-grunt-tasks).

Installation
------------

1. Go to Plugins -> Add New.
2. Click "Upload" right underneath "Install Plugins".
3. Upload the zip file that this readme was contained in.
4. Activate the plugin.
5. Go to SEO -> Extensions and enter your license key.
6. Save settings, your license key will be validated. If all is well, you should now see the XML Video Sitemap settings.
7. Make sure to hit the "Re-index videos" button if you have videos in old posts.

Frequently Asked Questions
--------------------------

You can find the [Video SEO FAQ](https://yoast.com/help/video-seo-faq/) in our help center.

Changelog
=========

## 14.8

Release date: 2023-08-08

#### Enhancements

* Adds CLI command to index videos: `wp yoast video index`.

#### Bugfixes

* Fixes a bug where a warning would be thrown on pages embedding a video which had been visited more than 30 days ago.
* Fixes a bug where Wistia videos would not be displayed correctly in a responsive way via the FitVids setting and a console error about jQuery would be thrown.

#### Other

* Drops compatibility with PHP 5.6, 7.0 and 7.1.
* Sets the minimum required Yoast SEO version to 20.13.
* Sets the minimum supported WordPress version to 6.1.
* Sets the tested up to WordPress version to 6.2.

## 14.7

Release date: 2023-03-14

#### Enhancements

* Improves the inline documentation for various filters.
* Improves the usability of the `wpseo_video_{$type}_details` filter by adding a `$post_id` parameter.
* Improves the usability of the `wpseo_video_family_friendly` filter by allowing to return `false` if the video is not family friendly.

#### Bugfixes

* Fixes a bug where a PHP8 deprecation notice would be thrown for `FILTER_SANITIZE_STRING`.
* Fixes a bug where a Schema validation warning would be thrown about the `isFamilyFriendly` property in the `VideoObject` piece being set to a string instead of a boolean value.
* Fixes a bug where the link to the _XML sitemaps_ settings would be incorrect when _XML sitemaps_ were disabled.
* Fixes a bug where the _video title_ assessment and the _video body_ assessment would also appear under the Readability analysis tab when the Cornerstone content toggle would be switched on.

#### Other

* Improves the compatibility with PHP 8.1.
* Reduces noise from PHP 8.1 deprecations.
* Sets the minimum required Yoast SEO version to 20.3.
* Sets the minimum supported WordPress version to 6.0.
* Support for the [Flowplayer5](https://wordpress.org/plugins/flowplayer5/) plugin has been dropped as the plugin is no longer supported by the author.
* Support for the [JW Player for WordPress](https://wordpress.org/plugins/jw-player-plugin-for-wordpress/) plugin has been dropped as the plugin is no longer supported by the author.
* Support for the [Smart YouTube](https://wordpress.org/plugins/smart-youtube/) plugin has been dropped as the plugin is no longer supported by the author.
* Support for the [TubePress](https://wordpress.org/plugins/tubepress/) plugin has been dropped as the plugin is no longer supported by the author.

### Earlier versions
For the changelog of earlier versions, please refer to [the changelog on yoast.com](https://yoa.st/video-seo-changelog).
