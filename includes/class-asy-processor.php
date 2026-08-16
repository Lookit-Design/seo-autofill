<?php
/**
 * Processor – Lookit SEO Autofill
 *
 * On publish: synchronously sets focus keyphrase, meta description, and
 * related keyphrases via ASY_Keyphrase_Engine (content extraction + Datamuse).
 * No external AI calls means no timeouts and no API costs.
 *
 * The reprocess AJAX action runs synchronously too — it's fast enough.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ASY_Processor {

	private static $processed_ids = array();

	public static function init() {
		$instance = new self();
		add_action( 'wp_after_insert_post', array( $instance, 'on_after_insert' ), 999, 4 );
		add_action( 'init', array( $instance, 'register_rest_hooks' ), 99 );
		add_action( 'elementor/editor/after_save', array( $instance, 'on_elementor_save' ), 999, 2 );
		add_action( 'wp_ajax_asy_reprocess_post', array( $instance, 'ajax_reprocess_post' ) );
		add_action( 'wp_ajax_asy_poll_keyphrases', array( $instance, 'ajax_poll_keyphrases' ) );

		// Lock metabox
		add_action( 'add_meta_boxes', array( $instance, 'register_lock_metabox' ) );
		add_action( 'save_post', array( $instance, 'save_lock_metabox' ), 10, 2 );
		add_action( 'rest_after_insert_post', array( $instance, 'save_lock_from_rest' ), 10, 2 );
	}

	// ── Publish hooks ─────────────────────────────────────────────────────────

	public function register_rest_hooks() {
		$post_types = get_post_types(
			array(
				'public'       => true,
				'show_in_rest' => true,
			)
		);
		foreach ( $post_types as $pt ) {
			add_action( "rest_after_insert_{$pt}", array( $this, 'on_rest_publish' ), 999, 2 );
		}
	}

	public function on_after_insert( $post_id, $post, $update, $post_before ) {
		if ( 'publish' !== $post->post_status ) {
			return;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}
		$this->process( $post );
	}

	public function on_rest_publish( $post, $request ) {
		if ( 'publish' !== $post->post_status ) {
			return;
		}
		$this->process( $post );
	}

	public function on_elementor_save( $post_id, $data ) {
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return;
		}
		$this->process( $post );
	}

	// ── Core process ──────────────────────────────────────────────────────────

	public function process( $post ) {
		if ( is_int( $post ) ) {
			$post = get_post( $post );
		}
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		if ( wp_is_post_revision( $post->ID ) || wp_is_post_autosave( $post->ID ) ) {
			return;
		}
		if ( in_array( $post->ID, self::$processed_ids, true ) ) {
			return;
		}
		self::$processed_ids[] = $post->ID;

		if ( ! defined( 'WPSEO_VERSION' ) ) {
			return;
		}

		// Respect the per-post lock — never overwrite hand-crafted SEO
		if ( get_post_meta( $post->ID, '_asy_seo_locked', true ) ) {
			$this->log( "Post {$post->ID} is locked — skipping Auto SEO." );
			return;
		}

		$templates = get_option( ASY_OPTION_KEY, array() );
		$pt        = $post->post_type;

		if ( empty( $templates[ $pt ]['enabled'] ) ) {
			return;
		}

		$row = $templates[ $pt ];

		// 1. Focus keyphrase — priority: top content word → slug → title
		if ( ! empty( $row['top_word_keyphrase'] ) ) {
			$keyphrase = $this->get_top_content_word( $post );
			if ( $keyphrase ) {
				update_post_meta( $post->ID, '_yoast_wpseo_focuskw', sanitize_text_field( $keyphrase ) );
				$this->log( "Keyphrase (top word) set for post {$post->ID}: {$keyphrase}" );
			}
		} elseif ( ! empty( $row['slug_keyphrase'] ) ) {
			$keyphrase = $this->slug_to_keyphrase( $post->post_name );
			update_post_meta( $post->ID, '_yoast_wpseo_focuskw', sanitize_text_field( $keyphrase ) );
			$this->log( "Keyphrase (slug) set for post {$post->ID}: {$keyphrase}" );
		} elseif ( ! empty( $row['set_keyphrase'] ) ) {
			update_post_meta( $post->ID, '_yoast_wpseo_focuskw', sanitize_text_field( $post->post_title ) );
			$this->log( "Keyphrase (title) set for post {$post->ID}: {$post->post_title}" );
		}

		// 2. Meta description from template
		if ( ! empty( $row['template'] ) ) {
			$description = $this->truncate( $this->resolve_template( $row['template'], $post ), 156 );
			update_post_meta( $post->ID, '_yoast_wpseo_metadesc', sanitize_text_field( $description ) );
			$this->log( "Meta description set for post {$post->ID}: {$description}" );
		}

		// 3. Related keyphrases via content extraction + Datamuse (fast, no timeout risk)
		if ( ! empty( $row['ai_keyphrases'] ) ) {
			$this->generate_keyphrases( $post );
		}

		update_post_meta( $post->ID, '_asy_processed', current_time( 'mysql' ) );
		update_post_meta( $post->ID, '_asy_version', ASY_VERSION );
	}

	// ── Keyphrase generation ──────────────────────────────────────────────────

	private function generate_keyphrases( WP_Post $post ) {
		$count  = (int) get_option( 'asy_kp_count', 3 );
		$result = ASY_Keyphrase_Engine::get_related_keyphrases( $post, $count );

		if ( is_wp_error( $result ) ) {
			$this->log( "Keyphrase error for post {$post->ID}: " . $result->get_error_message() );
			update_post_meta( $post->ID, '_asy_or_error', $result->get_error_message() );
			update_post_meta( $post->ID, '_asy_or_status', 'error' );
			return;
		}

		ASY_Keyphrase_Engine::save_related_keyphrases( $post->ID, $result );
		update_post_meta( $post->ID, '_asy_or_keyphrases', implode( ', ', $result ) );
		update_post_meta( $post->ID, '_asy_or_status', 'done' );
		delete_post_meta( $post->ID, '_asy_or_error' );
		$this->log( "Keyphrases set for post {$post->ID}: " . implode( ', ', $result ) );
	}

	// ── Admin AJAX: reprocess a single post ──────────────────────────────────

	public function ajax_reprocess_post() {
		check_ajax_referer( 'asy_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id ) {
			wp_send_json_error( 'Invalid post ID.' );
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			wp_send_json_error( "Post {$post_id} not found." );
		}

		if ( ! defined( 'WPSEO_VERSION' ) ) {
			wp_send_json_error( 'Yoast SEO is not active.' );
		}

		$count  = (int) get_option( 'asy_kp_count', 3 );
		$result = ASY_Keyphrase_Engine::get_related_keyphrases( $post, $count );

		if ( is_wp_error( $result ) ) {
			update_post_meta( $post_id, '_asy_or_error', $result->get_error_message() );
			update_post_meta( $post_id, '_asy_or_status', 'error' );
			wp_send_json_error( $result->get_error_message() );
		}

		ASY_Keyphrase_Engine::save_related_keyphrases( $post_id, $result );
		update_post_meta( $post_id, '_asy_or_keyphrases', implode( ', ', $result ) );
		update_post_meta( $post_id, '_asy_or_status', 'done' );
		delete_post_meta( $post_id, '_asy_or_error' );

		wp_send_json_success(
			array(
				'keyphrases' => $result,
				'message'    => 'Keyphrases saved: ' . implode( ', ', $result ),
			)
		);
	}

	// ── Admin AJAX: poll (kept for JS compat, now instant) ────────────────────

	public function ajax_poll_keyphrases() {
		check_ajax_referer( 'asy_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id ) {
			wp_send_json_error( 'Invalid post ID.' );
		}

		wp_send_json_success(
			array(
				'status'     => get_post_meta( $post_id, '_asy_or_status', true ) ?: 'unknown',
				'keyphrases' => get_post_meta( $post_id, '_asy_or_keyphrases', true ) ?: '',
				'error'      => get_post_meta( $post_id, '_asy_or_error', true ) ?: '',
			)
		);
	}

	// ── Lock metabox ──────────────────────────────────────────────────────────

	/**
	 * Register the "Auto SEO Lock" metabox on all public post types.
	 */
	public function register_lock_metabox() {
		$post_types = get_post_types( array( 'public' => true ) );
		foreach ( $post_types as $pt ) {
			add_meta_box(
				'asy_seo_lock',
				__( 'Auto SEO', 'lookit-seo-autofill' ),
				array( $this, 'render_lock_metabox' ),
				$pt,
				'side',
				'low'
			);
		}
	}

	/**
	 * Render the lock metabox HTML.
	 */
	public function render_lock_metabox( WP_Post $post ) {
		$locked = (bool) get_post_meta( $post->ID, '_asy_seo_locked', true );
		wp_nonce_field( 'asy_lock_nonce', 'asy_lock_nonce_field' );
		?>
		<div class="asy-lock-wrap">
			<label class="asy-lock-label">
				<input type="checkbox"
						name="asy_seo_locked"
						value="1"
						<?php checked( $locked ); ?>>
				<span><?php esc_html_e( 'Lock SEO fields', 'lookit-seo-autofill' ); ?></span>
			</label>
			<p class="asy-lock-hint">
				<?php esc_html_e( 'When locked, Auto SEO will not overwrite the focus keyphrase, meta description, or related keyphrases on publish.', 'lookit-seo-autofill' ); ?>
			</p>
			<?php if ( $locked ) : ?>
				<p class="asy-lock-status asy-lock-status--on">
					🔒 <?php esc_html_e( 'SEO fields are protected', 'lookit-seo-autofill' ); ?>
				</p>
			<?php else : ?>
				<p class="asy-lock-status asy-lock-status--off">
					🔓 <?php esc_html_e( 'Auto SEO is active', 'lookit-seo-autofill' ); ?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Save the lock value from Classic Editor / quick edit.
	 */
	public function save_lock_metabox( $post_id, WP_Post $post ) {
		// Verify nonce
		if ( ! isset( $_POST['asy_lock_nonce_field'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['asy_lock_nonce_field'] ) ), 'asy_lock_nonce' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		$locked = ! empty( $_POST['asy_seo_locked'] );
		update_post_meta( $post_id, '_asy_seo_locked', $locked ? '1' : '' );
	}

	/**
	 * Save the lock value from Gutenberg REST saves.
	 * Gutenberg passes meta via the REST API when the post is saved.
	 */
	public function save_lock_from_rest( $post, $request ) {
		$params = $request->get_params();
		if ( ! isset( $params['meta']['_asy_seo_locked'] ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}
		$locked = ! empty( $params['meta']['_asy_seo_locked'] );
		update_post_meta( $post->ID, '_asy_seo_locked', $locked ? '1' : '' );
	}

	// ── Template resolution ───────────────────────────────────────────────────

	/**
	 * Convert a URL slug into a readable keyphrase.
	 * "introducing-lookit-sucuri-purge" → "Introducing Lookit Sucuri Purge"
	 */
	private function slug_to_keyphrase( $slug ) {
		if ( empty( $slug ) ) {
			return '';
		}
		$phrase = str_replace( array( '-', '_' ), ' ', $slug );
		$phrase = preg_replace( '/\s+/', ' ', trim( $phrase ) );
		return ucwords( $phrase );
	}

	/**
	 * Find the top-scoring bigram (2-word phrase) from post content.
	 *
	 * Strategy:
	 *   1. Count individual word frequencies (stop words excluded)
	 *   2. Score every bigram as the SUM of its two word frequencies
	 *      — this naturally surfaces pairs where both words are important
	 *   3. Apply a ×1.5 bonus if the bigram appears in the post title
	 *   4. Return the highest-scoring bigram, title-cased
	 *
	 * Falls back to the top single word if fewer than 2 meaningful words exist.
	 *
	 * @return string  e.g. "Web Security", or empty string if nothing found.
	 */
	private function get_top_content_word( WP_Post $post ) {
		$text = ASY_Keyphrase_Engine::get_content_text( $post );

		if ( empty( $text ) ) {
			$text = sanitize_text_field( $post->post_title );
		}

		$text = strtolower( wp_strip_all_tags( $text ) );
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( '/[^a-z0-9\s\'\-]/', ' ', $text );
		$text = preg_replace( '/\s+/', ' ', trim( $text ) );

		$title_lower = strtolower( sanitize_text_field( $post->post_title ) );
		$stop_words  = ASY_Keyphrase_Engine::get_stop_words();

		// Build clean word list
		$raw_words = preg_split( '/\s+/', $text );
		$words     = array();
		foreach ( $raw_words as $w ) {
			$w = trim( $w, "'-" );
			if ( mb_strlen( $w ) < 3 ) {
				continue;
			}
			if ( isset( $stop_words[ $w ] ) ) {
				continue;
			}
			$words[] = $w;
		}

		if ( empty( $words ) ) {
			return '';
		}

		// Count word frequencies
		$freq = array();
		foreach ( $words as $w ) {
			$freq[ $w ] = ( $freq[ $w ] ?? 0 ) + 1;
		}

		// Need at least 2 words to form a bigram
		if ( count( $words ) < 2 ) {
			arsort( $freq );
			return ucfirst( key( $freq ) );
		}

		// Score every bigram
		$bigrams = array();
		$n       = count( $words );
		for ( $i = 0; $i < $n - 1; $i++ ) {
			$bigram = $words[ $i ] . ' ' . $words[ $i + 1 ];
			$score  = ( $freq[ $words[ $i ] ] ?? 1 ) + ( $freq[ $words[ $i + 1 ] ] ?? 1 );

			// Bonus if the bigram appears in the title
			if ( strpos( $title_lower, $bigram ) !== false ) {
				$score *= 1.5;
			}

			// Accumulate — same bigram may appear multiple times
			$bigrams[ $bigram ] = ( $bigrams[ $bigram ] ?? 0 ) + $score;
		}

		arsort( $bigrams );
		$top_bigram = key( $bigrams );

		return ucwords( $top_bigram );
	}

	private function resolve_template( $template, WP_Post $post ) {
		$replacements = array(
			'{title}'     => $post->post_title,
			'{site}'      => get_bloginfo( 'name' ),
			'{keyphrase}' => $post->post_title,
			'{excerpt}'   => $this->get_excerpt( $post ),
			'{category}'  => $this->get_primary_term( $post ),
			'{type}'      => $this->get_post_type_label( $post->post_type ),
		);
		return str_replace( array_keys( $replacements ), array_values( $replacements ), $template );
	}

	private function get_excerpt( WP_Post $post ) {
		if ( ! empty( $post->post_excerpt ) ) {
			return wp_trim_words( $post->post_excerpt, 25, '' );
		}
		return wp_trim_words( wp_strip_all_tags( $post->post_content ), 25, '' );
	}

	private function get_primary_term( WP_Post $post ) {
		$taxonomies = get_object_taxonomies( $post->post_type );
		if ( empty( $taxonomies ) ) {
			return '';
		}
		$primary_tax     = in_array( 'category', $taxonomies, true ) ? 'category' : $taxonomies[0];
		$primary_term_id = get_post_meta( $post->ID, '_yoast_wpseo_primary_' . $primary_tax, true );
		if ( $primary_term_id ) {
			$term = get_term( (int) $primary_term_id, $primary_tax );
			if ( $term && ! is_wp_error( $term ) ) {
				return $term->name;
			}
		}
		$terms = get_the_terms( $post->ID, $primary_tax );
		if ( $terms && ! is_wp_error( $terms ) ) {
			return $terms[0]->name;
		}
		return '';
	}

	private function get_post_type_label( $post_type ) {
		$obj = get_post_type_object( $post_type );
		return $obj ? $obj->labels->singular_name : $post_type;
	}

	private function truncate( $string, $max ) {
		if ( mb_strlen( $string ) <= $max ) {
			return $string;
		}
		return mb_substr( $string, 0, $max - 1 ) . '…';
	}

	private function log( $message ) {
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( '[Lookit SEO Autofill] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- guarded by WP_DEBUG_LOG, dev diagnostics only.
		}
	}
}
