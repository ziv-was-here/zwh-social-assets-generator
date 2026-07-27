<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SAG_Meta_Box {

	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_sag_generate', array( $this, 'handle_ajax' ) );
		add_action( 'wp_ajax_sag_generate_image', array( $this, 'handle_image_ajax' ) );
		add_action( 'save_post', array( $this, 'save_meta' ) );
	}

	public function register_meta_box() {
		$post_types = apply_filters( 'sag_post_types', array( 'post', 'page' ) );
		foreach ( $post_types as $type ) {
			add_meta_box(
				'sag_social_assets',
				'🚀 Social Assets Generator',
				array( $this, 'render_meta_box' ),
				$type,
				'normal',
				'default'
			);
		}
	}

	public function enqueue_assets( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		wp_enqueue_style(
			'sag-editor',
			SAG_PLUGIN_URL . 'assets/css/sag-editor.css',
			array(),
			SAG_VERSION
		);

		wp_enqueue_script(
			'sag-editor',
			SAG_PLUGIN_URL . 'assets/js/sag-editor.js',
			array( 'jquery' ),
			SAG_VERSION,
			true
		);

		$image_provider = SAG_Settings::get( 'image_provider', 'openai' );
		$has_image_api  = false;
		switch ( $image_provider ) {
			case 'openai':    $has_image_api = ! empty( SAG_Settings::get( 'openai_key' ) ); break;
			case 'gemini':    $has_image_api = ! empty( SAG_Settings::get( 'gemini_key' ) ); break;
			case 'stability': $has_image_api = ! empty( SAG_Settings::get( 'stability_key' ) ); break;
			case 'flux':      $has_image_api = ! empty( SAG_Settings::get( 'flux_key' ) ); break;
		}

		$image_provider_labels = array(
			'openai'    => 'OpenAI',
			'gemini'    => 'Imagen 4',
			'stability' => 'Stability',
			'flux'      => 'Flux',
		);

		wp_localize_script( 'sag-editor', 'sagData', array(
			'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
			'nonce'              => wp_create_nonce( 'sag_generate' ),
			'postId'             => get_the_ID(),
			'hasImageApi'        => $has_image_api,
			'imageProviderLabel' => $image_provider_labels[ $image_provider ] ?? 'Image',
		) );
	}

	public function render_meta_box( $post ) {
		$saved    = get_post_meta( $post->ID, '_sag_assets', true );
		$tone     = SAG_Settings::get( 'default_tone' );
		$provider = SAG_Settings::get( 'provider', 'claude' );
		$provider_labels = array(
			'claude'  => 'Claude',
			'openai'  => 'OpenAI',
			'gemini'  => 'Gemini',
			'groq'    => 'Groq',
			'mistral' => 'Mistral',
			'ollama'  => 'Ollama',
		);
		$provider_label  = $provider_labels[ $provider ] ?? ucfirst( $provider );
		$image_provider = SAG_Settings::get( 'image_provider', 'openai' );
		$has_image_api  = false;
		switch ( $image_provider ) {
			case 'openai':    $has_image_api = ! empty( SAG_Settings::get( 'openai_key' ) ); break;
			case 'gemini':    $has_image_api = ! empty( SAG_Settings::get( 'gemini_key' ) ); break;
			case 'stability': $has_image_api = ! empty( SAG_Settings::get( 'stability_key' ) ); break;
			case 'flux':      $has_image_api = ! empty( SAG_Settings::get( 'flux_key' ) ); break;
		}
		$image_provider_labels = array( 'openai' => 'OpenAI', 'gemini' => 'Imagen 4', 'stability' => 'Stability', 'flux' => 'Flux' );
		$img_label = $image_provider_labels[ $image_provider ] ?? 'Image';
		?>
		<div id="sag-wrap">

			<div id="sag-top-bar">
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=social-assets-generator' ) ); ?>"
					class="sag-settings-link">⚙ Settings</a>
			</div>

			<div class="sag-section" id="sag-section-text">
				<div class="sag-section-head">
					<h4 class="sag-section-title">📝 Text</h4>
					<span id="sag-provider-badge"><?php echo esc_html( $provider_label ); ?></span>
				</div>
				<p class="sag-section-desc">Generates 5 titles, 5 email subject lines, and ready-to-post copy for LinkedIn, Twitter/X, Instagram, and Facebook from this post.</p>
				<div class="sag-section-body">
					<input type="text" id="sag-tone" placeholder="Tone override (optional, e.g. casual, bold)"
						value="<?php echo esc_attr( $tone ); ?>">
					<button type="button" id="sag-generate-btn" class="button button-primary">
						Generate Social Assets
					</button>
				</div>
			</div>

			<div class="sag-section" id="sag-section-image">
				<div class="sag-section-head">
					<h4 class="sag-section-title">🎨 Image</h4>
				</div>
				<p class="sag-section-desc">Generates a social share image in the format you pick below, using <?php echo esc_html( $img_label ); ?>.</p>
				<div class="sag-section-body">
					<div id="sag-format-bar">
						<span class="sag-format-label">Format:</span>
						<button type="button" class="sag-format-btn active" data-format="banner">🖼 Banner<small>1200×630</small></button>
						<button type="button" class="sag-format-btn" data-format="feed_square">⬛ Square<small>1080×1080</small></button>
						<button type="button" class="sag-format-btn" data-format="feed_portrait">📷 Feed<small>1080×1350</small></button>
						<button type="button" class="sag-format-btn" data-format="stories">📲 Stories<small>1080×1920</small></button>
					</div>
					<button type="button" id="sag-image-btn" class="button <?php echo $has_image_api ? '' : 'sag-btn-prompt'; ?>">
						<?php echo $has_image_api ? '🎨 Generate Image (' . esc_html( $img_label ) . ')' : '🎨 Get Image Prompt'; ?>
					</button>
				</div>
			</div>

			<div id="sag-status" hidden></div>
			<div id="sag-image-status" hidden></div>

			<div id="sag-results" <?php echo $saved ? '' : 'hidden'; ?>>
				<div class="sag-tabs" role="tablist">
					<button type="button" class="sag-tab active" data-tab="titles" role="tab">Titles</button>
					<button type="button" class="sag-tab" data-tab="subjects" role="tab">Email subjects</button>
					<button type="button" class="sag-tab" data-tab="linkedin" role="tab">LinkedIn</button>
					<button type="button" class="sag-tab" data-tab="twitter" role="tab">Twitter / X</button>
					<button type="button" class="sag-tab" data-tab="instagram" role="tab">Instagram</button>
					<button type="button" class="sag-tab" data-tab="facebook" role="tab">Facebook</button>
					<button type="button" class="sag-tab" data-tab="hashtags" role="tab">Hashtags</button>
				</div>

				<div id="sag-panel-titles" class="sag-panel"></div>
				<div id="sag-panel-subjects" class="sag-panel" hidden></div>
				<div id="sag-panel-linkedin" class="sag-panel" hidden></div>
				<div id="sag-panel-twitter" class="sag-panel" hidden></div>
				<div id="sag-panel-instagram" class="sag-panel" hidden></div>
				<div id="sag-panel-facebook" class="sag-panel" hidden></div>
				<div id="sag-panel-hashtags" class="sag-panel" hidden></div>

				<div id="sag-save-row">
					<button type="button" id="sag-save-btn" class="button" disabled>Save to post meta</button>
					<span id="sag-save-hint" class="sag-save-hint">— select a title first</span>
					<span id="sag-saved-msg" hidden>✓ Saved</span>
				</div>
			</div>

			<!-- Image panel — always visible once generated -->
			<div id="sag-image-panel" hidden>
				<h4 class="sag-image-heading">Social share image</h4>
				<div id="sag-image-preview"></div>
				<div id="sag-image-actions" hidden>
					<button type="button" id="sag-save-image-btn" class="button button-primary">
						⬆ Save to media library
					</button>
					<span id="sag-image-saved-msg" hidden></span>
				</div>
				<div id="sag-image-prompt-panel" hidden>
					<p class="description">No image API key found — copy this prompt into DALL-E, Midjourney, Ideogram, or any AI image generator:</p>
					<div class="sag-copy-block">
						<div class="sag-copy-content">
							<div class="sag-copy-display" id="sag-image-prompt-text"></div>
							<div class="sag-copy-target" id="sag-image-prompt-raw" style="display:none"></div>
						</div>
						<button type="button" class="button sag-copy-prompt-btn">Copy</button>
					</div>
				</div>
			</div>

			<?php if ( $saved ) : ?>
				<script>window.sagSavedAssets = <?php echo wp_json_encode( $saved ); ?>;</script>
			<?php endif; ?>
		</div>
		<?php
	}

	public function handle_ajax() {
		@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,Squiz.PHP.DiscouragedFunctions.Discouraged
		check_ajax_referer( 'sag_generate', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		$post_id = intval( $_POST['post_id'] ?? 0 );
		$tone    = sanitize_text_field( wp_unslash( $_POST['tone'] ?? '' ) );
		$post    = get_post( $post_id );

		if ( ! $post ) {
			wp_send_json_error( 'Post not found.' );
		}

		$title   = $post->post_title;
		$content = wp_strip_all_tags( $post->post_content );

		if ( empty( trim( $content ) ) ) {
			wp_send_json_error( 'The post has no content yet. Add some content and try again.' );
		}

		$assets = SAG_API::generate( $title, $content, $tone );

		if ( is_wp_error( $assets ) ) {
			wp_send_json_error( $assets->get_error_message() );
		}

		wp_send_json_success( $assets );
	}

	public function handle_image_ajax() {
		@set_time_limit( 180 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,Squiz.PHP.DiscouragedFunctions.Discouraged
		check_ajax_referer( 'sag_generate', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		$post_id = intval( $_POST['post_id'] ?? 0 );
		$post    = get_post( $post_id );

		if ( ! $post ) {
			wp_send_json_error( 'Post not found.' );
		}

		$title   = $post->post_title;
		$content = wp_strip_all_tags( $post->post_content );
		$format  = sanitize_text_field( wp_unslash( $_POST['format'] ?? 'banner' ) );
		$allowed_formats = array( 'banner', 'feed_square', 'feed_portrait', 'stories' );
		if ( ! in_array( $format, $allowed_formats, true ) ) {
			$format = 'banner';
		}

		$result = SAG_API::generate_image( $title, $content, $format );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}

	public function save_meta() {
		// Assets saved via AJAX only
	}
}

// Save text assets to post meta
add_action( 'wp_ajax_sag_save_assets', function() {
	check_ajax_referer( 'sag_generate', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( 'Permission denied.' );
	}

	$post_id = intval( $_POST['post_id'] ?? 0 );
	$assets  = wp_unslash( $_POST['assets'] ?? array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	if ( ! is_array( $assets ) ) {
		wp_send_json_error( 'Invalid data.' );
	}

	$assets = map_deep( $assets, 'sanitize_text_field' );
	update_post_meta( $post_id, '_sag_assets', $assets );
	wp_send_json_success( 'Saved.' );
} );

// Save generated image to media library
add_action( 'wp_ajax_sag_save_image', function() {
	@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,Squiz.PHP.DiscouragedFunctions.Discouraged
	check_ajax_referer( 'sag_generate', 'nonce' );

	if ( ! current_user_can( 'upload_files' ) ) {
		wp_send_json_error( 'Permission denied.' );
	}

	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$post_id  = intval( $_POST['post_id'] ?? 0 );
	$title    = sanitize_text_field( wp_unslash( $_POST['title'] ?? 'Social share image' ) );
	$save_url = esc_url_raw( wp_unslash( $_POST['save_url'] ?? '' ) );

	if ( empty( $save_url ) ) {
		wp_send_json_error( 'No image URL provided.' );
	}

	$attachment_id = media_sideload_image( $save_url, $post_id, $title, 'id' );

	if ( is_wp_error( $attachment_id ) ) {
		wp_send_json_error( $attachment_id->get_error_message() );
	}

	// Delete temp file if it came from our sag-temp folder
	if ( strpos( $save_url, '/sag-temp/' ) !== false ) {
		$upload_dir = wp_upload_dir();
		$temp_path  = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'sag-temp' . DIRECTORY_SEPARATOR . basename( $save_url );
		wp_delete_file( $temp_path );
	}

	$attachment_url = wp_get_attachment_url( $attachment_id );
	wp_send_json_success( array(
		'attachment_id'  => $attachment_id,
		'attachment_url' => $attachment_url,
		'edit_url'       => get_edit_post_link( $attachment_id, 'raw' ),
	) );
} );
