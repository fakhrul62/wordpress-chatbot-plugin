<?php
/**
 * Knowledge table CRUD.
 *
 * @package WPAIChat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_AICHAT_Knowledge {
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'aichat_knowledge';
	}

	public static function upsert( string $type, int $object_id, string $title, string $content ): bool {
		global $wpdb;
		$table    = self::table();
		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE object_id = %d AND type = %s LIMIT 1", $object_id, $type ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$content  = self::clean_content( $content );
		$data     = array(
			'type'        => sanitize_key( $type ),
			'object_id'   => $object_id,
			'title'       => sanitize_text_field( $title ),
			'content'     => $content,
			'is_stale'    => 0,
			'last_synced' => current_time( 'mysql' ),
		);

		if ( $existing ) {
			return false !== $wpdb->update( $table, $data, array( 'id' => absint( $existing ) ), array( '%s', '%d', '%s', '%s', '%d', '%s' ), array( '%d' ) );
		}

		return false !== $wpdb->insert( $table, $data, array( '%s', '%d', '%s', '%s', '%d', '%s' ) );
	}

	public static function list( int $page = 1, int $per_page = 20 ): array {
		global $wpdb;
		$table  = self::table();
		$offset = max( 0, ( $page - 1 ) * $per_page );
		$items  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY last_synced DESC, id DESC LIMIT %d OFFSET %d", $per_page, $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array(
			'items'       => array_map( array( __CLASS__, 'format_item' ), $items ?: array() ),
			'total'       => $total,
			'total_pages' => (int) ceil( $total / $per_page ),
			'page'        => $page,
			'per_page'    => $per_page,
		);
	}

	public static function get( int $id ): ?array {
		global $wpdb;
		$table = self::table();
		$item  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $item ? self::format_item( $item ) : null;
	}

	public static function update_content( int $id, string $content ): bool {
		global $wpdb;
		$content = self::clean_content( $content );
		return false !== $wpdb->update(
			self::table(),
			array(
				'content'     => $content,
				'is_stale'    => 0,
				'last_synced' => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);
	}

	public static function delete( int $id ): bool {
		global $wpdb;
		return false !== $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
	}

	public static function mark_stale( int $object_id ): void {
		global $wpdb;
		$wpdb->update( self::table(), array( 'is_stale' => 1 ), array( 'object_id' => $object_id ), array( '%d' ), array( '%d' ) );
	}

	public static function all_content(): array {
		global $wpdb;
		$table = self::table();
		$rows  = $wpdb->get_results( "SELECT title, content FROM {$table} WHERE is_stale = 0 ORDER BY last_synced DESC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array_map(
			static function ( array $row ): array {
				$row['content'] = self::clean_content( (string) $row['content'] );
				return $row;
			},
			$rows ?: array()
		);
	}

	public static function best_chunks( string $query, int $limit = 5 ): array {
		$rows  = self::all_content();
		$terms = preg_split( '/\s+/', strtolower( sanitize_text_field( $query ) ) );
		$terms = array_filter( array_unique( $terms ?: array() ), static fn( $term ) => strlen( $term ) > 2 );
		foreach ( $rows as &$row ) {
			$haystack      = strtolower( $row['title'] . ' ' . $row['content'] );
			$row['_score'] = 0;
			foreach ( $terms as $term ) {
				$row['_score'] += substr_count( $haystack, $term );
			}
		}
		unset( $row );
		usort( $rows, static fn( $a, $b ) => $b['_score'] <=> $a['_score'] );
		$rows = array_slice( $rows, 0, $limit );
		return array_map(
			static function ( array $row ): string {
				return trim( $row['title'] . "\n" . $row['content'] );
			},
			$rows
		);
	}

	public static function total_chars(): int {
		global $wpdb;
		$table = self::table();
		return (int) $wpdb->get_var( "SELECT COALESCE(SUM(CHAR_LENGTH(content)), 0) FROM {$table} WHERE is_stale = 0" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	private static function format_item( array $item ): array {
		return array(
			'id'          => absint( $item['id'] ),
			'type'        => sanitize_key( $item['type'] ),
			'object_id'   => absint( $item['object_id'] ),
			'title'       => (string) $item['title'],
			'content'     => (string) $item['content'],
			'is_stale'    => (bool) $item['is_stale'],
			'last_synced' => (string) $item['last_synced'],
		);
	}

	private static function clean_content( string $content ): string {
		if ( class_exists( 'WP_AICHAT_Crawler' ) ) {
			return WP_AICHAT_Crawler::clean_content_text( $content );
		}

		$content = wp_strip_all_tags( $content, true );
		$content = html_entity_decode( $content, ENT_QUOTES, get_option( 'blog_charset', 'UTF-8' ) );
		$content = preg_replace( '/\s+/', ' ', (string) $content );
		return trim( (string) $content );
	}
}
