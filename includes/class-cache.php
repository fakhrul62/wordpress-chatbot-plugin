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
		return hash( 'sha256', 'v6|' . trim( strtolower( $question ) ) );
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
}
