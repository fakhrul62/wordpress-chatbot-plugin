<?php
/**
 * Frontend widget renderer.
 *
 * @package WPAIChat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_AICHAT_Widget {
	private static bool $shortcode_rendered = false;

	public static function register_assets(): void {
		wp_register_style( 'wp-aichat-widget', WP_AICHAT_URL . 'public/assets/widget.css', array(), WP_AICHAT_VERSION );
		wp_register_script( 'wp-aichat-widget', WP_AICHAT_URL . 'public/assets/widget.js', array(), WP_AICHAT_VERSION, true );
	}

	public static function shortcode(): string {
		self::$shortcode_rendered = true;
		self::enqueue();
		return self::html( false );
	}

	public static function render_floating_widget(): void {
		$settings = WP_AICHAT_Settings::get();
		if ( $settings['widget']['shortcode_mode'] || self::$shortcode_rendered || ! self::visible_on_current_page( $settings ) ) {
			return;
		}
		self::enqueue();
		echo self::html( true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	private static function enqueue(): void {
		wp_enqueue_style( 'wp-aichat-widget' );
		wp_enqueue_script( 'wp-aichat-widget' );
	}

	private static function visible_on_current_page( array $settings ): bool {
		$id = get_queried_object_id();
		if ( $id && in_array( $id, $settings['hide_on_ids'], true ) ) {
			return false;
		}
		if ( 'specific' === $settings['show_on_mode'] ) {
			return $id && in_array( $id, $settings['show_on_ids'], true );
		}
		return true;
	}

	private static function html( bool $floating ): string {
		$settings = WP_AICHAT_Settings::get();
		$w        = $settings['widget'];
		$config   = array(
			'rest_url'        => esc_url_raw( rest_url( 'wp-aichat/v1/chat' ) ),
			'nonce'           => wp_create_nonce( 'wp_rest' ),
			'welcome_message' => $w['welcome_message'],
		);
		$encoded  = base64_encode( wp_json_encode( $config ) );
		$style    = self::inline_style( $w, $floating );
		$icon     = ( 'custom' === $w['bubble_icon'] && $w['bubble_icon_url'] ) ? '<img src="' . esc_url( $w['bubble_icon_url'] ) . '" alt="">' : self::icon_chat();
		$avatar   = $w['bot_avatar_url'] ? '<img class="aichat-avatar" src="' . esc_url( $w['bot_avatar_url'] ) . '" alt="">' : '<span class="aichat-avatar aichat-avatar-fallback"></span>';

		ob_start();
		?>
		<style><?php echo esc_html( $style ); ?></style>
		<div id="wp-aichat-root" class="<?php echo $floating ? 'wp-aichat-floating' : 'wp-aichat-inline'; ?>" data-config="<?php echo esc_attr( $encoded ); ?>">
			<button id="wp-aichat-bubble" type="button" aria-label="<?php esc_attr_e( 'Open chat', 'wp-aichat' ); ?>"><?php echo wp_kses( $icon, self::svg_kses() ); ?></button>
			<div id="wp-aichat-window" aria-hidden="true">
				<div id="wp-aichat-header"><?php echo wp_kses( $avatar, self::avatar_kses() ); ?><span class="aichat-botname"><?php echo esc_html( $w['bot_name'] ); ?></span><button class="aichat-close" type="button" aria-label="<?php esc_attr_e( 'Close chat', 'wp-aichat' ); ?>"><?php echo wp_kses( self::icon_x(), self::svg_kses() ); ?></button></div>
				<div id="wp-aichat-messages" role="log" aria-live="polite"></div>
				<div id="wp-aichat-input-area"><textarea id="wp-aichat-input" placeholder="<?php echo esc_attr( $w['input_placeholder'] ); ?>" rows="1"></textarea><button id="wp-aichat-send" type="button" aria-label="<?php esc_attr_e( 'Send', 'wp-aichat' ); ?>"><?php echo wp_kses( self::icon_send(), self::svg_kses() ); ?></button></div>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	private static function inline_style( array $w, bool $floating ): string {
		$position = '';
		if ( $floating ) {
			if ( 'bottom-left' === $w['position'] ) {
				$position = 'left:24px;right:auto;bottom:24px;';
			} elseif ( 'bottom-center' === $w['position'] ) {
				$position = 'left:50%;right:auto;bottom:24px;transform:translateX(-50%);';
			} elseif ( 'custom' === $w['position'] ) {
				$x = $w['custom_x'] ?: '24';
				$y = $w['custom_y'] ?: '24';
				$position = 'right:' . absint( $x ) . 'px;bottom:' . absint( $y ) . 'px;';
			} else {
				$position = 'right:24px;bottom:24px;';
			}
		}
		$font_family = in_array( $w['font_family'], array( 'inherit', 'Inter', 'system-ui', 'Georgia', 'monospace' ), true ) ? $w['font_family'] : 'inherit';
		return '#wp-aichat-root{--aichat-bubble:' . sanitize_hex_color( $w['bubble_color'] ) . ';--aichat-window-bg:' . sanitize_hex_color( $w['window_bg'] ) . ';--aichat-header-bg:' . sanitize_hex_color( $w['header_bg'] ) . ';--aichat-header-text:' . sanitize_hex_color( $w['header_text_color'] ) . ';--aichat-user-bg:' . sanitize_hex_color( $w['user_msg_bg'] ) . ';--aichat-user-text:' . sanitize_hex_color( $w['user_msg_color'] ) . ';--aichat-bot-bg:' . sanitize_hex_color( $w['bot_msg_bg'] ) . ';--aichat-bot-text:' . sanitize_hex_color( $w['bot_msg_color'] ) . ';--aichat-font-size:' . absint( $w['font_size'] ) . 'px;--aichat-font-family:' . $font_family . ';--aichat-radius:' . absint( $w['border_radius'] ) . 'px;--aichat-width:' . absint( $w['window_width'] ) . 'px;--aichat-height:' . absint( $w['window_height'] ) . 'px;--aichat-shadow:' . ( $w['shadow'] ? '0 18px 60px rgba(0,0,0,.18)' : 'none' ) . ';' . $position . '}';
	}

	private static function icon_chat(): string { return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 9 9 0 0 1-3.8-.8L3 21l1.8-4.7A8.2 8.2 0 0 1 3 11.5C3 6.8 7 3 12 3s9 3.8 9 8.5Z"/></svg>'; }
	private static function icon_x(): string { return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>'; }
	private static function icon_send(): string { return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4 20-7Z"/><path d="M22 2 11 13"/></svg>'; }
	private static function svg_kses(): array { return array( 'svg' => array( 'viewBox' => true, 'aria-hidden' => true ), 'path' => array( 'd' => true ), 'img' => array( 'src' => true, 'alt' => true ) ); }
	private static function avatar_kses(): array { return array( 'img' => array( 'class' => true, 'src' => true, 'alt' => true ), 'span' => array( 'class' => true ) ); }
}
