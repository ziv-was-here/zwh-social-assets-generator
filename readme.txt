=== Social Assets Generator ===
Contributors: ziv
Tags: social media, ai, content, marketing, image generation
Requires at least: 6.0
Tested up to: 7.0
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate social media captions, titles, email subject lines, hashtags, and AI images from any post using multiple AI providers.

== Description ==

Social Assets Generator adds a meta box to every post and page editor. With one click it calls your chosen AI provider (Claude, OpenAI, Gemini, Groq, Mistral, or Ollama Cloud) and returns a complete social asset kit:

* 5 title options
* 5 email subject lines with preview text
* LinkedIn post (150–300 words)
* Twitter/X thread (5 tweets)
* Instagram caption with hashtags
* Facebook post
* Hashtag sets for all platforms

It also generates social share images via OpenAI gpt-image-2, Google Imagen 3, Stability AI, or Flux (fal.ai) in four formats: banner (1200×630), square feed (1080×1080), portrait feed (1080×1350), and Stories/Reels/TikTok (1080×1920).

Generated images are saved to the WordPress media library. All text assets are saved to post meta and reload automatically the next time you open the editor.

== Installation ==

1. Upload the `social-assets-generator` folder to `/wp-content/plugins/`.
2. Activate the plugin in **Plugins → Installed Plugins**.
3. Go to **Settings → Social Assets** and enter at least one AI provider API key.
4. Open any post or page — the **Social Assets Generator** meta box appears below the editor.

== Frequently Asked Questions ==

= Which AI providers are supported for text? =

Claude (Anthropic), OpenAI (GPT-4o), Google Gemini, Groq, Mistral, and Ollama Cloud.

= Which providers are supported for image generation? =

OpenAI gpt-image-2, Google Imagen 3, Stability AI Stable Image Core, and Flux via fal.ai.

= Do I need all API keys? =

No — one key is enough to get started. Configure whichever provider you have access to.

= Is any content sent to third parties? =

Yes — post title and content are sent to the AI provider you select. Review each provider's privacy policy before use.

== Screenshots ==

1. The Social Assets Generator meta box in the post editor.
2. The Settings page showing provider selection and image generation options.

== Changelog ==

= 1.0.0 =
* Initial release.
* Support for 6 text AI providers and 4 image providers.
* 4 image format presets: banner, square, portrait feed, stories.
* Save-to-post-meta with title/subject selection.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
