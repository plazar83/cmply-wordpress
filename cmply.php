<?php
/**
 * Plugin Name: CMPly – Cookie Consent Banner & GDPR Cookie Scanner
 * Plugin URI: https://cmply.app
 * Description: Cookie consent banner, automatic cookie scanner, prior-consent blocking, and Google Consent Mode v2 for WordPress.
 * Version: 1.0.22
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: CMPly
 * Text Domain: cmply
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package CMPlyCookieConsent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CMPLY_COOKIE_CONSENT_VERSION', '1.0.22' );
define( 'CMPLY_COOKIE_CONSENT_FILE', __FILE__ );
define( 'CMPLY_COOKIE_CONSENT_BASENAME', plugin_basename( __FILE__ ) );

require_once __DIR__ . '/includes/class-cmply.php';

add_action( 'plugins_loaded', array( 'CMPly_Cookie_Consent', 'init' ) );

register_activation_hook( __FILE__, array( 'CMPly_Cookie_Consent', 'activate' ) );
