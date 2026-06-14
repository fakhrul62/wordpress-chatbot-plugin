<?php
/**
 * Admin UI.
 *
 * @package WPAIChat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_AICHAT_Admin {
	public static function register_menu(): void {
		$icon = 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="black" stroke-width="1.8"><path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 9 9 0 0 1-3.8-.8L3 21l1.8-4.7A8.2 8.2 0 0 1 3 11.5C3 6.8 7 3 12 3s9 3.8 9 8.5Z"/></svg>' );
		add_menu_page( __( 'AI Chat', 'wp-aichat' ), __( 'AI Chat', 'wp-aichat' ), 'manage_options', 'wp-aichat', array( __CLASS__, 'render_crawl' ), $icon, 56 );
		add_submenu_page( 'wp-aichat', __( 'Knowledge Base', 'wp-aichat' ), __( 'Knowledge Base', 'wp-aichat' ), 'manage_options', 'wp-aichat', array( __CLASS__, 'render_crawl' ) );
		add_submenu_page( 'wp-aichat', __( 'AI Configuration', 'wp-aichat' ), __( 'AI Configuration', 'wp-aichat' ), 'manage_options', 'wp-aichat-ai-config', array( __CLASS__, 'render_ai_config' ) );
		add_submenu_page( 'wp-aichat', __( 'Widget Designer', 'wp-aichat' ), __( 'Widget Designer', 'wp-aichat' ), 'manage_options', 'wp-aichat-widget-designer', array( __CLASS__, 'render_widget_designer' ) );
		add_submenu_page( 'wp-aichat', __( 'Placement', 'wp-aichat' ), __( 'Placement', 'wp-aichat' ), 'manage_options', 'wp-aichat-placement', array( __CLASS__, 'render_placement' ) );
	}

	public static function enqueue_assets( string $hook ): void {
		if ( false === strpos( $hook, 'wp-aichat' ) && 'toplevel_page_wp-aichat' !== $hook ) {
			return;
		}
		wp_register_style( 'wp-aichat-admin', WP_AICHAT_URL . 'admin/assets/admin.css', array(), WP_AICHAT_VERSION );
		wp_register_script( 'wp-aichat-admin', WP_AICHAT_URL . 'admin/assets/admin.js', array(), WP_AICHAT_VERSION, true );
		wp_enqueue_style( 'wp-aichat-admin' );
		wp_enqueue_script( 'wp-aichat-admin' );
		wp_enqueue_media();
		wp_localize_script(
			'wp-aichat-admin',
			'WPAIChatAdmin',
			array(
				'restUrl'   => esc_url_raw( rest_url( 'wp-aichat/v1' ) ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'settings'  => WP_AICHAT_Settings::get(),
				'prompt'    => WP_AICHAT_Prompt_Builder::preview( WP_AICHAT_Settings::get() ),
				'chatIcon'  => self::icon_chat(),
				'xIcon'     => self::icon_x(),
				'sendIcon'  => self::icon_send(),
			)
		);
	}

	public static function render_crawl(): void { require WP_AICHAT_PATH . 'admin/views/page-crawl.php'; }
	public static function render_ai_config(): void { require WP_AICHAT_PATH . 'admin/views/page-ai-config.php'; }
	public static function render_widget_designer(): void { require WP_AICHAT_PATH . 'admin/views/page-widget-designer.php'; }
	public static function render_placement(): void { require WP_AICHAT_PATH . 'admin/views/page-placement.php'; }

	public static function icon_chat(): string {
		return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 9 9 0 0 1-3.8-.8L3 21l1.8-4.7A8.2 8.2 0 0 1 3 11.5C3 6.8 7 3 12 3s9 3.8 9 8.5Z"/></svg>';
	}
	public static function icon_x(): string { return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>'; }
	public static function icon_send(): string { return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4 20-7Z"/><path d="M22 2 11 13"/></svg>'; }
	public static function icon_eye(): string { return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>'; }
}
