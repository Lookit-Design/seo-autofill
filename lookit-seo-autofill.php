<?php
/**
 * Plugin Name: Lookit SEO Autofill
 * Plugin URI:  https://lookitdesign.com
 * Description: Automatically fills your SEO plugin's keyphrase, meta description, and related keyphrases (via content extraction + Datamuse) when a post is published. Works with Yoast SEO.
 * Version:     1.2.7
 * Author:      Lookit Design
 * License:     GPL-2.0+
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Text Domain: lookit-seo-autofill
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'ASY_VERSION',    '1.2.7' );
define( 'ASY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ASY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ASY_OPTION_KEY', 'asy_post_type_templates' );

require_once ASY_PLUGIN_DIR . 'includes/class-asy-keyphrase-engine.php';
require_once ASY_PLUGIN_DIR . 'includes/class-asy-openrouter.php'; // kept for back-compat
require_once ASY_PLUGIN_DIR . 'includes/class-asy-settings.php';
require_once ASY_PLUGIN_DIR . 'includes/class-asy-processor.php';

add_action( 'plugins_loaded', array( 'ASY_Settings',  'init' ) );
add_action( 'plugins_loaded', array( 'ASY_Processor', 'init' ) );

// Register the lock meta for Gutenberg REST access
add_action( 'init', function() {
    $post_types = get_post_types( array( 'public' => true ) );
    foreach ( $post_types as $pt ) {
        register_post_meta( $pt, '_asy_seo_locked', array(
            'show_in_rest'  => true,
            'single'        => true,
            'type'          => 'string',
            'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
        ) );
    }
} );
