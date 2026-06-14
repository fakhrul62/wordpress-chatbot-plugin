<?php
/**
 * Response cache CRUD.
 *
 * @package WPAIChat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_AICHAT_Cache {
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'aichat_cache';
	}

	public static function hash( string $question ): string {
		return hash( 'sha256', self::version() . '|' . self::normalize_question( $question ) );
	}

	public static function bump_version(): void {
		update_option( 'wp_aichat_cache_version', (string) time(), false );
	}

	public static function get( string $hash, int $ttl_hours ): ?string {
		global $wpdb;
		$table    = self::table();
		$min_time = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( max( 1, $ttl_hours ) * HOUR_IN_SECONDS ) );
		$row      = $wpdb->get_row( $wpdb->prepare( "SELECT response FROM {$table} WHERE question_hash = %s AND created_at >= %s LIMIT 1", $hash, $min_time ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $row ? (string) $row['response'] : null;
	}

	public static function set( string $hash, string $response ): bool {
		global $wpdb;
		$table = self::table();
		$old   = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE question_hash = %s LIMIT 1", $hash ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$data  = array(
			'question_hash' => $hash,
			'response'      => wp_kses_post( $response ),
			'created_at'    => current_time( 'mysql' ),
		);
		if ( $old ) {
			return false !== $wpdb->update( $table, $data, array( 'id' => absint( $old ) ), array( '%s', '%s', '%s' ), array( '%d' ) );
		}
		return false !== $wpdb->insert( $table, $data, array( '%s', '%s', '%s' ) );
	}

	public static function cleanup_expired(): void {
		global $wpdb;
		$settings = WP_AICHAT_Settings::get();
		$ttl_hours = max( 1, absint( $settings['cache_ttl_hours'] ?? 24 ) );
		$cutoff = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( $ttl_hours * HOUR_IN_SECONDS ) );
		$table = self::table();
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cutoff ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	private static function version(): string {
		$version = get_option( 'wp_aichat_cache_version', 'v7' );
		return is_string( $version ) && '' !== $version ? $version : 'v7';
	}

	private static function normalize_question( string $question ): string {
		$question = strtolower( wp_strip_all_tags( $question ) );
		$question = preg_replace( '/[^a-z0-9]+/i', ' ', (string) $question );
		$question = preg_replace( '/\b(?:what|is|are|s|tell|me|can|please|the|your|you|a|an)\b/i', ' ', (string) $question );
		$question = preg_replace( '/\b(?:price|prices|pricing|cost|costs|fee|fees)\b/i', 'price', (string) $question );
		$question = preg_replace( '/\b(?:contact|call|phone|email|reach)\b/i', 'contact', (string) $question );
		$question = preg_replace( '/\b(?:services|service|offer|offers)\b/i', 'service', (string) $question );
		return trim( preg_replace( '/\s+/', ' ', (string) $question ) );
	}
}
