<?php
/**
 * @package Lookit_SEO_Autofill
 */

class Test_Lookit_SEO_Autofill_Plugin extends WP_UnitTestCase {

	public function tear_down() {
		delete_option( ASY_OPTION_KEY );
		parent::tear_down();
	}

	public function test_plugin_defines_version() {
		$this->assertTrue( defined( 'ASY_VERSION' ) );
	}

	public function test_settings_class_is_available() {
		$this->assertTrue( class_exists( 'ASY_Settings' ) );
	}

	public function test_sanitize_templates_uses_safe_keys() {
		$settings = new ASY_Settings();
		$result   = $settings->sanitize_templates(
			array(
				'Post Type!' => array(
					'enabled'  => '1',
					'template' => 'Hello',
				),
			)
		);
		$this->assertArrayHasKey( 'posttype', $result );
		$this->assertTrue( $result['posttype']['enabled'] );
		$this->assertSame( 'Hello', $result['posttype']['template'] );
	}

	public function test_lock_meta_auth_requires_edit_post() {
		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		$own    = self::factory()->post->create( array( 'post_author' => $author ) );
		$other  = self::factory()->post->create( array( 'post_author' => $editor ) );

		wp_set_current_user( $author );
		$this->assertTrue( asy_lock_meta_auth( false, '_asy_seo_locked', $own ) );
		$this->assertFalse( asy_lock_meta_auth( false, '_asy_seo_locked', $other ) );
	}

	public function test_publish_fills_empty_yoast_fields() {
		if ( ! defined( 'WPSEO_VERSION' ) ) {
			define( 'WPSEO_VERSION', 'test' );
		}
		update_option(
			ASY_OPTION_KEY,
			array(
				'post' => array(
					'enabled'       => true,
					'set_keyphrase' => true,
					'template'      => 'Description for {title}',
				),
			)
		);
		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'draft',
				'post_title'  => 'Useful article',
			)
		);

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			)
		);

		$this->assertSame( 'Useful article', get_post_meta( $post_id, '_yoast_wpseo_focuskw', true ) );
		$this->assertSame( 'Description for Useful article', get_post_meta( $post_id, '_yoast_wpseo_metadesc', true ) );
	}

	public function test_publish_preserves_manual_yoast_fields() {
		if ( ! defined( 'WPSEO_VERSION' ) ) {
			define( 'WPSEO_VERSION', 'test' );
		}
		update_option(
			ASY_OPTION_KEY,
			array(
				'post' => array(
					'enabled'       => true,
					'set_keyphrase' => true,
					'template'      => 'Generated description',
					'ai_keyphrases' => true,
				),
			)
		);
		$post_id = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		update_post_meta( $post_id, '_yoast_wpseo_focuskw', 'Manual keyphrase' );
		update_post_meta( $post_id, '_yoast_wpseo_metadesc', 'Manual description' );
		update_post_meta( $post_id, '_yoast_wpseo_focuskeywords', '[{"keyword":"Manual related"}]' );

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			)
		);

		$this->assertSame( 'Manual keyphrase', get_post_meta( $post_id, '_yoast_wpseo_focuskw', true ) );
		$this->assertSame( 'Manual description', get_post_meta( $post_id, '_yoast_wpseo_metadesc', true ) );
		$this->assertSame( '[{"keyword":"Manual related"}]', get_post_meta( $post_id, '_yoast_wpseo_focuskeywords', true ) );
	}

	public function test_datamuse_trigger_query_encodes_multiword_seed_once() {
		$urls   = array();
		$filter = static function ( $response, $args, $url ) use ( &$urls ) {
			$urls[] = $url;
			return array(
				'headers'  => array(),
				'body'     => '[]',
				'response' => array( 'code' => 200 ),
				'cookies'  => array(),
			);
		};
		add_filter( 'pre_http_request', $filter, 10, 3 );

		$method = new ReflectionMethod( ASY_Keyphrase_Engine::class, 'datamuse_expand' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}
		$method->invoke( null, 'Two Words', array(), 1 );
		remove_filter( 'pre_http_request', $filter, 10 );

		parse_str( (string) wp_parse_url( $urls[1], PHP_URL_QUERY ), $query );
		$this->assertSame( 'Two Words', $query['rel_trg'] );
	}
}
