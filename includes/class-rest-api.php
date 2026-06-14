<?php
/**
 * REST API routes.
 *
 * @package WPAIChat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_AICHAT_REST_API {
	public static function register_routes(): void {
		register_rest_route( 'wp-aichat/v1', '/crawl/start', array( 'methods' => 'POST', 'callback' => array( 'WP_AICHAT_Crawler', 'stream_crawl' ), 'permission_callback' => array( __CLASS__, 'can_manage' ) ) );
		register_rest_route( 'wp-aichat/v1', '/knowledge', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'get_knowledge' ), 'permission_callback' => array( __CLASS__, 'can_manage' ) ) );
		register_rest_route( 'wp-aichat/v1', '/knowledge', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'add_knowledge' ), 'permission_callback' => array( __CLASS__, 'can_manage' ) ) );
		register_rest_route( 'wp-aichat/v1', '/knowledge/(?P<id>\d+)', array( 'methods' => 'PUT', 'callback' => array( __CLASS__, 'update_knowledge' ), 'permission_callback' => array( __CLASS__, 'can_manage' ) ) );
		register_rest_route( 'wp-aichat/v1', '/knowledge/(?P<id>\d+)', array( 'methods' => 'DELETE', 'callback' => array( __CLASS__, 'delete_knowledge' ), 'permission_callback' => array( __CLASS__, 'can_manage' ) ) );
		register_rest_route( 'wp-aichat/v1', '/knowledge/(?P<id>\d+)/resync', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'resync_knowledge' ), 'permission_callback' => array( __CLASS__, 'can_manage' ) ) );
		register_rest_route( 'wp-aichat/v1', '/settings', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'get_settings' ), 'permission_callback' => array( __CLASS__, 'can_manage' ) ) );
		register_rest_route( 'wp-aichat/v1', '/settings', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'save_settings' ), 'permission_callback' => array( __CLASS__, 'can_manage' ) ) );
		register_rest_route( 'wp-aichat/v1', '/test-connection', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'test_connection' ), 'permission_callback' => array( __CLASS__, 'can_manage' ) ) );
		register_rest_route( 'wp-aichat/v1', '/test-fallbacks', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'test_fallbacks' ), 'permission_callback' => array( __CLASS__, 'can_manage' ) ) );
		// Public by design: this endpoint accepts text only, never accepts URLs or provider endpoints, rate-limits by IP, and sends requests only to fixed provider URLs.
		register_rest_route( 'wp-aichat/v1', '/chat', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'chat' ), 'permission_callback' => '__return_true' ) );
	}

	public static function can_manage( WP_REST_Request $request ): bool {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		return current_user_can( 'manage_options' ) && wp_verify_nonce( $nonce, 'wp_rest' );
	}

	public static function get_knowledge( WP_REST_Request $request ): WP_REST_Response {
		return rest_ensure_response( WP_AICHAT_Knowledge::list( max( 1, absint( $request['page'] ?: 1 ) ), min( 100, max( 1, absint( $request['per_page'] ?: 20 ) ) ) ) );
	}

	public static function add_knowledge( WP_REST_Request $request ) {
		$title   = sanitize_text_field( (string) $request->get_param( 'title' ) );
		$content = wp_kses_post( (string) $request->get_param( 'content' ) );
		if ( ! WP_AICHAT_Knowledge::add_custom( $title, $content ) ) {
			return new WP_Error( 'create_failed', __( 'Could not add knowledge item.', 'wp-aichat' ), array( 'status' => 400 ) );
		}
		return rest_ensure_response( array( 'success' => true ) );
	}

	public static function update_knowledge( WP_REST_Request $request ) {
		$id      = absint( $request['id'] );
		$content = wp_kses_post( (string) $request->get_param( 'content' ) );
		if ( ! WP_AICHAT_Knowledge::update_content( $id, $content ) ) {
			return new WP_Error( 'update_failed', __( 'Could not update knowledge item.', 'wp-aichat' ), array( 'status' => 500 ) );
		}
		return rest_ensure_response( array( 'success' => true, 'item' => WP_AICHAT_Knowledge::get( $id ) ) );
	}

	public static function delete_knowledge( WP_REST_Request $request ) {
		if ( ! WP_AICHAT_Knowledge::delete( absint( $request['id'] ) ) ) {
			return new WP_Error( 'delete_failed', __( 'Could not delete knowledge item.', 'wp-aichat' ), array( 'status' => 500 ) );
		}
		return rest_ensure_response( array( 'success' => true ) );
	}

	public static function resync_knowledge( WP_REST_Request $request ) {
		$item = WP_AICHAT_Knowledge::get( absint( $request['id'] ) );
		if ( ! $item ) {
			return new WP_Error( 'not_found', __( 'Knowledge item not found.', 'wp-aichat' ), array( 'status' => 404 ) );
		}
		$post = get_post( $item['object_id'] );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return new WP_Error( 'source_missing', __( 'Original content is no longer published.', 'wp-aichat' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( WP_AICHAT_Crawler::crawl_post( $post ) );
	}

	public static function get_settings(): WP_REST_Response {
		$settings = WP_AICHAT_Settings::get_for_admin();
		$settings['system_prompt_preview'] = WP_AICHAT_Prompt_Builder::preview( $settings );
		return rest_ensure_response( $settings );
	}

	public static function save_settings( WP_REST_Request $request ): WP_REST_Response {
		$payload  = json_decode( $request->get_body(), true );
		$settings = WP_AICHAT_Settings::save( is_array( $payload ) ? $payload : array() );
		$safe_settings = WP_AICHAT_Settings::response_safe( $settings );
		$safe_settings['system_prompt_preview'] = WP_AICHAT_Prompt_Builder::preview( $settings );
		return rest_ensure_response( array( 'success' => true, 'settings' => $safe_settings ) );
	}

	public static function test_connection( WP_REST_Request $request ) {
		$payload  = json_decode( $request->get_body(), true );
		$current  = WP_AICHAT_Settings::get();
		$settings = is_array( $payload ) ? WP_AICHAT_Settings::sanitize( array_merge( $current, $payload ) ) : $current;
		$result   = WP_AICHAT_AI_Provider::test_from_settings( $settings );

		if ( is_wp_error( $result ) ) {
			return new WP_Error( 'aichat_test_failed', $result->get_error_message(), array( 'status' => 400 ) );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => $result,
			)
		);
	}

	public static function test_fallbacks( WP_REST_Request $request ) {
		$payload  = json_decode( $request->get_body(), true );
		$current  = WP_AICHAT_Settings::get();
		$settings = is_array( $payload ) ? WP_AICHAT_Settings::sanitize( array_merge( $current, $payload ) ) : $current;
		$result   = WP_AICHAT_AI_Provider::test_fallbacks( $settings );
		return rest_ensure_response( array( 'success' => true, 'message' => $result ) );
	}

	public static function chat( WP_REST_Request $request ) {
		$limited = self::rate_limited();
		if ( is_wp_error( $limited ) ) {
			return $limited;
		}

		$payload = json_decode( $request->get_body(), true );
		$message = isset( $payload['message'] ) ? sanitize_text_field( (string) $payload['message'] ) : '';
		if ( '' === $message || strlen( $message ) > 1000 ) {
			return new WP_Error( 'invalid_message', __( 'Message must be between 1 and 1000 characters.', 'wp-aichat' ), array( 'status' => 400 ) );
		}

		$settings = WP_AICHAT_Settings::get();
		$hash     = WP_AICHAT_Cache::hash( $message );
		if ( $settings['cache_enabled'] ) {
			$cached = WP_AICHAT_Cache::get( $hash, (int) $settings['cache_ttl_hours'] );
			if ( null !== $cached ) {
				return rest_ensure_response( array( 'response' => $cached, 'cached' => true ) );
			}
		}

		$knowledge = self::knowledge_for_message( $message );
		$prompt    = WP_AICHAT_Prompt_Builder::build( $settings, $knowledge );
		$contents  = self::sanitize_conversation( isset( $payload['conversation'] ) && is_array( $payload['conversation'] ) ? $payload['conversation'] : array() );
		$contents[] = array( 'role' => 'user', 'parts' => array( array( 'text' => $message ) ) );

		$result = WP_AICHAT_AI_Provider::generate_from_settings( $settings, $prompt, $contents, $knowledge );
		if ( is_wp_error( $result ) ) {
			return new WP_Error( 'aichat_ai_failed', $result->get_error_message(), array( 'status' => 500 ) );
		}

		if ( $settings['cache_enabled'] ) {
			WP_AICHAT_Cache::set( $hash, $result );
		}
		return rest_ensure_response( array( 'response' => $result ) );
	}

	private static function sanitize_conversation( array $conversation ): array {
		$items = array_slice( $conversation, -10 );
		$out   = array();
		foreach ( $items as $item ) {
			$role = isset( $item['role'] ) && 'model' === $item['role'] ? 'model' : 'user';
			$text = isset( $item['text'] ) ? sanitize_text_field( (string) $item['text'] ) : '';
			if ( '' !== $text ) {
				$out[] = array( 'role' => $role, 'parts' => array( array( 'text' => self::limit_text( $text, 1000 ) ) ) );
			}
		}
		return $out;
	}

	private static function limit_text( string $text, int $limit ): string {
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $limit ) : substr( $text, 0, $limit );
	}

	private static function knowledge_for_message( string $message ): string {
		if ( WP_AICHAT_Knowledge::total_chars() < 32000 ) {
			$chunks = array_map( static fn( $row ) => trim( $row['title'] . "\n" . $row['content'] ), WP_AICHAT_Knowledge::all_content() );
		} else {
			$chunks = WP_AICHAT_Knowledge::best_chunks( $message, 5 );
		}
		return self::fit_knowledge_budget( implode( "\n\n---\n\n", array_filter( $chunks ) ), 9000 );
	}

	private static function fit_knowledge_budget( string $knowledge, int $max_tokens ): string {
		$max_chars = $max_tokens * 4;
		if ( strlen( $knowledge ) <= $max_chars ) {
			return $knowledge;
		}
		return rtrim( substr( $knowledge, 0, $max_chars ) ) . "\n\n[Knowledge truncated to fit the configured token budget.]";
	}

	private static function rate_limited() {
		$ip   = self::request_ip();
		$key  = 'wp_aichat_rl_' . md5( $ip );
		$hits = (int) get_transient( $key );
		if ( $hits >= 20 ) {
			return new WP_Error( 'rate_limited', __( 'Too many chat requests. Please wait a minute and try again.', 'wp-aichat' ), array( 'status' => 429 ) );
		}
		set_transient( $key, $hits + 1, MINUTE_IN_SECONDS );
		return true;
	}

	private static function request_ip(): string {
		$candidates = array();
		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			$candidates[] = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
		}
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$forwarded = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			$candidates[] = trim( (string) $forwarded[0] );
		}
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$candidates[] = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		foreach ( $candidates as $candidate ) {
			if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
				return $candidate;
			}
		}
		return 'unknown';
	}
}
