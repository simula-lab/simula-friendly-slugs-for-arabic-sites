=== Simula Friendly Slugs for Arabic Sites ===
Contributors: simulalab 
Tags: arabic, slug
Requires at least: 4.6
Tested up to: 6.8
Requires PHP: 7.0
Stable tag: 1.0.0
License: GPLv2
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
- Translation (Requires a Google Translate API key)

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
