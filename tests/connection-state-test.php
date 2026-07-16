<?php
/**
 * Minimal callback-state test harness. Run with PHP 7.4+.
 */

define( 'ABSPATH', __DIR__ );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'CMPLY_COOKIE_CONSENT_VERSION', '1.0.20' );
define( 'CMPLY_COOKIE_CONSENT_BASENAME', 'cmply/cmply.php' );

$test_user_id = 42;
$test_options = array();
$test_transients = array();
$test_remote_response = array();
$test_remote_requests = 0;

class WP_Error {}

function get_current_user_id() {
	global $test_user_id;
	return $test_user_id;
}

function wp_generate_password() {
	return bin2hex( random_bytes( 10 ) );
}

function wp_salt() {
	return 'test-only-wordpress-auth-salt';
}

function get_option( $name, $default = false ) {
	global $test_options;
	return array_key_exists( $name, $test_options ) ? $test_options[ $name ] : $default;
}

function get_transient( $name ) {
	global $test_transients;
	return array_key_exists( $name, $test_transients ) ? $test_transients[ $name ] : false;
}

function set_transient( $name, $value ) {
	global $test_transients;
	$test_transients[ $name ] = $value;
	return true;
}

function delete_transient( $name ) {
	global $test_transients;
	unset( $test_transients[ $name ] );
	return true;
}

function wp_parse_args( $args, $defaults = array() ) {
	return array_merge( $defaults, $args );
}

function sanitize_text_field( $value ) {
	return trim( (string) $value );
}

function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}

function sanitize_email( $value ) {
	return filter_var( $value, FILTER_SANITIZE_EMAIL );
}

function sanitize_textarea_field( $value ) {
	return trim( (string) $value );
}

function wp_unslash( $value ) {
	return $value;
}

function esc_url_raw( $value ) {
	return $value;
}

function esc_url( $value ) {
	return $value;
}

function wp_parse_url( $value, $component = -1 ) {
	return parse_url( $value, $component );
}

function wp_create_nonce( $action ) {
	return 'cmply_connect_callback' === $action ? 'test-callback-nonce' : '';
}

function admin_url( $path = '' ) {
	return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
}

function home_url() {
	return 'https://example.test';
}

function wp_json_encode( $value ) {
	return json_encode( $value );
}

function wp_safe_remote_request() {
	global $test_remote_response, $test_remote_requests;
	++$test_remote_requests;
	return $test_remote_response;
}

function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}

function wp_remote_retrieve_response_code( $response ) {
	return $response['response']['code'] ?? 0;
}

function wp_remote_retrieve_body( $response ) {
	return $response['body'] ?? '';
}

function add_query_arg( $key, $value = null, $url = null ) {
	if ( is_array( $key ) ) {
		$args = $key;
		$url  = $value;
	} else {
		$args = array( $key => $value );
	}

	$parts = parse_url( $url );
	parse_str( $parts['query'] ?? '', $query );
	$query = array_merge( $query, $args );

	return $parts['scheme'] . '://' . $parts['host'] . ( $parts['path'] ?? '' ) . '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
}

function untrailingslashit( $value ) {
	return rtrim( $value, '/\\' );
}

require_once dirname( __DIR__ ) . '/includes/class-cmply.php';

$class   = new ReflectionClass( 'CMPly_Cookie_Consent' );
$create  = $class->getMethod( 'create_connection_state' );
$consume = $class->getMethod( 'consume_connection_state' );
$connect = $class->getMethod( 'connect_url' );
$normalize_gcm = $class->getMethod( 'normalize_gcm_defaults' );
$request_gcm = $class->getMethod( 'request_gcm_settings' );
$get_gcm = $class->getMethod( 'get_gcm_settings' );
$create->setAccessible( true );
$consume->setAccessible( true );
$connect->setAccessible( true );
$normalize_gcm->setAccessible( true );
$request_gcm->setAccessible( true );
$get_gcm->setAccessible( true );

function assert_state( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$first  = $create->invoke( null );
$second = $create->invoke( null );
assert_state( $consume->invoke( null, $first ), 'first concurrent state must remain valid' );
assert_state( $consume->invoke( null, $second ), 'second concurrent state must remain valid' );

$tampered = substr( $first, 0, -1 ) . ( 'a' === substr( $first, -1 ) ? 'b' : 'a' );
assert_state( ! $consume->invoke( null, $tampered ), 'tampered state must fail' );

$test_user_id = 7;
assert_state( ! $consume->invoke( null, $first ), 'state must be bound to its administrator' );
$test_user_id = 42;

$payload = '42.' . ( time() - 1 ) . '.expiredtoken';
$expired = $payload . '.' . hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );
assert_state( ! $consume->invoke( null, $expired ), 'expired state must fail' );

$connect_url = $connect->invoke( null, array( 'sdk_base_url' => 'https://cmply.app' ) );
parse_str( wp_parse_url( $connect_url, PHP_URL_QUERY ), $connect_query );
$return_url = $connect_query['return_url'] ?? '';
parse_str( wp_parse_url( $return_url, PHP_URL_QUERY ), $return_query );
assert_state( 'cmply_connect_callback' === ( $return_query['action'] ?? '' ), 'callback action must be preserved' );
assert_state( 'test-callback-nonce' === ( $return_query['_wpnonce'] ?? '' ), 'callback URL must include a WordPress nonce' );

