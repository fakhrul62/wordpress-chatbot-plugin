<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$settings = WP_AICHAT_Settings::get();
?>
<div class="aichat-admin" data-page="ai-config">
	<header class="aichat-page-header">
		<h1><?php esc_html_e( 'AI Configuration', 'wp-aichat' ); ?></h1>
		<p><?php esc_html_e( 'Configure the responder, behavior rules, and the system prompt.', 'wp-aichat' ); ?></p>
	</header>
	<form id="aichat-settings-form">
		<section class="aichat-card">
			<h2><?php esc_html_e( 'AI Provider', 'wp-aichat' ); ?></h2>
			<label><?php esc_html_e( 'Free fallback model', 'wp-aichat' ); ?><input type="text" name="ai_model" value="<?php echo esc_attr( $settings['ai_model'] ); ?>"></label>
			<label><?php esc_html_e( 'Optional OpenAI API Key', 'wp-aichat' ); ?><span class="aichat-password-row"><input type="password" name="openai_api_key" value="<?php echo esc_attr( $settings['openai_api_key'] ); ?>"><button type="button" class="aichat-icon-button" data-toggle-password><?php echo wp_kses( WP_AICHAT_Admin::icon_eye(), array( 'svg' => array( 'viewBox' => true, 'aria-hidden' => true ), 'path' => array( 'd' => true ), 'circle' => array( 'cx' => true, 'cy' => true, 'r' => true ) ) ); ?></button></span></label>
			<label><?php esc_html_e( 'OpenAI model', 'wp-aichat' ); ?><input type="text" name="openai_model" value="<?php echo esc_attr( $settings['openai_model'] ); ?>"></label>
			<p class="aichat-note"><?php esc_html_e( 'If an OpenAI key is saved, chat will try OpenAI first. If it fails or no key is saved, it uses the normal free fallback flow.', 'wp-aichat' ); ?></p>
			<button type="button" class="aichat-button aichat-button-secondary" id="aichat-test-connection"><?php esc_html_e( 'Test Connection', 'wp-aichat' ); ?></button>
			<div class="aichat-inline-status" id="aichat-test-status"></div>
		</section>
		<section class="aichat-card">
			<h2><?php esc_html_e( 'Behavior', 'wp-aichat' ); ?></h2>
			<label><?php esc_html_e( 'Language', 'wp-aichat' ); ?><input type="text" name="language" value="<?php echo esc_attr( $settings['language'] ); ?>"></label>
			<label><?php esc_html_e( 'Tone', 'wp-aichat' ); ?><select name="tone"><option value="friendly"><?php esc_html_e( 'Friendly', 'wp-aichat' ); ?></option><option value="professional"><?php esc_html_e( 'Professional', 'wp-aichat' ); ?></option><option value="formal"><?php esc_html_e( 'Formal', 'wp-aichat' ); ?></option><option value="concise"><?php esc_html_e( 'Concise', 'wp-aichat' ); ?></option></select></label>
			<label class="aichat-toggle"><input type="checkbox" name="cache_enabled" <?php checked( $settings['cache_enabled'] ); ?>><span></span><?php esc_html_e( 'Cache responses', 'wp-aichat' ); ?></label>
			<label data-cache-ttl><?php esc_html_e( 'Cache TTL in hours', 'wp-aichat' ); ?><input type="number" min="1" name="cache_ttl_hours" value="<?php echo esc_attr( $settings['cache_ttl_hours'] ); ?>"></label>
		</section>
		<section class="aichat-card">
			<h2><?php esc_html_e( 'Content Rules', 'wp-aichat' ); ?></h2>
			<label><?php esc_html_e( 'Bot will only discuss these topics (leave blank for no restriction)', 'wp-aichat' ); ?><textarea name="whitelist" rows="4"><?php echo esc_textarea( $settings['whitelist'] ); ?></textarea></label>
			<label><?php esc_html_e( 'Blocked Topics / Words', 'wp-aichat' ); ?><textarea name="blacklist" rows="4"><?php echo esc_textarea( $settings['blacklist'] ); ?></textarea></label>
			<label><?php esc_html_e( 'Custom Instructions', 'wp-aichat' ); ?><textarea name="custom_instructions" rows="5"><?php echo esc_textarea( $settings['custom_instructions'] ); ?></textarea></label>
		</section>
		<section class="aichat-card">
			<h2><?php esc_html_e( 'System Prompt Preview', 'wp-aichat' ); ?></h2>
			<label class="aichat-toggle"><input type="checkbox" id="aichat-override-toggle" <?php checked( '' !== $settings['system_prompt_override'] ); ?>><span></span><?php esc_html_e( 'Override system prompt', 'wp-aichat' ); ?></label>
			<textarea name="system_prompt_override" id="aichat-prompt-preview" rows="12"><?php echo esc_textarea( '' !== $settings['system_prompt_override'] ? $settings['system_prompt_override'] : WP_AICHAT_Prompt_Builder::preview( $settings ) ); ?></textarea>
			<p class="aichat-note"><?php esc_html_e( 'This is sent to the selected online AI provider on every conversation.', 'wp-aichat' ); ?></p>
			<button type="submit" class="aichat-button aichat-button-primary"><?php esc_html_e( 'Save Configuration', 'wp-aichat' ); ?></button>
			<div class="aichat-inline-status" id="aichat-save-status"></div>
		</section>
	</form>
</div>
