<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SAG_Settings {

	const OPTION_KEY = 'sag_settings';

	private static $providers = array(
		'claude'  => array( 'label' => 'Claude',        'sub' => 'Anthropic',     'key_field' => 'claude_key',  'url' => 'https://console.anthropic.com' ),
		'openai'  => array( 'label' => 'OpenAI',        'sub' => 'ChatGPT',       'key_field' => 'openai_key',  'url' => 'https://platform.openai.com' ),
		'gemini'  => array( 'label' => 'Gemini',        'sub' => 'Google',        'key_field' => 'gemini_key',  'url' => 'https://aistudio.google.com/app/apikey' ),
		'groq'    => array( 'label' => 'Groq',          'sub' => 'Free tier',     'key_field' => 'groq_key',    'url' => 'https://console.groq.com/keys' ),
		'mistral' => array( 'label' => 'Mistral',       'sub' => 'Free tier',     'key_field' => 'mistral_key', 'url' => 'https://console.mistral.ai/api-keys' ),
		'ollama'  => array( 'label' => 'Ollama Cloud',  'sub' => 'Pro required',  'key_field' => 'ollama_key',  'url' => 'https://ollama.com/settings/keys' ),
	);

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function add_settings_page() {
		add_options_page(
			'Social Assets Generator',
			'Social Assets',
			'manage_options',
			'social-assets-generator',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings() {
		register_setting(
			'sag_settings_group',
			self::OPTION_KEY,
			array( $this, 'sanitize_settings' )
		);
	}

	public function sanitize_settings( $input ) {
		$output = array();
		$valid_providers       = array_keys( self::$providers );
		$valid_image_providers = array( 'openai', 'gemini', 'stability', 'flux' );
		$output['provider']       = in_array( $input['provider'] ?? '', $valid_providers, true ) ? $input['provider'] : 'claude';
		$output['image_provider'] = in_array( $input['image_provider'] ?? '', $valid_image_providers, true ) ? $input['image_provider'] : 'openai';
		$output['claude_key']     = sanitize_text_field( $input['claude_key']    ?? '' );
		$output['claude_model']   = sanitize_text_field( $input['claude_model']  ?? 'claude-sonnet-4-6' );
		$output['openai_key']     = sanitize_text_field( $input['openai_key']    ?? '' );
		$output['openai_model']   = sanitize_text_field( $input['openai_model']  ?? 'gpt-4o' );
		$output['gemini_key']     = sanitize_text_field( $input['gemini_key']    ?? '' );
		$output['gemini_model']   = sanitize_text_field( $input['gemini_model']  ?? 'gemini-2.5-flash' );
		$output['groq_key']       = sanitize_text_field( $input['groq_key']      ?? '' );
		$output['groq_model']     = sanitize_text_field( $input['groq_model']    ?? 'llama-3.3-70b-versatile' );
		$output['mistral_key']    = sanitize_text_field( $input['mistral_key']   ?? '' );
		$output['mistral_model']  = sanitize_text_field( $input['mistral_model'] ?? 'mistral-small-latest' );
		$output['ollama_key']     = sanitize_text_field( $input['ollama_key']    ?? '' );
		$output['ollama_model']   = sanitize_text_field( $input['ollama_model']  ?? 'qwen3.5' );
		$output['stability_key']  = sanitize_text_field( $input['stability_key'] ?? '' );
		$output['flux_key']       = sanitize_text_field( $input['flux_key']      ?? '' );
		$output['default_tone']   = sanitize_text_field( $input['default_tone']  ?? '' );
		return $output;
	}

	public static function get( $key, $default = '' ) {
		$settings = get_option( self::OPTION_KEY, array() );
		return $settings[ $key ] ?? $default;
	}

	// --- Settings page ---

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$active = self::get( 'provider', 'claude' );
		?>
		<div class="wrap sag-wrap">

			<style>
				/* "The Weathered Chronicle" design system — zivwashere.com */
				@import url('https://fonts.googleapis.com/css2?family=Epilogue:ital,wght@0,400;0,600;0,700;1,700;1,800&display=swap');

				.sag-wrap {
					--sag-paper: #fff8f1; --sag-ink: #211b0c; --sag-ink-soft: #50453b;
					--sag-red: #aa361d; --sag-red-deep: #7f1802;
					--sag-blue: #42617d; --sag-blue-light: #bdddfe;
					--sag-gold: #daa430; --sag-cream: #f9edd4; --sag-cream-low: #fff2da; --sag-cream-high: #ede1c9;
					--sag-font: 'Epilogue', -apple-system, "Segoe UI", Roboto, sans-serif;
					max-width: 1100px;
					margin: 20px 20px 40px 0;
					padding: 28px 32px 36px;
					background: var(--sag-paper);
					border: 2px solid var(--sag-ink);
					box-shadow: 6px 6px 0 var(--sag-ink);
					font-family: var(--sag-font);
					color: var(--sag-ink);
				}

				/* Product header */
				.sag-product-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 20px; flex-wrap: wrap; padding-bottom: 20px; margin-bottom: 4px; border-bottom: 2px solid var(--sag-ink); }
				.sag-product-brand { display: flex; align-items: center; gap: 16px; }
				.sag-product-mark { display: flex; align-items: center; justify-content: center; width: 52px; height: 52px; flex-shrink: 0; background: var(--sag-red); border: 2px solid var(--sag-ink); box-shadow: 3px 3px 0 var(--sag-ink); color: #fff; font-size: 24px; }
				.sag-product-title { margin: 0; padding: 0; font-size: 1.7rem; font-weight: 800; font-style: italic; line-height: 1.1; text-transform: uppercase; color: var(--sag-ink); }
				.sag-product-tagline { margin: 4px 0 0; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--sag-ink-soft); }
				.sag-product-meta { display: flex; align-items: center; gap: 10px; }
				.sag-version-badge { display: inline-block; padding: 3px 9px; background: var(--sag-gold); border: 2px solid var(--sag-ink); font-size: 11px; font-weight: 700; letter-spacing: .05em; }
				.sag-brand-link { display: inline-block; padding: 3px 9px; background: var(--sag-ink); border: 2px solid var(--sag-ink); color: var(--sag-paper) !important; font-size: 11px; font-weight: 700; font-style: italic; letter-spacing: .05em; text-decoration: none; transition: transform .12s ease, box-shadow .12s ease; }
				.sag-brand-link:hover, .sag-brand-link:focus { transform: translate(-2px,-2px); box-shadow: 2px 2px 0 var(--sag-red); color: #fff !important; }

				.sag-layout { display: flex; gap: 32px; align-items: flex-start; margin-top: 24px; }
				.sag-form-col { flex: 1; min-width: 0; }
				.sag-sidebar-col { width: 250px; flex-shrink: 0; }

				.sag-provider-select-wrap { margin-bottom: 24px; }
				.sag-provider-select-wrap label { display: block; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 6px; font-size: 12px; color: var(--sag-ink); }
				.sag-wrap select { font-family: var(--sag-font); font-size: 14px; padding: 6px 10px; background: #fff; border: 2px solid var(--sag-ink); border-radius: 0; color: var(--sag-ink); box-shadow: none; }
				.sag-wrap select:focus { border-color: var(--sag-red); outline: none; box-shadow: none; }
				#sag-provider-dropdown { width: 100%; max-width: 340px; }

				.sag-wrap input[type="text"], .sag-wrap input[type="password"] { font-family: var(--sag-font); background: #fff; border: 2px solid var(--sag-ink); border-radius: 0; color: var(--sag-ink); box-shadow: none; }
				.sag-wrap input[type="text"]:focus, .sag-wrap input[type="password"]:focus { border-color: var(--sag-red); background: var(--sag-paper); outline: none; box-shadow: none; }

				.sag-provider-group { display: none; background: var(--sag-cream-low); border: 2px solid var(--sag-ink); border-radius: 0; padding: 20px 24px; margin-bottom: 24px; box-shadow: 4px 4px 0 var(--sag-ink); }
				.sag-provider-group.sag-active { display: block; }
				.sag-provider-group h3 { margin: 0 0 16px; font-size: 13px; font-weight: 800; font-style: italic; text-transform: uppercase; letter-spacing: .04em; color: var(--sag-ink); border-bottom: 2px solid var(--sag-ink); padding-bottom: 10px; }
				.sag-provider-group .form-table { margin: 0; }
				.sag-provider-group .form-table th { width: 130px; padding: 10px 10px 10px 0; font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: var(--sag-ink); }
				.sag-provider-group .form-table td { padding: 8px 0; }
				.sag-wrap .description { color: var(--sag-ink-soft); }
				.sag-wrap .description a, .sag-wrap a { color: var(--sag-blue); font-weight: 600; }

				.sag-tone-wrap { background: var(--sag-cream-low); border: 2px solid var(--sag-ink); border-radius: 0; padding: 20px 24px; margin-bottom: 24px; box-shadow: 4px 4px 0 var(--sag-ink); }
				.sag-tone-wrap h3 { margin: 0 0 14px; font-size: 13px; font-weight: 800; font-style: italic; text-transform: uppercase; letter-spacing: .04em; color: var(--sag-ink); border-bottom: 2px solid var(--sag-ink); padding-bottom: 10px; }
				.sag-tone-wrap label { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; display: block; margin-bottom: 6px; }

				/* Buttons */
				.sag-wrap .button-primary { background: var(--sag-red) !important; border: 2px solid var(--sag-ink) !important; border-radius: 0 !important; color: #fff !important; font-family: var(--sag-font); font-weight: 700; font-style: italic; text-transform: uppercase; letter-spacing: .03em; text-shadow: none; transition: transform .12s ease, box-shadow .12s ease; }
				.sag-wrap .button-primary:hover, .sag-wrap .button-primary:focus { background: var(--sag-red-deep) !important; transform: translate(-2px,-2px); box-shadow: 3px 3px 0 var(--sag-ink); }

				/* Sidebar */
				.sag-sidebar-box { background: var(--sag-cream); border: 2px solid var(--sag-ink); border-radius: 0; padding: 16px 18px; box-shadow: 4px 4px 0 var(--sag-ink); }
				.sag-sidebar-box h3 { margin: 0 0 14px; font-size: 12px; font-weight: 800; font-style: italic; text-transform: uppercase; letter-spacing: .05em; color: var(--sag-ink); border-bottom: 2px solid var(--sag-ink); padding-bottom: 10px; }
				.sag-provider-status { list-style: none; margin: 0; padding: 0; }
				.sag-provider-status li { display: flex; align-items: center; gap: 10px; padding: 8px 6px; font-size: 13px; }
				.sag-provider-status li:nth-child(even) { background: var(--sag-cream-low); }
				.sag-status-dot { width: 10px; height: 10px; border-radius: 0; border: 2px solid var(--sag-ink); flex-shrink: 0; }
				.sag-status-dot.has-key { background: var(--sag-blue); }
				.sag-status-dot.no-key { background: var(--sag-cream-high); }
				.sag-provider-name { flex: 1; font-weight: 600; }
				.sag-provider-name span { display: block; font-size: 11px; font-weight: 400; color: var(--sag-ink-soft); }
				.sag-key-badge { font-size: 10px; padding: 2px 7px; border-radius: 0; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; border: 2px solid var(--sag-ink); }
				.sag-key-badge.saved { background: var(--sag-blue-light); color: var(--sag-ink); }
				.sag-key-badge.missing { background: var(--sag-paper); color: var(--sag-ink-soft); border-color: var(--sag-ink-soft); }
				.sag-active-badge { font-size: 10px; background: var(--sag-red); color: #fff; border: 2px solid var(--sag-ink); border-radius: 0; padding: 1px 6px; font-weight: 700; font-style: italic; text-transform: uppercase; letter-spacing: .04em; }

				@media screen and (max-width: 960px) {
					.sag-layout { flex-direction: column; }
					.sag-sidebar-col { width: 100%; }
					.sag-wrap { padding: 20px 16px 28px; margin-right: 10px; }
				}
			</style>

			<header class="sag-product-header">
				<div class="sag-product-brand">
					<span class="sag-product-mark" aria-hidden="true">🚀</span>
					<div>
						<h1 class="sag-product-title">Social Assets Generator</h1>
						<p class="sag-product-tagline">One post in. Every channel out.</p>
					</div>
				</div>
				<div class="sag-product-meta">
					<span class="sag-version-badge">v<?php echo esc_html( defined( 'SAG_VERSION' ) ? SAG_VERSION : '1.0.0' ); ?></span>
					<a class="sag-brand-link" href="https://zivwashere.com" target="_blank" rel="noopener">zivwashere.com</a>
				</div>
			</header>

			<div class="sag-layout">

				<!-- Main form -->
				<div class="sag-form-col">
					<form method="post" action="options.php">
						<?php settings_fields( 'sag_settings_group' ); ?>

						<!-- Provider selector -->
						<div class="sag-provider-select-wrap">
							<label for="sag-provider-dropdown">Active AI provider</label>
							<select id="sag-provider-dropdown" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[provider]">
								<option value="claude"  <?php selected( $active, 'claude' );  ?>>Claude (Anthropic)</option>
								<option value="openai"  <?php selected( $active, 'openai' );  ?>>OpenAI (ChatGPT)</option>
								<option value="gemini"  <?php selected( $active, 'gemini' );  ?>>Google Gemini — free tier</option>
								<option value="groq"    <?php selected( $active, 'groq' );    ?>>Groq — free tier, fast</option>
								<option value="mistral" <?php selected( $active, 'mistral' ); ?>>Mistral — free tier</option>
								<option value="ollama"  <?php selected( $active, 'ollama' );  ?>>Ollama Cloud — Pro plan</option>
							</select>
						</div>

						<!-- Claude -->
						<div class="sag-provider-group <?php echo $active === 'claude' ? 'sag-active' : ''; ?>" data-provider="claude">
							<h3>Claude settings</h3>
							<table class="form-table"><tbody>
								<tr>
									<th><label>API key</label></th>
									<td>
										<input type="password" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[claude_key]"
											value="<?php echo esc_attr( self::get( 'claude_key' ) ); ?>"
											class="regular-text" autocomplete="new-password">
										<p class="description">Get your key at <a href="https://console.anthropic.com" target="_blank">console.anthropic.com</a></p>
									</td>
								</tr>
								<tr>
									<th><label>Model</label></th>
									<td><?php $this->model_select( 'claude_model', 'claude-sonnet-4-6', array(
										'claude-opus-4-8'           => 'Claude Opus 4.8 — most capable',
										'claude-sonnet-4-6'         => 'Claude Sonnet 4.6 — recommended',
										'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5 — fastest',
									) ); ?></td>
								</tr>
							</tbody></table>
						</div>

						<!-- OpenAI -->
						<div class="sag-provider-group <?php echo $active === 'openai' ? 'sag-active' : ''; ?>" data-provider="openai">
							<h3>OpenAI settings</h3>
							<table class="form-table"><tbody>
								<tr>
									<th><label>API key</label></th>
									<td>
										<input type="password" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[openai_key]"
											value="<?php echo esc_attr( self::get( 'openai_key' ) ); ?>"
											class="regular-text" autocomplete="new-password">
										<p class="description">Get your key at <a href="https://platform.openai.com" target="_blank">platform.openai.com</a></p>
									</td>
								</tr>
								<tr>
									<th><label>Model</label></th>
									<td><?php $this->model_select( 'openai_model', 'gpt-4o', array(
										'gpt-4o'        => 'GPT-4o — recommended',
										'gpt-4-turbo'   => 'GPT-4 Turbo',
										'gpt-3.5-turbo' => 'GPT-3.5 Turbo — fastest',
									) ); ?></td>
								</tr>
							</tbody></table>
						</div>

						<!-- Gemini -->
						<div class="sag-provider-group <?php echo $active === 'gemini' ? 'sag-active' : ''; ?>" data-provider="gemini">
							<h3>Google Gemini settings</h3>
							<table class="form-table"><tbody>
								<tr>
									<th><label>API key</label></th>
									<td>
										<input type="password" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[gemini_key]"
											value="<?php echo esc_attr( self::get( 'gemini_key' ) ); ?>"
											class="regular-text" autocomplete="new-password">
										<p class="description">Free key at <a href="https://aistudio.google.com/app/apikey" target="_blank">aistudio.google.com</a> — 1M tokens/day free</p>
									</td>
								</tr>
								<tr>
									<th><label>Model</label></th>
									<td><?php $this->model_select( 'gemini_model', 'gemini-2.5-flash', array(
										'gemini-2.5-flash'      => 'Gemini 2.5 Flash — recommended, free',
										'gemini-2.5-flash-lite' => 'Gemini 2.5 Flash-Lite — fastest, free',
										'gemini-3.5-flash'      => 'Gemini 3.5 Flash — most capable, free',
										'gemini-2.5-pro'        => 'Gemini 2.5 Pro — advanced reasoning',
									) ); ?></td>
								</tr>
							</tbody></table>
						</div>

						<!-- Groq -->
						<div class="sag-provider-group <?php echo $active === 'groq' ? 'sag-active' : ''; ?>" data-provider="groq">
							<h3>Groq settings</h3>
							<table class="form-table"><tbody>
								<tr>
									<th><label>API key</label></th>
									<td>
										<input type="password" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[groq_key]"
											value="<?php echo esc_attr( self::get( 'groq_key' ) ); ?>"
											class="regular-text" autocomplete="new-password">
										<p class="description">Free key at <a href="https://console.groq.com/keys" target="_blank">console.groq.com</a></p>
									</td>
								</tr>
								<tr>
									<th><label>Model</label></th>
									<td><?php $this->model_select( 'groq_model', 'llama-3.3-70b-versatile', array(
										'llama-3.3-70b-versatile' => 'Llama 3.3 70B — recommended',
										'llama-3.1-8b-instant'    => 'Llama 3.1 8B — fastest',
										'mixtral-8x7b-32768'      => 'Mixtral 8x7B',
									) ); ?></td>
								</tr>
							</tbody></table>
						</div>

						<!-- Mistral -->
						<div class="sag-provider-group <?php echo $active === 'mistral' ? 'sag-active' : ''; ?>" data-provider="mistral">
							<h3>Mistral settings</h3>
							<table class="form-table"><tbody>
								<tr>
									<th><label>API key</label></th>
									<td>
										<input type="password" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[mistral_key]"
											value="<?php echo esc_attr( self::get( 'mistral_key' ) ); ?>"
											class="regular-text" autocomplete="new-password">
										<p class="description">Free key at <a href="https://console.mistral.ai/api-keys" target="_blank">console.mistral.ai</a></p>
									</td>
								</tr>
								<tr>
									<th><label>Model</label></th>
									<td><?php $this->model_select( 'mistral_model', 'mistral-small-latest', array(
										'mistral-small-latest'  => 'Mistral Small — fast, free tier',
										'mistral-medium-latest' => 'Mistral Medium — balanced',
										'open-mixtral-8x7b'     => 'Mixtral 8x7B — open source',
									) ); ?></td>
								</tr>
							</tbody></table>
						</div>

						<!-- Ollama Cloud -->
						<div class="sag-provider-group <?php echo $active === 'ollama' ? 'sag-active' : ''; ?>" data-provider="ollama">
							<h3>Ollama Cloud settings</h3>
							<table class="form-table"><tbody>
								<tr>
									<th><label>API key</label></th>
									<td>
										<input type="password" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[ollama_key]"
											value="<?php echo esc_attr( self::get( 'ollama_key' ) ); ?>"
											class="regular-text" autocomplete="new-password">
										<p class="description">Get your key at <a href="https://ollama.com/settings/keys" target="_blank">ollama.com/settings/keys</a> — requires Pro plan ($20/mo)</p>
									</td>
								</tr>
								<tr>
									<th><label>Model</label></th>
									<td><?php $this->model_select( 'ollama_model', 'qwen3.5', array(
										'qwen3.5'           => 'Qwen 3.5 — popular, vision + tools',
										'gemma4'            => 'Gemma 4 — Google, fast',
										'gpt-oss:120b'      => 'GPT-OSS 120B — OpenAI open-weight',
										'gpt-oss:20b'       => 'GPT-OSS 20B — OpenAI open-weight, faster',
										'deepseek-v4-flash' => 'DeepSeek V4 Flash — efficient',
										'deepseek-v4-pro'   => 'DeepSeek V4 Pro — frontier',
										'glm-5.1'           => 'GLM 5.1 — strong coding',
										'minimax-m3'        => 'MiniMax M3 — 1M context',
										'kimi-k2.6'         => 'Kimi K2.6 — agentic, multimodal',
									) ); ?></td>
								</tr>
							</tbody></table>
						</div>

						<!-- Image Generation section -->
						<?php
						$active_img = self::get( 'image_provider', 'openai' );
						?>
						<div class="sag-provider-select-wrap" style="margin-top:28px; border-top:2px solid #211b0c; padding-top:20px;">
							<label for="sag-image-provider-dropdown">Image generation provider</label>
							<select id="sag-image-provider-dropdown" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[image_provider]">
								<option value="openai"    <?php selected( $active_img, 'openai' ); ?>>OpenAI — gpt-image-2</option>
								<option value="gemini"    <?php selected( $active_img, 'gemini' ); ?>>Google — Imagen 3 (free)</option>
								<option value="stability" <?php selected( $active_img, 'stability' ); ?>>Stability AI — Stable Image Core</option>
								<option value="flux"      <?php selected( $active_img, 'flux' ); ?>>Flux via fal.ai</option>
							</select>
							<p class="description" style="margin-top:6px;">Independent of the text provider above. OpenAI and Gemini reuse their existing API keys.</p>
						</div>

						<!-- Image: OpenAI (reuses openai_key) -->
						<div class="sag-provider-group sag-img-group <?php echo $active_img === 'openai' ? 'sag-active' : ''; ?>" data-img-provider="openai">
							<h3>OpenAI image settings</h3>
							<table class="form-table"><tbody>
								<tr>
									<th>API key</th>
									<td>
										<?php if ( ! empty( self::get( 'openai_key' ) ) ) : ?>
											<span style="color:#42617d; font-weight:700; font-size:13px;">✓ Using your saved OpenAI key</span>
										<?php else : ?>
											<span class="description">No OpenAI key saved yet. Enter it in the <strong>OpenAI settings</strong> section above and save — this image option reuses it automatically. Get one at <a href="https://platform.openai.com" target="_blank">platform.openai.com</a></span>
										<?php endif; ?>
									</td>
								</tr>
								<tr><th>Model</th><td><strong>gpt-image-2</strong> <span class="description">— 1536×1024, medium quality</span></td></tr>
							</tbody></table>
						</div>

						<!-- Image: Gemini Imagen (reuses gemini_key) -->
						<div class="sag-provider-group sag-img-group <?php echo $active_img === 'gemini' ? 'sag-active' : ''; ?>" data-img-provider="gemini">
							<h3>Google Imagen 3 settings</h3>
							<table class="form-table"><tbody>
								<tr>
									<th>API key</th>
									<td>
										<?php if ( ! empty( self::get( 'gemini_key' ) ) ) : ?>
											<span style="color:#42617d; font-weight:700; font-size:13px;">✓ Using your saved Gemini key</span>
										<?php else : ?>
											<span class="description">No Gemini key saved yet. Enter it in the <strong>Google Gemini settings</strong> section above and save — this image option reuses it automatically. Free at <a href="https://aistudio.google.com/app/apikey" target="_blank">aistudio.google.com</a></span>
										<?php endif; ?>
									</td>
								</tr>
								<tr><th>Model</th><td><strong>imagen-3.0-generate-002</strong> <span class="description">— 16:9 ratio, PNG</span></td></tr>
							</tbody></table>
						</div>

						<!-- Image: Stability AI -->
						<div class="sag-provider-group sag-img-group <?php echo $active_img === 'stability' ? 'sag-active' : ''; ?>" data-img-provider="stability">
							<h3>Stability AI settings</h3>
							<table class="form-table"><tbody>
								<tr>
									<th>API key</th>
									<td>
										<input type="password" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[stability_key]"
											value="<?php echo esc_attr( self::get( 'stability_key' ) ); ?>"
											class="regular-text" autocomplete="new-password">
										<p class="description">Get your key at <a href="https://platform.stability.ai/account/keys" target="_blank">platform.stability.ai</a></p>
									</td>
								</tr>
								<tr><th>Model</th><td><strong>Stable Image Core</strong> <span class="description">— 16:9 ratio, PNG</span></td></tr>
							</tbody></table>
						</div>

						<!-- Image: Flux via fal.ai -->
						<div class="sag-provider-group sag-img-group <?php echo $active_img === 'flux' ? 'sag-active' : ''; ?>" data-img-provider="flux">
							<h3>Flux (fal.ai) settings</h3>
							<table class="form-table"><tbody>
								<tr>
									<th>API key</th>
									<td>
										<input type="password" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[flux_key]"
											value="<?php echo esc_attr( self::get( 'flux_key' ) ); ?>"
											class="regular-text" autocomplete="new-password">
										<p class="description">Get your key at <a href="https://fal.ai/dashboard/keys" target="_blank">fal.ai/dashboard/keys</a></p>
									</td>
								</tr>
								<tr><th>Model</th><td><strong>flux-pro</strong> via fal.ai <span class="description">— landscape 16:9</span></td></tr>
							</tbody></table>
						</div>

						<!-- Tone (always visible) -->
						<div class="sag-tone-wrap">
							<h3>Content settings</h3>
							<label for="sag-tone-input">Default tone</label>
							<input type="text" id="sag-tone-input"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[default_tone]"
								value="<?php echo esc_attr( self::get( 'default_tone' ) ); ?>"
								class="regular-text"
								placeholder="e.g. professional, witty, conversational">
							<p class="description">Leave blank to let the AI match the post's natural voice.</p>
						</div>

						<?php submit_button( 'Save settings' ); ?>
					</form>
				</div><!-- /.sag-form-col -->

				<!-- Sidebar -->
				<div class="sag-sidebar-col">
					<div class="sag-sidebar-box">
						<h3>Saved API keys</h3>
						<ul class="sag-provider-status">
							<?php foreach ( self::$providers as $id => $info ) :
								$has_key = ! empty( self::get( $info['key_field'] ) );
								$is_active = ( $active === $id );
							?>
							<li>
								<span class="sag-status-dot <?php echo $has_key ? 'has-key' : 'no-key'; ?>"></span>
								<span class="sag-provider-name">
									<?php echo esc_html( $info['label'] ); ?>
									<span><?php echo esc_html( $info['sub'] ); ?></span>
								</span>
								<?php if ( $is_active ) : ?>
									<span class="sag-active-badge">Active</span>
								<?php else : ?>
									<span class="sag-key-badge <?php echo $has_key ? 'saved' : 'missing'; ?>">
										<?php echo $has_key ? 'Saved' : 'No key'; ?>
									</span>
								<?php endif; ?>
							</li>
							<?php endforeach; ?>
						</ul>
					</div>
				</div><!-- /.sag-sidebar-col -->

			</div><!-- /.sag-layout -->
		</div>

		<script>
		(function() {
			// Text provider switcher
			var dropdown = document.getElementById('sag-provider-dropdown');
			if (dropdown) {
				function showProvider(val) {
					document.querySelectorAll('.sag-provider-group:not(.sag-img-group)').forEach(function(el) {
						el.classList.toggle('sag-active', el.dataset.provider === val);
					});
				}
				dropdown.addEventListener('change', function() { showProvider(this.value); });
				showProvider(dropdown.value);
			}

			// Image provider switcher
			var imgDropdown = document.getElementById('sag-image-provider-dropdown');
			if (imgDropdown) {
				function showImageProvider(val) {
					document.querySelectorAll('.sag-img-group').forEach(function(el) {
						el.classList.toggle('sag-active', el.dataset.imgProvider === val);
					});
				}
				imgDropdown.addEventListener('change', function() { showImageProvider(this.value); });
				showImageProvider(imgDropdown.value);
			}
		})();
		</script>
		<?php
	}

	// --- Helper ---

	private function model_select( $field, $default, $options ) {
		$val = self::get( $field, $default );
		echo '<select name="' . esc_attr( self::OPTION_KEY ) . '[' . esc_attr( $field ) . ']">';
		foreach ( $options as $id => $label ) {
			echo '<option value="' . esc_attr( $id ) . '" ' . selected( $val, $id, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}
}
