<?php
/**
 * PSR-4 fallback autoloader.
 *
 * Used only when `vendor/autoload.php` is missing, e.g. a fresh git clone where
 * `composer dump-autoload` has not been run (vendor/ is not committed). The
 * plugin has no runtime Composer dependencies, so this mirrors the PSR-4 and
 * "files" entries of composer.json and keeps activation from fataling.
 *
 * @package TeraxAI
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/libs/assets.php';
require_once __DIR__ . '/includes/functions.php';

spl_autoload_register(
	static function ( $class_name ) {
		// Longest prefix first, so TeraxAI\Database\ wins over TeraxAI\.
		$map = array(
			'TeraxAI\\Database\\' => __DIR__ . '/database/',
			'TeraxAI\\Libs\\'     => __DIR__ . '/libs/',
			'TeraxAI\\'           => __DIR__ . '/includes/',
		);

		foreach ( $map as $prefix => $dir ) {
			if ( 0 !== strpos( $class_name, $prefix ) ) {
				continue;
			}

			$file = $dir . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
			if ( is_readable( $file ) ) {
				require_once $file;
			}
			return;
		}
	}
);
