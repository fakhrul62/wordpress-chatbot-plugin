<?php
/**
 * Online AI provider client.
 *
 * @package WPAIChat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_AICHAT_AI_Provider {
	public static function generate_from_settings( array $settings, string $prompt, array $contents, string $knowledge = '' ) {
		$message = self::latest_user_message( $contents );

		if ( ! empty( $settings['openai_api_key'] ) ) {
			$reliable = self::generate_openai( $settings, $prompt, $contents );
			if ( ! is_wp_error( $reliable ) ) {
				return $reliable;
			}
			self::record_failure( 'openai', $reliable, $settings );
		}

		$primary = self::generate_pollinations( $settings, $prompt, $contents );
		if ( ! is_wp_error( $primary ) ) {
			return $primary;
		}
		self::record_failure( 'pollinations', $primary, $settings );

		$fallback = self::generate_ovh( $prompt, $contents );
		if ( ! is_wp_error( $fallback ) ) {
			return $fallback;
		}
		self::record_failure( 'ovh', $fallback, $settings );

		return self::knowledge_fallback( $message, $knowledge );
	}

	public static function test_from_settings( array $settings ) {
		if ( empty( $settings['openai_api_key'] ) ) {
			return __( 'Free fallback is configured. Add an OpenAI API key for more reliable 24/7 answers.', 'wp-aichat' );
		}

		$result = self::generate_openai(
			$settings,
			'You are a test assistant. Reply with OK only.',
			array(
				array(
					'role'  => 'user',
					'parts' => array( array( 'text' => 'Reply with OK.' ) ),
				),
			)
		);

		return is_wp_error( $result ) ? $result : __( 'OpenAI connection successful. If it fails during chat, the free fallback will still be used.', 'wp-aichat' );
	}

	public static function test_fallbacks( array $settings ): string {
		$prompt = 'You are a test assistant. Reply with OK only.';
		$contents = array(
			array(
				'role'  => 'user',
				'parts' => array( array( 'text' => 'Reply with OK.' ) ),
			),
		);
		$results = array();
		$pollinations = self::generate_pollinations( $settings, $prompt, $contents, true );
		$results[] = is_wp_error( $pollinations ) ? 'Pollinations: ' . $pollinations->get_error_message() : 'Pollinations: OK';
		$ovh = self::generate_ovh( $prompt, $contents, true );
		$results[] = is_wp_error( $ovh ) ? 'OVH: ' . $ovh->get_error_message() : 'OVH: OK';
		return implode( "\n", $results );
	}

	private static function generate_openai( array $settings, string $prompt, array $contents ) {
		$api_key = trim( (string) ( $settings['openai_api_key'] ?? '' ) );
		if ( '' === $api_key ) {
			return new WP_Error( 'aichat_openai_missing_key', __( 'OpenAI API key is missing.', 'wp-aichat' ) );
		}

		$messages = array( array( 'role' => 'system', 'content' => $prompt ) );
		foreach ( $contents as $content ) {
			$role = isset( $content['role'] ) && 'model' === $content['role'] ? 'assistant' : 'user';
			$text = $content['parts'][0]['text'] ?? '';
			if ( '' !== $text ) {
				$messages[] = array( 'role' => $role, 'content' => $text );
			}
		}

		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'       => ! empty( $settings['openai_model'] ) ? $settings['openai_model'] : 'gpt-4o-mini',
						'messages'    => $messages,
						'max_tokens'  => 800,
						'temperature' => 0.3,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$json = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $code ) {
			$message = isset( $json['error']['message'] ) ? sanitize_text_field( $json['error']['message'] ) : sprintf( __( 'OpenAI request failed with HTTP %d.', 'wp-aichat' ), $code );
			return new WP_Error( 'aichat_openai_error', $message );
		}

		$text = $json['choices'][0]['message']['content'] ?? '';
		$text = self::clean_ai_answer( (string) $text );
		return '' !== $text ? $text : new WP_Error( 'aichat_openai_empty', __( 'OpenAI returned an empty response.', 'wp-aichat' ) );
	}

	private static function generate_pollinations( array $settings, string $prompt, array $contents, bool $force = false ) {
		if ( ! $force && self::provider_blocked( 'pollinations' ) ) {
			return new WP_Error( 'aichat_provider_blocked', __( 'Pollinations is temporarily skipped after repeated failures.', 'wp-aichat' ) );
		}
		$model    = ! empty( $settings['ai_model'] ) ? $settings['ai_model'] : 'openai-fast';
		$messages = array( array( 'role' => 'system', 'content' => $prompt ) );

		foreach ( $contents as $content ) {
			$role = isset( $content['role'] ) && 'model' === $content['role'] ? 'assistant' : 'user';
			$text = $content['parts'][0]['text'] ?? '';
			if ( '' !== $text ) {
				$messages[] = array( 'role' => $role, 'content' => $text );
			}
		}

		$response = wp_remote_post(
			'https://text.pollinations.ai/openai',
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'model'       => $model,
						'messages'    => $messages,
						'max_tokens'  => 700,
						'temperature' => 0.5,
						'safe'        => true,
						'stream'      => false,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$json = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $code ) {
			$message = isset( $json['error']['message'] ) ? sanitize_text_field( $json['error']['message'] ) : sprintf( __( 'The online AI request failed with HTTP %d. The free service may be temporarily rate limited.', 'wp-aichat' ), $code );
			return new WP_Error( 'aichat_online_ai_error', $message );
		}

		$text = $json['choices'][0]['message']['content'] ?? '';
		$text = self::clean_ai_answer( (string) $text );
		return '' !== $text ? $text : new WP_Error( 'aichat_online_ai_empty', __( 'The online AI service returned an empty response.', 'wp-aichat' ) );
	}

	private static function generate_ovh( string $prompt, array $contents, bool $force = false ) {
		if ( ! $force && self::provider_blocked( 'ovh' ) ) {
			return new WP_Error( 'aichat_provider_blocked', __( 'OVH fallback is temporarily skipped after repeated failures.', 'wp-aichat' ) );
		}
		$messages = array( array( 'role' => 'system', 'content' => $prompt ) );
		foreach ( $contents as $content ) {
			$role = isset( $content['role'] ) && 'model' === $content['role'] ? 'assistant' : 'user';
			$text = $content['parts'][0]['text'] ?? '';
			if ( '' !== $text ) {
				$messages[] = array( 'role' => $role, 'content' => $text );
			}
		}

		$response = wp_remote_post(
			'https://oai.endpoints.kepler.ai.cloud.ovh.net/v1/chat/completions',
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'model'       => 'Llama-3.1-8B-Instruct',
						'messages'    => $messages,
						'max_tokens'  => 700,
						'temperature' => 0.5,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$json = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $code ) {
			$message = isset( $json['error']['message'] ) ? sanitize_text_field( $json['error']['message'] ) : sprintf( __( 'The fallback online AI request failed with HTTP %d.', 'wp-aichat' ), $code );
			return new WP_Error( 'aichat_fallback_ai_error', $message );
		}

		$text = $json['choices'][0]['message']['content'] ?? '';
		$text = self::clean_ai_answer( (string) $text );
		return '' !== $text ? $text : new WP_Error( 'aichat_fallback_ai_empty', __( 'The fallback online AI service returned an empty response.', 'wp-aichat' ) );
	}

	private static function latest_user_message( array $contents ): string {
		for ( $i = count( $contents ) - 1; $i >= 0; $i-- ) {
			if ( isset( $contents[ $i ]['role'] ) && 'user' === $contents[ $i ]['role'] ) {
				return (string) ( $contents[ $i ]['parts'][0]['text'] ?? '' );
			}
		}
		return '';
	}

	private static function is_contact_question( string $message ): bool {
		$message = strtolower( $message );
		foreach ( array( 'contact', 'call', 'phone', 'number', 'email', 'address', 'location', 'reach', 'hours', 'open' ) as $term ) {
			if ( str_contains( $message, $term ) ) {
				return true;
			}
		}
		return false;
	}

	private static function is_services_question( string $message ): bool {
		$message = strtolower( $message );
		foreach ( array( 'service', 'services', 'offer', 'offers', 'do you do', 'what do you provide', 'what can you do' ) as $term ) {
			if ( str_contains( $message, $term ) ) {
				return true;
			}
		}
		return false;
	}

	private static function is_price_question( string $message ): bool {
		$message = strtolower( $message );
		foreach ( array( 'price', 'prices', 'pricing', 'cost', 'costs', 'fee', 'fees', 'charge', 'charges', 'rate', 'rates', 'how much', 'exact price', 'xact price' ) as $term ) {
			if ( str_contains( $message, $term ) ) {
				return true;
			}
		}
		return false;
	}

	private static function price_answer( string $knowledge ): string {
		$text = self::normalize_text( $knowledge );
		if ( '' === $text ) {
			return __( 'I could not find pricing details in the site knowledge base yet. Please run a crawl first.', 'wp-aichat' );
		}

		preg_match_all( '/(?:৳|Tk\.?|BDT|\$|USD|€|£)\s?\d[\d,]*(?:\.\d{1,2})?|\d[\d,]*(?:\.\d{1,2})?\s?(?:৳|Tk\.?|BDT|USD|dollars?|taka)/i', $text, $price_matches, PREG_OFFSET_CAPTURE );
		$items = array();

		foreach ( $price_matches[0] ?? array() as $match ) {
			$price = trim( (string) $match[0] );
			$offset = (int) $match[1];
			$context = self::price_context( $text, $offset );
			if ( self::is_noisy_price_context( $context ) ) {
				continue;
			}
			$label = self::price_label( $context, $price );
			$key = strtolower( $label . '|' . $price );
			$items[ $key ] = array(
				'label' => $label,
				'price' => $price,
			);
		}

		if ( empty( $items ) ) {
			return __( 'I could not find an exact price in the site knowledge base. Please contact the site directly for accurate pricing.', 'wp-aichat' );
		}

		$lines = array();
		foreach ( array_slice( array_values( $items ), 0, 6 ) as $item ) {
			$lines[] = '- ' . $item['label'] . ': ' . $item['price'];
		}

		return "I found these exact prices:\n\n" . implode( "\n", $lines );
	}

	private static function price_context( string $text, int $offset ): string {
		$start = max( 0, $offset - 90 );
		$chunk = substr( $text, $start, 190 );
		return trim( preg_replace( '/\s+/', ' ', (string) $chunk ) );
	}

	private static function is_noisy_price_context( string $context ): bool {
		return (bool) preg_match( '/reasonable|competitive|affordable|cheap|best price|fair price|estimate only/i', $context );
	}

	private static function price_label( string $context, string $price ): string {
		$before = trim( substr( $context, 0, max( 0, strpos( $context, $price ) ?: 0 ) ) );
		$before = preg_replace( '/\b(?:Starting From|Starts From|From|Price|Cost|Only|Claim offer|Request Job Estimate)\b/i', ' ', (string) $before );
		$before = preg_replace( '/[^A-Za-z0-9& ]+/', ' ', (string) $before );
		$before = trim( preg_replace( '/\s+/', ' ', (string) $before ) );
		$words = preg_split( '/\s+/', $before ) ?: array();
		$words = array_slice( $words, -5 );
		$label = trim( implode( ' ', $words ) );
		if ( '' === $label || preg_match( '/^(home|services|our|popular|starting)$/i', $label ) ) {
			return __( 'Listed price', 'wp-aichat' );
		}
		return self::title_case_label( $label );
	}

	private static function title_case_label( string $label ): string {
		$label = strtolower( $label );
		return ucwords( $label );
	}

	private static function services_answer( string $knowledge ): string {
		$text = self::normalize_text( $knowledge );
		if ( '' === $text ) {
			return __( 'I could not find service details in the site knowledge base yet. Please run a crawl first.', 'wp-aichat' );
		}

		$service_names = self::extract_service_names( $text );
		if ( empty( $service_names ) ) {
			return __( 'I could not find a clean services list in the site knowledge base. Please check the Services page for the latest details.', 'wp-aichat' );
		}

		$lines = array_map(
			static fn( $service ) => '- ' . $service,
			array_slice( $service_names, 0, 10 )
		);

		return "They offer these services:\n\n" . implode( "\n", $lines );
	}

	private static function extract_service_names( string $text ): array {
		$section = self::extract_between_labels(
			$text,
			array( 'Our Popular Services', 'Popular Services', 'Our Services', 'Services', 'Products', 'Courses', 'Programs', 'Offerings' ),
			array( 'Projects', 'Portfolio', 'Testimonials', 'Reviews', 'Contact', 'Address', 'Hours', 'About', 'Blog', 'News' )
		);
		if ( '' === $section ) {
			return array();
		}

		$section = preg_replace( '/\b(?:View all|Learn more|Read more|Claim offer|Request estimate|Contact us)\b/i', '. ', (string) $section );
		preg_match_all( '/(?:^|[.;:])\s*([A-Z][A-Za-z0-9&+\-\/ ]{3,60})(?=(?:[.;:]|$))/', $section, $matches );
		$candidates = array_map( 'trim', $matches[1] ?? array() );
		$services = array();
		foreach ( $candidates as $candidate ) {
			$candidate = trim( preg_replace( '/\s+/', ' ', $candidate ) );
			if ( strlen( $candidate ) < 4 || str_word_count( $candidate ) > 8 ) {
				continue;
			}
			if ( preg_match( '/testimonial|project|customer|review|address|hours|contact|welcome|home|about/i', $candidate ) ) {
				continue;
			}
			$services[] = $candidate;
		}

		return array_values( array_unique( array_slice( $services, 0, 10 ) ) );
	}

	private static function clean_ai_answer( string $answer ): string {
		$answer = trim( wp_strip_all_tags( $answer ) );
		$answer = preg_replace( "/[ \t]+\n/", "\n", (string) $answer );
		$answer = preg_replace( "/\n{3,}/", "\n\n", (string) $answer );
		$answer = preg_replace( '/[ \t]{2,}/', ' ', (string) $answer );
		if ( strlen( $answer ) > 1200 ) {
			$answer = substr( $answer, 0, 1197 ) . '...';
		}
		return trim( (string) $answer );
	}

	private static function contact_answer( string $knowledge ): string {
		$text = self::normalize_text( $knowledge );
		if ( '' === $text ) {
			return __( 'I could not find contact details in the site knowledge base yet. Please run a crawl first.', 'wp-aichat' );
		}

		preg_match_all( '/(?:\+?\d[\d\s().-]{7,}\d)/', $text, $phone_matches );
		preg_match_all( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $email_matches );

		$phones = array_values( array_unique( array_map( array( __CLASS__, 'clean_phone' ), $phone_matches[0] ?? array() ) ) );
		$emails = array_values( array_unique( array_map( array( __CLASS__, 'clean_email' ), $email_matches[0] ?? array() ) ) );
		$phones = array_values( array_filter( $phones, static fn( $phone ) => strlen( preg_replace( '/\D+/', '', $phone ) ) >= 8 ) );
		$emails = array_values( array_filter( $emails ) );

		$address = self::extract_between_labels( $text, array( 'Address', 'Location' ), array( 'Hours', 'Contacts', 'Contact', 'Phone', 'Email', 'Services' ) );
		$hours   = self::extract_between_labels( $text, array( 'Hours', 'Opening Hours', 'Business Hours' ), array( 'Contacts', 'Contact', 'Phone', 'Email', 'Services', 'Our Services' ) );

		$lines = array();
		if ( ! empty( $phones ) ) {
			$lines[] = 'Phone: ' . implode( ', ', array_slice( $phones, 0, 2 ) );
		}
		if ( ! empty( $emails ) ) {
			$lines[] = 'Email: ' . implode( ', ', array_slice( $emails, 0, 2 ) );
		}
		if ( '' !== $address ) {
			$lines[] = 'Address: ' . $address;
		}
		if ( '' !== $hours ) {
			$lines[] = 'Hours: ' . $hours;
		}

		if ( empty( $lines ) ) {
			return __( 'I could not find clear contact details in the site knowledge base. Please check the Contact page for the latest information.', 'wp-aichat' );
		}

		return "You can contact them here:\n\n" . implode( "\n\n", $lines );
	}

	private static function normalize_text( string $text ): string {
		$text = wp_strip_all_tags( $text );
		$text = str_replace( array( "\r", "\n", "\t", '---' ), ' ', $text );
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = preg_replace( '/(\+?\d[\d\s().-]{7,}\d)([A-Z][A-Z0-9._%+\-]*@[A-Z0-9.\-]+\.[A-Z]{2,})/i', '$1 $2', (string) $text );
		$text = preg_replace( '/(\d)\s*-\s*([AP]M)/i', '$1 $2', (string) $text );
		$text = preg_replace( '/\bto(?=\d)/i', 'to ', (string) $text );
		return trim( (string) $text );
	}

	private static function clean_phone( string $phone ): string {
		$phone = preg_replace( '/[^\d+().\s-]/', '', $phone );
		return trim( preg_replace( '/\s+/', ' ', (string) $phone ) );
	}

	private static function clean_email( string $email ): string {
		$email = trim( $email );
		$email = preg_replace( '/^\d+([A-Z][A-Z0-9._%+\-]*@[A-Z0-9.\-]+\.[A-Z]{2,})$/i', '$1', $email );
		return sanitize_email( (string) $email );
	}

	private static function extract_between_labels( string $text, array $start_labels, array $end_labels ): string {
		foreach ( $start_labels as $start ) {
			$pattern = '/\b' . preg_quote( $start, '/' ) . '\b\s*:?\s*(.+?)(?=\b(?:' . implode( '|', array_map( static fn( $label ) => preg_quote( $label, '/' ), $end_labels ) ) . ')\b|$)/i';
			if ( preg_match( $pattern, $text, $matches ) ) {
				$value = trim( (string) $matches[1] );
				$value = preg_replace( '/\s+/', ' ', $value );
				$value = preg_replace( '/\b(\w+)(\s+\1\b)+/i', '$1', (string) $value );
				return trim( self::limit_sentence_noise( (string) $value ) );
			}
		}
		return '';
	}

	private static function limit_sentence_noise( string $value ): string {
		$parts = preg_split( '/(?<=[.!?])\s+/', $value );
		if ( is_array( $parts ) && count( $parts ) > 1 ) {
			$value = $parts[0];
		}
		return strlen( $value ) > 180 ? substr( $value, 0, 177 ) . '...' : $value;
	}

	private static function knowledge_fallback( string $message, string $knowledge ): string {
		$message = strtolower( trim( $message ) );
		if ( in_array( $message, array( 'hi', 'hello', 'hey', 'salam', 'assalamualaikum' ), true ) ) {
			return __( 'Hi! How can I help you today?', 'wp-aichat' );
		}

		$knowledge = trim( wp_strip_all_tags( $knowledge ) );
		if ( '' === $knowledge ) {
			return __( 'I am online, but the free AI service is busy right now. Please try again in a moment.', 'wp-aichat' );
		}

		if ( self::is_services_question( $message ) ) {
			return self::services_answer( $knowledge );
		}
		if ( self::is_price_question( $message ) ) {
			return self::price_answer( $knowledge );
		}

		$knowledge = self::normalize_text( $knowledge );
		$sentences = self::knowledge_sentences( $knowledge );
		$terms     = preg_split( '/\s+/', strtolower( sanitize_text_field( $message ) ) );
		$terms     = self::meaningful_terms( $terms ?: array() );
		$scored    = array();

		foreach ( $sentences as $sentence ) {
			$sentence = self::clean_sentence( (string) $sentence );
			if ( '' === $sentence || self::is_noisy_sentence( $sentence ) ) {
				continue;
			}
			$score = 0;
			foreach ( $terms as $term ) {
				if ( str_contains( strtolower( $sentence ), $term ) ) {
					$score++;
				}
			}
			if ( $score < 1 ) {
				continue;
			}
			$scored[] = array(
				'text'  => $sentence,
				'score' => $score,
			);
		}

		usort( $scored, static fn( $a, $b ) => $b['score'] <=> $a['score'] );
		$best = array_slice( $scored, 0, 3 );
		if ( empty( $best ) || ( $best[0]['score'] ?? 0 ) < 1 ) {
			return self::not_found_answer();
		}

		$lines = array_map(
			static fn( $row ) => '- ' . $row['text'],
			$best
		);
		return "Here is what I found:\n\n" . implode( "\n", $lines );
	}

	private static function meaningful_terms( array $terms ): array {
		$stop_words = array( 'what', 'which', 'where', 'when', 'how', 'can', 'could', 'would', 'should', 'the', 'and', 'for', 'with', 'you', 'your', 'are', 'that', 'this', 'there', 'have', 'has', 'about', 'tell', 'give', 'info', 'information' );
		$clean = array();
		foreach ( $terms as $term ) {
			$term = strtolower( preg_replace( '/[^a-z0-9]+/i', '', (string) $term ) );
			if ( strlen( $term ) < 3 || in_array( $term, $stop_words, true ) ) {
				continue;
			}
			$clean[] = $term;
		}
		return array_values( array_unique( $clean ) );
	}

	private static function knowledge_sentences( string $knowledge ): array {
		return preg_split( '/(?<=[.!?])\s+/', (string) $knowledge ) ?: array();
	}

	private static function clean_sentence( string $sentence ): string {
		$sentence = trim( preg_replace( '/\s+/', ' ', $sentence ) );
		$sentence = preg_replace( '/\b(.{4,40})\s+\1\b/i', '$1', (string) $sentence );
		return strlen( $sentence ) > 220 ? substr( $sentence, 0, 217 ) . '...' : (string) $sentence;
	}

	private static function is_noisy_sentence( string $sentence ): bool {
		if ( strlen( $sentence ) < 18 ) {
			return true;
		}
		if ( preg_match( '/request job estimate|view all|claim offer|testimonial|they cleared|explained what caused|satisfaction guaranteed/i', $sentence ) ) {
			return true;
		}
		if ( preg_match( '/reasonable|competitive prices|competitive pricing|affordable/i', $sentence ) ) {
			return true;
		}
		$word_count = str_word_count( $sentence );
		return $word_count > 34;
	}

	private static function not_found_answer(): string {
		return __( 'I could not find a clear answer in the site knowledge base. Please check the relevant page or contact the site directly for the most accurate information.', 'wp-aichat' );
	}

	private static function provider_blocked( string $provider ): bool {
		return (bool) get_transient( 'wp_aichat_provider_blocked_' . sanitize_key( $provider ) );
	}

	private static function record_failure( string $provider, WP_Error $error, array $settings ): void {
		$key = 'wp_aichat_provider_failures_' . sanitize_key( $provider );
		$count = (int) get_transient( $key ) + 1;
		set_transient( $key, $count, 10 * MINUTE_IN_SECONDS );
		if ( $count >= 3 ) {
			set_transient( 'wp_aichat_provider_blocked_' . sanitize_key( $provider ), 1, 5 * MINUTE_IN_SECONDS );
			delete_transient( $key );
		}
		if ( ! empty( $settings['provider_logging'] ) ) {
			error_log( sprintf( 'WP AI Chat provider failure [%s]: %s', sanitize_key( $provider ), $error->get_error_message() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}
