<?php
/**
 * Minimal callback-state test harness. Run with PHP 7.4+.
 */

define( 'ABSPATH', __DIR__ );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'CMPLY_COOKIE_CONSENT_BASENAME', 'cmply/cmply.php' );

$test_user_id = 42;

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

require_once dirname( __DIR__ ) . '/includes/class-cmply.php';

$class   = new ReflectionClass( 'CMPly_Cookie_Consent' );
$create  = $class->getMethod( 'create_connection_state' );
$consume = $class->getMethod( 'consume_connection_state' );
$create->setAccessible( true );
$consume->setAccessible( true );

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

echo "Connection state tests passed.\n";
