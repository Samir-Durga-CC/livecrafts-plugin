<?php
/**
 * REST API: /wp-json/livecrafts/v1/...
 * Auth: a logged-in cookie + X-WP-Nonce (the widget), or an Application Password (the Livecrafts backend).
 * Every route needs the livecrafts_edit capability unless noted.
 *
 *   GET  status                     drafts, conflicts, last release, changes made outside Livecrafts since then
 *   GET  changes                    history (?status=draft|live|discarded &post= &source= &limit= &before=)
 *   GET  changes/<id>               one change with its full before/after
 *   POST changes                    make a draft change {kind, post, target, value, label?, ref?, source?}
 *   POST changes/<id>/revert        drop a draft change / put a live change's old value back as a draft
 *   POST drafts/discard             drop every draft (or ?post=); without ?post= also puts back theme files edited since the last release
 *   POST pages                      new page/post as a draft {title, content?, type?, slug?, parent?, template?}
 *   GET  post                       title/content/excerpt/status of a page as editors see it (?id=&view=draft|live)
 *   GET  deploy/check               what a deploy would do, conflicts included (nothing is written)
 *   POST deploy                     livecrafts_deploy + password {password, notes, force?, ids?}
 *   GET  releases                   deploys, resets, baselines
 *   POST releases/<id>/reset        livecrafts_deploy + password {password, notes}
 *   POST releases/baseline          livecrafts_deploy {notes}
 *   GET  notes  / POST notes        notes of the site and of a page (?post=) {post?, text}
 *   GET  widget-token               a fresh signed token for the logged-in person (cookie auth)
 *   POST preview-token              a 10-minute view-only token: <page url>?lc_preview=<token> shows the drafts
 *   POST connect                    manage_options: the site secret for the Livecrafts backend {rotate?}
 *   GET  debug/target               one ACF/Elementor target: draft value, live value, recent changes
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function livecrafts_rest_permission() {
	return livecrafts_can_edit();
}

function livecrafts_rest_deploy_permission() {
	return livecrafts_can_deploy();
}

add_action( 'rest_api_init', function () {
	$ns    = 'livecrafts/v1';
	$route = function ( $path, $methods, $callback, $permission = 'livecrafts_rest_permission' ) use ( $ns ) {
		register_rest_route( $ns, $path, array( 'methods' => $methods, 'callback' => $callback, 'permission_callback' => $permission ) );
	};
	$route( '/status', 'GET', 'livecrafts_rest_status' );
	$route( '/changes', 'GET', 'livecrafts_rest_changes' );
	$route( '/changes', 'POST', 'livecrafts_rest_change_create' );
	$route( '/changes/(?P<id>\d+)', 'GET', 'livecrafts_rest_change_get' );
	$route( '/changes/(?P<id>\d+)/revert', 'POST', 'livecrafts_rest_change_revert' );
	$route( '/drafts/discard', 'POST', 'livecrafts_rest_discard' );
	$route( '/pages', 'POST', 'livecrafts_rest_page_create' );
	$route( '/post', 'GET', 'livecrafts_rest_post' );
	$route( '/deploy/check', 'GET', 'livecrafts_rest_deploy_check' );
	$route( '/deploy', 'POST', 'livecrafts_rest_deploy', 'livecrafts_rest_deploy_permission' );
	$route( '/releases', 'GET', 'livecrafts_rest_releases' );
	$route( '/releases/(?P<id>\d+)/reset', 'POST', 'livecrafts_rest_reset', 'livecrafts_rest_deploy_permission' );
	$route( '/releases/baseline', 'POST', 'livecrafts_rest_baseline', 'livecrafts_rest_deploy_permission' );
	$route( '/notes', 'GET', 'livecrafts_rest_notes_get' );
	$route( '/notes', 'POST', 'livecrafts_rest_notes_set' );
	$route( '/widget-token', 'GET', 'livecrafts_rest_widget_token' );
	$route( '/preview-token', 'POST', 'livecrafts_rest_preview_token' );
	$route( '/connect', 'POST', 'livecrafts_rest_connect', function () { return current_user_can( 'manage_options' ); } );
	$route( '/debug/target', 'GET', 'livecrafts_rest_debug_target' );
} );

/** Options every change-making route shares: who it is credited to, where it came from, the backend's reference. */
function livecrafts_rest_change_opts( WP_REST_Request $req ) {
	return array( 'actor' => livecrafts_actor( $req ), 'source' => livecrafts_request_source( $req ), 'ref' => sanitize_text_field( (string) $req->get_param( 'ref' ) ) );
}

