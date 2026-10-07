<?php
/**
 * Deploy and reset - the only two ways Livecrafts changes what visitors see. Both need the deploy capability and the
 * deploy password (auth.php), and both create a release.
 *
 * Deploy: for every object with drafts
 *   1. check everything first - nothing is written while any object has a problem:
 *      read the LIVE state, make sure every draft target still starts from the value the draft started from (otherwise
 *      someone changed it meanwhile: a conflict the person must confirm), apply the drafts on top of the live state
 *   2. write the result with the source's own API: wp_update_post, Elementor's Document::save (Elementor validates,
 *      makes its revision and rebuilds the page CSS), update_field for ACF, wp_update_custom_css_post for CSS
 *   3. read it back; if it is not what was meant, the object is put back exactly as it was and reported as failed
 *   4. snapshot before/after (for resets) and mark the changes live
 *
 * Reset to a release: every object changed after that release is put back to its state at that release; pages first
 * published after it go to the Trash. A reset is itself a release, so it can be undone by resetting to the release
 * before it.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** All draft changes, oldest first, grouped per object: "type:id" => array( type, id, changes ). */
function livecrafts_draft_groups( $ids = null ) {
	$groups = array();
	$after  = 0;
	do {
		$batch = livecrafts_changes_query( array( 'status' => 'draft', 'oldest_first' => true, 'since_id' => $after, 'limit' => 500 ) );
		foreach ( $batch as $c ) {
			$after = $c['id'];
			if ( $ids && ! in_array( $c['id'], $ids, true ) ) continue;
			$key = $c['object_type'] . ':' . $c['object_id'];
			if ( ! isset( $groups[ $key ] ) ) $groups[ $key ] = array( $c['object_type'], $c['object_id'], array() );
			$groups[ $key ][2][] = $c;
		}
	} while ( count( $batch ) === 500 );
	return $groups;
}

/** Draft changes whose live value changed after the draft change was made (someone edited it meanwhile). */
function livecrafts_conflicts( array $live, array $changes ) {
	$seen = array();
	$out  = array();
	foreach ( $changes as $c ) {
		$kind = livecrafts_kind( $c['kind'] );
		if ( ! $kind ) continue;
		$group = livecrafts_kind_group( $kind, $c['target'] );
		if ( isset( $seen[ $group ] ) ) continue;
		$seen[ $group ] = true;
		$now = call_user_func( $kind['read'], $live, $c['target'], $c['payload'] );
		if ( isset( $c['payload']['base'] ) ) {
			$eq = livecrafts_kind_base( $kind, $live, $c['target'], $c['payload'] ) === $c['payload']['base'];
		} else { // changes stored before fingerprints existed
			$was = isset( $c['payload']['before'] ) ? $c['payload']['before'] : null;
			$eq  = isset( $kind['equal'] ) ? call_user_func( $kind['equal'], $now, $was, $c['target'] ) : livecrafts_same( $now, $was );
		}
		if ( ! $eq ) {
			$out[] = array( 'change' => $c['id'], 'summary' => $c['summary'], 'target' => $c['target'],
				'draft_started_from' => livecrafts_shorten( isset( $c['payload']['before'] ) ? $c['payload']['before'] : null, 200 ), 'live_now' => livecrafts_shorten( $now, 200 ) );
		}
	}
	return $out;
}

/**
 * Check every draft against the live site without writing anything.
 * Returns array( 'plan' => [...], 'conflicts' => [...], 'errors' => [...] ).
 */
function livecrafts_deploy_check( $ids = null ) {
	$plan = array(); $conflicts = array(); $errors = array();
	foreach ( livecrafts_draft_groups( $ids ) as $g ) {
		list( $type, $id, $changes ) = $g;
		$label = livecrafts_object_label( $type, $id );
		$live  = livecrafts_object_data( $type, $id );
		if ( $live === null ) { $errors[] = array( 'object' => $label, 'error' => 'It was deleted.' ); continue; }
		$c = livecrafts_conflicts( $live, $changes );
		if ( $c ) $conflicts[] = array( 'object' => $label, 'type' => $type, 'id' => $id, 'targets' => $c );
		$want = $live;
		foreach ( $changes as $change ) {
			$r = livecrafts_apply_change( $want, $change );
			if ( is_wp_error( $r ) ) { $errors[] = array( 'object' => $label, 'change' => $change['id'], 'error' => $r->get_error_message() ); break; }
		}
		$plan[] = array( 'type' => $type, 'id' => $id, 'label' => $label, 'changes' => $changes, 'live' => $live, 'want' => $want );
	}
	return array( 'plan' => $plan, 'conflicts' => $conflicts, 'errors' => $errors );
}

