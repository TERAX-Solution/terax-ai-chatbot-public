<?php
/**
 * Encryption helper.
 *
 * Provides reversible encryption for secrets that must be stored at rest and
 * later retrieved in plaintext (for example SMTP credentials). The encryption
 * key is derived from the site's WordPress salts, so encrypted values are not
 * portable between installations.
 *
 * @package TeraxAI\Libs\Utils
 * @since 1.0.0
 */

namespace TeraxAI\Libs\Utils;

/**
 * Class Encryption
 *
 * @since 1.0.0
 */
class Encryption {

	/**
	 * Cipher method used for encryption.
	 *
	 * @var string
	 */
	const CIPHER = 'aes-256-cbc';

	/**
	 * Whether values can be encrypted on this server. Without the PHP openssl extension secrets are
	 * stored as plain text (the admin shows a notice, TX-503).
	 *
	 * @return bool
	 */
	public static function available() {
		/**
		 * Filter the OpenSSL availability check (lets tests simulate a server without the extension).
		 *
		 * @param bool $available Whether openssl_encrypt() exists.
		 */
		return (bool) apply_filters( 'terax_ai_openssl_available', function_exists( 'openssl_encrypt' ) );
	}

	/**
	 * True when the value looks like our ciphertext but cannot be decrypted, e.g. after the WordPress
	 * salts changed or the database was moved to another site. Plain-text values are not "unreadable".
	 *
	 * @param string $value Stored value.
	 * @return bool
	 */
	public static function unreadable( $value ) {
		return null === self::try_decrypt( $value );
	}

	/**
	 * Derive the binary encryption key from the site's auth salt.
	 *
	 * @return string
	 */
	private static function get_key() {
		return hash( 'sha256', wp_salt( 'auth' ), true );
	}

	/**
	 * Encrypt a plaintext value.
	 *
	 * Returns a base64-encoded string containing the IV and ciphertext. If the
	 * OpenSSL extension is unavailable the original value is returned unchanged
	 * so the plugin keeps functioning.
	 *
	 * @param string $value Plaintext value to encrypt.
	 * @return string
	 */
	public static function encrypt( $value ) {
		if ( '' === (string) $value || ! self::available() ) {
			return (string) $value;
		}

		$iv_length  = openssl_cipher_iv_length( self::CIPHER );
		$iv         = openssl_random_pseudo_bytes( $iv_length );
		$ciphertext = openssl_encrypt( (string) $value, self::CIPHER, self::get_key(), OPENSSL_RAW_DATA, $iv );

		if ( false === $ciphertext ) {
			return (string) $value;
		}

		return base64_encode( $iv . $ciphertext );
	}

	/**
	 * Decrypt a value previously produced by {@see Encryption::encrypt()}.
	 *
	 * @param string $value Encrypted value.
	 * @return string
	 */
	public static function decrypt( $value ) {
		$plain = self::try_decrypt( $value );

		// Undecryptable ciphertext counts as "no value": sending it to a vendor as an API key only
		// produces a confusing authentication error.
		return null === $plain ? '' : $plain;
	}

	/**
	 * Decrypt, or return null when the value is ciphertext that cannot be decrypted.
	 * Plain-text values (stored before encryption existed, or without OpenSSL) come back unchanged.
	 *
	 * @param string $value Stored value.
	 * @return string|null
	 */
	private static function try_decrypt( $value ) {
		if ( '' === (string) $value || ! function_exists( 'openssl_decrypt' ) ) {
			return (string) $value;
		}

		$decoded = base64_decode( $value, true );

		if ( false === $decoded ) {
			return (string) $value;
		}

		$iv_length = openssl_cipher_iv_length( self::CIPHER );

		if ( strlen( $decoded ) <= $iv_length ) {
			return (string) $value;
		}

		$iv         = substr( $decoded, 0, $iv_length );
		$ciphertext = substr( $decoded, $iv_length );
		$plaintext  = openssl_decrypt( $ciphertext, self::CIPHER, self::get_key(), OPENSSL_RAW_DATA, $iv );

		return false === $plaintext ? null : $plaintext;
	}
}
