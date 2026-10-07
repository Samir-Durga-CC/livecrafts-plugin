<?php
/**
 * The change ledger: one row per change, whatever made it (the assistant, the widget, WP admin, the Elementor editor).
 * It is what "status" and "history" show, what the assistant reads to know what happened, and what Revert works from.
 *
 *   status  draft      saved, seen only by editors (preview); not on the live site yet
 *           live       on the live site (deployed, made outside Livecrafts, or a reset)
 *           discarded  dropped from the draft before it was deployed
 *   kind    how the change is applied - see kinds.php (post.field, acf.field, el.setting, css.block, post.create, post.restore ...)
 *           external.edit = made outside Livecrafts (recorded with before/after snapshots), restore = part of a reset
 *   payload before / after values and what is needed to apply the change again on top of the live site
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** Insert one change. Returns its id, or 0 when it could not be stored. */
function livecrafts_change_insert( array $c ) {
	global $wpdb;
	$payload = wp_json_encode( isset( $c['payload'] ) ? $c['payload'] : array() );
	if ( $payload === false ) return 0; // not valid UTF-8: refuse rather than store a broken record
	$now = livecrafts_now();
	$ok  = $wpdb->insert( livecrafts_table( 'changes' ), array(
		'status'      => $c['status'],
		'source'      => $c['source'],
		'user_id'     => isset( $c['user_id'] ) ? (int) $c['user_id'] : get_current_user_id(),
		'object_type' => $c['object_type'],
		'object_id'   => (int) $c['object_id'],
		'kind'        => $c['kind'],
		'target'      => isset( $c['target'] ) ? mb_substr( (string) $c['target'], 0, 191 ) : '',
		'summary'     => isset( $c['summary'] ) ? mb_substr( (string) $c['summary'], 0, 255 ) : '',
		'payload'     => $payload,
		'release_id'  => isset( $c['release_id'] ) ? (int) $c['release_id'] : null,
		'reverts'     => isset( $c['reverts'] ) ? (int) $c['reverts'] : null,
		'ref'         => isset( $c['ref'] ) ? mb_substr( (string) $c['ref'], 0, 64 ) : '',
		'created_at'  => $now,
		'updated_at'  => $now,
	) );
	return $ok ? (int) $wpdb->insert_id : 0;
}

function livecrafts_change_update( $id, array $fields ) {
	global $wpdb;
	if ( array_key_exists( 'payload', $fields ) ) $fields['payload'] = wp_json_encode( $fields['payload'] );
	$fields['updated_at'] = livecrafts_now();
	return false !== $wpdb->update( livecrafts_table( 'changes' ), $fields, array( 'id' => (int) $id ) );
}

function livecrafts_change_get( $id ) {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . livecrafts_table( 'changes' ) . ' WHERE id = %d', $id ), ARRAY_A );
	return $row ? livecrafts_change_row( $row ) : null;
}

function livecrafts_change_row( array $row ) {
	foreach ( array( 'id', 'user_id', 'object_id', 'release_id', 'reverts' ) as $k ) {
		$row[ $k ] = $row[ $k ] === null ? null : (int) $row[ $k ];
	}
	$payload        = json_decode( (string) $row['payload'], true );
	$row['payload'] = is_array( $payload ) ? $payload : array();
	return $row;
}

/**
 * Changes, newest first (or oldest first with oldest_first).
 * $args: status (string|array), object_type, object_id, source, kind, since_id, before_id, release_id, limit (max 500).
 */
function livecrafts_changes_query( array $args = array() ) {
	global $wpdb;
	$where = array( '1=1' );
	$vals  = array();
	if ( ! empty( $args['status'] ) ) {
		$list    = (array) $args['status'];
		$where[] = 'status IN (' . implode( ',', array_fill( 0, count( $list ), '%s' ) ) . ')';
		$vals    = array_merge( $vals, $list );
	}
	foreach ( array( 'object_type', 'source', 'kind' ) as $col ) {
		if ( ! empty( $args[ $col ] ) ) { $where[] = "$col = %s"; $vals[] = (string) $args[ $col ]; }
	}
	if ( isset( $args['object_id'] ) && $args['object_id'] !== null ) { $where[] = 'object_id = %d'; $vals[] = (int) $args['object_id']; }
	if ( ! empty( $args['since_id'] ) )   { $where[] = 'id > %d';         $vals[] = (int) $args['since_id']; }
	if ( ! empty( $args['before_id'] ) )  { $where[] = 'id < %d';         $vals[] = (int) $args['before_id']; }
	if ( ! empty( $args['release_id'] ) ) { $where[] = 'release_id = %d'; $vals[] = (int) $args['release_id']; }

	$order = empty( $args['oldest_first'] ) ? 'DESC' : 'ASC';
	$limit = isset( $args['limit'] ) ? max( 1, min( 500, (int) $args['limit'] ) ) : 100;
	$sql   = 'SELECT * FROM ' . livecrafts_table( 'changes' ) . ' WHERE ' . implode( ' AND ', $where ) . " ORDER BY id $order LIMIT $limit";
	$rows  = $wpdb->get_results( $vals ? $wpdb->prepare( $sql, $vals ) : $sql, ARRAY_A );
	return array_map( 'livecrafts_change_row', (array) $rows );
}

