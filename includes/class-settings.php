<?php
/**
 * Settings storage.
 *
 * @package WPAIChat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_AICHAT_Settings {
	public static function defaults(): array {
		return array(
			'ai_provider'            => 'pollinations',
			'ai_model'               => 'openai-fast',
			'openai_model'           => 'gpt-4o-mini',
			'openai_api_key'         => '',
			'openai_api_key_encrypted' => '',
			'openai_api_key_last4'   => '',
			'provider_logging'       => false,
			'language'               => 'English',
			'tone'                   => 'friendly',
			'whitelist'              => '',
			'blacklist'              => '',
			'custom_instructions'    => '',
			'system_prompt_override' => '',
			'cache_enabled'          => true,
			'cache_ttl_hours'        => 24,
			'shop_url'               => '',
			'show_on_mode'           => 'all',
			'show_on_ids'            => array(),
			'hide_on_ids'            => array(),
			'widget'                 => array(
				'position'           => 'bottom-right',
				'custom_x'           => '',
				'custom_y'           => '',
				'bubble_color'       => '#111111',
				'bubble_icon'        => 'default',
				'bubble_icon_url'    => '',
				'window_bg'          => '#ffffff',
				'header_bg'          => '#111111',
				'header_text_color'  => '#ffffff',
				'bot_name'           => 'Assistant',
				'bot_avatar_url'     => '',
				'user_msg_bg'        => '#111111',
				'user_msg_color'     => '#ffffff',
				'bot_msg_bg'         => '#f4f4f4',
				'bot_msg_color'      => '#111111',
				'font_size'          => '14',
				'font_family'        => 'inherit',
				'border_radius'      => '12',
				'shadow'             => true,
				'window_width'       => '360',
				'window_height'      => '520',
				'welcome_message'    => 'Hi! How can I help you today?',
				'input_placeholder'  => 'Type a message...',
				'shortcode_mode'     => false,
			),
		);
	}

	public static function get(): array {
		$raw      = get_option( WP_AICHAT_OPTION, '' );
		$decoded  = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : array();
		$settings = is_array( $decoded ) ? $decoded : array();
		unset( $settings['api_key'], $settings['ollama_url'] );
		$legacy_plain_key = ! empty( $settings['openai_api_key'] ) ? sanitize_text_field( (string) $settings['openai_api_key'] ) : '';
		$settings = self::merge_defaults( $settings, self::defaults() );
		$settings['ai_provider'] = 'pollinations';
		$settings['openai_api_key'] = self::decrypt_api_key( (string) $settings['openai_api_key_encrypted'] );
		if ( '' === $settings['openai_api_key'] && '' !== $legacy_plain_key ) {
			$settings['openai_api_key'] = $legacy_plain_key;
		}
		return $settings;
	}

	public static function get_for_admin(): array {
		$settings = self::get();
		$settings['openai_api_key'] = '';
		$settings['openai_api_key_masked'] = self::masked_api_key( (string) $settings['openai_api_key_last4'] );
		unset( $settings['openai_api_key_encrypted'] );
		return $settings;
	}

	public static function get_public_safe(): array {
		$settings = self::get_for_admin();
		unset( $settings['openai_api_key'], $settings['openai_api_key_masked'], $settings['openai_api_key_last4'] );
		return $settings;
	}

	public static function ensure_defaults(): void {
		if ( false === get_option( WP_AICHAT_OPTION, false ) ) {
			add_option( WP_AICHAT_OPTION, wp_json_encode( self::defaults() ), '', false );
			return;
		}

		$raw     = get_option( WP_AICHAT_OPTION, '' );
		$decoded = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : array();
		if ( is_array( $decoded ) ) {
			$changed = false;
			if ( isset( $decoded['api_key'] ) || isset( $decoded['ollama_url'] ) ) {
				unset( $decoded['api_key'], $decoded['ollama_url'] );
				$changed = true;
			}
			if ( ! empty( $decoded['openai_api_key'] ) && empty( $decoded['openai_api_key_encrypted'] ) ) {
				$plain = sanitize_text_field( (string) $decoded['openai_api_key'] );
				$decoded['openai_api_key_encrypted'] = self::encrypt_api_key( $plain );
				$decoded['openai_api_key_last4'] = substr( $plain, -4 );
				$decoded['openai_api_key'] = '';
				$changed = true;
			}
			if ( $changed ) {
				$decoded['ai_provider'] = 'pollinations';
				$decoded['ai_model']    = ! empty( $decoded['ai_model'] ) ? sanitize_text_field( (string) $decoded['ai_model'] ) : 'openai-fast';
				update_option( WP_AICHAT_OPTION, wp_json_encode( self::merge_defaults( $decoded, self::defaults() ) ), false );
			}
		}
	}

	public static function save( array $settings ): array {
		$clean = self::sanitize( $settings );
		$stored = $clean;
		$stored['openai_api_key'] = '';
		update_option( WP_AICHAT_OPTION, wp_json_encode( $stored ), false );
		return $clean;
	}

	public static function sanitize( array $input ): array {
		$current = self::get();
		$merged  = self::merge_defaults( $input, $current );

		unset( $merged['api_key'], $merged['ollama_url'], $merged['openai_api_key_masked'] );
		$merged['ai_provider']            = 'pollinations';
		$merged['ai_model']               = sanitize_text_field( (string) $merged['ai_model'] );
		$merged['openai_model']           = sanitize_text_field( (string) $merged['openai_model'] );
		$new_api_key                      = isset( $input['openai_api_key'] ) ? trim( sanitize_text_field( (string) $input['openai_api_key'] ) ) : '';
		if ( '' !== $new_api_key ) {
			$merged['openai_api_key_encrypted'] = self::encrypt_api_key( $new_api_key );
			$merged['openai_api_key_last4']     = substr( $new_api_key, -4 );
		} else {
			$current_api_key = (string) ( $current['openai_api_key'] ?? '' );
			$merged['openai_api_key_encrypted'] = ! empty( $current['openai_api_key_encrypted'] ) ? (string) $current['openai_api_key_encrypted'] : self::encrypt_api_key( $current_api_key );
			$merged['openai_api_key_last4']     = ! empty( $current['openai_api_key_last4'] ) ? (string) $current['openai_api_key_last4'] : ( '' !== $current_api_key ? substr( $current_api_key, -4 ) : '' );
		}
		$merged['openai_api_key'] = self::decrypt_api_key( (string) $merged['openai_api_key_encrypted'] );
		$merged['language']               = sanitize_text_field( (string) $merged['language'] );
		$merged['tone']                   = in_array( $merged['tone'], array( 'friendly', 'professional', 'formal', 'concise' ), true ) ? $merged['tone'] : 'friendly';
		$merged['whitelist']              = sanitize_textarea_field( (string) $merged['whitelist'] );
		$merged['blacklist']              = sanitize_textarea_field( (string) $merged['blacklist'] );
		$merged['custom_instructions']    = sanitize_textarea_field( (string) $merged['custom_instructions'] );
		$merged['system_prompt_override'] = sanitize_textarea_field( (string) $merged['system_prompt_override'] );
		$merged['cache_enabled']          = (bool) $merged['cache_enabled'];
		$merged['cache_ttl_hours']        = max( 1, absint( $merged['cache_ttl_hours'] ) );
		$merged['provider_logging']       = (bool) $merged['provider_logging'];
		$merged['shop_url']               = esc_url_raw( (string) $merged['shop_url'] );
		$merged['show_on_mode']           = 'specific' === $merged['show_on_mode'] ? 'specific' : 'all';
		$merged['show_on_ids']            = array_values( array_filter( array_map( 'absint', (array) $merged['show_on_ids'] ) ) );
		$merged['hide_on_ids']            = array_values( array_filter( array_map( 'absint', (array) $merged['hide_on_ids'] ) ) );

		$w = $merged['widget'];
		$w['position']          = in_array( $w['position'], array( 'bottom-right', 'bottom-left', 'bottom-center', 'custom' ), true ) ? $w['position'] : 'bottom-right';
		$w['custom_x']          = self::clean_number_string( $w['custom_x'] );
		$w['custom_y']          = self::clean_number_string( $w['custom_y'] );
		$w['bubble_color']      = sanitize_hex_color( $w['bubble_color'] ) ?: '#111111';
		$w['bubble_icon']       = 'custom' === $w['bubble_icon'] ? 'custom' : 'default';
		$w['bubble_icon_url']   = esc_url_raw( (string) $w['bubble_icon_url'] );
		$w['window_bg']         = sanitize_hex_color( $w['window_bg'] ) ?: '#ffffff';
		$w['header_bg']         = sanitize_hex_color( $w['header_bg'] ) ?: '#111111';
		$w['header_text_color'] = sanitize_hex_color( $w['header_text_color'] ) ?: '#ffffff';
		$w['bot_name']          = sanitize_text_field( (string) $w['bot_name'] );
		$w['bot_avatar_url']    = esc_url_raw( (string) $w['bot_avatar_url'] );
		$w['user_msg_bg']       = sanitize_hex_color( $w['user_msg_bg'] ) ?: '#111111';
		$w['user_msg_color']    = sanitize_hex_color( $w['user_msg_color'] ) ?: '#ffffff';
		$w['bot_msg_bg']        = sanitize_hex_color( $w['bot_msg_bg'] ) ?: '#f4f4f4';
		$w['bot_msg_color']     = sanitize_hex_color( $w['bot_msg_color'] ) ?: '#111111';
		$w['font_size']         = (string) min( 24, max( 11, absint( $w['font_size'] ) ) );
		$w['font_family']       = in_array( $w['font_family'], array( 'inherit', 'Inter', 'system-ui', 'Georgia', 'monospace' ), true ) ? $w['font_family'] : 'inherit';
		$w['border_radius']     = (string) min( 32, max( 0, absint( $w['border_radius'] ) ) );
		$w['shadow']            = (bool) $w['shadow'];
		$w['window_width']      = (string) min( 640, max( 280, absint( $w['window_width'] ) ) );
		$w['window_height']     = (string) min( 760, max( 360, absint( $w['window_height'] ) ) );
		$w['welcome_message']   = sanitize_text_field( (string) $w['welcome_message'] );
		$w['input_placeholder'] = sanitize_text_field( (string) $w['input_placeholder'] );
		$w['shortcode_mode']    = (bool) $w['shortcode_mode'];

		$merged['widget'] = $w;
		return $merged;
	}

	public static function response_safe( array $settings ): array {
		$settings['openai_api_key'] = '';
		$settings['openai_api_key_masked'] = self::masked_api_key( (string) ( $settings['openai_api_key_last4'] ?? '' ) );
		unset( $settings['openai_api_key_encrypted'] );
		return $settings;
	}

	private static function clean_number_string( mixed $value ): string {
		if ( '' === $value || null === $value ) {
			return '';
		}
		return (string) absint( $value );
	}

	private static function encryption_key(): string {
		return hash( 'sha256', wp_salt( 'auth' ) . wp_salt( 'secure_auth' ), true );
	}

	private static function encrypt_api_key( string $value ): string {
		if ( '' === $value ) {
			return '';
		}
		if ( function_exists( 'openssl_encrypt' ) ) {
			$iv = random_bytes( 16 );
			$ciphertext = openssl_encrypt( $value, 'aes-256-cbc', self::encryption_key(), OPENSSL_RAW_DATA, $iv );
			if ( false !== $ciphertext ) {
				return 'enc:' . base64_encode( $iv . $ciphertext );
			}
		}
		return 'b64:' . base64_encode( $value );
	}

	private static function decrypt_api_key( string $value ): string {
		if ( '' === $value ) {
			return '';
		}
		if ( str_starts_with( $value, 'enc:' ) && function_exists( 'openssl_decrypt' ) ) {
			$raw = base64_decode( substr( $value, 4 ), true );
			if ( is_string( $raw ) && strlen( $raw ) > 16 ) {
				$iv = substr( $raw, 0, 16 );
				$ciphertext = substr( $raw, 16 );
				$plain = openssl_decrypt( $ciphertext, 'aes-256-cbc', self::encryption_key(), OPENSSL_RAW_DATA, $iv );
				return is_string( $plain ) ? $plain : '';
			}
		}
		if ( str_starts_with( $value, 'b64:' ) ) {
			$plain = base64_decode( substr( $value, 4 ), true );
			return is_string( $plain ) ? $plain : '';
		}
		return $value;
	}

	private static function masked_api_key( string $last4 ): string {
		return '' === $last4 ? '' : '********' . $last4;
	}

	private static function merge_defaults( array $settings, array $defaults ): array {
		foreach ( $defaults as $key => $value ) {
			if ( is_array( $value ) ) {
				$settings[ $key ] = self::merge_defaults( isset( $settings[ $key ] ) && is_array( $settings[ $key ] ) ? $settings[ $key ] : array(), $value );
			} elseif ( ! array_key_exists( $key, $settings ) ) {
				$settings[ $key ] = $value;
			}
		}
		return $settings;
	}
}
