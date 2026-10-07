<?php
/**
 * Render one front-end page in this process and print the HTML - used by integration.php to compare what an editor
 * (preview) and a visitor (live site) get.
 *
 *   php tests/render.php <path to WordPress> <page url> <user id, 0 = visitor> [draft|live]
 */

if ( PHP_SAPI !== 'cli' || $argc < 4 ) exit( 1 );
list( , $wp, $url, $user ) = $argv;
$view  = $argv[4] ?? 'draft';
$parts = parse_url( $url );
$_SERVER['HTTP_HOST']      = $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );
$_SERVER['SERVER_NAME']    = $parts['host'];
$_SERVER['REQUEST_URI']    = ( $parts['path'] ?? '/' ) . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' );
$_SERVER['REQUEST_METHOD'] = 'GET';
if ( ( $parts['scheme'] ?? 'http' ) === 'https' ) $_SERVER['HTTPS'] = 'on';
if ( $view === 'live' ) $_COOKIE['livecrafts_view'] = 'live';

define( 'WP_USE_THEMES', true );
require rtrim( $wp, '/\\' ) . '/wp-load.php';
wp_set_current_user( (int) $user ); // who is looking, before WordPress handles the request
wp();
require ABSPATH . WPINC . '/template-loader.php';