/**
 * Deploy all drafts (or only the change ids in $opts['ids']). $opts['force'] = deploy even where the live site changed
 * underneath a draft (the draft value wins). Returns a result array or WP_Error (nothing written).
 */
function livecrafts_deploy( $user_id, $notes, array $opts = array() ) {
	$ids   = ! empty( $opts['ids'] ) ? array_map( 'intval', (array) $opts['ids'] ) : null;
	$check = livecrafts_deploy_check( $ids );
	if ( ! $check['plan'] ) return new WP_Error( 'livecrafts_nothing', 'There are no draft changes to deploy.', array( 'status' => 400 ) );
	if ( $check['errors'] ) {
		return new WP_Error( 'livecrafts_cannot_deploy', 'Some draft changes cannot be applied any more. Revert them first.', array( 'status' => 409, 'errors' => $check['errors'] ) );
	}
	if ( $check['conflicts'] && empty( $opts['force'] ) ) {
		return new WP_Error( 'livecrafts_conflict', 'Someone changed the live site under some drafts since they were made.', array( 'status' => 409, 'conflicts' => $check['conflicts'] ) );
	}

	$release = livecrafts_release_insert( 'deploy', $user_id, $notes );
	$done    = array();
	$failed  = array();
	foreach ( $check['plan'] as $p ) {
		$r = livecrafts_commit( $p, $release );
		if ( is_wp_error( $r ) ) $failed[] = array( 'object' => $p['label'], 'error' => $r->get_error_message() );
		else $done[] = array( 'object' => $p['label'], 'changes' => count( $p['changes'] ), 'url' => $p['type'] === 'post' ? get_permalink( $p['id'] ) : null );
	}
	$count   = array_sum( wp_list_pluck( $done, 'changes' ) );
	$summary = sprintf( '%d change%s on %d item%s', $count, $count === 1 ? '' : 's', count( $done ), count( $done ) === 1 ? '' : 's' ) . ( $failed ? sprintf( ', %d failed', count( $failed ) ) : '' );
	livecrafts_release_close( $release, $summary );
	do_action( 'livecrafts_deployed', $release, $done, $failed );
	return array( 'ok' => ! $failed, 'release' => livecrafts_release_get( $release ), 'deployed' => $done, 'failed' => $failed );
}

/** Write one object's planned state to the live site, verify it, snapshot it. true or WP_Error (object left as it was). */
function livecrafts_commit( array $p, $release_id ) {
	$type = $p['type']; $id = $p['id']; $live = $p['live']; $want = $p['want'];
	livecrafts_snapshot_save( $type, $id, 'before', $live, $release_id );

	livecrafts_writing( true );
	try {
		$r = $type === 'css' ? livecrafts_commit_css( $want ) : livecrafts_commit_post( $id, $live, $want, $p['changes'] );
	} finally {
		livecrafts_writing( false );
	}
	$after = livecrafts_object_data( $type, $id );
	if ( ! is_wp_error( $r ) ) {
		$wrong = livecrafts_verify_commit( $want, $after, $p['changes'] );
		if ( $wrong ) $r = new WP_Error( 'livecrafts_verify_failed', 'NOT DEPLOYED: after saving, ' . implode( ', ', $wrong ) . ' did not have the new value. The live page was put back as it was.' );
	}
	if ( is_wp_error( $r ) ) {
		livecrafts_object_restore( $type, $id, $live );
		return $r;
	}

	livecrafts_snapshot_save( $type, $id, 'after', $after, $release_id );
	foreach ( $p['changes'] as $c ) livecrafts_change_update( $c['id'], array( 'status' => 'live', 'release_id' => $release_id ) );
	livecrafts_draft_refresh( $type, $id );
	return true;
}

