<?php
/**
 * @package Lookit_SEO_Autofill
 */

class Test_Lookit_SEO_Autofill_Plugin extends WP_UnitTestCase {

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
}
