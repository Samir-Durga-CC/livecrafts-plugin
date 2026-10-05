<?php
/**
 * REST API: /wp-json/livecrafts/v1/{save,revert,undo,target}
 * Every route requires a logged-in user who can edit pages (cookie + X-WP-Nonce).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function livecrafts_rest_permission() {
	return current_user_can( 'edit_pages' );
}

add_action( 'rest_api_init', function () {
	foreach ( array( 'save', 'revert', 'undo', 'target' ) as $route ) {
		register_rest_route( 'livecrafts/v1', '/' . $route, array(
			'methods'             => 'POST',
			'callback'            => 'livecrafts_rest_' . $route,
			'permission_callback' => 'livecrafts_rest_permission',
		) );
	}
} );

// Read-only debug endpoints (what the server sees) - used by the panel's Debug box and the browser console helpers.
// Read the saved style/text patches of one page (by URL or page key) + the site-wide ones.
add_action( 'rest_api_init', function () {
	register_rest_route( 'livecrafts/v1', '/patches', array(
		'methods' => 'GET', 'callback' => 'livecrafts_rest_patches', 'permission_callback' => 'livecrafts_rest_permission',
	) );
} );

function livecrafts_rest_patches( WP_REST_Request $req ) {
	$key = (string) $req->get_param( 'pageKey' );
	$url = (string) $req->get_param( 'url' );
	if ( $key === '' && $url !== '' ) {
		$post = function_exists( 'livecrafts_url_to_post' ) ? livecrafts_url_to_post( $url ) : 0;
		$path = wp_parse_url( $url, PHP_URL_PATH );
		$key  = $post ? 'p' . $post : 'u:' . md5( $path ? $path : '/' );
	}
	if ( ! livecrafts_valid_key( $key ) ) return new WP_Error( 'livecrafts_bad_request', 'Pass a page URL or a valid pageKey.', array( 'status' => 400 ) );
	$all = livecrafts_get_all();
	return array(
		'ok'      => true,
		'pageKey' => $key,
		'page'    => isset( $all[ $key ] ) ? (object) $all[ $key ] : new stdClass(),
		'site'    => isset( $all['site'] ) ? (object) $all['site'] : new stdClass(),
	);
}

add_action( 'rest_api_init', function () {
	foreach ( array( 'fields', 'target', 'elementor' ) as $route ) {
		register_rest_route( 'livecrafts/v1', '/debug/' . $route, array(
			'methods'             => 'GET',
			'callback'            => 'livecrafts_rest_debug_' . $route,
			'permission_callback' => 'livecrafts_rest_permission',
		) );
	}
} );

function livecrafts_target_key( WP_REST_Request $req ) {
	$scope = $req->get_param( 'scope' ) === 'site' ? 'site' : 'page';
	$key   = $scope === 'site' ? 'site' : (string) $req->get_param( 'pageKey' );
	return livecrafts_valid_key( $key ) ? $key : null;
}

/** Save styles and/or text PATCH for one element (replaces that element's patch). */
function livecrafts_rest_save( WP_REST_Request $req ) {
	$key = livecrafts_target_key( $req );
	$sel = livecrafts_clean_selector( $req->get_param( 'selector' ) );
	if ( ! $key || $sel === '' ) return new WP_Error( 'livecrafts_bad_request', 'Invalid page or selector.', array( 'status' => 400 ) );

	$patch = array();
	// "styles" apply on every screen; "styles_tablet" / "styles_mobile" only up to 1024px / 767px wide.
	foreach ( array( 'styles', 'styles_tablet', 'styles_mobile' ) as $k ) {
		$s = livecrafts_clean_styles( $req->get_param( $k ) );
		if ( $s ) $patch[ $k ] = $s;
	}

	$text = $req->get_param( 'text' );
	if ( is_string( $text ) ) {
		$patch['text'] = mb_substr( trim( wp_strip_all_tags( $text ) ), 0, 3000 );
	}

	$saved = livecrafts_set_patch( $key, $sel, $patch );
	return array( 'ok' => true, 'key' => $key, 'selector' => $sel, 'patch' => $saved );
}

/** Remove the patch for one element. */
function livecrafts_rest_revert( WP_REST_Request $req ) {
	$key = livecrafts_target_key( $req );
	$sel = livecrafts_clean_selector( $req->get_param( 'selector' ) );
	if ( ! $key || $sel === '' ) return new WP_Error( 'livecrafts_bad_request', 'Invalid page or selector.', array( 'status' => 400 ) );
	livecrafts_set_patch( $key, $sel, null );
	return array( 'ok' => true );
}

/**
 * Undo the most recent change: an ACF write (any post) or a patch on this page / site-wide.
 */
function livecrafts_rest_undo( WP_REST_Request $req ) {
	$page = (string) $req->get_param( 'pageKey' );
	if ( ! livecrafts_valid_key( $page ) ) return new WP_Error( 'livecrafts_bad_request', 'Invalid page.', array( 'status' => 400 ) );

	$log = get_option( LIVECRAFTS_LOG, array() );
	$log = is_array( $log ) ? $log : array();
	for ( $i = count( $log ) - 1; $i >= 0; $i-- ) {
		$entry   = $log[ $i ];
		$is_acf  = isset( $entry['type'] ) && $entry['type'] === 'acf';
		$is_el   = isset( $entry['type'] ) && $entry['type'] === 'el';
		if ( ! $is_acf && ! $is_el && $entry['key'] !== $page && $entry['key'] !== 'site' ) continue;

		if ( $is_el ) {
			if ( ! function_exists( 'livecrafts_el_restore' ) || ! current_user_can( 'edit_post', (int) $entry['post'] ) ) {
				return new WP_Error( 'livecrafts_forbidden', 'You cannot undo this change.', array( 'status' => 403 ) );
			}
			$restored = livecrafts_el_restore( $entry );
			if ( is_wp_error( $restored ) ) return $restored; // keep the log entry so the user can retry
			array_splice( $log, $i, 1 );
			update_option( LIVECRAFTS_LOG, $log, false );
			return array( 'ok' => true, 'undone' => $entry['selector'] );
		}

		if ( $is_acf ) {
			// Check permission BEFORE consuming the log entry.
			if ( ! function_exists( 'update_field' ) || ! current_user_can( 'edit_post', (int) $entry['post'] ) ) {
				return new WP_Error( 'livecrafts_forbidden', 'You cannot undo this change.', array( 'status' => 403 ) );
			}
			array_splice( $log, $i, 1 );
			update_option( LIVECRAFTS_LOG, $log, false );
			$restore = ( isset( $entry['raw_before'] ) && $entry['raw_before'] !== null ) ? $entry['raw_before'] : '';
			update_field( $entry['field_key'], $restore, (int) $entry['post'] );
			clean_post_cache( (int) $entry['post'] );
			return array( 'ok' => true, 'undone' => $entry['selector'] );
		}

		array_splice( $log, $i, 1 );
		update_option( LIVECRAFTS_LOG, $log, false );
		$all = livecrafts_get_all();
		if ( empty( $entry['before'] ) ) {
			unset( $all[ $entry['key'] ][ $entry['selector'] ] );
			if ( empty( $all[ $entry['key'] ] ) ) unset( $all[ $entry['key'] ] );
		} else {
			$all[ $entry['key'] ][ $entry['selector'] ] = $entry['before'];
		}
		update_option( LIVECRAFTS_OPT, $all, false );
		return array( 'ok' => true, 'undone' => $entry['selector'] );
	}
	return array( 'ok' => false, 'message' => 'Nothing to undo.' );
}
