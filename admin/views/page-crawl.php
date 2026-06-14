<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div class="aichat-admin" data-page="crawl">
	<header class="aichat-page-header">
		<h1><?php esc_html_e( 'Knowledge Base', 'wp-aichat' ); ?></h1>
		<p><?php esc_html_e( 'Scan your site content and manage the knowledge your AI chat can use.', 'wp-aichat' ); ?></p>
	</header>
	<section class="aichat-card">
		<h2><?php esc_html_e( 'Crawl Site', 'wp-aichat' ); ?></h2>
		<p><?php esc_html_e( "Scan your site's pages and posts to build the knowledge base.", 'wp-aichat' ); ?></p>
		<button class="aichat-button aichat-button-primary" id="aichat-start-crawl"><?php esc_html_e( 'Crawl Site', 'wp-aichat' ); ?></button>
		<div class="aichat-progress" hidden><span></span></div>
		<div class="aichat-log" id="aichat-crawl-log" hidden></div>
		<div class="aichat-inline-status" id="aichat-crawl-status"></div>
	</section>
	<section class="aichat-card">
		<h2><?php esc_html_e( 'Add Custom Knowledge', 'wp-aichat' ); ?></h2>
		<form id="aichat-custom-knowledge-form">
			<label><?php esc_html_e( 'Title or question', 'wp-aichat' ); ?><input type="text" name="title" required></label>
			<label><?php esc_html_e( 'Answer or fact', 'wp-aichat' ); ?><textarea name="content" rows="4" required></textarea></label>
			<button type="submit" class="aichat-button aichat-button-primary"><?php esc_html_e( 'Add Knowledge', 'wp-aichat' ); ?></button>
			<div class="aichat-inline-status" id="aichat-custom-knowledge-status"></div>
		</form>
	</section>
	<section class="aichat-card">
		<div class="aichat-card-head">
			<h2><?php esc_html_e( 'Knowledge Base', 'wp-aichat' ); ?></h2>
			<button class="aichat-button aichat-button-secondary" id="aichat-refresh-knowledge"><?php esc_html_e( 'Refresh', 'wp-aichat' ); ?></button>
		</div>
		<div id="aichat-knowledge-table"></div>
	</section>
</div>
