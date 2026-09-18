<?php
/**
 * Uninstall routine for Lookit SEO Autofill.
 *
 * @package Lookit_SEO_Autofill
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$asy_uninstall_options   = array(
	'asy_openrouter_api_key',
	'asy_post_type_templates',
	'asy_kp_count',
);
$asy_uninstall_post_meta = array(
	'_asy_seo_locked',
	'_asy_processed',
	'_asy_version',
	'_asy_or_error',
	'_asy_or_status',
	'_asy_or_keyphrases',
);

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $asy_uninstall_site_id ) {
		switch_to_blog( $asy_uninstall_site_id );
		foreach ( $asy_uninstall_options as $asy_uninstall_option ) {
			delete_option( $asy_uninstall_option );
		}
		foreach ( $asy_uninstall_post_meta as $asy_uninstall_meta_key ) {
			delete_post_meta_by_key( $asy_uninstall_meta_key );
		}
		restore_current_blog();
	}
} else {
	foreach ( $asy_uninstall_options as $asy_uninstall_option ) {
		delete_option( $asy_uninstall_option );
	}
	foreach ( $asy_uninstall_post_meta as $asy_uninstall_meta_key ) {
		delete_post_meta_by_key( $asy_uninstall_meta_key );
	}
}
