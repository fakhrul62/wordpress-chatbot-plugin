<?php
use PHPUnit\Framework\TestCase;

final class WP_AICHAT_Settings_Test extends TestCase {
	protected function setUp(): void {
		$GLOBALS['wp_aichat_options'] = array();
	}

	public function test_sanitize_encrypts_openai_key_and_masks_admin_value(): void {
		$settings = WP_AICHAT_Settings::save( array( 'openai_api_key' => 'sk-test-1234567890', 'ai_mode' => 'openai_only' ) );

		$this->assertSame( 'openai_only', $settings['ai_mode'] );
		$this->assertSame( 'sk-test-1234567890', $settings['openai_api_key'] );

		$stored = json_decode( get_option( WP_AICHAT_OPTION ), true );
		$this->assertSame( '', $stored['openai_api_key'] );
		$this->assertNotEmpty( $stored['openai_api_key_encrypted'] );

		$admin = WP_AICHAT_Settings::get_for_admin();
		$this->assertSame( '', $admin['openai_api_key'] );
		$this->assertSame( '********7890', $admin['openai_api_key_masked'] );
	}

	public function test_invalid_ai_mode_falls_back_to_default(): void {
		$settings = WP_AICHAT_Settings::sanitize( array( 'ai_mode' => 'bad' ) );
		$this->assertSame( 'openai_fallback', $settings['ai_mode'] );
	}
}