/** Every changed target must now read back as planned. Returns the summaries of those that do not. */
function livecrafts_verify_commit( $want, $after, array $changes ) {
	if ( ! $after ) return array( 'the item' );
	$wrong = array();
	foreach ( $changes as $c ) {
		$kind = livecrafts_kind( $c['kind'] );
		if ( $c['kind'] === 'post.create' ) {
			if ( $after['fields']['status'] !== 'publish' ) $wrong[] = 'the publish status';
			continue;
		}
		if ( ! $kind ) continue;
		if ( $c['kind'] === 'object.restore' ) { // restored fields and content meta must match
			foreach ( livecrafts_data_diff( $want, $after ) as $path ) {
				if ( $path !== 'fields.content' && strpos( $path, 'meta._elementor_' ) !== 0 ) $wrong[] = $path;
			}
			continue;
		}
		if ( ! livecrafts_kind_landed( $kind, $after, $want, $c['target'], $c['payload'] ) ) $wrong[] = '“' . $c['summary'] . '”';
	}
	return array_values( array_unique( $wrong ) );
}

function livecrafts_commit_css( array $want ) {
	$r = wp_update_custom_css_post( $want['css'] );
	return is_wp_error( $r ) ? $r : true;
}

/** Write the parts of a post that differ, each through the API that owns it. */
function livecrafts_commit_post( $post_id, array $live, array $want, array $changes ) {
	$diff   = livecrafts_data_diff( $live, $want );
	$fields = array();
	$meta   = array();
	foreach ( $diff as $path ) {
		list( $part, $key ) = explode( '.', $path, 2 );
		if ( $part === 'fields' ) $fields[ $key ] = $want['fields'][ $key ];
		else $meta[ $key ] = true;
	}
	if ( in_array( 'post.create', wp_list_pluck( $changes, 'kind' ), true ) ) $fields['status'] = 'publish';

	if ( $fields ) {
		$map = array( 'title' => 'post_title', 'content' => 'post_content', 'excerpt' => 'post_excerpt', 'status' => 'post_status' );
		$arr = array( 'ID' => $post_id );
		foreach ( $fields as $k => $v ) $arr[ $map[ $k ] ] = $v;
		$r = wp_update_post( wp_slash( $arr ), true );
		if ( is_wp_error( $r ) ) return $r;
	}

	if ( isset( $meta['_elementor_data'] ) ) {
		if ( livecrafts_el_active() ) {
			$r = livecrafts_el_save( $post_id, livecrafts_el_tree( $want ) );
			if ( is_wp_error( $r ) ) return $r;
			unset( $meta['_elementor_version'], $meta['_elementor_edit_mode'], $meta['_elementor_template_type'] ); // Elementor writes these itself
		} else {
			update_post_meta( $post_id, '_elementor_data', wp_slash( $want['meta']['_elementor_data'] ) );
		}
		unset( $meta['_elementor_data'] );
	}

	// ACF values: through update_field, which writes the value and its field reference.
	if ( function_exists( 'update_field' ) ) {
		foreach ( array_keys( $meta ) as $key ) {
			$ref = isset( $want['meta'][ '_' . $key ] ) ? $want['meta'][ '_' . $key ] : '';
			if ( $key[0] === '_' || ! is_string( $ref ) || strpos( $ref, 'field_' ) !== 0 || ! array_key_exists( $key, $want['meta'] ) ) continue;
			update_field( $ref, $want['meta'][ $key ], $post_id );
			unset( $meta[ $key ], $meta[ '_' . $key ] );
		}
	}

	foreach ( array_keys( $meta ) as $key ) {
		if ( array_key_exists( $key, $want['meta'] ) ) update_post_meta( $post_id, $key, wp_slash( $want['meta'][ $key ] ) );
		else delete_post_meta( $post_id, $key );
	}
	clean_post_cache( $post_id );
	return true;
}

/* ------------------------------------------------------------------ reset + baseline */

