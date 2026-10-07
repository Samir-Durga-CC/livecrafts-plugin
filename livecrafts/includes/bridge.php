<?php
/**
 * Site Bridge endpoints for the Livecrafts backend:
 *
 *   GET /livecrafts/v1/ping                       who/what is this site, plugin version, what it can edit
 *   GET /livecrafts/v1/map?url=...|post=ID        every editable value on a page (ACF fields + Elementor settings),
 *                                                 with stable target ids. &view=live for the live values (default: draft,
 *                                                 i.e. what editors see).
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
		'ok'      => true,
		'plugin'  => 'livecrafts',
		'version' => LIVECRAFTS_VERSION,
		'site'    => array( 'name' => get_bloginfo( 'name' ), 'url' => home_url( '/' ), 'wp' => get_bloginfo( 'version' ), 'php' => PHP_VERSION ),
		'user'    => array(
			'login' => $user && $user->exists() ? $user->user_login : '', 'can_edit' => livecrafts_can_edit(), 'can_deploy' => livecrafts_can_deploy(),
			'can_edit_pages' => current_user_can( 'edit_pages' ), 'can_edit_themes' => current_user_can( 'edit_themes' ), 'can_edit_css' => current_user_can( 'edit_css' ),
		),
		'capabilities' => array(
			'acf'         => livecrafts_acf_active(),
			'elementor'   => livecrafts_el_active(),
			'drafts'      => true,
			'kinds'       => array_keys( livecrafts_kinds() ),
			'theme_files' => current_user_can( 'edit_themes' ),
			'theme'       => get_stylesheet(),
			'block_theme' => function_exists( 'wp_is_block_theme' ) ? wp_is_block_theme() : false,
		),
		'drafts'  => livecrafts_draft_count(),
	);
}

/** Public page URL -> post ID (handles the static front page and sub-directory installs). */
function livecrafts_url_to_post( $url ) {
	$clean = trailingslashit( strtok( (string) $url, '?#' ) );
	$home  = trailingslashit( home_url( '/' ) );
	if ( untrailingslashit( $clean ) === untrailingslashit( $home ) && get_option( 'show_on_front' ) === 'page' && (int) get_option( 'page_on_front' ) ) {
		return (int) get_option( 'page_on_front' );
	}
	$id = (int) url_to_postid( $url );
	if ( ! $id ) { // draft pages have no pretty URL yet: ?page_id= / ?p=
		$query = array();
		wp_parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
		$id = ! empty( $query['page_id'] ) ? (int) $query['page_id'] : ( ! empty( $query['p'] ) ? (int) $query['p'] : 0 );
	}
	return $id;
}

function livecrafts_rest_map( WP_REST_Request $req ) {
	$post = (int) $req->get_param( 'post' );
	$url  = (string) $req->get_param( 'url' );
	if ( ! $post && $url !== '' ) $post = livecrafts_url_to_post( $url );
	if ( ! $post || ! get_post( $post ) ) return new WP_Error( 'livecrafts_no_page', 'Could not map that URL to a page on this site. Pass a full page URL of this site, or a post id.', array( 'status' => 404 ) );
	if ( ! current_user_can( 'edit_post', $post ) ) return new WP_Error( 'livecrafts_forbidden', 'You cannot edit this page.', array( 'status' => 403 ) );

	$view = $req->get_param( 'view' ) === 'live' ? 'live' : 'draft';
	$data = $view === 'live' ? livecrafts_post_data( $post ) : livecrafts_draft_data( 'post', $post );
	if ( is_wp_error( $data ) ) return $data;
	$acf = livecrafts_acf_scan( $post, $data );
	$el  = livecrafts_el_scan( $post, $data );
	return array(
		'ok'        => true,
		'view'      => $view,
		'post'      => array( 'id' => $post, 'type' => get_post_type( $post ), 'title' => $data['fields']['title'], 'status' => get_post_status( $post ), 'url' => get_permalink( $post ), 'edit_link' => get_edit_post_link( $post, 'raw' ) ),
		'builders'  => array( 'elementor' => ! empty( $data['meta']['_elementor_data'] ), 'acf_fields' => count( $acf ), 'elementor_settings' => count( $el ) ),
		'acf'       => $acf,
		'elementor' => $el,
		'drafts'    => count( livecrafts_draft_changes( 'post', $post ) ),
		'note'      => 'Each entry has a stable target id (tid). Change it with POST /changes (kind acf.field target <field key>, or kind el.setting target <element id>:<setting>). Text not listed here is not in ACF/Elementor (it may be block content, a menu, a widget or the theme).',
	);
}