/** The highest change id at a moment (UTC datetime). */
function livecrafts_change_id_at( $datetime ) {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(id) FROM ' . livecrafts_table( 'changes' ) . ' WHERE created_at <= %s', $datetime ) );
}

function livecrafts_rest_status() {
	global $wpdb;
	$check  = livecrafts_deploy_check();
	$drafts = array();
	foreach ( $check['plan'] as $p ) {
		$o = array( 'type' => $p['type'], 'id' => $p['id'], 'label' => $p['label'] );
		if ( $p['type'] === 'post' ) { $o['url'] = get_permalink( $p['id'] ); $o['preview'] = get_preview_post_link( $p['id'] ); }
		$drafts[] = array( 'object' => $o, 'changes' => array_map( 'livecrafts_change_public', $p['changes'] ) );
	}
	$files = livecrafts_files_pending(); // theme files: live at once, not yet accepted by a release
	if ( $files ) {
		$drafts[] = array( 'object' => array( 'type' => 'file', 'id' => 0, 'label' => 'Theme files (already live)' ), 'changes' => array_map( 'livecrafts_change_public', array_reverse( $files ) ) );
	}
	$releases = livecrafts_releases( 1 );
	$last     = $releases ? $releases[0] : null;
	$since    = $last ? livecrafts_change_id_at( gmdate( 'Y-m-d H:i:s', strtotime( $last['at'] ) ) ) : 0;
	$outside  = livecrafts_changes_query( array( 'status' => 'live', 'kind' => 'external.edit', 'since_id' => $since, 'limit' => 50 ) );
	return array(
		'ok'        => true,
		'drafts'    => array( 'count' => (int) array_sum( array_map( function ( $d ) { return count( $d['changes'] ); }, $drafts ) ), 'objects' => $drafts ),
		'files'     => count( $files ),
		'conflicts' => $check['conflicts'],
		'broken'    => $check['errors'],
		'last_release'                  => $last,
		'outside_changes_since_release' => array_map( 'livecrafts_change_public', $outside ),
		'legacy_overlay'                => livecrafts_migration_report(),
		'last_fatal'                    => get_option( 'livecrafts_last_fatal', null ),
		'deploy'    => array( 'allowed' => livecrafts_can_deploy(), 'asks_for' => livecrafts_has_deploy_password() ? 'deploy password' : 'your WordPress password' ),
		'note'      => 'Drafts are seen only by logged-in editors. Visitors see the live site until a person deploys.',
	);
}

function livecrafts_rest_changes( WP_REST_Request $req ) {
	$args = array( 'limit' => $req->get_param( 'limit' ) ? (int) $req->get_param( 'limit' ) : 50 );
	$status = (string) $req->get_param( 'status' );
	if ( in_array( $status, array( 'draft', 'live', 'discarded' ), true ) ) $args['status'] = $status;
	if ( $req->get_param( 'post' ) ) { $args['object_type'] = 'post'; $args['object_id'] = (int) $req->get_param( 'post' ); }
	if ( $req->get_param( 'css' ) ) { $args['object_type'] = 'css'; $args['object_id'] = 0; }
	if ( $req->get_param( 'source' ) ) $args['source'] = sanitize_key( $req->get_param( 'source' ) );
	if ( $req->get_param( 'before' ) ) $args['before_id'] = (int) $req->get_param( 'before' );
	if ( $req->get_param( 'release' ) ) $args['release_id'] = (int) $req->get_param( 'release' );
	return array( 'ok' => true, 'changes' => array_map( 'livecrafts_change_public', livecrafts_changes_query( $args ) ) );
}

function livecrafts_rest_change_get( WP_REST_Request $req ) {
	$c = livecrafts_change_get( (int) $req['id'] );
	if ( ! $c ) return new WP_Error( 'livecrafts_unknown', 'Unknown change.', array( 'status' => 404 ) );
	$out = livecrafts_change_public( $c, true );
	if ( $c['kind'] === 'external.edit' || $c['kind'] === 'restore' ) {
		$before = livecrafts_change_snapshot( $c['id'], 'before' );
		$after  = livecrafts_change_snapshot( $c['id'], 'after' );
		$out['diff'] = livecrafts_snapshot_diff_view( $before, $after );
	}
	return array( 'ok' => true, 'change' => $out );
}

