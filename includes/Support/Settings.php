<?php
/**
 * Settings repository.
 *
 * @package TeraxAI
 */

namespace TeraxAI\Support;

use TeraxAI\Libs\Utils\Encryption;
use TeraxAI\Traits\Base;

defined( 'ABSPATH' ) || exit;

/**
 * Class Settings
 *
 * Single source of truth for per-site settings. Stored as one option,
 * merged over config/settings.php defaults. API keys are encrypted at rest
 * and never leave the server in plaintext.
 */
class Settings {

	use Base;

	const OPTION = 'terax_ai_settings';

	/**
	 * Cached merged settings.
	 *
	 * @var array|null
	 */
	private $cache = null;

	/**
	 * Get all settings or one value by dot path.
	 *
	 * @param string|null $path    e.g. "ai.provider".
	 * @param mixed       $fallback Value returned when the setting does not exist.
	 * @return mixed
	 */
	public function get( $path = null, $fallback = null ) {
		$all = $this->all();

		if ( null === $path ) {
			return $all;
		}

		$value = $all;
		foreach ( explode( '.', $path ) as $key ) {
			if ( ! is_array( $value ) || ! array_key_exists( $key, $value ) ) {
				return $fallback;
			}
			$value = $value[ $key ];
		}

		return $value;
	}

	/**
	 * All settings merged with defaults.
	 *
	 * @return array
	 */
	public function all() {
		if ( null === $this->cache ) {
			$stored      = get_option( self::OPTION, array() );
			$this->cache = $this->merge( terax_ai_config( 'settings' ), is_array( $stored ) ? $stored : array() );
		}
		return $this->cache;
	}

	/**
	 * Decrypted API key for a provider.
	 *
	 * @param string $provider Provider id.
	 * @return string
	 */
	public function api_key( $provider ) {
		$keys = (array) $this->get( 'ai.api_keys', array() );
		return empty( $keys[ $provider ] ) ? '' : Encryption::decrypt( $keys[ $provider ] );
	}

	/**
	 * Why a stored API key cannot be used, for the admin notice.
	 *
	 * @param string $provider Provider id.
	 * @return string '' (fine or no key), 'unreadable' (cannot be decrypted, re-enter it) or 'no_openssl' (stored as plain text).
	 */
	public function key_problem( $provider ) {
		$keys = (array) $this->get( 'ai.api_keys', array() );
		if ( empty( $keys[ $provider ] ) ) {
			return '';
		}
		if ( Encryption::unreadable( $keys[ $provider ] ) ) {
			return 'unreadable';
		}
		return Encryption::available() ? '' : 'no_openssl';
	}

	/**
	 * Update settings from a (partial) admin payload.
	 *
	 * @param array $input Raw input.
	 * @return array Saved settings (admin-safe view).
	 */
	public function update( array $input ) {
		$current  = $this->all();
		$defaults = terax_ai_config( 'settings' );

		// API keys: only overwrite when a new non-empty value is sent; allow explicit removal.
		$keys = (array) $current['ai']['api_keys'];
		if ( isset( $input['ai']['api_keys'] ) && is_array( $input['ai']['api_keys'] ) ) {
			foreach ( $input['ai']['api_keys'] as $provider => $key ) {
				$provider = sanitize_key( $provider );
				$key      = trim( (string) $key );
				if ( '__delete__' === $key ) {
					unset( $keys[ $provider ] );
				} elseif ( '' !== $key ) {
					$keys[ $provider ] = Encryption::encrypt( sanitize_text_field( $key ) );
				}
			}
		}
		unset( $input['ai']['api_keys'] );

		$merged                   = $this->merge( $current, $this->sanitize( $input, $defaults ) );
		$merged['ai']['api_keys'] = $keys;

		// Sales mode is not allowed for every plan and industry (e.g. never for dental): enforce it on the server.
		$merged = \TeraxAI\Chat\SalesMode::enforce( $merged );

		update_option( self::OPTION, $merged, false );
		$this->cache = null;

		/**
		 * Fires after settings were saved.
		 *
		 * @param array $merged New settings.
		 * @param array $current Previous settings.
		 */
		do_action( 'terax_ai_settings_updated', $merged, $current );

		return $this->for_admin();
	}

	/**
	 * Settings safe to send to the admin UI (keys masked).
	 *
	 * @return array
	 */
	public function for_admin() {
		$all   = $this->all();
		$hints = array();

		foreach ( array_keys( (array) $all['ai']['api_keys'] ) as $provider ) {
			$key                = $this->api_key( $provider );
			$hints[ $provider ] = $key ? '••••' . substr( $key, -4 ) : '';
		}

		$all['ai']['api_keys'] = $hints;
		return $all;
	}

	/**
	 * Recursively sanitize input using the defaults as a type schema.
	 *
	 * Unknown keys are dropped; values are cast to the type of the default.
	 *
	 * @param mixed $input    Raw input.
	 * @param mixed $defaults Default structure.
	 * @return array
	 */
	private function sanitize( $input, $defaults ) {
		$clean = array();

		if ( ! is_array( $input ) ) {
			return $clean;
		}

		foreach ( $input as $key => $value ) {
			if ( ! array_key_exists( $key, $defaults ) ) {
				continue;
			}

			$default = $defaults[ $key ];

			if ( is_array( $default ) && $this->is_assoc( $default ) ) {
				$clean[ $key ] = $this->sanitize( $value, $default );
			} elseif ( is_array( $default ) ) {
				$clean[ $key ] = array_values( array_filter( array_map( array( $this, 'sanitize_scalar' ), (array) $value ), 'strlen' ) );
			} elseif ( is_bool( $default ) ) {
				$clean[ $key ] = rest_sanitize_boolean( $value );
			} elseif ( is_int( $default ) ) {
				$clean[ $key ] = (int) $value;
			} elseif ( is_float( $default ) ) {
				$clean[ $key ] = (float) $value;
			} elseif ( in_array( $key, array( 'greeting', 'instructions', 'custom_text', 'consent_text' ), true ) ) {
				$clean[ $key ] = sanitize_textarea_field( $value );
			} elseif ( in_array( $key, array( 'url', 'base_url', 'privacy_url' ), true ) ) {
				$clean[ $key ] = esc_url_raw( $value );
			} elseif ( 'primary_color' === $key ) {
				$clean[ $key ] = sanitize_hex_color( $value ) ? sanitize_hex_color( $value ) : $default;
			} else {
				$clean[ $key ] = sanitize_text_field( (string) $value );
			}
		}

		return $clean;
	}

	/**
	 * Sanitize a list item.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private function sanitize_scalar( $value ) {
		return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
	}

	/**
	 * Deep merge where lists are replaced and maps are merged.
	 *
	 * @param array $base     Base.
	 * @param array $override Override.
	 * @return array
	 */
	private function merge( array $base, array $override ) {
		foreach ( $override as $key => $value ) {
			if ( is_array( $value ) && isset( $base[ $key ] ) && is_array( $base[ $key ] ) && $this->is_assoc( $base[ $key ] ) ) {
				$base[ $key ] = $this->merge( $base[ $key ], $value );
			} else {
				$base[ $key ] = $value;
			}
		}
		return $base;
	}

	/**
	 * Whether an array is associative (a map).
	 *
	 * @param array $arr Array.
	 * @return bool
	 */
	private function is_assoc( array $arr ) {
		if ( array() === $arr ) {
			return false;
		}
		return array_keys( $arr ) !== range( 0, count( $arr ) - 1 );
	}
}
