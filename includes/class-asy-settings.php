<?php
/**
 * Settings Page – Lookit SEO Autofill
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ASY_Settings {

	public static function init() {
		$instance = new self();
		add_action( 'admin_menu', array( $instance, 'register_menu' ) );
		add_action( 'admin_init', array( $instance, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $instance, 'enqueue_assets' ) );
		add_action( 'wp_ajax_asy_save_templates', array( $instance, 'ajax_save_templates' ) );
		add_action( 'wp_ajax_asy_save_api_key', array( $instance, 'ajax_save_api_key' ) );
	}

	// ── Menu ──────────────────────────────────────────────────────────────────

	public function register_menu() {
		$parent = function_exists( 'wpseo_init' ) ? 'wpseo_dashboard' : 'options-general.php';
		add_submenu_page(
			$parent,
			__( 'Auto SEO Templates', 'lookit-seo-autofill' ),
			__( 'Auto SEO Templates', 'lookit-seo-autofill' ),
			'manage_options',
			'lookit-seo-autofill',
			array( $this, 'render_page' )
		);
	}

	// ── Settings API ──────────────────────────────────────────────────────────

	public function register_settings() {
		register_setting(
			'asy_settings_group',
			ASY_OPTION_KEY,
			array(
				'sanitize_callback' => array( $this, 'sanitize_templates' ),
			)
		);
	}

	public function sanitize_templates( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}
		$clean = array();
		foreach ( $input as $post_type => $data ) {
			$pt           = sanitize_key( $post_type );
			$clean[ $pt ] = array(
				'enabled'            => ! empty( $data['enabled'] ),
				'template'           => isset( $data['template'] ) ? sanitize_text_field( $data['template'] ) : '',
				'set_keyphrase'      => ! empty( $data['set_keyphrase'] ),
				'slug_keyphrase'     => ! empty( $data['slug_keyphrase'] ),
				'top_word_keyphrase' => ! empty( $data['top_word_keyphrase'] ),
				'ai_keyphrases'      => ! empty( $data['ai_keyphrases'] ),
			);
		}
		return $clean;
	}

	// ── Assets ────────────────────────────────────────────────────────────────

	public function enqueue_assets( $hook ) {
		// Settings page: full JS + CSS
		if ( strpos( $hook, 'lookit-seo-autofill' ) !== false ) {
			wp_enqueue_style( 'asy-admin', ASY_PLUGIN_URL . 'assets/admin.css', array(), ASY_VERSION );
			wp_enqueue_script( 'asy-admin', ASY_PLUGIN_URL . 'assets/admin.js', array( 'jquery' ), ASY_VERSION, true );
			wp_localize_script(
				'asy-admin',
				'ASY',
				array(
					'ajax_url'    => admin_url( 'admin-ajax.php' ),
					'nonce'       => wp_create_nonce( 'asy_nonce' ),
					'saved'       => __( 'Settings saved!', 'lookit-seo-autofill' ),
					'key_saved'   => __( 'API key saved!', 'lookit-seo-autofill' ),
					'error'       => __( 'Save failed. Please try again.', 'lookit-seo-autofill' ),
					'has_api_key' => ! empty( get_option( 'asy_openrouter_api_key', '' ) ),
				)
			);
			return;
		}
		// Post edit screens: CSS only (for the lock metabox styles)
		if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			wp_enqueue_style( 'asy-admin', ASY_PLUGIN_URL . 'assets/admin.css', array(), ASY_VERSION );
		}
	}

	// ── AJAX: save templates ──────────────────────────────────────────────────

	public function ajax_save_templates() {
		check_ajax_referer( 'asy_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied.' ); }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized field-by-field in sanitize_templates() below.
		$raw = isset( $_POST['templates'] ) ? wp_unslash( $_POST['templates'] ) : array();
		update_option( ASY_OPTION_KEY, $this->sanitize_templates( $raw ) );

		// Also save keyphrase count
		if ( isset( $_POST['kp_count'] ) ) {
			update_option( 'asy_kp_count', absint( $_POST['kp_count'] ) );
		}

		wp_send_json_success();
	}

	// ── AJAX: save API key separately (keeps it out of POST logs) ────────────

	public function ajax_save_api_key() {
		check_ajax_referer( 'asy_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied.' ); }

		$key = isset( $_POST['api_key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) ) : '';
		if ( empty( $key ) ) {
			wp_send_json_error( 'Empty key.' ); }

		update_option( 'asy_openrouter_api_key', $key );
		wp_send_json_success();
	}

	// ── Render ────────────────────────────────────────────────────────────────

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$saved      = get_option( ASY_OPTION_KEY, array() );
		$post_types = $this->get_public_post_types();
		$kp_count   = (int) get_option( 'asy_kp_count', 3 );

		$placeholders = array(
			'{title}'     => __( 'Page title', 'lookit-seo-autofill' ),
			'{site}'      => __( 'Site name', 'lookit-seo-autofill' ),
			'{keyphrase}' => __( 'Post title used as keyphrase', 'lookit-seo-autofill' ),
			'{excerpt}'   => __( 'First 25 words of content', 'lookit-seo-autofill' ),
			'{category}'  => __( 'First category / term', 'lookit-seo-autofill' ),
			'{type}'      => __( 'Post type label', 'lookit-seo-autofill' ),
		);
		?>
		<div class="wrap asy-wrap">

			<div class="asy-header">
				<h1><?php esc_html_e( 'Auto SEO Templates', 'lookit-seo-autofill' ); ?></h1>
				<p class="asy-subtitle">
					<?php esc_html_e( 'Auto-fill Yoast keyphrase, meta description, and related keyphrases when a post is published.', 'lookit-seo-autofill' ); ?>
				</p>
			</div>

			<div id="asy-notice" class="asy-notice" style="display:none;"></div>

			<!-- ── Related Keyphrases Settings Card ── -->
			<div class="asy-card asy-or-card">
				<div class="asy-card-header">
					<span><?php esc_html_e( 'Related Keyphrases', 'lookit-seo-autofill' ); ?></span>
					<span class="asy-badge asy-badge--free"><?php esc_html_e( 'Free — no API key needed', 'lookit-seo-autofill' ); ?></span>
				</div>
				<div class="asy-or-body">
					<p class="asy-or-desc">
						<?php esc_html_e( 'When a post is published, Auto SEO analyses the post content to find the most relevant phrases, then expands them using the free Datamuse semantic API. No AI credits, no timeouts, no external accounts required.', 'lookit-seo-autofill' ); ?>
					</p>

					<div class="asy-or-row">
						<label class="asy-or-label" for="asy_kp_count"><?php esc_html_e( 'Keyphrases to generate', 'lookit-seo-autofill' ); ?></label>
						<div class="asy-or-field">
							<select id="asy_kp_count" name="kp_count" class="asy-or-select asy-or-select--sm">
								<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
									<option value="<?php echo esc_attr( $i ); ?>" <?php selected( $kp_count, $i ); ?>><?php echo esc_html( $i ); ?></option>
								<?php endfor; ?>
							</select>
							<p class="asy-or-hint"><?php esc_html_e( 'How many related keyphrases to add to Yoast on each publish.', 'lookit-seo-autofill' ); ?></p>
						</div>
					</div>
				</div>
			</div>

			<!-- ── Test & Reprocess Card ── -->
			<div class="asy-card asy-reprocess-card">
				<div class="asy-card-header">
					<span><?php esc_html_e( 'Test & Reprocess', 'lookit-seo-autofill' ); ?></span>
				</div>
				<div class="asy-or-body">
					<p class="asy-or-desc">
						<?php esc_html_e( 'Generate related keyphrases for any existing post right now — useful for posts published before this feature was enabled, or to refresh keyphrases after editing content.', 'lookit-seo-autofill' ); ?>
					</p>
					<div class="asy-or-row">
						<label class="asy-or-label" for="asy_reprocess_id"><?php esc_html_e( 'Post ID', 'lookit-seo-autofill' ); ?></label>
						<div class="asy-or-field">
							<div class="asy-or-key-wrap">
								<input type="number" id="asy_reprocess_id" class="asy-or-input asy-or-input--sm"
										placeholder="e.g. 27037" min="1" style="width:140px;">
								<button type="button" id="asy-reprocess-btn" class="button button-primary">
									<?php esc_html_e( 'Generate Keyphrases', 'lookit-seo-autofill' ); ?>
								</button>
							</div>
							<div id="asy-reprocess-result" class="asy-reprocess-result" style="display:none;"></div>
						</div>
					</div>
				</div>
			</div>

			<!-- ── Placeholder bar ── -->
			<div class="asy-placeholder-bar">
				<span class="asy-placeholder-label"><?php esc_html_e( 'Template placeholders — click to copy:', 'lookit-seo-autofill' ); ?></span>
				<div class="asy-placeholders">
					<?php foreach ( $placeholders as $tag => $desc ) : ?>
						<button type="button" class="asy-placeholder-btn" data-tag="<?php echo esc_attr( $tag ); ?>">
							<code><?php echo esc_html( $tag ); ?></code>
							<span><?php echo esc_html( $desc ); ?></span>
						</button>
					<?php endforeach; ?>
				</div>
			</div>

			<!-- ── Post types table ── -->
			<div class="asy-card">
				<div class="asy-card-header">
					<span class="asy-col-toggle"><?php esc_html_e( 'Enable', 'lookit-seo-autofill' ); ?></span>
					<span class="asy-col-pt"><?php esc_html_e( 'Post Type', 'lookit-seo-autofill' ); ?></span>
					<span class="asy-col-kp"><?php esc_html_e( 'Auto Keyphrase', 'lookit-seo-autofill' ); ?></span>
					<span class="asy-col-ai"><?php esc_html_e( 'Related Keyphrases', 'lookit-seo-autofill' ); ?></span>
					<span class="asy-col-tpl"><?php esc_html_e( 'Meta Description Template', 'lookit-seo-autofill' ); ?></span>
				</div>

				<div id="asy-rows">
					<?php
					foreach ( $post_types as $pt_slug => $pt_label ) :
						$row         = isset( $saved[ $pt_slug ] ) ? $saved[ $pt_slug ] : array();
						$enabled     = ! empty( $row['enabled'] );
						$tpl         = isset( $row['template'] ) ? $row['template'] : '';
						$set_kp      = isset( $row['set_keyphrase'] ) ? (bool) $row['set_keyphrase'] : true;
						$slug_kp     = ! empty( $row['slug_keyphrase'] );
						$top_word_kp = ! empty( $row['top_word_keyphrase'] );
						$ai_kp       = ! empty( $row['ai_keyphrases'] );
						?>
					<div class="asy-row<?php echo $enabled ? ' asy-row--active' : ''; ?>" data-pt="<?php echo esc_attr( $pt_slug ); ?>">

						<div class="asy-col-toggle">
							<label class="asy-toggle">
								<input type="checkbox" class="asy-enable-cb"
										name="templates[<?php echo esc_attr( $pt_slug ); ?>][enabled]"
										value="1" <?php checked( $enabled ); ?>>
								<span class="asy-toggle-slider"></span>
							</label>
						</div>

						<div class="asy-col-pt">
							<span class="asy-pt-label"><?php echo esc_html( $pt_label ); ?></span>
							<span class="asy-pt-slug"><?php echo esc_html( $pt_slug ); ?></span>
						</div>

						<div class="asy-col-kp">
							<label class="asy-checkbox-label">
								<input type="checkbox"
										name="templates[<?php echo esc_attr( $pt_slug ); ?>][set_keyphrase]"
										value="1" <?php checked( $set_kp ); ?>>
								<?php esc_html_e( 'Use title', 'lookit-seo-autofill' ); ?>
							</label>
							<label class="asy-checkbox-label">
								<input type="checkbox"
										name="templates[<?php echo esc_attr( $pt_slug ); ?>][slug_keyphrase]"
										value="1" <?php checked( $slug_kp ); ?>>
								<?php esc_html_e( 'Use slug', 'lookit-seo-autofill' ); ?>
							</label>
							<label class="asy-checkbox-label">
								<input type="checkbox"
										name="templates[<?php echo esc_attr( $pt_slug ); ?>][top_word_keyphrase]"
										value="1" <?php checked( $top_word_kp ); ?>>
								<?php esc_html_e( 'Top content word', 'lookit-seo-autofill' ); ?>
							</label>
						</div>

						<div class="asy-col-ai">
							<label class="asy-checkbox-label">
								<input type="checkbox"
										name="templates[<?php echo esc_attr( $pt_slug ); ?>][ai_keyphrases]"
										value="1" <?php checked( $ai_kp ); ?>>
								<?php esc_html_e( 'Auto related keyphrases', 'lookit-seo-autofill' ); ?>
							</label>
						</div>

						<div class="asy-col-tpl">
							<input type="text" class="asy-tpl-input"
									name="templates[<?php echo esc_attr( $pt_slug ); ?>][template]"
									value="<?php echo esc_attr( $tpl ); ?>"
									placeholder="<?php esc_attr_e( 'e.g. {title} — {excerpt} | Lookit Design', 'lookit-seo-autofill' ); ?>">
						</div>

					</div>
					<?php endforeach; ?>
				</div>

				<div class="asy-card-footer">
					<button type="button" id="asy-save-btn" class="button button-primary">
						<?php esc_html_e( 'Save Settings', 'lookit-seo-autofill' ); ?>
					</button>
				</div>
			</div>

			<div class="asy-info-box">
				<h3><?php esc_html_e( 'How it works', 'lookit-seo-autofill' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'When a post is published, Auto SEO fills the Yoast focus keyphrase and meta description instantly.', 'lookit-seo-autofill' ); ?></li>
					<li><?php esc_html_e( '"Use title" sets the focus keyphrase to the full post title. "Use slug" converts the URL slug into a readable phrase. "Top content word" finds the single most-used meaningful word in the post content — great for keeping the keyphrase short and on-topic.', 'lookit-seo-autofill' ); ?></li>
					<li><?php esc_html_e( 'Priority if multiple are checked: Top content word → Slug → Title.', 'lookit-seo-autofill' ); ?></li>
					<li><?php esc_html_e( 'Related keyphrases are generated from the post content and expanded via Datamuse — no API key or credits needed.', 'lookit-seo-autofill' ); ?></li>
					<li><?php esc_html_e( 'All fields are overwritten on each publish. Requires Yoast SEO (free or premium).', 'lookit-seo-autofill' ); ?></li>
				</ul>
			</div>

		</div>
		<?php
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	private function get_public_post_types() {
		$types  = get_post_types( array( 'public' => true ), 'objects' );
		$result = array();
		foreach ( $types as $slug => $obj ) {
			$result[ $slug ] = $obj->labels->singular_name ?: $slug;
		}
		return $result;
	}
}
