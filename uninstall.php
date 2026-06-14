<?php
/**
 * Uninstall cleanup.
 *
 * @package WPAIChat
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$knowledge_table = $wpdb->prefix . 'aichat_knowledge';
$cache_table     = $wpdb->prefix . 'aichat_cache';

$wpdb->query( 'DROP TABLE IF EXISTS ' . esc_sql( $knowledge_table ) );
$wpdb->query( 'DROP TABLE IF EXISTS ' . esc_sql( $cache_table ) );
$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $wpdb->options . ' WHERE option_name = %s', 'wp_aichat_settings' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
