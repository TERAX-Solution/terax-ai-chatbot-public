<?php
/**
 * Standalone test for TX-503 (encryption safety), no WordPress needed.
 *
 *   php tests/encryption-test.php
 *
 * Needs the PHP openssl extension. If the CLI has none: php -d extension_dir=<php>/ext -d extension=openssl tests/encryption-test.php
 *
 * @package TeraxAI
 */

namespace {

	define( 'ABSPATH', __DIR__ . '/' );

	$GLOBALS['t_salt']    = 'salt-one';
	$GLOBALS['t_filters'] = array();

	function wp_salt() {
		return $GLOBALS['t_salt'];
	}
	function apply_filters( $tag, $value ) {
		return isset( $GLOBALS['t_filters'][ $tag ] ) ? call_user_func( $GLOBALS['t_filters'][ $tag ], $value ) : $value;
	}

	require dirname( __DIR__ ) . '/libs/Utils/Encryption.php';

	use TeraxAI\Libs\Utils\Encryption;

	$fails = 0;
	function check( $name, $ok ) {
		global $fails;
		echo ( $ok ? 'PASS ' : 'FAIL ' ) . $name . "\n";
		if ( ! $ok ) {
			++$fails;
		}
	}

	if ( ! function_exists( 'openssl_encrypt' ) ) {
		echo "SKIP: the openssl extension is not loaded in this PHP CLI.\n";
		exit( 2 );
	}

	$key    = 'sk-ant-test-1234567890';
	$stored = Encryption::encrypt( $key );

	check( 'encrypted value is not the plain key', $stored !== $key && false === strpos( $stored, 'sk-ant' ) );
	check( 'round trip returns the key', Encryption::decrypt( $stored ) === $key );
	check( 'a readable value is not "unreadable"', ! Encryption::unreadable( $stored ) );

	// Existing stored keys keep working: plain text saved before encryption, or without OpenSSL.
	check( 'plain-text key (with hyphens) is returned unchanged', Encryption::decrypt( $key ) === $key && ! Encryption::unreadable( $key ) );
	check( 'empty value stays empty', '' === Encryption::decrypt( '' ) && ! Encryption::unreadable( '' ) );

	// Salts changed (migrated DB): never hand the ciphertext to a vendor as if it were the key.
	$GLOBALS['t_salt'] = 'salt-two';
	check( 'after a salt change decrypt() returns an empty string, not the ciphertext', '' === Encryption::decrypt( $stored ) );
	check( 'after a salt change the value is reported as unreadable', Encryption::unreadable( $stored ) );
	check( 'a plain key is still fine after a salt change', Encryption::decrypt( $key ) === $key );
	$GLOBALS['t_salt'] = 'salt-one';
	check( 'with the old salt back the key works again', Encryption::decrypt( $stored ) === $key );

	// Server without OpenSSL (simulated): stored as plain text, and available() says so.
	check( 'available() is true here', Encryption::available() );
	$GLOBALS['t_filters']['terax_ai_openssl_available'] = function () {
		return false;
	};
	check( 'available() is false when the filter says OpenSSL is missing', ! Encryption::available() );
	check( 'without OpenSSL the value is stored unchanged', Encryption::encrypt( $key ) === $key );
	unset( $GLOBALS['t_filters']['terax_ai_openssl_available'] );

	echo $fails ? "\n$fails FAILED\n" : "\nALL PASSED\n";
	exit( $fails ? 1 : 0 );
}
