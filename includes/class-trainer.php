<?php
/**
 * Site training orchestration.
 *
 * @package WPAIChat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_AICHAT_Trainer {
	public static function stream_train(): void {
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
		$rows     = WP_AICHAT_Knowledge::rows_for_training();
		if ( empty( $rows ) ) {
			self::send_event( array( 'status' => 'error', 'message' => __( 'No knowledge content found. Run a crawl or add custom knowledge first.', 'wp-aichat' ), 'progress' => 100 ) );
			exit;
		}

		self::send_event( array( 'status' => 'started', 'message' => __( 'Training started.', 'wp-aichat' ), 'progress' => 0 ) );
		$knowledge_text = self::training_text( $rows );
		self::send_event( array( 'status' => 'summary_started', 'message' => __( 'Creating site summary.', 'wp-aichat' ), 'progress' => 10 ) );

		$summary = self::generate_summary( $settings, $knowledge_text );
		if ( is_wp_error( $summary ) ) {
			self::send_event( array( 'status' => 'error', 'message' => $summary->get_error_message(), 'progress' => 100 ) );
			exit;
		}
		$summary_id = WP_AICHAT_Knowledge::upsert_summary( $summary );
		self::send_event( array( 'status' => 'summary_complete', 'message' => __( 'Site summary saved to the knowledge base.', 'wp-aichat' ), 'summary_id' => $summary_id, 'progress' => 35 ) );

		if ( empty( $settings['openai_api_key'] ) ) {
			self::send_event( array( 'status' => 'embeddings_skipped', 'message' => __( 'Embeddings skipped because no OpenAI API key is configured.', 'wp-aichat' ), 'progress' => 100 ) );
			self::send_event( array( 'status' => 'complete', 'message' => __( 'Training complete. Summary is now part of chat context.', 'wp-aichat' ), 'summary_id' => $summary_id, 'embeddings' => 0, 'progress' => 100 ) );
			exit;
		}

		$embedding_items = array();
		$training_rows   = WP_AICHAT_Knowledge::rows_for_training();
		$chunks          = self::chunks_from_rows( $training_rows );
		$total           = max( 1, count( $chunks ) );
		$done            = 0;
		self::send_event( array( 'status' => 'embeddings_started', 'message' => __( 'Creating semantic search embeddings.', 'wp-aichat' ), 'total' => $total, 'progress' => 40 ) );

		foreach ( $chunks as $chunk ) {
			$done++;
			$vector = WP_AICHAT_AI_Provider::embedding_from_settings( $settings, $chunk['text'] );
			if ( is_wp_error( $vector ) ) {
				self::send_event( array( 'status' => 'embedding_error', 'message' => $vector->get_error_message(), 'progress' => min( 95, 40 + (int) round( ( $done / $total ) * 55 ) ) ) );
				continue;
			}
			$embedding_items[] = array(
				'knowledge_id' => $chunk['knowledge_id'],
				'chunk_text'   => $chunk['text'],
				'vector'       => $vector,
			);
			self::send_event( array( 'status' => 'embedding_progress', 'message' => sprintf( __( 'Embedded chunk %1$d of %2$d.', 'wp-aichat' ), $done, $total ), 'progress' => min( 95, 40 + (int) round( ( $done / $total ) * 55 ) ) ) );
		}

		$stored = empty( $embedding_items ) ? 0 : WP_AICHAT_Knowledge::replace_embeddings( $embedding_items );
		self::send_event( array( 'status' => 'complete', 'message' => __( 'Training complete. Summary and semantic search are ready.', 'wp-aichat' ), 'summary_id' => $summary_id, 'embeddings' => $stored, 'progress' => 100 ) );
		exit;
	}

	private static function generate_summary( array $settings, string $knowledge_text ) {
		$prompt = 'You summarize WordPress site knowledge for a chatbot. Write as the site owner using "we" and "our".';
		$message = "Summarize this site's purpose, services, products, and key facts in 500 words or less. Use only the supplied content. Avoid invented details.\n\n" . self::limit_text( $knowledge_text, 24000 );
		$result = WP_AICHAT_AI_Provider::generate_from_settings(
			$settings,
			$prompt,
			array(
				array(
					'role'  => 'user',
					'parts' => array( array( 'text' => $message ) ),
				),
			),
			''
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$result = trim( wp_strip_all_tags( (string) $result ) );
		if ( '' === $result || str_contains( strtolower( $result ), 'free ai service is busy' ) ) {
			return new WP_Error( 'aichat_summary_failed', __( 'Could not generate a useful site summary. Check the AI provider connection and try again.', 'wp-aichat' ) );
		}
		return $result;
	}

	private static function training_text( array $rows ): string {
		$parts = array();
		foreach ( $rows as $row ) {
			$parts[] = trim( (string) $row['title'] . "\n" . (string) $row['content'] );
		}
		return implode( "\n\n---\n\n", array_filter( $parts ) );
	}

	private static function chunks_from_rows( array $rows ): array {
		$chunks = array();
		foreach ( $rows as $row ) {
			$text = trim( (string) $row['title'] . "\n" . (string) $row['content'] );
			foreach ( self::chunk_text( $text, 500 ) as $chunk ) {
				$chunks[] = array(
					'knowledge_id' => absint( $row['id'] ),
					'text'         => $chunk,
				);
			}
		}
		return $chunks;
	}

	private static function chunk_text( string $text, int $size ): array {
		$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $text ) ) );
		if ( '' === $text ) {
			return array();
		}
		$chunks = array();
		$length = strlen( $text );
		for ( $offset = 0; $offset < $length; $offset += $size ) {
			$chunk = trim( substr( $text, $offset, $size ) );
			if ( strlen( $chunk ) >= 40 ) {
				$chunks[] = $chunk;
			}
		}
		return $chunks;
	}

	private static function limit_text( string $text, int $limit ): string {
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $limit ) : substr( $text, 0, $limit );
	}

	private static function send_event( array $data ): void {
		echo 'data: ' . wp_json_encode( $data ) . "\n\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( function_exists( 'ob_flush' ) ) {
			ob_flush();
		}
		flush();
	}
}
