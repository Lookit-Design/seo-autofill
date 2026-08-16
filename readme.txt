=== Lookit SEO Autofill ===
Contributors: lookitdesign
Tags: seo, yoast, meta description, keyphrase, autofill
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.2.7
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically fills your SEO plugin's keyphrase, meta description, and related keyphrases on publish. Works with Yoast SEO.

== Description ==

Lookit SEO Autofill automatically fills your SEO plugin's fields on publish using per-post-type settings, so you don't have to set the focus keyphrase and meta description by hand for every post. It is designed to work with Yoast SEO.

The focus keyphrase, meta description, and related keyphrases are generated from the post's own content (title, slug, and body) plus the free Datamuse API. Related keyphrases can optionally be generated with an AI model via OpenRouter if you add an API key.

== External Services ==

This plugin connects to two external services:

* Datamuse (https://www.datamuse.com/api/) — used to find related words/phrases for keyphrase generation. The plugin sends individual words or short phrases derived from your post content. No API key or personal data is sent. Terms: https://www.datamuse.com/api/ — Privacy: https://www.datamuse.com/api/

* OpenRouter (https://openrouter.ai) — optional; used only if you configure an API key and enable AI related keyphrases. When triggered on publish, the plugin sends the post title and a content excerpt plus your prompt to OpenRouter, which routes it to the AI model you select. Terms: https://openrouter.ai/terms — Privacy: https://openrouter.ai/privacy

== Changelog ==

= 1.2.7 =
* Reordered the API-key sanitization so the input is sanitized before trimming, clearing the last Plugin Check warning.

= 1.2.6 =
* Renamed the plugin to "Lookit SEO Autofill" (the previous name used a restricted trademarked term and could not be published).
* Plugin Check compliance pass: unified the text domain to the plugin slug, added wp_unslash()/sanitization to all form and AJAX input, escaped admin output, converted the OpenRouter prompt heredoc to a standard string, added a readme Stable tag and an External Services disclosure, and set Tested up to 7.0.
* No option keys, post meta keys, class names, constants, or AJAX action names changed, so existing installs keep their settings.

= 1.1.1 =
* Fixed: Related keyphrases now use the correct Yoast meta key _yoast_wpseo_focuskeywords.
* Fixed: Related keyphrases are stored as a JSON-encoded array of objects with keyword + score fields, matching Yoast Premium's exact format.

= 1.1.0 =
* New: OpenRouter integration — generates related keyphrases from post content on publish.
* New: OpenRouter settings section — API key, model selector (free models only), keyphrase count.
* New: "AI related keyphrases" toggle per post type in the settings table.
* New: Uses openrouter/free as default model (auto-selects best available free model).
* New: API key saved via separate AJAX call to avoid exposure in request logs.
* Changed: Added "AI Keyphrases" column to the post types table.

= 1.0.3 =
* Fixed: Replaced transition_post_status with wp_after_insert_post (priority 999).
* Fixed: Added Elementor after_save hook.
* Added: Debug logging when WP_DEBUG_LOG is enabled.

= 1.0.2 =
* Fixed: Author corrected to Lookit Design.

= 1.0.1 =
* Fixed: Gutenberg / REST API hook timing.
* Changed: Always overwrite existing Yoast values.

= 1.0.0 =
* Initial release.
