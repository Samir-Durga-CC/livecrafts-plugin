<?php
/**
 * Theme-file changes in the ledger. A theme file cannot be a draft (it is live the moment it is written), but it is
 * recorded like every other change, so there is ONE history for everything the assistant (or anyone) changed:
 *
 *   kind file.write   object_type 'file'   target = path relative to the WordPress folder
 *   payload           before / after = the whole file (null = it did not exist / does not exist any more)
 *   status            live. release_id stays empty until a person deploys: such a change is "pending" - Discard all and a
 *                     reset put the file back, Deploy accepts it into the release.
 *
 * Reverting a file change writes the old content back at once (only if the file still is exactly what the change left),
 * and records that as a new change that points back (reverts), like git revert.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const LIVECRAFTS_FILE_KIND = 'file.write';

/** The ledger stores JSON: refuse to write a file whose content cannot be stored (invalid UTF-8) rather than lose the undo. */
function livecrafts_file_storable( $before, $after ) {
	return wp_json_encode( array( $before, $after ) ) !== false;
}

/**
 * Record one theme-file write. $o: actor, source, ref, reverts, release_id. Returns the public change, or null when
 * nothing changed / it could not be stored.
 */
function livecrafts_file_record( $rel, $before, $after, array $o = array() ) {
	if ( $before === $after ) return null;
	$verb = $before === null ? 'Create' : ( $after === null ? 'Delete' : 'Edit' );
	$id   = livecrafts_change_insert( array(
		'status'      => 'live',
		'source'      => isset( $o['source'] ) ? $o['source'] : 'assistant',
		'user_id'     => isset( $o['actor'] ) ? (int) $o['actor'] : get_current_user_id(),
		'object_type' => 'file',
		'object_id'   => 0,
		'kind'        => LIVECRAFTS_FILE_KIND,
		'target'      => $rel,
		'summary'     => $verb . ' ' . $rel,
		'payload'     => array(
			'before'     => $before,
			'after'      => $after,
			'sha_before' => $before === null ? null : sha1( $before ),
			'sha_after'  => $after === null ? null : sha1( $after ),
		),
		'release_id'  => isset( $o['release_id'] ) ? (int) $o['release_id'] : null,
		'reverts'     => isset( $o['reverts'] ) ? (int) $o['reverts'] : null,
		'ref'         => isset( $o['ref'] ) ? $o['ref'] : '',
	) );
	return $id ? livecrafts_change_public( livecrafts_change_get( $id ) ) : null;
}

/** Take back a change the backend itself undid straight away (the page broke): it never happened, so it leaves the history. */
function livecrafts_file_drop( $change_id, $user_id, $why ) {
	$c = $change_id ? livecrafts_change_get( (int) $change_id ) : null;
	if ( ! $c || $c['kind'] !== LIVECRAFTS_FILE_KIND || $c['status'] !== 'live' || $c['release_id'] !== null ) return false;
	$payload = $c['payload'];
	$payload['discarded'] = array( 'by' => (int) $user_id, 'why' => $why );
	livecrafts_change_update( $c['id'], array( 'status' => 'discarded', 'payload' => $payload ) );
	return true;
}

/* ------------------------------------------------------------------ which file changes are still "open" */

/** File changes (not reverts) that were not reverted, newest first. $where is extra SQL with %d placeholders for $vals. */
function livecrafts_files_query( $where, array $vals = array() ) {
	global $wpdb;
	$sql  = 'SELECT * FROM ' . livecrafts_table( 'changes' ) . " WHERE kind = 'file.write' AND status = 'live' AND reverts IS NULL AND $where ORDER BY id DESC LIMIT 500";
	$rows = $wpdb->get_results( $vals ? $wpdb->prepare( $sql, $vals ) : $sql, ARRAY_A );
	$out  = array();
	foreach ( (array) $rows as $row ) {
		$c = livecrafts_change_row( $row );
		if ( empty( $c['payload']['reverted_by'] ) ) $out[] = $c;
	}
	return $out;
}

/** Edits to theme files that no release has accepted yet - Discard all undoes them. Newest first. */
function livecrafts_files_pending() {
	return livecrafts_files_query( 'release_id IS NULL' );
}

