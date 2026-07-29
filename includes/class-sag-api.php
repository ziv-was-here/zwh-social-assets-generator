<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SAG_API {

	/**
	 * Generate social assets from post content.
	 */
	public static function generate( $title, $content, $tone = '' ) {
		$provider = SAG_Settings::get( 'provider', 'claude' );
		$system   = self::build_system_prompt();
		$user     = self::build_user_prompt( $title, $content, $tone );

		switch ( $provider ) {
			case 'openai':
				return self::call_openai_compatible(
					'https://api.openai.com/v1/chat/completions',
					SAG_Settings::get( 'openai_key' ),
					SAG_Settings::get( 'openai_model', 'gpt-4o' ),
					$system, $user, 'openai', true
				);
			case 'gemini':
				return self::call_gemini( $system, $user );
			case 'groq':
				return self::call_openai_compatible(
					'https://api.groq.com/openai/v1/chat/completions',
					SAG_Settings::get( 'groq_key' ),
					SAG_Settings::get( 'groq_model', 'llama-3.3-70b-versatile' ),
					$system, $user, 'groq', false
				);
			case 'mistral':
				return self::call_openai_compatible(
					'https://api.mistral.ai/v1/chat/completions',
					SAG_Settings::get( 'mistral_key' ),
					SAG_Settings::get( 'mistral_model', 'mistral-small-latest' ),
					$system, $user, 'mistral', false
				);
			case 'ollama':
				return self::call_ollama_cloud( $system, $user );
			default:
				return self::call_claude( $system, $user );
		}
	}

	/**
	 * Generate a social share image via the configured image provider.
	 *
	 * @return array|WP_Error
	 *   type=image: { preview_url, transient_key, save_type, prompt } — base64 providers
	 *   type=image: { preview_url, save_url, save_type='url', prompt } — URL providers
	 *   type=prompt: { prompt } — no key configured
	 */
	/**
	 * Supported $format values: banner | feed_square | feed_portrait | stories
	 */
	public static function generate_image( $title, $content, $format = 'banner' ) {
		$prompt         = self::build_image_prompt( $title, $content, $format );
		$image_provider = SAG_Settings::get( 'image_provider', 'openai' );

		switch ( $image_provider ) {
			case 'gemini':    return self::generate_image_gemini( $prompt, $format );
			case 'stability': return self::generate_image_stability( $prompt, $format );
			case 'flux':      return self::generate_image_flux( $prompt, $format );
			default:          return self::generate_image_openai( $prompt, $format );
		}
	}

	// -------------------------------------------------------------------------
	// Image provider methods
	// -------------------------------------------------------------------------

	/** OpenAI gpt-image-2 — returns b64_json */
	private static function generate_image_openai( $prompt, $format = 'banner' ) {
		$api_key = SAG_Settings::get( 'openai_key' );
		if ( empty( $api_key ) ) {
			return array( 'type' => 'prompt', 'prompt' => $prompt );
		}

		// gpt-image-2 supports: 1024x1024, 1024x1536 (portrait), 1536x1024 (landscape)
		$size_map = array(
			'banner'        => '1536x1024',
			'feed_square'   => '1024x1024',
			'feed_portrait' => '1024x1536',
			'stories'       => '1024x1536',
		);
		$size = $size_map[ $format ] ?? '1536x1024';

		$response = wp_remote_post(
			'https://api.openai.com/v1/images/generations',
			array(
				'timeout' => 120,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body' => wp_json_encode( array(
					'model'   => 'gpt-image-2',
					'prompt'  => $prompt,
					'n'       => 1,
					'size'    => $size,
					// 'low' generates noticeably faster than 'medium' — trades a bit of image
					// fidelity for a much lower chance of tripping a hosting/proxy timeout
					// (e.g. Cloudflare's 100s edge cap) on slower-responding requests.
					'quality' => 'low',
				) ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 ) {
			return new WP_Error( 'sag_image_error', $data['error']['message'] ?? "OpenAI image API error (HTTP {$code})" );
		}

		// gpt-image-2 returns b64_json
		$b64 = $data['data'][0]['b64_json'] ?? '';
		// Fallback: some configs may return a URL
		if ( empty( $b64 ) ) {
			$url = $data['data'][0]['url'] ?? '';
			if ( ! empty( $url ) ) {
				return array( 'type' => 'image', 'preview_url' => $url, 'save_url' => $url, 'save_type' => 'url', 'prompt' => $prompt );
			}
			return new WP_Error( 'sag_image_error', 'No image data returned by OpenAI.' );
		}

		return self::make_base64_result( $b64, 'png', $prompt );
	}

	/**
	 * Google Gemini image generation — "Nano Banana" family via generateContent.
	 * Google is shutting down the old Imagen predict API (imagen-4.0-*) on 2026-08-17 for ALL
	 * accounts, including paid ones — "no longer available to new users" on a paid key is that
	 * shutdown, not a billing/access problem. gemini-3.1-flash-image and gemini-3-pro-image are
	 * the recommended replacements; user picks which one in Settings → Social Assets.
	 */
	private static function generate_image_gemini( $prompt, $format = 'banner' ) {
		$api_key = SAG_Settings::get( 'gemini_key' );
		if ( empty( $api_key ) ) {
			return array( 'type' => 'prompt', 'prompt' => $prompt );
		}

		$model = SAG_Settings::get( 'gemini_image_model', 'gemini-3.1-flash-image' );
		if ( ! in_array( $model, array( 'gemini-3.1-flash-image', 'gemini-3-pro-image' ), true ) ) {
			$model = 'gemini-3.1-flash-image';
		}

		$aspect_map = array(
			'banner'        => '16:9',
			'feed_square'   => '1:1',
			'feed_portrait' => '3:4',
			'stories'       => '9:16',
		);
		$aspect = $aspect_map[ $format ] ?? '16:9';

		// v1beta + generationConfig.imageConfig is the confirmed-working combo for the Nano Banana
		// family. v1 (non-beta) exposes a stricter "responseFormat.image" field backed by a proto
		// enum that rejects plain strings like "16:9"/"1K" — that's what threw the
		// "Invalid value ... ImageResponseFormat.AspectRatio" error.
		$url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

		$response = wp_remote_post( $url, array(
			'timeout' => 120,
			'headers' => array(
				'Content-Type'   => 'application/json',
				'x-goog-api-key' => $api_key,
			),
			'body'    => wp_json_encode( array(
				'contents'         => array(
					array( 'parts' => array( array( 'text' => $prompt ) ) ),
				),
				'generationConfig' => array(
					'responseModalities' => array( 'IMAGE' ),
					'imageConfig'        => array( 'aspectRatio' => $aspect, 'imageSize' => '1K' ),
				),
			) ),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 ) {
			$message = $data['error']['message'] ?? "Gemini image generation error (HTTP {$code})";
			return new WP_Error( 'sag_image_error', $message );
		}

		// Google's REST JSON mapping is normally camelCase (inlineData/mimeType), but check both
		// casings defensively since it's not guaranteed by the docs.
		$parts = $data['candidates'][0]['content']['parts'] ?? array();
		foreach ( $parts as $part ) {
			$inline = $part['inlineData'] ?? $part['inline_data'] ?? null;
			if ( $inline && ! empty( $inline['data'] ) ) {
				$mimetype = $inline['mimeType'] ?? $inline['mime_type'] ?? 'image/png';
				$ext      = ( strpos( $mimetype, 'jpeg' ) !== false ) ? 'jpg' : 'png';
				return self::make_base64_result( $inline['data'], $ext, $prompt, $mimetype );
			}
		}

		return new WP_Error( 'sag_image_error', 'Gemini returned no image data — the prompt may have been blocked by safety filters. Try rephrasing the post title/content.' );
	}

	/** Stability AI v2beta Stable Image Core — multipart/form-data → base64 JSON */
	private static function generate_image_stability( $prompt, $format = 'banner' ) {
		$api_key = SAG_Settings::get( 'stability_key' );
		if ( empty( $api_key ) ) {
			return array( 'type' => 'prompt', 'prompt' => $prompt );
		}

		// Stability AI supports: 1:1, 2:3, 3:2, 4:5, 5:4, 9:16, 16:9, 21:9, 9:21
		$aspect_map = array(
			'banner'        => '3:2',
			'feed_square'   => '1:1',
			'feed_portrait' => '4:5',
			'stories'       => '9:16',
		);
		$aspect = $aspect_map[ $format ] ?? '3:2';

		$boundary = '----SAGBoundary' . bin2hex( random_bytes( 8 ) );
		$body     = self::build_multipart( $boundary, array(
			'prompt'        => $prompt,
			'aspect_ratio'  => $aspect,
			'output_format' => 'png',
		) );

		$response = wp_remote_post(
			'https://api.stability.ai/v2beta/stable-image/generate/core',
			array(
				'timeout' => 120,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Accept'        => 'application/json',
					'Content-Type'  => "multipart/form-data; boundary={$boundary}",
				),
				'body' => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 ) {
			$msg = $data['errors'][0] ?? $data['message'] ?? "Stability AI error (HTTP {$code})";
			return new WP_Error( 'sag_image_error', $msg );
		}

		$b64 = $data['image'] ?? '';
		if ( empty( $b64 ) ) {
			return new WP_Error( 'sag_image_error', 'No image data returned by Stability AI.' );
		}

		return self::make_base64_result( $b64, 'png', $prompt );
	}

	/** fal.ai Flux — returns a URL */
	private static function generate_image_flux( $prompt, $format = 'banner' ) {
		$api_key = SAG_Settings::get( 'flux_key' );
		if ( empty( $api_key ) ) {
			return array( 'type' => 'prompt', 'prompt' => $prompt );
		}

		// Flux supports exact width/height (in multiples of 32, typically)
		$size_map = array(
			'banner'        => array( 'width' => 1216, 'height' => 640 ),  // ~1200×630 closest
			'feed_square'   => array( 'width' => 1024, 'height' => 1024 ),
			'feed_portrait' => array( 'width' => 864,  'height' => 1088 ), // ~4:5
			'stories'       => array( 'width' => 576,  'height' => 1024 ), // 9:16
		);
		$size = $size_map[ $format ] ?? $size_map['banner'];

		$response = wp_remote_post(
			'https://fal.run/fal-ai/flux-pro',
			array(
				'timeout' => 120,
				'headers' => array(
					'Authorization' => 'Key ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body' => wp_json_encode( array(
					'prompt'     => $prompt,
					'image_size' => $size,
					'num_images' => 1,
				) ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 ) {
			$msg = $data['detail'] ?? $data['message'] ?? "Flux error (HTTP {$code})";
			return new WP_Error( 'sag_image_error', $msg );
		}

		$url = $data['images'][0]['url'] ?? '';
		if ( empty( $url ) ) {
			return new WP_Error( 'sag_image_error', 'No image URL returned by Flux.' );
		}

		return array( 'type' => 'image', 'preview_url' => $url, 'save_url' => $url, 'save_type' => 'url', 'prompt' => $prompt );
	}

	// -------------------------------------------------------------------------
	// Image helpers
	// -------------------------------------------------------------------------

	/**
	 * Decode base64 image → save to uploads/sag-temp/ → return URL payload.
	 * Avoids storing large binary data in the WP database (transients hit max_allowed_packet).
	 */
	private static function make_base64_result( $b64, $ext = 'png', $prompt = '', $mime = '' ) {
		if ( empty( $mime ) ) {
			$mime = ( $ext === 'jpg' ) ? 'image/jpeg' : 'image/png';
		}

		$img_data = base64_decode( $b64 );
		$result   = self::save_temp_image( $img_data, $ext );

		if ( is_wp_error( $result ) ) {
			// Fallback: return a data URL so the user can at least preview the image
			return array(
				'type'        => 'image',
				'preview_url' => "data:{$mime};base64,{$b64}",
				'save_url'    => '',
				'save_type'   => 'nourl',
				'prompt'      => $prompt,
			);
		}

		return array(
			'type'        => 'image',
			'preview_url' => $result['url'],
			'save_url'    => $result['url'],
			'save_type'   => 'url',
			'prompt'      => $prompt,
		);
	}

	/**
	 * Write image binary to a temp subfolder of the WP uploads directory.
	 * Returns array( 'path' => ..., 'url' => ... ) or WP_Error.
	 */
	private static function save_temp_image( $img_data, $ext = 'png' ) {
		$upload_dir = wp_upload_dir();
		$temp_dir   = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'sag-temp';

		if ( ! file_exists( $temp_dir ) ) {
			wp_mkdir_p( $temp_dir );
			// Prevent directory listing
			@file_put_contents( $temp_dir . DIRECTORY_SEPARATOR . 'index.php', '<?php // Silence is golden.' );
		}

		// Clean up temp files older than 2 hours
		$pattern = $temp_dir . DIRECTORY_SEPARATOR . 'sag-*.{png,jpg,jpeg,webp}';
		foreach ( glob( $pattern, GLOB_BRACE ) ?: array() as $old_file ) {
			if ( @filemtime( $old_file ) < ( time() - 7200 ) ) {
				wp_delete_file( $old_file );
			}
		}

		$filename = 'sag-' . wp_generate_uuid4() . '.' . $ext;
		$filepath = $temp_dir . DIRECTORY_SEPARATOR . $filename;

		if ( file_put_contents( $filepath, $img_data ) === false ) {
			return new WP_Error( 'sag_write_error', 'Could not write temp image file. Check uploads folder permissions.' );
		}

		return array(
			'path' => $filepath,
			'url'  => $upload_dir['baseurl'] . '/sag-temp/' . $filename,
		);
	}

	/** Build a multipart/form-data body string. */
	private static function build_multipart( $boundary, $fields ) {
		$body = '';
		foreach ( $fields as $name => $value ) {
			$body .= "--{$boundary}\r\n";
			$body .= "Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n";
			$body .= $value . "\r\n";
		}
		$body .= "--{$boundary}--\r\n";
		return $body;
	}

	private static function build_image_prompt( $title, $content, $format = 'banner' ) {
		$format_desc = array(
			'banner'        => 'horizontal link preview/banner format (1200×630 px, 1.91:1 landscape)',
			'feed_square'   => 'square feed post format (1080×1080 px, 1:1)',
			'feed_portrait' => 'vertical feed post format (1080×1350 px, 4:5 portrait)',
			'stories'       => 'vertical full-screen Stories/Reels/TikTok format (1080×1920 px, 9:16 portrait)',
		);
		$desc    = $format_desc[ $format ] ?? $format_desc['banner'];
		$summary = wp_trim_words( $content, 60, '' );

		return "Create a bold, modern social media image in {$desc} for a blog post titled \"{$title}\". "
			. "Style: clean editorial design, strong typography feel, visually striking and professional. "
			. "Key theme: {$summary}. "
			. "No logos. No URLs. Minimal text — only a short headline if any. "
			. "High contrast, vibrant but professional color palette.";
	}

	// -------------------------------------------------------------------------
	// Text prompt builders
	// -------------------------------------------------------------------------

	private static function build_system_prompt() {
		return 'You are a social media content strategist. You produce complete, publish-ready social asset kits from blog posts. Always respond with valid JSON only — no markdown fences, no commentary outside the JSON object.';
	}

	private static function build_user_prompt( $title, $content, $tone ) {
		$tone_instruction = $tone
			? "Write in this tone: {$tone}."
			: 'Match the natural voice of the post — do not be overly formal or overly casual.';

		$content = wp_trim_words( $content, 1200, '' );

		return 'Generate a complete social assets kit for the blog post below. ' . $tone_instruction . "\n\n"
			. "Return a single JSON object with exactly these keys:\n\n"
			. "{\n"
			. '  "titles": [ { "title": "string", "note": "one line on when this works best" } ],' . "\n"
			. '  "subject_lines": [ { "subject": "string", "preview": "max 90 chars" } ],' . "\n"
			. '  "linkedin": "string — 150-300 words. Hook line, 3-5 short paragraphs, CTA, 5 hashtags at end",' . "\n"
			. '  "twitter_thread": ["1/ hook tweet (<=280 chars)","2/ key point (<=280 chars)","3/ key point (<=280 chars)","4/ key point (<=280 chars)","5/ CTA tweet (<=280 chars)"],' . "\n"
			. '  "instagram": "string — 100-150 words. Strong first line, line breaks, question CTA, 10 hashtags on separate line",' . "\n"
			. '  "facebook": "string — 80-120 words. Conversational, relatable opening, 3 hashtags max",' . "\n"
			. '  "hashtags": { "linkedin": ["tag1","tag2","tag3","tag4","tag5"], "twitter": ["tag1","tag2","tag3","tag4","tag5"], "instagram": ["tag1","tag2","tag3","tag4","tag5","tag6","tag7","tag8","tag9","tag10","tag11","tag12","tag13","tag14","tag15"] }' . "\n"
			. "}\n\n"
			. "titles: exactly 5 items — mix question, number, how-to, bold claim, provocative\n"
			. "subject_lines: exactly 5 items — mix curiosity, direct value, one provocative option\n\n"
			. "---\n"
			. 'POST TITLE: ' . $title . "\n\n"
			. "POST CONTENT:\n"
			. $content;
	}

	// -------------------------------------------------------------------------
	// Provider calls — text generation
	// -------------------------------------------------------------------------

	private static function call_claude( $system, $user ) {
		$api_key = SAG_Settings::get( 'claude_key' );
		$model   = SAG_Settings::get( 'claude_model', 'claude-sonnet-4-6' );

		if ( empty( $api_key ) ) {
			return new WP_Error( 'sag_no_key', 'Claude API key is not configured. Go to Settings → Social Assets.' );
		}

		$response = wp_remote_post(
			'https://api.anthropic.com/v1/messages',
			array(
				'timeout' => 90,
				'headers' => array(
					'x-api-key'         => $api_key,
					'anthropic-version' => '2023-06-01',
					'content-type'      => 'application/json',
				),
				'body' => wp_json_encode( array(
					'model'      => $model,
					'max_tokens' => 8000, // The full social kit (titles, subjects, LinkedIn, thread, IG, FB, hashtags) can run long — 4096 was truncating mid-JSON.
					'system'     => $system,
					'messages'   => array( array( 'role' => 'user', 'content' => $user ) ),
				) ),
			)
		);

		return self::parse_response( $response, 'claude' );
	}

	private static function call_openai_compatible( $endpoint, $api_key, $model, $system, $user, $provider_label, $json_mode ) {
		if ( empty( $api_key ) ) {
			return new WP_Error( 'sag_no_key', ucfirst( $provider_label ) . ' API key is not configured. Go to Settings → Social Assets.' );
		}

		$body = array(
			'model'      => $model,
			'max_tokens' => 8000, // Explicit — left unset this fell back to inconsistent per-provider defaults that could truncate the JSON mid-response.
			'messages'   => array(
				array( 'role' => 'system', 'content' => $system ),
				array( 'role' => 'user',   'content' => $user ),
			),
		);

		if ( $json_mode ) {
			$body['response_format'] = array( 'type' => 'json_object' );
		}

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 90,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body' => wp_json_encode( $body ),
			)
		);

		return self::parse_response( $response, 'openai_compat' );
	}

	private static function call_gemini( $system, $user ) {
		$api_key = SAG_Settings::get( 'gemini_key' );
		$model   = SAG_Settings::get( 'gemini_model', 'gemini-2.5-flash' );

		if ( empty( $api_key ) ) {
			return new WP_Error( 'sag_no_key', 'Gemini API key is not configured. Go to Settings → Social Assets.' );
		}

		// Use a low thinkingBudget to keep response time reasonable
		$body = array(
			'system_instruction' => array(
				'parts' => array( array( 'text' => $system ) ),
			),
			'contents' => array(
				array( 'parts' => array( array( 'text' => $user ) ) ),
			),
			'generationConfig' => array(
				'responseMimeType' => 'application/json',
				'maxOutputTokens'  => 8192, // Thinking tokens count against this budget on 2.5+ models — 4096 left too little room for the actual JSON output and was truncating mid-response.
				'thinkingConfig'   => array( 'thinkingBudget' => 1024 ),
			),
		);

		$url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$api_key}";

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 90,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $body ),
			)
		);

		return self::parse_response( $response, 'gemini' );
	}

	private static function call_ollama_cloud( $system, $user ) {
		$api_key = SAG_Settings::get( 'ollama_key' );
		$model   = SAG_Settings::get( 'ollama_model', 'qwen3.5' );

		if ( empty( $api_key ) ) {
			return new WP_Error( 'sag_no_key', 'Ollama Cloud API key is not configured. Go to Settings → Social Assets.' );
		}

		$response = wp_remote_post(
			'https://ollama.com/api/chat',
			array(
				'timeout' => 90,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body' => wp_json_encode( array(
					'model'    => $model,
					'stream'   => false,
					'options'  => array( 'num_predict' => 8000 ),
					'messages' => array(
						array( 'role' => 'system', 'content' => $system ),
						array( 'role' => 'user',   'content' => $user ),
					),
				) ),
			)
		);

		return self::parse_response( $response, 'ollama' );
	}

	// -------------------------------------------------------------------------
	// Response parser
	// -------------------------------------------------------------------------

	private static function parse_response( $response, $provider ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		if ( $code !== 200 ) {
			$message = $data['error']['message'] ?? "API error (HTTP {$code})";
			return new WP_Error( 'sag_api_error', $message );
		}

		// Extract text content per provider
		if ( 'claude' === $provider ) {
			$text = $data['content'][0]['text'] ?? '';

		} elseif ( 'gemini' === $provider ) {
			// Gemini 2.5+ may return multiple parts (thinking + output). Skip thought parts.
			$text  = '';
			$parts = $data['candidates'][0]['content']['parts'] ?? array();
			foreach ( $parts as $part ) {
				if ( ! empty( $part['thought'] ) ) continue;
				if ( isset( $part['text'] ) ) {
					$text = $part['text'];
					break;
				}
			}

		} elseif ( 'ollama' === $provider ) {
			$text = $data['message']['content'] ?? '';

		} else {
			$text = $data['choices'][0]['message']['content'] ?? '';
		}

		if ( empty( $text ) ) {
			return new WP_Error( 'sag_empty_response', 'The API returned an empty response.' );
		}

		return self::extract_json( $text );
	}

	/**
	 * Multi-pass JSON extractor.
	 */
	private static function extract_json( $text ) {
		$text = trim( $text );

		// Pass 1: direct decode
		$decoded = json_decode( $text, true );
		if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
			return $decoded;
		}

		// Pass 2: strip markdown fences
		$stripped = preg_replace( '/^```(?:json)?\s*/i', '', $text );
		$stripped = preg_replace( '/\s*```\s*$/i', '', $stripped );
		$decoded  = json_decode( trim( $stripped ), true );
		if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
			return $decoded;
		}

		// Pass 3: find the outermost { ... } block
		$start = strpos( $text, '{' );
		$end   = strrpos( $text, '}' );
		if ( $start !== false && $end !== false && $end > $start ) {
			$extracted = substr( $text, $start, $end - $start + 1 );
			$decoded   = json_decode( $extracted, true );
			if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
				return $decoded;
			}
		}

		$open_braces  = substr_count( $text, '{' );
		$close_braces = substr_count( $text, '}' );
		$truncated_hint = ( $open_braces > $close_braces )
			? ' The response looks cut off mid-JSON (unbalanced braces) — this usually means the output hit the model\'s token limit before finishing. Try a shorter post, or switch to a model with a larger output limit.'
			: '';

		return new WP_Error(
			'sag_json_error',
			'Could not parse the API response as JSON.' . $truncated_hint . ' First 400 chars: ' . substr( $text, 0, 400 )
		);
	}
}
