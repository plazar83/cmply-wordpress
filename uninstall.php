<?php
/**
 * Uninstall cleanup.
 *
 * @package CMPlyCookieConsent
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$cmply_options = get_option( 'cmply_options', array() );
if ( is_array( $cmply_options ) && ! empty( $cmply_options['site_id'] ) ) {
	delete_transient( 'cmply_gcm_' . md5( (string) $cmply_options['site_id'] ) );
}

delete_option( 'cmply_options' );
delete_option( 'cmply_cookie_consent_options' );
delete_option( 'cmply_api_key' );
delete_option( 'cmply_connection_id' );
