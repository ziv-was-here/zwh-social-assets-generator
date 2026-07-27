=== Social Assets Generator ===
Contributors: ziv
Tags: social media, ai, content, marketing, image generation
Requires at least: 6.0
Tested up to: 7.0
Stable tag: 1.3.2
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

It also generates social share images via OpenAI gpt-image-2, Google Imagen 4, Stability AI, or Flux (fal.ai) in four formats: banner (1200×630), square feed (1080×1080), portrait feed (1080×1350), and Stories/Reels/TikTok (1080×1920).

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

OpenAI gpt-image-2, Google Imagen 4, Stability AI Stable Image Core, and Flux via fal.ai.

= Do I need all API keys? =

No — one key is enough to get started. Configure whichever provider you have access to.

= Is any content sent to third parties? =

Yes — post title and content are sent to the AI provider you select. Review each provider's privacy policy before use.

== Screenshots ==

1. The Social Assets Generator meta box in the post editor.
2. The Settings page showing provider selection and image generation options.

== Changelog ==

= 1.3.2 =
* Fix: if your Image generation provider was OpenAI or Gemini but your Active AI provider (text) was something else, there was no visible way to set that key — it lived in a text-provider section hidden behind an unrelated dropdown. Both image sections now have their own editable API key field, live-synced with the text-provider field so there's still only one value saved, not two conflicting ones.

= 1.3.1 =
* Fix: imagen-4.0-generate-001 started returning "This model is no longer available to new users" — but Google's ListModels API still lists it as predict-capable, so our v1.2.0 auto-detection kept re-selecting the same broken model. Added a persistent 30-day exclusion list: once a model fails with a "not found" / "no longer available" / "deprecated" style error, it's excluded from auto-selection going forward and the next-best available Imagen model (fast/ultra variant, or whatever Google adds next) is used automatically instead.

= 1.3.0 =
* Changed: the meta box now separates text and image generation into two clearly labeled sections, stacked vertically, each with a one-line explanation of what it generates — instead of two buttons sitting side by side with no context.

= 1.2.0 =
* New: Google Imagen model is now auto-detected instead of hardcoded. We've hit Google retiring the Imagen model ID twice in one week (imagen-3.0-generate-001, then -002) — the plugin now calls ListModels itself, picks the newest model that supports "predict", and caches it for 24 hours. If a cached model gets retired mid-day, the very next request detects the 404/"not found" response, clears the cache, re-detects, and retries automatically — no more manual hotfixes when Google renames a model.
* Settings → Social Assets now shows the currently auto-detected Imagen model instead of a fixed name.

= 1.1.2 =
* Fix: imagen-3.0-generate-002 also 404'd — confirmed via Google's ListModels API that no imagen-3.0-* model is available anymore. Switched to imagen-4.0-generate-001 (currently the only Imagen model exposing the predict method on the Gemini API). Relabeled "Imagen 3" to "Imagen 4" throughout settings and meta box.

= 1.1.1 =
* Fix: Google Imagen calls failed with "models/imagen-3.0-generate-001 is not found for API version v1beta" — Google retired that model ID. Updated to imagen-3.0-generate-002.

= 1.1.0 =
* Fix: OpenAI and Gemini API keys entered in Settings → Social Assets were silently discarded on save. The Image Generation section rendered a second, always-blank input with the same field name as the main provider key, and the blank one was overwriting the saved value. Removed the duplicate field.
* New: GitHub-based update system (updates served from GitHub releases, with update badge and manual "Check for updates" support)

= 1.0.0 =
* Initial release.
* Support for 6 text AI providers and 4 image providers.
* 4 image format presets: banner, square, portrait feed, stories.
* Save-to-post-meta with title/subject selection.

== Upgrade Notice ==

= 1.3.2 =
Fixes no way to set an OpenAI/Gemini key for image generation when a different provider is used for text.

= 1.3.1 =
Fixes Imagen auto-selection re-picking a model Google has blocked for new users — adds a persistent exclusion list so it falls through to the next available model automatically.

= 1.3.0 =
Meta box UX update — text and image generation are now separate labeled sections.

= 1.2.0 =
Google Imagen model is now auto-detected and self-healing — no more breakage when Google retires a model ID.

= 1.1.2 =
Fixes Google image generation for real this time — switches to Imagen 4, the only model your API key actually has predict access to.

= 1.1.1 =
Fixes Google Imagen image generation (retired model ID).

= 1.1.0 =
Fixes API keys not saving in Settings → Social Assets, and adds self-updating from GitHub.

= 1.0.0 =
Initial release.
