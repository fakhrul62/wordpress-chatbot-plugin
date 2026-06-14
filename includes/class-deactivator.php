<?php
/**
 * Deactivation tasks.
 *
 * @package WPAIChat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_AICHAT_Deactivator {
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'wp_aichat_cleanup_cache' );
	}
}
