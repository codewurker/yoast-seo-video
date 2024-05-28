[![Coverage Status](https://coveralls.io/repos/github/Yoast/wpseo-video/badge.svg?branch=trunk&t=Vi74c9)](https://coveralls.io/github/Yoast/wpseo-video?branch=trunk)

Video SEO
=========
Requires at least: 6.4
Tested up to: 6.5
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

## 14.9

Release date: 2024-05-28

#### Enhancements

* Enhances the `uploadDate` property of a `VideoObject` schema object, by turning it into a DateTime format instead of just Date, satisfying the newest recommendations for rich results.
* Introduces a new way of retrieving translations for Yoast Video SEO, by utilizing the TranslationPress service. Instead of having to ship all translations with every release, we can now load the translations on a per-install basis, tailored to the user's setup. This means smaller plugin releases and less bloat on the user's server.

#### Other

* Fixes support for embedded TED videos.
* Improves discoverability of the security policy.
* Makes required PHP extensions explicit.
* Renames all Twitter references to X.
* Sets the minimum required Yoast SEO version to 22.8.
* Sets the minimum supported WordPress version to 6.4.
* Sets the WordPress tested up to version to 6.5.
* Users requiring this package via [WP]Packagist can now use the `composer/installers` v2.

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

### Earlier versions
For the changelog of earlier versions, please refer to [the changelog on yoast.com](https://yoa.st/video-seo-changelog).
