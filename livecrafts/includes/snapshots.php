<?php
/**
 * Object data: everything Livecrafts can change on one object, as a plain array. The same shape is used for the live
 * state, the draft state (live + draft changes), and the snapshots that resets restore.
 *
 *   post  array( 'fields' => array( title, content, excerpt, status ), 'meta' => array( key => value ) )   tracked meta only
 *   css   array( 'css' => string )                                                                       Additional CSS of the active theme
 *
 * Snapshots are stored compressed in {prefix}livecrafts_snapshots, one row per object per phase (before / after) of a
 * release or of a change made outside Livecrafts.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** Meta that is not content: caches, locks, bookkeeping. Never copied into drafts, never restored. */
function livecrafts_meta_tracked( $key ) {
	static $skip = array(
		'_edit_lock', '_edit_last', '_encloseme', '_pingme', '_wp_old_slug', '_wp_old_date', '_wp_desired_post_slug',
		'_wp_trash_meta_status', '_wp_trash_meta_time', '_wp_trash_meta_comments_status',
		'_elementor_css', '_elementor_element_cache', '_elementor_page_assets', '_elementor_controls_usage',
		'_elementor_screenshot', '_elementor_screenshot_failed',
	);
	$key = (string) $key;
	if ( $key === '' || in_array( $key, $skip, true ) ) return false;
	if ( strpos( $key, '_livecrafts' ) === 0 || strpos( $key, '_oembed_' ) === 0 ) return false;
	return true;
}

/** Post types whose changes are tracked: everything public except media (pages, posts, products ...), menu links, Elementor and block templates. */
function livecrafts_tracked_post_type( $type ) {
	if ( $type === 'attachment' || $type === 'revision' ) return false;
	if ( in_array( $type, array( 'nav_menu_item', 'elementor_library', 'wp_template', 'wp_template_part' ), true ) ) return true;
	$obj = get_post_type_object( $type );
	return $obj && $obj->public;
}

/**
 * "Livecrafts is writing" flag (nestable). While it is on, the outside-change watcher ignores saves (they are ours)
 * and the preview never swaps values.
 */
function livecrafts_writing( $on = null ) {
	static $depth = 0;
	if ( $on === true ) $depth++;
	elseif ( $on === false ) $depth = max( 0, $depth - 1 );
	return $depth > 0;
}

/** Equal for our purposes: scalars compare as text ("12" = 12), arrays by content. */
function livecrafts_same( $a, $b ) {
	if ( ( is_scalar( $a ) || $a === null ) && ( is_scalar( $b ) || $b === null ) ) return (string) $a === (string) $b;
	return wp_json_encode( $a ) === wp_json_encode( $b );
}

/** The LIVE state of a post (never the draft preview). */
function livecrafts_post_data( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post ) return null;
	livecrafts_preview_suspend( true );
	$meta = array();
	foreach ( (array) get_post_meta( $post->ID ) as $key => $values ) {
		if ( livecrafts_meta_tracked( $key ) && isset( $values[0] ) ) $meta[ $key ] = maybe_unserialize( $values[0] );
	}
	livecrafts_preview_suspend( false );
	ksort( $meta );
	return array(
		'fields' => array( 'title' => $post->post_title, 'content' => $post->post_content, 'excerpt' => $post->post_excerpt, 'status' => $post->post_status ),
		'meta'   => $meta,
	);
}

/** The LIVE Additional CSS of the active theme. */
function livecrafts_css_data() {
	livecrafts_preview_suspend( true );
	$css = wp_get_custom_css();
	livecrafts_preview_suspend( false );
	return array( 'css' => (string) $css );
}

function livecrafts_object_data( $object_type, $object_id ) {
	if ( $object_type === 'css' ) return livecrafts_css_data();
	if ( $object_type === 'post' ) return livecrafts_post_data( $object_id );
	return null;
}

/** What differs between two states of an object: "fields.title", "meta._elementor_data", "css" ... */
function livecrafts_data_diff( $a, $b ) {
	if ( ! is_array( $a ) || ! is_array( $b ) ) return array( '*' );
	if ( isset( $a['css'] ) || isset( $b['css'] ) ) {
		return livecrafts_same( isset( $a['css'] ) ? $a['css'] : '', isset( $b['css'] ) ? $b['css'] : '' ) ? array() : array( 'css' );
	}
	$out = array();
	foreach ( array( 'fields', 'meta' ) as $part ) {
		$x = isset( $a[ $part ] ) ? $a[ $part ] : array();
		$y = isset( $b[ $part ] ) ? $b[ $part ] : array();
		foreach ( array_unique( array_merge( array_keys( $x ), array_keys( $y ) ) ) as $k ) {
			if ( ! array_key_exists( $k, $x ) || ! array_key_exists( $k, $y ) || ! livecrafts_same( $x[ $k ], $y[ $k ] ) ) $out[] = $part . '.' . $k;
		}
	}
	return $out;
}

