<?php
use PHPUnit\Framework\TestCase;

final class WP_AICHAT_Prompt_Builder_Test extends TestCase {
	public function test_override_placeholder_inserts_knowledge(): void {
		$settings = WP_AICHAT_Settings::defaults();
		$settings['system_prompt_override'] = 'Rules. {{knowledge}}';
		$this->assertSame( 'Rules. Site facts', WP_AICHAT_Prompt_Builder::build( $settings, 'Site facts' ) );
	}

	public function test_override_without_placeholder_replaces_generated_prompt(): void {
		$settings = WP_AICHAT_Settings::defaults();
		$settings['system_prompt_override'] = 'Custom only';
		$this->assertSame( 'Custom only', WP_AICHAT_Prompt_Builder::build( $settings, 'Site facts' ) );
	}
}
