<?php
/**
 * Global helper functions.
 *
 * @package TeraxAI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Load a config file from /config, filtered.
 *
 * @param string $name Config file name without extension.
 * @return array
 */
function terax_ai_config( $name ) {
	static $cache = array();

	if ( ! isset( $cache[ $name ] ) ) {
		$path           = __DIR__ . '/../config/' . sanitize_file_name( $name ) . '.php';
		$cache[ $name ] = file_exists( $path ) ? (array) require $path : array();
	}

	/**
	 * Filter any config array, e.g. `terax_ai_config_providers`.
	 *
	 * @param array $config Config values.
	 */
	return apply_filters( 'terax_ai_config_' . $name, $cache[ $name ] );
}

/**
 * Shortcut to the settings repository.
 *
 * @param string|null $path    Dot path, e.g. "ai.provider". Null returns all settings.
 * @param mixed       $fallback Value returned when the setting does not exist.
 * @return mixed
 */
function terax_ai_setting( $path = null, $fallback = null ) {
	return \TeraxAI\Support\Settings::get_instance()->get( $path, $fallback );
}

/**
 * Database table name with WP prefix.
 *
 * @param string $name Short table name (chunks, conversations, messages).
 * @return string
 */
function terax_ai_table( $name ) {
	global $wpdb;
	return $wpdb->prefix . 'terax_ai_' . $name;
}
