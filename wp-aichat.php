<?php
/**
 * Plugin Name: WP AI Chat
 * Description: Site-aware online AI chat widget with a WordPress knowledge base.
 * Version: 1.0.22
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Fakhrul Alam
 * Text Domain: wp-aichat
 *
 * @package WPAIChat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WP_AICHAT_VERSION', '1.0.22' );
define( 'WP_AICHAT_MIN_WP', '6.0' );
define( 'WP_AICHAT_MIN_PHP', '8.0' );
define( 'WP_AICHAT_FILE', __FILE__ );
define( 'WP_AICHAT_PATH', plugin_dir_path( __FILE__ ) );
define( 'WP_AICHAT_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_AICHAT_OPTION', 'wp_aichat_settings' );

require_once WP_AICHAT_PATH . 'includes/class-settings.php';
require_once WP_AICHAT_PATH . 'includes/class-activator.php';
require_once WP_AICHAT_PATH . 'includes/class-deactivator.php';
require_once WP_AICHAT_PATH . 'includes/class-knowledge.php';
require_once WP_AICHAT_PATH . 'includes/class-cache.php';
require_once WP_AICHAT_PATH . 'includes/class-crawler.php';
require_once WP_AICHAT_PATH . 'includes/class-trainer.php';
require_once WP_AICHAT_PATH . 'includes/class-ai-provider.php';
require_once WP_AICHAT_PATH . 'includes/class-prompt-builder.php';
require_once WP_AICHAT_PATH . 'includes/class-rest-api.php';
require_once WP_AICHAT_PATH . 'admin/class-admin.php';
require_once WP_AICHAT_PATH . 'public/class-widget.php';

register_activation_hook( __FILE__, array( 'WP_AICHAT_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WP_AICHAT_Deactivator', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		load_plugin_textdomain( 'wp-aichat', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		WP_AICHAT_Settings::ensure_defaults();
		WP_AICHAT_Activator::maybe_upgrade();
		if ( ! wp_next_scheduled( 'wp_aichat_cleanup_cache' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'wp_aichat_cleanup_cache' );
		}
	}
);

add_action(
	'init',
	static function () {
		add_shortcode( 'wp_aichat', array( 'WP_AICHAT_Widget', 'shortcode' ) );
	}
);

add_action( 'rest_api_init', array( 'WP_AICHAT_REST_API', 'register_routes' ) );
add_action( 'admin_menu', array( 'WP_AICHAT_Admin', 'register_menu' ) );
add_action( 'admin_enqueue_scripts', array( 'WP_AICHAT_Admin', 'enqueue_assets' ) );
add_action( 'wp_enqueue_scripts', array( 'WP_AICHAT_Widget', 'register_assets' ) );
add_action( 'wp_footer', array( 'WP_AICHAT_Widget', 'render_floating_widget' ) );
add_action( 'save_post', array( 'WP_AICHAT_Crawler', 'mark_stale_on_save' ), 10, 3 );
add_action( 'wp_aichat_cleanup_cache', array( 'WP_AICHAT_Cache', 'cleanup_expired' ) );

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	static function ( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=wp-aichat-ai-config' ) ),
			esc_html__( 'Settings', 'wp-aichat' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}
);
