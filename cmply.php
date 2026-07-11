<?php
/**
 * Plugin Name: CMPly
 * Plugin URI: https://cmply.app
 * Description: Connects WordPress to CMPly.app for cookie consent, consent records, and consent-based script blocking.
 * Version: 1.0.16
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: CMPly
 * Author URI: https://cmply.app
 * Text Domain: cmply
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package CMPlyCookieConsent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CMPLY_COOKIE_CONSENT_VERSION', '1.0.16' );
define( 'CMPLY_COOKIE_CONSENT_FILE', __FILE__ );
define( 'CMPLY_COOKIE_CONSENT_BASENAME', plugin_basename( __FILE__ ) );

require_once __DIR__ . '/includes/class-cmply.php';

add_action( 'plugins_loaded', array( 'CMPly_Cookie_Consent', 'init' ) );

register_activation_hook( __FILE__, array( 'CMPly_Cookie_Consent', 'activate' ) );
