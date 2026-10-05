<?php
/**
 * Site Bridge endpoints for the central Livecrafts backend (and anything else that talks to this site over REST).
 * Auth: a logged-in cookie + nonce, OR an Application Password (Users > Profile) over HTTPS - WordPress core handles both.
 *
 *   GET /livecrafts/v1/ping                  who/what is this site, plugin version, what it can edit
 *   GET /livecrafts/v1/map?url=...|post=ID   every editable value on a page (ACF fields + Elementor settings), with stable target ids
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'rest_api_init', function () {
	register_rest_route( 'livecrafts/v1', '/ping', array(
		'methods' => 'GET', 'callback' => 'livecrafts_rest_ping', 'permission_callback' => 'livecrafts_rest_permission',
	) );
	register_rest_route( 'livecrafts/v1', '/map', array(
		'methods' => 'GET', 'callback' => 'livecrafts_rest_map', 'permission_callback' => 'livecrafts_rest_permission',
	) );
} );

function livecrafts_rest_ping() {
	$user = wp_get_current_user();
	return array(
		'ok'           => true,
		'plugin'       => 'livecrafts',
		'version'      => LIVECRAFTS_VERSION,
		'site'         => array( 'name' => get_bloginfo( 'name' ), 'url' => home_url( '/' ), 'wp' => get_bloginfo( 'version' ), 'php' => PHP_VERSION ),
		'user'         => array( 'login' => $user && $user->exists() ? $user->user_login : '', 'can_edit_pages' => current_user_can( 'edit_pages' ), 'can_edit_themes' => current_user_can( 'edit_themes' ) ),
		'capabilities' => array(
			'acf'       => function_exists( 'get_field' ),
			'elementor' => function_exists( 'livecrafts_el_active' ) && livecrafts_el_active(),
			'targets'   => array( 'acf', 'el' ),
			'endpoints' => array( 'ping', 'map', 'target', 'debug/target', 'debug/fields', 'debug/elementor', 'debug/locate', 'undo', 'save', 'revert', 'theme-files', 'theme-file', 'assistant', 'patches' ),
			'manual'      => true,
			'theme_files' => current_user_can( 'edit_themes' ),
			'theme'       => get_stylesheet(),
			'block_theme' => function_exists( 'wp_is_block_theme' ) ? wp_is_block_theme() : false,
		),
	);
}

/** Public page URL -> post ID (handles the static front page and sub-directory installs). */
function livecrafts_url_to_post( $url ) {
	$clean = trailingslashit( strtok( (string) $url, '?#' ) );
	$home  = trailingslashit( home_url( '/' ) );
	if ( untrailingslashit( $clean ) === untrailingslashit( $home ) && get_option( 'show_on_front' ) === 'page' && (int) get_option( 'page_on_front' ) ) {
		return (int) get_option( 'page_on_front' );
	}
	return (int) url_to_postid( $url );
}

function livecrafts_rest_map( WP_REST_Request $req ) {
	$post = (int) $req->get_param( 'post' );
	$url  = (string) $req->get_param( 'url' );
	if ( ! $post && $url !== '' ) $post = livecrafts_url_to_post( $url );
	if ( ! $post || ! get_post( $post ) ) return new WP_Error( 'livecrafts_no_page', 'Could not map that URL to a page on this site. Pass a full page URL of this site, or a post id.', array( 'status' => 404 ) );
	if ( ! current_user_can( 'edit_post', $post ) ) return new WP_Error( 'livecrafts_forbidden', 'You cannot edit this page.', array( 'status' => 403 ) );

	$acf = livecrafts_scan_fields( $post );
	$el  = livecrafts_el_scan( $post );
	return array(
		'ok'        => true,
		'post'      => array( 'id' => $post, 'type' => get_post_type( $post ), 'title' => get_the_title( $post ), 'status' => get_post_status( $post ), 'url' => get_permalink( $post ), 'edit_link' => get_edit_post_link( $post, 'raw' ) ),
		'builders'  => array( 'elementor' => (bool) get_post_meta( $post, '_elementor_data', true ), 'acf_fields' => count( $acf ), 'elementor_settings' => count( $el ) ),
		'acf'       => $acf,
		'elementor' => $el,
		'note'      => 'Each entry has a stable target id (tid). Use it with POST /target. Text that is not listed here is not linked to ACF/Elementor (it may be post content, a menu, a widget or hard-coded).',
	);
}
