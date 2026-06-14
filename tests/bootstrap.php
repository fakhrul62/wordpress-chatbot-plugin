<?php
define( 'ABSPATH', __DIR__ . '/../' );
define( 'WP_AICHAT_OPTION', 'wp_aichat_settings' );

$GLOBALS['wp_aichat_options'] = array();
$GLOBALS['wp_aichat_transients'] = array();

function get_option( $key, $default = false ) { return $GLOBALS['wp_aichat_options'][ $key ] ?? $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['wp_aichat_options'][ $key ] = $value; return true; }
function add_option( $key, $value, $deprecated = '', $autoload = null ) { $GLOBALS['wp_aichat_options'][ $key ] = $value; return true; }
function get_transient( $key ) { return $GLOBALS['wp_aichat_transients'][ $key ] ?? false; }
function set_transient( $key, $value, $expiration = 0 ) { $GLOBALS['wp_aichat_transients'][ $key ] = $value; return true; }
function delete_transient( $key ) { unset( $GLOBALS['wp_aichat_transients'][ $key ] ); return true; }
function wp_json_encode( $value ) { return json_encode( $value ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function sanitize_hex_color( $value ) { return preg_match( '/^#[a-f0-9]{6}$/i', (string) $value ) ? $value : null; }
function esc_url_raw( $value ) { return (string) $value; }
function absint( $value ) { return abs( (int) $value ); }
function wp_salt( $scheme = 'auth' ) { return 'unit-test-' . $scheme; }
function wp_strip_all_tags( $value ) { return strip_tags( (string) $value ); }
function wp_kses_post( $value ) { return (string) $value; }
function current_time( $type ) { return time(); }
function __( $text, $domain = null ) { return $text; }
function get_bloginfo( $show = '' ) { return 'Test Site'; }
function home_url( $path = '' ) { return 'https://example.test/' . ltrim( $path, '/' ); }

require_once __DIR__ . '/../includes/class-settings.php';
require_once __DIR__ . '/../includes/class-cache.php';
require_once __DIR__ . '/../includes/class-prompt-builder.php';
