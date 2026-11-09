<?php
/**
 * Admin app assets.
 *
 * @package TeraxAI
 */

declare(strict_types=1);

namespace TeraxAI\Assets;

use TeraxAI\Libs\Assets;
use TeraxAI\Support\Features;
use TeraxAI\Traits\Base;

defined( 'ABSPATH' ) || exit;

/**
 * Class Admin
 */
class Admin {

	use Base;

	const HANDLE     = 'terax-ai-admin';
	const OBJ_NAME   = 'teraxAiAdmin';
	const DEV_SCRIPT = 'src/admin/main.jsx';
	const SCREEN     = 'toplevel_page_terax-ai-assistant';
	const JSX_SHIM   = 'terax-ai-jsx-runtime';

	/**
	 * Hook.
	 *
	 * @return void
	 */
	public function bootstrap() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_script' ) );
	}

	/**
	 * Enqueue on our screen only.
	 *
	 * @param string $screen Hook suffix.
	 * @return void
	 */
	public function enqueue_script( $screen ) {
		if ( self::SCREEN !== $screen ) {
			return;
		}

		Assets\enqueue_asset(
			TERAX_AI_DIR . 'assets/admin/dist',
			self::DEV_SCRIPT,
			array(
				'handle'       => self::HANDLE,
				'in-footer'    => true,
				// The build does not contain React: WordPress' own copy is loaded first.
				'dependencies' => array( 'react', 'react-dom', $this->jsx_runtime_handle() ),
			)
		);

		$data = array(
			'apiUrl'   => esc_url_raw( rest_url( TERAX_AI_ROUTE_PREFIX ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'version'  => TERAX_AI_VERSION,
			'siteUrl'  => home_url( '/' ),
			'siteName' => get_bloginfo( 'name' ),
			'plan'     => Features::plan(),
			'planLabel' => Features::plan_label(),
			'managedAi' => Features::can( 'managed_ai' ),
			'planFacts' => $this->plan_facts(),
			'docsUrl'  => 'https://terax.dev/docs/ai-assistant',
			/**
			 * Where the "Upgrade" buttons in the admin lead (pricing page or checkout).
			 *
			 * @param string $url URL.
			 */
			'upgradeUrl' => esc_url_raw( apply_filters( 'terax_ai_upgrade_url', 'https://terax.dev/pricing' ) ),
			'locale'   => str_replace( '_', '-', get_user_locale() ),
			'i18n'     => $this->translations(),
		);

		/**
		 * Data for the admin app (window.teraxAiAdmin). Add-ons add their own keys; the Pro add-on sets `pro` to true.
		 *
		 * @param array $data Data.
		 */
		$data = (array) apply_filters( 'terax_ai_admin_data', $data );
		wp_add_inline_script( self::HANDLE, 'window.' . self::OBJ_NAME . ' = ' . wp_json_encode( $data ) . ';', 'before' );
	}

	/**
	 * Script handle that provides window.ReactJSXRuntime.
	 *
	 * WordPress 6.6 and newer register it (react-jsx-runtime). Older versions (the plugin supports 6.2 and newer) do
	 * not, so a small replacement built on React.createElement is registered under our own handle.
	 *
	 * @return string
	 */
	private function jsx_runtime_handle() {
		if ( wp_script_is( 'react-jsx-runtime', 'registered' ) ) {
			return 'react-jsx-runtime';
		}

		wp_register_script( self::JSX_SHIM, false, array( 'react' ), TERAX_AI_VERSION, true );
		wp_add_inline_script(
			self::JSX_SHIM,
			implode(
				"\n",
				array(
					'(function () {',
					'	if (window.ReactJSXRuntime) { return; }',
					'	var h = window.React.createElement;',
					'	function withKey(props, key) {',
					'		var out = {};',
					'		for (var name in props) { if (Object.prototype.hasOwnProperty.call(props, name) && name !== "children") { out[name] = props[name]; } }',
					'		if (key !== undefined) { out.key = key; }',
					'		return out;',
					'	}',
					'	window.ReactJSXRuntime = {',
					'		Fragment: window.React.Fragment,',
					'		jsx: function (type, props, key) { return props.children === undefined ? h(type, withKey(props, key)) : h(type, withKey(props, key), props.children); },',
					'		jsxs: function (type, props, key) { return h.apply(null, [type, withKey(props, key)].concat(props.children)); }',
					'	};',
					'})();',
				)
			)
		);

		return self::JSX_SHIM;
	}

	/**
	 * Facts about the plans for upgrade copy, read from config/plans.php so the texts never go stale:
	 * which plans include a feature, the monthly messages of the first plan with Managed AI,
	 * and the label of the first plan without a message limit.
	 *
	 * @return array
	 */
	private function plan_facts() {
		$facts = array(
			'managed_ai'      => array(),
			'custom_prompt'   => array(),
			'sales_mode'      => array(),
			'managed_ai_msgs' => null,
			'unlimited_plan'  => null,
		);
		foreach ( (array) terax_ai_config( 'plans' ) as $plan ) {
			$limit = (int) ( $plan['limits']['messages_per_month'] ?? 0 );
			foreach ( array( 'managed_ai', 'custom_prompt', 'sales_mode' ) as $feature ) {
				if ( ! empty( $plan['features'][ $feature ] ) ) {
					$facts[ $feature ][] = $plan['label'];
				}
			}
			if ( null === $facts['managed_ai_msgs'] && ! empty( $plan['features']['managed_ai'] ) ) {
				$facts['managed_ai_msgs'] = $limit;
			}
			// Only Managed AI plans count: the top-of-limit copy promises that Managed AI, own prompt and no branding come along.
			if ( null === $facts['unlimited_plan'] && $limit < 0 && ! empty( $plan['features']['managed_ai'] ) ) {
				$facts['unlimited_plan'] = $plan['label'];
			}
		}
		return $facts;
	}

	/**
	 * Admin UI translations: English text => translated text.
	 *
	 * Read from languages/admin-{locale}.json for the current user's locale, falling back to
	 * any file of the same language (de_AT and de_CH use admin-de_DE.json).
	 *
	 * @return array
	 */
	private function translations() {
		$locale = get_user_locale();
		$dir    = TERAX_AI_DIR . 'languages/';
		$files  = array_merge(
			array( $dir . 'admin-' . $locale . '.json' ),
			(array) glob( $dir . 'admin-' . substr( $locale, 0, 2 ) . '_*.json' )
		);

		foreach ( $files as $file ) {
			if ( is_readable( $file ) ) {
				$map = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				return is_array( $map ) ? $map : array();
			}
		}

		return array();
	}
}