/** The draft changes of one object, in the order they were made (= the order they are applied). */
function livecrafts_draft_changes( $object_type, $object_id ) {
	return livecrafts_changes_query( array( 'status' => 'draft', 'object_type' => $object_type, 'object_id' => $object_id, 'oldest_first' => true, 'limit' => 500 ) );
}

/** How many draft changes there are (site-wide). */
function livecrafts_draft_count() {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . livecrafts_table( 'changes' ) . ' WHERE status = %s', 'draft' ) );
}

/** "Page “About”", "Additional CSS" ... */
function livecrafts_object_label( $object_type, $object_id ) {
	if ( $object_type === 'css' ) return 'Additional CSS';
	$post = get_post( $object_id );
	if ( ! $post ) return 'Deleted item #' . (int) $object_id;
	$type = get_post_type_object( $post->post_type );
	return ( $type ? $type->labels->singular_name : $post->post_type ) . ' “' . ( $post->post_title !== '' ? $post->post_title : '#' . $post->ID ) . '”';
}

/** Long values cut short for lists (the full change is available by id). */
function livecrafts_shorten( $value, $max = 300 ) {
	if ( is_string( $value ) ) return mb_strlen( $value ) > $max ? mb_substr( $value, 0, $max ) . '…' : $value;
	if ( is_array( $value ) ) {
		$out = array();
		foreach ( $value as $k => $v ) $out[ $k ] = livecrafts_shorten( $v, $max );
		return $out;
	}
	return $value;
}

/** A change as the REST API and the assistant see it. */
function livecrafts_change_public( array $c, $full = false ) {
	$user = $c['user_id'] ? get_userdata( $c['user_id'] ) : null;
	$out  = array(
		'id'      => $c['id'],
		'status'  => $c['status'],
		'source'  => $c['source'],
		'user'    => $user ? array( 'id' => (int) $user->ID, 'login' => $user->user_login, 'name' => $user->display_name ) : null,
		'object'  => array( 'type' => $c['object_type'], 'id' => $c['object_id'], 'label' => livecrafts_object_label( $c['object_type'], $c['object_id'] ) ),
		'kind'    => $c['kind'],
		'target'  => $c['target'],
		'summary' => $c['summary'],
		'release' => $c['release_id'],
		'reverts' => $c['reverts'],
		'ref'     => $c['ref'],
		'at'      => gmdate( 'c', strtotime( $c['created_at'] . ' UTC' ) ),
		'updated' => gmdate( 'c', strtotime( $c['updated_at'] . ' UTC' ) ),
		'payload' => $full ? $c['payload'] : livecrafts_shorten( $c['payload'] ),
	);
	if ( $c['object_type'] === 'post' && get_post( $c['object_id'] ) ) $out['object']['url'] = get_permalink( $c['object_id'] );
	return $out;
}

/* ------------------------------------------------------------------ releases */

function livecrafts_release_insert( $kind, $user_id, $notes, $summary = '' ) {
	global $wpdb;
	$wpdb->insert( livecrafts_table( 'releases' ), array(
		'kind'       => $kind,
		'user_id'    => (int) $user_id,
		'notes'      => mb_substr( (string) $notes, 0, 5000 ),
		'summary'    => mb_substr( (string) $summary, 0, 255 ),
		'created_at' => livecrafts_now(),
	) );
	return (int) $wpdb->insert_id;
}

/** Close a release: remember its place in the snapshot history (resets use it) and its one-line summary. */
function livecrafts_release_close( $release_id, $summary ) {
	global $wpdb;
	$mark = (int) $wpdb->get_var( 'SELECT MAX(id) FROM ' . livecrafts_table( 'snapshots' ) );
	$wpdb->update( livecrafts_table( 'releases' ), array( 'summary' => mb_substr( (string) $summary, 0, 255 ), 'last_snapshot_id' => $mark ), array( 'id' => (int) $release_id ) );
}

function livecrafts_release_get( $id ) {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . livecrafts_table( 'releases' ) . ' WHERE id = %d', $id ), ARRAY_A );
	return $row ? livecrafts_release_public( $row ) : null;
}

function livecrafts_releases( $limit = 50 ) {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . livecrafts_table( 'releases' ) . ' ORDER BY id DESC LIMIT %d', max( 1, min( 200, (int) $limit ) ) ), ARRAY_A );
	return array_map( 'livecrafts_release_public', (array) $rows );
}

function livecrafts_release_public( array $r ) {
	global $wpdb;
	$user = $r['user_id'] ? get_userdata( (int) $r['user_id'] ) : null;
	return array(
		'id'      => (int) $r['id'],
		'kind'    => $r['kind'],
		'user'    => $user ? array( 'id' => (int) $user->ID, 'login' => $user->user_login, 'name' => $user->display_name ) : null,
		'notes'   => $r['notes'],
		'summary' => $r['summary'],
		'changes' => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . livecrafts_table( 'changes' ) . ' WHERE release_id = %d', $r['id'] ) ),
		'mark'    => (int) $r['last_snapshot_id'],
		'at'      => gmdate( 'c', strtotime( $r['created_at'] . ' UTC' ) ),
	);
}
