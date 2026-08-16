<?php
/**
 * Uninstall routine for Lookit SEO Autofill.
 *
 * @package Lookit_SEO_Autofill
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$asy_uninstall_options = array(
	'asy_openrouter_api_key',
	'asy_post_type_templates',
	'asy_kp_count',
);

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $asy_uninstall_site_id ) {
		switch_to_blog( $asy_uninstall_site_id );
		foreach ( $asy_uninstall_options as $asy_uninstall_option ) {
			delete_option( $asy_uninstall_option );
		}
		restore_current_blog();
	}
} else {
	foreach ( $asy_uninstall_options as $asy_uninstall_option ) {
		delete_option( $asy_uninstall_option );
	}
}