/** Before/after of each changed field of two snapshots, readable (Elementor trees as pretty JSON). */
function livecrafts_snapshot_diff_view( $before, $after ) {
	if ( ! $before || ! $after ) return array();
	$out = array();
	foreach ( livecrafts_data_diff( $before, $after ) as $path ) {
		$get = function ( $data ) use ( $path ) {
			if ( $path === 'css' ) return $data['css'];
			list( $part, $key ) = explode( '.', $path, 2 );
			$v = isset( $data[ $part ][ $key ] ) ? $data[ $part ][ $key ] : '';
			if ( $key === '_elementor_data' && is_string( $v ) ) { $d = json_decode( $v, true ); if ( $d !== null ) $v = $d; }
			return is_string( $v ) ? $v : wp_json_encode( $v, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		};
		$out[] = array( 'path' => $path, 'before' => $get( $before ), 'after' => $get( $after ) );
	}
	return $out;
}

function livecrafts_rest_change_create( WP_REST_Request $req ) {
	$kind = (string) $req->get_param( 'kind' );
	$def  = livecrafts_kind( $kind );
	if ( ! $def || $kind === 'object.restore' ) return livecrafts_bad_value( 'Unknown kind. Use post.field, acf.field, el.setting or css.block.' );
	$object_type = $def['object'];
	$object_id   = $object_type === 'css' ? 0 : (int) $req->get_param( 'post' );
	$opts        = livecrafts_rest_change_opts( $req ) + array( 'args' => array( 'label' => (string) $req->get_param( 'label' ) ) );
	return livecrafts_change_create( $kind, $object_type, $object_id, (string) $req->get_param( 'target' ), $req->get_param( 'value' ), $opts );
}

function livecrafts_rest_change_revert( WP_REST_Request $req ) {
	return livecrafts_change_revert( (int) $req['id'], livecrafts_rest_change_opts( $req ) );
}

function livecrafts_rest_discard( WP_REST_Request $req ) {
	$post   = (int) $req->get_param( 'post' );
	$object = $post ? array( 'post', $post ) : ( $req->get_param( 'css' ) ? array( 'css', 0 ) : null );
	if ( $post && ! current_user_can( 'edit_post', $post ) ) return new WP_Error( 'livecrafts_forbidden', 'You cannot edit this page.', array( 'status' => 403 ) );
	$report = array();
	$n      = livecrafts_discard_drafts( $object, livecrafts_actor( $req ), 'discard', $report );
	$files  = isset( $report['files'] ) ? (int) $report['files'] : 0;
	$errors = isset( $report['file_errors'] ) ? $report['file_errors'] : array();
	$note   = $n ? 'The drafts were dropped. The live site was not changed by that.' : ( $files || $errors ? '' : 'There were no drafts.' );
	if ( $files ) $note = trim( $note . ' ' . $files . ' theme file' . ( $files === 1 ? '' : 's' ) . ' put back as before (they were live at once).' );
	if ( $errors ) $note = trim( $note . ' ' . count( $errors ) . ' theme file(s) could not be put back: ' . implode( '; ', wp_list_pluck( $errors, 'error' ) ) );
	return array( 'ok' => ! $errors, 'discarded' => $n, 'files_restored' => $files, 'file_errors' => $errors, 'note' => $note );
}

function livecrafts_rest_page_create( WP_REST_Request $req ) {
	return livecrafts_page_create( $req->get_params(), livecrafts_rest_change_opts( $req ) );
}

function livecrafts_rest_post( WP_REST_Request $req ) {
	$id   = (int) $req->get_param( 'id' );
	$view = $req->get_param( 'view' ) === 'live' ? 'live' : 'draft';
	if ( ! $id || ! get_post( $id ) || ! current_user_can( 'edit_post', $id ) ) return new WP_Error( 'livecrafts_no_page', 'Unknown page, or you cannot edit it.', array( 'status' => 404 ) );
	$data = $view === 'live' ? livecrafts_post_data( $id ) : livecrafts_draft_data( 'post', $id );
	if ( is_wp_error( $data ) ) return $data;
	return array(
		'ok' => true, 'id' => $id, 'view' => $view, 'type' => get_post_type( $id ),
		'title' => $data['fields']['title'], 'status' => $data['fields']['status'], 'excerpt' => $data['fields']['excerpt'],
		'content' => $data['fields']['content'], 'template' => isset( $data['meta']['_wp_page_template'] ) ? $data['meta']['_wp_page_template'] : 'default',
		'elementor' => isset( $data['meta']['_elementor_data'] ) && $data['meta']['_elementor_data'] !== '',
		'url' => get_permalink( $id ), 'preview' => get_preview_post_link( $id ),
		'drafts' => count( livecrafts_draft_changes( 'post', $id ) ),
	);
}

function livecrafts_rest_deploy_check() {
	$check = livecrafts_deploy_check();
	$items = array();
	foreach ( $check['plan'] as $p ) $items[] = array( 'object' => $p['label'], 'changes' => wp_list_pluck( $p['changes'], 'summary' ) );
	$files = livecrafts_files_pending();
	if ( $files ) $items[] = array( 'object' => 'Theme files (already live)', 'changes' => wp_list_pluck( array_reverse( $files ), 'summary' ) );
	return array( 'ok' => ! $check['errors'], 'items' => $items, 'conflicts' => $check['conflicts'], 'errors' => $check['errors'],
		'asks_for' => livecrafts_has_deploy_password() ? 'deploy password' : 'your WordPress password' );
}

function livecrafts_rest_deploy( WP_REST_Request $req ) {
	$user = get_current_user_id();
	$ok   = livecrafts_check_deploy_password( $user, $req->get_param( 'password' ) );
	if ( is_wp_error( $ok ) ) return $ok;
	$notes = trim( sanitize_textarea_field( (string) $req->get_param( 'notes' ) ) );
	return livecrafts_deploy( $user, $notes, array( 'force' => (bool) $req->get_param( 'force' ), 'ids' => $req->get_param( 'ids' ) ) );
}

function livecrafts_rest_releases( WP_REST_Request $req ) {
	return array( 'ok' => true, 'releases' => livecrafts_releases( $req->get_param( 'limit' ) ? (int) $req->get_param( 'limit' ) : 50 ) );
}

function livecrafts_rest_reset( WP_REST_Request $req ) {
	$user = get_current_user_id();
	$ok   = livecrafts_check_deploy_password( $user, $req->get_param( 'password' ) );
	if ( is_wp_error( $ok ) ) return $ok;
	$source = $req->get_param( 'source' ) === 'admin-panel' ? 'admin-panel' : 'widget';
	return livecrafts_reset_to( (int) $req['id'], $user, trim( sanitize_textarea_field( (string) $req->get_param( 'notes' ) ) ), $source );
}

function livecrafts_rest_baseline( WP_REST_Request $req ) {
	return array( 'ok' => true, 'release' => livecrafts_mark_baseline( get_current_user_id(), sanitize_textarea_field( (string) $req->get_param( 'notes' ) ) ) );
}

function livecrafts_rest_notes_get( WP_REST_Request $req ) {
	$post = (int) $req->get_param( 'post' );
	if ( $post && ! current_user_can( 'edit_post', $post ) ) return new WP_Error( 'livecrafts_forbidden', 'You cannot read this page.', array( 'status' => 403 ) );
	return array( 'ok' => true, 'site' => livecrafts_notes_get( 0 ), 'page' => $post ? livecrafts_notes_get( $post ) : null );
}

function livecrafts_rest_notes_set( WP_REST_Request $req ) {
	$post = (int) $req->get_param( 'post' );
	if ( $post && ! current_user_can( 'edit_post', $post ) ) return new WP_Error( 'livecrafts_forbidden', 'You cannot edit this page.', array( 'status' => 403 ) );
	$r = livecrafts_notes_set( $post, $req->get_param( 'text' ), livecrafts_actor( $req ) );
	return is_wp_error( $r ) ? $r : array( 'ok' => true, 'notes' => $r );
}

function livecrafts_rest_widget_token() {
	return array( 'ok' => true, 'token' => livecrafts_widget_token( get_current_user_id() ), 'expires_in' => LIVECRAFTS_TOKEN_TTL );
}

function livecrafts_rest_preview_token() {
	return array( 'ok' => true, 'token' => livecrafts_preview_token( get_current_user_id() ), 'param' => 'lc_preview', 'expires_in' => 10 * MINUTE_IN_SECONDS );
}

function livecrafts_rest_connect( WP_REST_Request $req ) {
	return array( 'ok' => true, 'secret' => livecrafts_secret( (bool) $req->get_param( 'rotate' ) ), 'site' => untrailingslashit( home_url() ), 'version' => LIVECRAFTS_VERSION );
}

/** GET debug/target?targetId=acf:<key>:<post> | el:<post>:<id>:<setting> - the value in the draft and on the live site. */
function livecrafts_rest_debug_target( WP_REST_Request $req ) {
	$raw = (string) $req->get_param( 'targetId' );
	$t   = livecrafts_parse_target( $raw );
	if ( ! $t ) return new WP_Error( 'livecrafts_bad_request', 'Invalid target.', array( 'status' => 400 ) );
	if ( ! current_user_can( 'edit_post', $t['post'] ) ) return new WP_Error( 'livecrafts_forbidden', 'You cannot read this content.', array( 'status' => 403 ) );
	$live  = livecrafts_post_data( $t['post'] );
	$draft = livecrafts_draft_data( 'post', $t['post'] );
	if ( ! $live ) return new WP_Error( 'livecrafts_no_page', 'That page does not exist.', array( 'status' => 404 ) );
	if ( is_wp_error( $draft ) ) $draft = $live;

	if ( $t['type'] === 'el' ) {
		$kind   = 'el.setting';
		$target = $t['id'] . ':' . $t['path'];
		$d      = livecrafts_el_read( livecrafts_el_tree( $draft ), $t['id'], $t['path'] );
		$l      = livecrafts_el_read( livecrafts_el_tree( $live ), $t['id'], $t['path'] );
		$out    = array( 'kind' => 'elementor', 'found' => $d['found'], 'widget' => $d['type'], 'setting' => $t['path'], 'draft_value' => $d['value'], 'live_value' => $l['value'],
			'where_stored' => 'wp_postmeta "_elementor_data" (one JSON tree for the whole page) of post ' . $t['post'] );
		if ( is_array( $d['value'] ) && ! empty( $d['value']['id'] ) ) $out['attachment'] = livecrafts_attachment_info( $d['value']['id'] );
	} elseif ( $t['type'] === 'acfv' ) {
		$kind   = 'acf.value';
		$target = $t['name'];
		$field  = livecrafts_acf_field_of( $draft, $t['name'] );
		if ( ! $field ) return new WP_Error( 'livecrafts_no_field', 'There is no ACF value "' . $t['name'] . '" on this page.', array( 'status' => 404 ) );
		$out = array( 'kind' => 'acf', 'field' => array( 'key' => $field['key'], 'name' => $field['name'], 'label' => $field['label'], 'type' => $field['type'] ), 'meta_name' => $t['name'],
			'draft_value' => isset( $draft['meta'][ $t['name'] ] ) ? $draft['meta'][ $t['name'] ] : '', 'live_value' => isset( $live['meta'][ $t['name'] ] ) ? $live['meta'][ $t['name'] ] : '',
			'where_stored' => 'wp_postmeta "' . $t['name'] . '" of post ' . $t['post'] );
	} else {
		$field = livecrafts_acf_field( $t['key'], $t['post'] );
		if ( ! $field ) return new WP_Error( 'livecrafts_no_field', 'That ACF field is not part of this page.', array( 'status' => 404 ) );
		$kind   = 'acf.field';
		$target = $t['key'];
		$name   = $field['name'];
		$out    = array( 'kind' => 'acf', 'field' => array( 'key' => $field['key'], 'name' => $name, 'label' => $field['label'], 'type' => $field['type'], 'group' => $field['group_title'] ),
			'draft_value' => isset( $draft['meta'][ $name ] ) ? $draft['meta'][ $name ] : '', 'live_value' => isset( $live['meta'][ $name ] ) ? $live['meta'][ $name ] : '',
			'where_stored' => 'wp_postmeta "' . $name . '" of post ' . $t['post'] );
		if ( $field['type'] === 'image' && $out['draft_value'] ) $out['attachment'] = livecrafts_attachment_info( $out['draft_value'] );
	}
	$recent = array();
	foreach ( livecrafts_changes_query( array( 'object_type' => 'post', 'object_id' => $t['post'], 'kind' => $kind, 'limit' => 100 ) ) as $c ) {
		if ( $c['target'] === $target ) $recent[] = livecrafts_change_public( $c );
		if ( count( $recent ) >= 5 ) break;
	}
	$out['target']         = $raw;
	$out['post']           = array( 'id' => $t['post'], 'type' => get_post_type( $t['post'] ), 'title' => get_the_title( $t['post'] ), 'edit_link' => get_edit_post_link( $t['post'], 'raw' ) );
	$out['recent_changes'] = $recent;
	return $out;
}
