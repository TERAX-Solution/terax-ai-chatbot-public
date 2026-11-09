<?php
/**
 * General plugin configuration.
 *
 * Values here are build-time defaults for YOUR distribution (TERAX), not
 * per-site settings. Per-site settings live in config/settings.php (defaults)
 * and the `terax_ai_settings` option.
 *
 * Every value can be overridden at runtime with the `terax_ai_config_plugin` filter
 * or with a constant in wp-config.php where noted.
 *
 * @package TeraxAI
 */

defined( 'ABSPATH' ) || exit;

return array(
	'plugin_name'   => 'TERAX AI Assistant',
	'menu_title'    => 'AI Assistant',

	// Branding link shown in the widget footer (removable on paid plans).
	'branding_text' => 'Powered by TERAX',
	'branding_url'  => 'https://terax.dev',
);