/**
 * Put an object back to a stored state, through WordPress APIs (a new WordPress revision is made; Elementor rebuilds
 * its CSS on the next view). Returns true or WP_Error.
 */
function livecrafts_object_restore( $object_type, $object_id, array $state ) {
	livecrafts_writing( true );
	try {
		if ( $object_type === 'css' ) {
			$r = wp_update_custom_css_post( (string) $state['css'] );
			return is_wp_error( $r ) ? $r : true;
		}
		if ( ! get_post( $object_id ) ) return new WP_Error( 'livecrafts_gone', 'That item no longer exists.', array( 'status' => 404 ) );
		$f = $state['fields'];
		$r = wp_update_post( wp_slash( array(
			'ID' => $object_id, 'post_title' => $f['title'], 'post_content' => $f['content'], 'post_excerpt' => $f['excerpt'], 'post_status' => $f['status'],
		) ), true );
		if ( is_wp_error( $r ) ) return $r;
		$live = livecrafts_post_data( $object_id );
		foreach ( $state['meta'] as $key => $value ) {
			if ( ! array_key_exists( $key, $live['meta'] ) || ! livecrafts_same( $live['meta'][ $key ], $value ) ) update_post_meta( $object_id, $key, wp_slash( $value ) );
		}
		foreach ( array_diff_key( $live['meta'], $state['meta'] ) as $key => $unused ) delete_post_meta( $object_id, $key );
		livecrafts_el_flush( $object_id );
		clean_post_cache( $object_id );
		return true;
	} finally {
		livecrafts_writing( false );
	}
}

/* ------------------------------------------------------------------ snapshot storage */

function livecrafts_snapshot_save( $object_type, $object_id, $phase, $data, $release_id = null, $change_id = null ) {
	global $wpdb;
	$json = wp_json_encode( $data );
	if ( $json === false ) return 0;
	$blob = function_exists( 'gzcompress' ) ? 'z:' . base64_encode( gzcompress( $json, 6 ) ) : 'j:' . $json;
	$wpdb->insert( livecrafts_table( 'snapshots' ), array(
		'release_id'  => $release_id ? (int) $release_id : null,
		'change_id'   => $change_id ? (int) $change_id : null,
		'object_type' => $object_type,
		'object_id'   => (int) $object_id,
		'phase'       => $phase,
		'data'        => $blob,
		'created_at'  => livecrafts_now(),
	) );
	return (int) $wpdb->insert_id;
}

function livecrafts_snapshot_decode( $blob ) {
	$blob = (string) $blob;
	$json = strpos( $blob, 'z:' ) === 0 ? gzuncompress( base64_decode( substr( $blob, 2 ) ) ) : substr( $blob, 2 );
	$data = json_decode( (string) $json, true );
	return is_array( $data ) ? $data : null;
}

function livecrafts_snapshot_get( $id ) {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . livecrafts_table( 'snapshots' ) . ' WHERE id = %d', $id ), ARRAY_A );
	if ( ! $row ) return null;
	$row['data'] = livecrafts_snapshot_decode( $row['data'] );
	return $row;
}

/** The snapshot of a change made outside Livecrafts (phase before / after). */
function livecrafts_change_snapshot( $change_id, $phase ) {
	global $wpdb;
	$blob = $wpdb->get_var( $wpdb->prepare( 'SELECT data FROM ' . livecrafts_table( 'snapshots' ) . ' WHERE change_id = %d AND phase = %s ORDER BY id DESC LIMIT 1', $change_id, $phase ) );
	return $blob ? livecrafts_snapshot_decode( $blob ) : null;
}

/**
 * The state an object had at a point in the snapshot history ($mark = a release's last_snapshot_id):
 * its latest "after" snapshot at or before the mark; if it was not changed before then, the first "before" snapshot
 * taken after the mark. null = no recorded change after the mark (nothing to restore).
 */
function livecrafts_state_at( $object_type, $object_id, $mark ) {
	global $wpdb;
	$t    = livecrafts_table( 'snapshots' );
	$blob = $wpdb->get_var( $wpdb->prepare( "SELECT data FROM $t WHERE object_type = %s AND object_id = %d AND phase = 'after' AND id <= %d ORDER BY id DESC LIMIT 1", $object_type, $object_id, $mark ) );
	if ( ! $blob ) {
		$blob = $wpdb->get_var( $wpdb->prepare( "SELECT data FROM $t WHERE object_type = %s AND object_id = %d AND phase = 'before' AND id > %d ORDER BY id ASC LIMIT 1", $object_type, $object_id, $mark ) );
	}
	return $blob ? livecrafts_snapshot_decode( $blob ) : null;
}

/** Objects with a recorded change after the mark. */
function livecrafts_objects_changed_after( $mark ) {
	global $wpdb;
	return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT DISTINCT object_type, object_id FROM ' . livecrafts_table( 'snapshots' ) . ' WHERE id > %d', $mark ), ARRAY_A );
}
