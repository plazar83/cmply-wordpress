<?php
/**
 * Uninstall cleanup.
 *
 * @package CMPlyCookieConsent
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'cmply_options' );
delete_option( 'cmply_cookie_consent_options' );
delete_option( 'cmply_api_key' );
delete_option( 'cmply_connection_id' );