$plugin_source = file_get_contents( dirname( __DIR__ ) . '/includes/class-cmply.php' );
assert_state( false !== strpos( $plugin_source, "check_admin_referer( 'cmply_connect_callback' )" ), 'callback handler must verify the WordPress nonce' );

$sanitized = CMPly_Cookie_Consent::sanitize_options(
	array(
		'plan'            => 'pro',
		'pageviews_used'  => 2443,
		'pageviews_limit' => 750000,
		'connection_id'   => 'connection-123',
		'last_synced_at'  => 123456,
	)
);
assert_state( 2443 === $sanitized['pageviews_used'], 'fresh pageview usage must survive sanitization' );
assert_state( 750000 === $sanitized['pageviews_limit'], 'fresh pageview limit must survive sanitization' );
assert_state( 'connection-123' === $sanitized['connection_id'], 'fresh connection ID must survive sanitization' );
assert_state( 123456 === $sanitized['last_synced_at'], 'last sync time must survive sanitization' );

$gcm_row = array(
	'region'                => 'de',
	'analytics_storage'     => 'denied',
	'ad_storage'            => 'denied',
	'functionality_storage' => 'granted',
	'security_storage'      => 'granted',
	'ad_user_data'          => 'denied',
	'ad_personalization'     => 'denied',
);
$gcm_defaults = $normalize_gcm->invoke( null, array( $gcm_row ) );
assert_state( is_array( $gcm_defaults ), 'valid GCM defaults must be accepted' );
assert_state( 'DE' === $gcm_defaults[0]['region'], 'GCM country codes must be normalized to uppercase' );

$global_row           = $gcm_row;
$global_row['region'] = 'ALL';
$global_defaults      = $normalize_gcm->invoke( null, array( $global_row ) );
assert_state( 'all' === $global_defaults[0]['region'], 'the global GCM region must be normalized to all' );

$duplicate_defaults = $normalize_gcm->invoke( null, array( $gcm_row, $gcm_row ) );
assert_state( false === $duplicate_defaults, 'duplicate GCM regions must be rejected' );

$invalid_region           = $gcm_row;
$invalid_region['region'] = 'europe';
assert_state( false === $normalize_gcm->invoke( null, array( $invalid_region ) ), 'invalid GCM regions must be rejected' );

$invalid_consent                      = $gcm_row;
$invalid_consent['analytics_storage'] = 'maybe';
assert_state( false === $normalize_gcm->invoke( null, array( $invalid_consent ) ), 'invalid consent states must be rejected' );
assert_state( false === $normalize_gcm->invoke( null, array() ), 'at least one GCM default row is required' );

$test_options['cmply_api_key'] = '11111111-1111-4111-8111-111111111111';
$gcm_options = array(
	'site_id'       => '22222222-2222-4222-8222-222222222222',
	'connection_id' => '33333333-3333-4333-8333-333333333333',
	'sdk_base_url'  => 'https://cmply.app',
);
$test_remote_response = array(
	'response' => array( 'code' => 200 ),
	'body'     => json_encode(
		array(
			'ok'   => true,
			'data' => array(
				'enabled'  => true,
				'mode'     => 'advanced',
				'defaults' => array( $gcm_row ),
			),
		)
	),
);
$gcm_response = $request_gcm->invoke( null, $gcm_options );
assert_state( ! empty( $gcm_response['ok'] ), 'valid CMPly GCM responses must be accepted' );

$test_remote_response = array( 'response' => array( 'code' => 401 ), 'body' => '{}' );
$gcm_response = $request_gcm->invoke( null, $gcm_options );
assert_state( 'connection_failed' === $gcm_response['error'], 'GCM authorization failures must be actionable' );

$test_remote_response = array( 'response' => array( 'code' => 500 ), 'body' => '{}' );
$gcm_response = $request_gcm->invoke( null, $gcm_options );
assert_state( 'service_unavailable' === $gcm_response['error'], 'GCM service failures must not be reported as validation failures' );

$test_remote_response = array(
	'response' => array( 'code' => 200 ),
	'body'     => json_encode( array( 'ok' => true, 'data' => array( 'enabled' => true, 'mode' => 'advanced', 'defaults' => array( $gcm_row ) ) ) ),
);
$test_remote_requests = 0;
$test_transients      = array();
assert_state( ! empty( $get_gcm->invoke( null, $gcm_options )['ok'] ), 'GCM settings must load from CMPly' );
assert_state( ! empty( $get_gcm->invoke( null, $gcm_options )['cached'] ), 'a second GCM read must use the transient cache' );
assert_state( 1 === $test_remote_requests, 'the GCM cache must prevent duplicate remote requests' );

assert_state( false !== strpos( $plugin_source, "set_transient( self::gcm_cache_key( \$options )" ), 'saved GCM settings must populate the cache' );
assert_state( false !== strpos( $plugin_source, "check_admin_referer( 'cmply_save_gcm' )" ), 'GCM updates must verify a WordPress nonce' );

echo "Connection state tests passed.\n";
