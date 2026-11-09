<?php
/**
 * Admin menu.
 *
 * @package TeraxAI
 */

namespace TeraxAI\Admin;

use TeraxAI\Traits\Base;

defined( 'ABSPATH' ) || exit;

/**
 * Class Menu
 */
class Menu {

	use Base;

	const SLUG = 'terax-ai-assistant';

	/**
	 * Hook.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'maybe_redirect_to_setup' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( TERAX_AI_PLUGIN_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Register pages. Sub-pages are hash routes of the React app.
	 *
	 * @return void
	 */
	public function menu() {
		$title = terax_ai_config( 'plugin' )['menu_title'];

		add_menu_page( $title, $title, 'manage_options', self::SLUG, array( $this, 'render' ), 'dashicons-format-chat', 58 );

		$base  = 'admin.php?page=' . self::SLUG;
		$pages = apply_filters(
			'terax_ai_submenu_pages',
			array(
				'dashboard'     => __( 'Dashboard', 'terax-ai-assistant' ),
				'settings'      => __( 'Settings', 'terax-ai-assistant' ),
				'knowledge'     => __( 'Knowledge', 'terax-ai-assistant' ),
				'conversations' => __( 'Conversations', 'terax-ai-assistant' ),
				'insights'      => __( 'Insights', 'terax-ai-assistant' ),
				'requests'      => __( 'Requests', 'terax-ai-assistant' ),
			)
		);

		$first = true;
		foreach ( $pages as $route => $label ) {
			add_submenu_page( self::SLUG, $label, $label, 'manage_options', $first ? self::SLUG : $base . '#/' . $route, $first ? array( $this, 'render' ) : null );
			$first = false;
		}
	}

	/**
	 * After a fresh activation, open the setup wizard once.
	 * Skipped for bulk activation, AJAX and users who cannot manage options.
	 *
	 * @return void
	 */
	public function maybe_redirect_to_setup() {
		if ( ! get_transient( 'terax_ai_redirect_to_setup' ) ) {
			return;
		}
		delete_transient( 'terax_ai_redirect_to_setup' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only reads core's own "activate-multi" flag.
		if ( wp_doing_ajax() || is_network_admin() || isset( $_GET['activate-multi'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '#/setup' ) );
		exit;
	}

	/**
	 * React mount point.
	 *
	 * @return void
	 */
	public function render() {
		echo '<div id="terax-ai-admin" class="terax-ai-admin"></div>';
	}

	/**
	 * "Settings" link on the Plugins screen.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::SLUG . '#/settings' ) ) . '">' . esc_html__( 'Settings', 'terax-ai-assistant' ) . '</a>' );
		return $links;
	}
}
