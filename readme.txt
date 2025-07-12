=== Simula Friendly Slugs for Arabic Sites ===
Contributors: simulalab 
Tags: arabic, slug
Requires at least: 4.6
Tested up to: 6.8
Requires PHP: 7.0
Stable tag: 1.0.0
License: GPL v2
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Donate link: https://simulalab.org

Automatically generate friendly slugs for posts/pages with Arabic titles via transliteration, 3arabizi or translation.

== Description ==

A WordPress plugin developed by [Simula Lab Ltd](https://simulalab.org) that helps users to automatically generate URL-friendly slugs for Arabic content by transliteration, “3arabizi,” machine translation, or leave unchanged—perfect for social media sharing and SEO.
The plugin is also available to download from the official wordpress plugins directory here: https://wordpress.org/plugins/simula-friendly-slugs-for-arabic-sites

It supports generating slugs:

- Transliteration (using PHP/ICU)
- 3arabizi
- Hash (from the Arabic title)
- Translation (using an external translation service provider such as Google Translate API)

After installing and activating the plugin, navigate to the "Settings" menu in the Admin Dashboard and select "Friendly slugs".

The plugin has no effect on non-Arabic post titles.

== Screenshots ==

1. screenshot-1.png – The settings user-interface
2. screenshot-2.png - The slug of a post with an Arabic title with the transliteration option selected
3. screenshot-3.png - The slug of a post with an Arabic title with the translation option selected

== Changelog ==

= 1.0.0 =
* This is the first version

== Upgrade Notice ==

= 1.0.0 =

== Frequently Asked Questions ==

= Does the plugin have an effect on the public facing side of non-Arabic sites? =

Only if the method was set to anything other than "No Change" and the post title was in Arabic.

= Does it work with custom post types? = 

Yes

== External services ==

This plugin can optionally call an external translation service API when “Translation” mode is selected.

When “Translation” mode is selected, the plugin will send your post’s title text to a configured external translation API endpoint (for example, Google Cloud Translation API v2 at https://translation.googleapis.com/language/translate/v2). No other data is transmitted. 

If you have not provided valid credentials (for example API Key) for the service provider under Settings -> Friendly Slugs, the plugin falls back to a server-side hash of the original title and makes no external calls.

You are responsible for obtaining and configuring an API key or other credentials for the selected service provider, and for complying with that provider’s terms of service and privacy policy (for Google Cloud Translation, see https://cloud.google.com/translate/terms and https://policies.google.com/privacy). 

All other slug-generation methods (transliteration, 3arabizi, hash) run entirely on the host without contacting any third-party service.
