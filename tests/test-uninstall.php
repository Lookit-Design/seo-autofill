<?php
/**
 * @package Lookit_SEO_Autofill
 */

class Test_Lookit_SEO_Autofill_Uninstall extends WP_UnitTestCase {

	public function test_uninstall_deletes_plugin_options() {
		update_option( 'asy_openrouter_api_key', 'lookit-test-value' );
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_asy_seo_locked', '1' );
		update_post_meta( $post_id, '_asy_processed', '2026-09-17 12:00:00' );
		update_post_meta( $post_id, '_asy_or_status', 'done' );

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'lookit-seo-autofill/lookit-seo-autofill.php' );
		}
		require dirname( __DIR__ ) . '/uninstall.php';

		$this->assertFalse( get_option( 'asy_openrouter_api_key' ) );
		$this->assertSame( '', get_post_meta( $post_id, '_asy_seo_locked', true ) );
		$this->assertSame( '', get_post_meta( $post_id, '_asy_processed', true ) );
		$this->assertSame( '', get_post_meta( $post_id, '_asy_or_status', true ) );
	}
}
