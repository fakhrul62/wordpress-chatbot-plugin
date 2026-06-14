<?php
use PHPUnit\Framework\TestCase;

final class WP_AICHAT_Cache_Test extends TestCase {
	protected function setUp(): void {
		$GLOBALS['wp_aichat_options'] = array();
	}

	public function test_hash_normalizes_similar_questions(): void {
		$this->assertSame( WP_AICHAT_Cache::hash( "What's your price?" ), WP_AICHAT_Cache::hash( 'pricing' ) );
	}

	public function test_bump_version_changes_hash(): void {
		$before = WP_AICHAT_Cache::hash( 'contact' );
		WP_AICHAT_Cache::bump_version();
		$after = WP_AICHAT_Cache::hash( 'contact' );
		$this->assertNotSame( $before, $after );
	}
}
