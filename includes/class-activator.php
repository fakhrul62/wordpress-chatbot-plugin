<?php
/**
 * Activation tasks.
 *
 * @package WPAIChat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_AICHAT_Activator {
	public static function activate(): void {
		if ( version_compare( get_bloginfo( 'version' ), WP_AICHAT_MIN_WP, '<' ) || version_compare( PHP_VERSION, WP_AICHAT_MIN_PHP, '<' ) ) {
			deactivate_plugins( plugin_basename( WP_AICHAT_FILE ) );
			wp_die(
				esc_html(
					sprintf(
						/* translators: 1: WordPress version, 2: PHP version. */
						__( 'WP AI Chat requires WordPress %1$s or higher and PHP %2$s or higher.', 'wp-aichat' ),
						WP_AICHAT_MIN_WP,
						WP_AICHAT_MIN_PHP
					)
				),
				esc_html__( 'Plugin activation failed', 'wp-aichat' ),
				array( 'back_link' => true )
			);
		}

		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		self::install_tables();
		WP_AICHAT_Settings::ensure_defaults();
		if ( ! wp_next_scheduled( 'wp_aichat_cleanup_cache' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'wp_aichat_cleanup_cache' );
		}
		update_option( 'wp_aichat_db_version', WP_AICHAT_VERSION, false );
	}

	public static function maybe_upgrade(): void {
		if ( get_option( 'wp_aichat_db_version', '' ) === WP_AICHAT_VERSION ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		self::install_tables();
		update_option( 'wp_aichat_db_version', WP_AICHAT_VERSION, false );
	}

	private static function install_tables(): void {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		$knowledge_table = $wpdb->prefix . 'aichat_knowledge';
		$cache_table     = $wpdb->prefix . 'aichat_cache';
		$embeddings_table = $wpdb->prefix . 'aichat_embeddings';

		$sql_knowledge = "CREATE TABLE {$knowledge_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			type VARCHAR(50) NOT NULL,
			object_id BIGINT UNSIGNED NOT NULL,
			title TEXT NOT NULL,
			content LONGTEXT NOT NULL,
			is_stale TINYINT(1) DEFAULT 0,
			last_synced DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY object_id (object_id),
			KEY type (type),
			KEY is_stale (is_stale)
		) {$charset_collate};";

		$sql_cache = "CREATE TABLE {$cache_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			question_hash CHAR(64) NOT NULL,
			response LONGTEXT NOT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY question_hash (question_hash),
			KEY created_at (created_at)
		) {$charset_collate};";

		$sql_embeddings = "CREATE TABLE {$embeddings_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			knowledge_id BIGINT UNSIGNED NOT NULL,
			chunk_text LONGTEXT NOT NULL,
			vector LONGTEXT NOT NULL,
			dims SMALLINT UNSIGNED NOT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY knowledge_id (knowledge_id),
			KEY dims (dims)
		) {$charset_collate};";

		dbDelta( $sql_knowledge );
		dbDelta( $sql_cache );
		dbDelta( $sql_embeddings );
	}
}
