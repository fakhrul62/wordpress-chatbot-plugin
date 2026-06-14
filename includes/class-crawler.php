<?php
/**
 * Site crawler.
 *
 * @package WPAIChat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_AICHAT_Crawler {
	public static function posts(): array {
		return get_posts(
			array(
				'post_type'      => array( 'page', 'post' ),
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'fields'         => 'all',
			)
		);
	}

	public static function extract( WP_Post $post ): string {
		$content = strip_shortcodes( $post->post_content );
		$content = self::clean_content_text( $content );
		return function_exists( 'mb_substr' ) ? mb_substr( $content, 0, 2000 ) : substr( $content, 0, 2000 );
	}

	public static function clean_content_text( string $content ): string {
		$content = wp_strip_all_tags( $content, true );
		$content = html_entity_decode( $content, ENT_QUOTES, get_option( 'blog_charset', 'UTF-8' ) );
		$content = str_replace( array( "\r", "\n", "\t", '&nbsp;' ), ' ', $content );
		$content = preg_replace( '/\s+/', ' ', (string) $content );
		$content = preg_replace( '/(\+?\d[\d\s().-]{7,}\d)([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})/i', '$1 $2', (string) $content );
		$content = preg_replace( '/\b([A-Z][a-z]+(?:\s+[A-Z][a-z]+){0,3})(?=(?:[A-Z][a-z]{2,}|Blocked|Repair|Repairs|Installation|Cleaning|Services?|Courses?|Classes?|Programs?|Products?)\b)/', '$1. ', (string) $content );
		$content = preg_replace( '/(\d)\s*-\s*([AP]M)\b/i', '$1 $2', (string) $content );
		$content = preg_replace( '/\bto(?=\d)/i', 'to ', (string) $content );
		$content = preg_replace( '/\b(\d{1,2})\s*([AP]M)\s*to\s*(\d{1,2})\s*([AP]M)\b/i', '$1 $2 to $3 $4', (string) $content );
		$content = preg_replace( '/\s+([,.;:!?])/', '$1', (string) $content );
		$content = preg_replace( '/([.!?]){2,}/', '$1', (string) $content );
		$content = preg_replace( '/\b([A-Za-z][A-Za-z ]{3,80})\s+\1\b/i', '$1', (string) $content );
		$content = self::remove_duplicate_sentences( (string) $content );
		$content = preg_replace( '/\s+/', ' ', (string) $content );
		return trim( (string) $content );
	}

	private static function remove_duplicate_sentences( string $content ): string {
		$sentences = preg_split( '/(?<=[.!?])\s+/', $content ) ?: array();
		$seen      = array();
		$clean     = array();

		foreach ( $sentences as $sentence ) {
			$sentence = trim( (string) $sentence );
			if ( '' === $sentence ) {
				continue;
			}
			$key = strtolower( preg_replace( '/[^a-z0-9]+/i', '', $sentence ) );
			if ( '' !== $key && isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$clean[]      = $sentence;
		}

		return implode( ' ', $clean );
	}

	public static function crawl_post( WP_Post $post ): array {
		$type    = 'page' === $post->post_type ? 'page' : 'post';
		$title   = get_the_title( $post );
		$content = self::extract( $post );
		if ( '' === $content ) {
			return array(
				'type'              => $type,
				'object_id'         => $post->ID,
				'title'             => $title,
				'status'            => 'skipped',
				'extracted_content' => '',
			);
		}
		WP_AICHAT_Knowledge::upsert( $type, $post->ID, $title, $content );
		return array(
			'type'              => $type,
			'object_id'         => $post->ID,
			'title'             => $title,
			'status'            => 'extracted',
			'extracted_content' => $content,
		);
	}

	public static function stream_crawl(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			status_header( 403 );
			exit;
		}

		$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			status_header( 403 );
			exit;
		}

		nocache_headers();
		header( 'Content-Type: text/event-stream; charset=' . get_option( 'blog_charset' ) );
		header( 'X-Accel-Buffering: no' );

		$settings = WP_AICHAT_Settings::get();
		if ( class_exists( 'WooCommerce' ) ) {
			$shop_id              = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'shop' ) : 0;
			$settings['shop_url'] = $shop_id > 0 ? get_permalink( $shop_id ) : '';
			WP_AICHAT_Settings::save( $settings );
		}

		$posts = self::posts();
		$total = count( $posts );
		$count = 0;
		self::send_event( array( 'status' => 'started', 'total' => $total, 'progress' => 0 ) );

		foreach ( $posts as $post ) {
			$count++;
			$result             = self::crawl_post( $post );
			$result['progress'] = $total > 0 ? (int) round( ( $count / $total ) * 100 ) : 100;
			self::send_event( $result );
		}

		self::send_event( array( 'status' => 'complete', 'count' => $count, 'progress' => 100 ) );
		exit;
	}

	public static function mark_stale_on_save( int $post_id, WP_Post $post, bool $update ): void {
		if ( wp_is_post_revision( $post_id ) || ! $update || ! in_array( $post->post_type, array( 'page', 'post' ), true ) ) {
			return;
		}
		WP_AICHAT_Knowledge::mark_stale( $post_id );
	}

	private static function send_event( array $data ): void {
		echo 'data: ' . wp_json_encode( $data ) . "\n\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( function_exists( 'ob_flush' ) ) {
			ob_flush();
		}
		flush();
	}
}