/**
 * Put the site back to how it was at a release. Drafts are dropped first. Returns a result array or WP_Error.
 * $source: who asked (widget / admin-panel).
 */
function livecrafts_reset_to( $release_id, $user_id, $notes, $source = 'widget' ) {
	global $wpdb;
	$target = livecrafts_release_get( $release_id );
	if ( ! $target ) return new WP_Error( 'livecrafts_unknown', 'Unknown release.', array( 'status' => 404 ) );

	livecrafts_discard_drafts( null, $user_id, 'reset' );
	$release = livecrafts_release_insert( 'reset', $user_id, $notes );
	$label   = 'Reset to release #' . $target['id'];
	$created = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare(
		'SELECT object_id FROM ' . livecrafts_table( 'changes' ) . " WHERE kind = 'post.create' AND status = 'live' AND release_id > %d",
		$target['id']
	) ) );

	$restored = array(); $failed = array();
	foreach ( livecrafts_objects_changed_after( $target['mark'] ) as $o ) {
		$type = $o['object_type']; $id = (int) $o['object_id'];
		if ( $type === 'post' && in_array( $id, $created, true ) ) continue;
		$state = livecrafts_state_at( $type, $id, $target['mark'] );
		$live  = livecrafts_object_data( $type, $id );
		if ( ! $state || ! $live || ! livecrafts_data_diff( $live, $state ) ) continue;
		$r = livecrafts_reset_object( $type, $id, $live, $state, $release, $user_id, $source, $label );
		if ( is_wp_error( $r ) ) $failed[] = array( 'object' => livecrafts_object_label( $type, $id ), 'error' => $r->get_error_message() );
		else $restored[] = livecrafts_object_label( $type, $id );
	}
	foreach ( $created as $id ) {
		if ( ! in_array( get_post_status( $id ), array( 'publish', 'private', 'future' ), true ) ) continue;
		$live  = livecrafts_post_data( $id );
		$state = $live;
		$state['fields']['status'] = 'draft';
		$r = livecrafts_reset_object( 'post', $id, $live, $state, $release, $user_id, $source, $label . ' (published after it: back to draft)' );
		if ( is_wp_error( $r ) ) $failed[] = array( 'object' => livecrafts_object_label( 'post', $id ), 'error' => $r->get_error_message() );
		else $restored[] = livecrafts_object_label( 'post', $id ) . ' → draft';
	}

	$summary = $label . ': ' . count( $restored ) . ' item' . ( count( $restored ) === 1 ? '' : 's' ) . ( $failed ? ', ' . count( $failed ) . ' failed' : '' );
	livecrafts_release_close( $release, $summary );
	return array( 'ok' => ! $failed, 'release' => livecrafts_release_get( $release ), 'restored' => $restored, 'failed' => $failed );
}

function livecrafts_reset_object( $type, $id, array $live, array $state, $release, $user_id, $source, $summary ) {
	$cid = livecrafts_change_insert( array(
		'status' => 'live', 'source' => $source, 'user_id' => $user_id, 'object_type' => $type, 'object_id' => $id,
		'kind' => 'restore', 'summary' => $summary, 'release_id' => $release, 'payload' => array( 'changed' => livecrafts_data_diff( $live, $state ) ),
	) );
	livecrafts_snapshot_save( $type, $id, 'before', $live, $release, $cid );
	$r = livecrafts_object_restore( $type, $id, $state );
	if ( is_wp_error( $r ) ) {
		livecrafts_change_update( $cid, array( 'status' => 'discarded' ) );
		return $r;
	}
	livecrafts_snapshot_save( $type, $id, 'after', livecrafts_object_data( $type, $id ), $release, $cid );
	return true;
}

/** Mark "this is how the site is now" (e.g. when it is handed over to the client): a release to reset to later. */
function livecrafts_mark_baseline( $user_id, $notes ) {
	$id = livecrafts_release_insert( 'baseline', $user_id, $notes );
	livecrafts_release_close( $id, 'Baseline' . ( trim( (string) $notes ) !== '' ? ': ' . mb_substr( trim( $notes ), 0, 200 ) : '' ) );
	return livecrafts_release_get( $id );
}