/** Edits that went into a release after $release_id - a reset to that release undoes them. Newest first. */
function livecrafts_files_released_after( $release_id ) {
	return livecrafts_files_query( 'release_id > %d', array( (int) $release_id ) );
}

/* ------------------------------------------------------------------ revert */

/**
 * Put a file back as it was before this change. $o: actor, source, ref, release_id, bulk (Discard all / reset: the route
 * already checked who may do it). Returns array( ok, change, note ) or WP_Error - the file is left alone on any error.
 */
function livecrafts_file_revert( array $c, array $o = array() ) {
	$p = $c['payload'];
	if ( ! empty( $p['reverted_by'] ) ) return new WP_Error( 'livecrafts_already', 'This file change was already reverted.', array( 'status' => 409 ) );
	if ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ) {
		return new WP_Error( 'livecrafts_forbidden', 'File editing is switched off on this site (DISALLOW_FILE_EDIT), so the file cannot be restored from here.', array( 'status' => 403 ) );
	}
	if ( empty( $o['bulk'] ) && ! current_user_can( 'edit_themes' ) ) {
		return new WP_Error( 'livecrafts_forbidden', 'Your account cannot change theme files.', array( 'status' => 403 ) );
	}
	$rel    = (string) $c['target'];
	$before = array_key_exists( 'before', $p ) ? $p['before'] : null;
	$after  = array_key_exists( 'after', $p ) ? $p['after'] : null;

	$existing = livecrafts_theme_path( $rel, true );
	$exists   = ! is_wp_error( $existing );
	$now      = $exists ? file_get_contents( $existing ) : null;
	if ( $now !== $after ) {
		return new WP_Error( 'livecrafts_changed', 'The file ' . $rel . ' was changed again after this edit, so it is not restored automatically. Revert the newer change first.', array( 'status' => 409 ) );
	}

	if ( $before === null ) { // this change created the file: take it away again
		if ( $exists && ! @unlink( $existing ) ) return new WP_Error( 'livecrafts_delete_failed', 'Could not delete ' . $rel . '.', array( 'status' => 500 ) );
		if ( $exists && function_exists( 'opcache_invalidate' ) ) @opcache_invalidate( $existing, true );
	} else {
		$abs = $exists ? $existing : livecrafts_theme_path( $rel, false );
		if ( is_wp_error( $abs ) ) return $abs;
		if ( ( $exists && ! is_writable( $abs ) ) || ( ! $exists && ! is_writable( dirname( $abs ) ) ) ) {
			return new WP_Error( 'livecrafts_not_writable', 'The server does not allow writing ' . $rel . ' (file permissions).', array( 'status' => 500 ) );
		}
		if ( file_put_contents( $abs, $before, LOCK_EX ) === false ) return new WP_Error( 'livecrafts_write_failed', 'Could not write ' . $rel . '.', array( 'status' => 500 ) );
		clearstatcache( true, $abs );
		if ( function_exists( 'opcache_invalidate' ) ) @opcache_invalidate( $abs, true );
	}

	$new = livecrafts_file_record( $rel, $after, $before, $o + array( 'reverts' => $c['id'] ) );
	$p['reverted_by'] = $new ? $new['id'] : -1;
	livecrafts_change_update( $c['id'], array( 'payload' => $p ) );
	return array( 'ok' => true, 'change' => $new, 'note' => 'The file is restored. Theme files are live at once, so this is already on the site.' );
}

/**
 * Discard all: put back every theme file the assistant changed since the last release. Fills $report with
 * files (how many) and file_errors (the ones that could not be restored, e.g. edited again by someone else).
 */
function livecrafts_files_discard( $user_id, array &$report ) {
	$report['files'] = 0;
	foreach ( livecrafts_files_pending() as $c ) { // newest first, so a file changed twice goes back step by step
		$r = livecrafts_file_revert( $c, array( 'bulk' => true, 'actor' => $user_id, 'source' => 'system', 'ref' => 'discard' ) );
		if ( is_wp_error( $r ) ) $report['file_errors'][] = array( 'file' => $c['target'], 'error' => $r->get_error_message() );
		else $report['files']++;
	}
}
