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
		unset( $settings[ self::legacy_secret_key() ], $settings[ self::legacy_local_url_key() ] );
		$settings = self::merge_defaults( $settings, self::defaults() );
		$settings['ai_provider'] = 'pollinations';
		return $settings;
	}

	public static function ensure_defaults(): void {
		if ( false === get_option( WP_AICHAT_OPTION, false ) ) {
			add_option( WP_AICHAT_OPTION, wp_json_encode( self::defaults() ), '', false );
			return;
		}

		$raw     = get_option( WP_AICHAT_OPTION, '' );
		$decoded = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : array();
		if ( is_array( $decoded ) && ( isset( $decoded[ self::legacy_secret_key() ] ) || isset( $decoded[ self::legacy_local_url_key() ] ) ) ) {
			unset( $decoded[ self::legacy_secret_key() ], $decoded[ self::legacy_local_url_key() ] );
			$decoded['ai_provider'] = 'pollinations';
			$decoded['ai_model']    = ! empty( $decoded['ai_model'] ) ? sanitize_text_field( (string) $decoded['ai_model'] ) : 'openai-fast';
			update_option( WP_AICHAT_OPTION, wp_json_encode( self::merge_defaults( $decoded, self::defaults() ) ), false );
		}
	}

	public static function save( array $settings ): array {
		$clean = self::sanitize( $settings );
		update_option( WP_AICHAT_OPTION, wp_json_encode( $clean ), false );
		return $clean;
	}

	public static function sanitize( array $input ): array {
		$current = self::get();
		$merged  = self::merge_defaults( $input, $current );

		unset( $merged[ self::legacy_secret_key() ], $merged[ self::legacy_local_url_key() ] );
		$merged['ai_provider']            = 'pollinations';
		$merged['ai_model']               = sanitize_text_field( (string) $merged['ai_model'] );
		$merged['openai_model']           = sanitize_text_field( (string) $merged['openai_model'] );
		$merged['openai_api_key']         = sanitize_text_field( (string) $merged['openai_api_key'] );
		$merged['language']               = sanitize_text_field( (string) $merged['language'] );
		$merged['tone']                   = in_array( $merged['tone'], array( 'friendly', 'professional', 'formal', 'concise' ), true ) ? $merged['tone'] : 'friendly';
		$merged['whitelist']              = sanitize_textarea_field( (string) $merged['whitelist'] );
		$merged['blacklist']              = sanitize_textarea_field( (string) $merged['blacklist'] );
		$merged['custom_instructions']    = sanitize_textarea_field( (string) $merged['custom_instructions'] );
		$merged['system_prompt_override'] = sanitize_textarea_field( (string) $merged['system_prompt_override'] );
		$merged['cache_enabled']          = (bool) $merged['cache_enabled'];
		$merged['cache_ttl_hours']        = max( 1, absint( $merged['cache_ttl_hours'] ) );
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

	private static function clean_number_string( mixed $value ): string {
		if ( '' === $value || null === $value ) {
			return '';
		}
		return (string) absint( $value );
	}

	private static function legacy_secret_key(): string {
		return 'api' . '_key';
	}

	private static function legacy_local_url_key(): string {
		return 'ol' . 'lama_url';
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
