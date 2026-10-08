<?php
/**
 * Drafts: every change made with Livecrafts first becomes a DRAFT. Editors see it on the site (preview.php);
 * visitors keep seeing the live site until someone deploys (deploy.php).
 *
 * The draft of an object is never stored as a separate copy of the truth. It is always:
 *     live state  +  the object's draft changes, applied in order (kinds.php)
 * so a change made meanwhile in WP admin or the Elementor editor is never lost, and dropping one draft change is exact.
 *
 * For pages/posts the result is also written to a hidden "preview copy" (post type livecrafts_draft, child of the
 * page) so the preview can read it quickly - including Elementor, which renders the copy's element tree and CSS.
 * The copy is rebuilt after every draft change and deleted when the object has no drafts left.
 * Site CSS drafts need no copy: the preview computes them on the fly.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const LIVECRAFTS_DRAFT_TYPE = 'livecrafts_draft';

add_action( 'init', function () {
	register_post_type( LIVECRAFTS_DRAFT_TYPE, array(
		'label'               => 'Livecrafts preview copies',
		'public'              => false,
		'show_ui'             => false,
		'show_in_rest'        => false,
		'exclude_from_search' => true,
		'rewrite'             => false,
		'query_var'           => false,
		'can_export'          => false,
		'supports'            => array( 'title', 'editor', 'excerpt', 'custom-fields' ),
	) );
} );

// A page deleted for good takes its preview copy and its drafts with it (WordPress does not delete children of
// another post type).
add_action( 'before_delete_post', function ( $post_id ) {
	global $wpdb;
	if ( get_post_type( $post_id ) === LIVECRAFTS_DRAFT_TYPE ) return;
	$copy = livecrafts_draft_copy_id( $post_id );
	if ( $copy ) wp_delete_post( $copy, true );
	livecrafts_draft_copy_id( $post_id, true );
	if ( get_option( 'livecrafts_db_version' ) ) {
		$wpdb->update( livecrafts_table( 'changes' ), array( 'status' => 'discarded', 'updated_at' => livecrafts_now() ), array( 'status' => 'draft', 'object_type' => 'post', 'object_id' => (int) $post_id ) );
	}
} );

/** The preview copy of a post (0 = none). Cached per request; $forget clears the cache after a rebuild. */
function livecrafts_draft_copy_id( $post_id, $forget = false ) {
	static $cache = array();
	$post_id = (int) $post_id;
	if ( $forget ) { unset( $cache[ $post_id ] ); return 0; }
	if ( ! isset( $cache[ $post_id ] ) ) {
		global $wpdb;
		$cache[ $post_id ] = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_parent = %d ORDER BY ID DESC LIMIT 1",
			LIVECRAFTS_DRAFT_TYPE, $post_id
		) );
	}
	return $cache[ $post_id ];
}

/**
 * The draft state of an object: live state + its draft changes. Returns the data array, or WP_Error if a change can no
 * longer be applied (e.g. its Elementor element was deleted in the editor meanwhile).
 */
function livecrafts_draft_data( $object_type, $object_id, $live = null ) {
	$data = $live !== null ? $live : livecrafts_object_data( $object_type, $object_id );
	if ( $data === null ) return new WP_Error( 'livecrafts_gone', 'That item does not exist.', array( 'status' => 404 ) );
	foreach ( livecrafts_draft_changes( $object_type, $object_id ) as $c ) {
		$r = livecrafts_apply_change( $data, $c );
		if ( is_wp_error( $r ) ) {
			return new WP_Error( 'livecrafts_draft_broken', 'Draft change #' . $c['id'] . ' (' . $c['summary'] . ') cannot be applied any more: ' . $r->get_error_message(), array( 'status' => 409, 'change' => $c['id'] ) );
		}
	}
	return $data;
}

/** Apply one stored change to a data array (kinds without "apply", like post.create, change nothing here). */
function livecrafts_apply_change( array &$data, array $c ) {
	$kind = livecrafts_kind( $c['kind'] );
	if ( ! $kind ) return true;
	$after = array_key_exists( 'after', $c['payload'] ) ? $c['payload']['after'] : null;
	return call_user_func_array( $kind['apply'], array( &$data, $c['target'], $after, $c['payload'] ) );
}

/** Rewrite the preview copy of a post from live + drafts (or delete it when there are no drafts). Returns the copy id. */
function livecrafts_draft_rebuild( $post_id ) {
	$copy    = livecrafts_draft_copy_id( $post_id );
	$changes = array_filter( livecrafts_draft_changes( 'post', $post_id ), function ( $c ) { return (bool) livecrafts_kind( $c['kind'] ); } );
	if ( ! $changes ) {
		if ( $copy ) wp_delete_post( $copy, true );
		livecrafts_draft_copy_id( $post_id, true );
		return 0;
	}
	$data = livecrafts_draft_data( 'post', $post_id );
	if ( is_wp_error( $data ) ) return $data;

	livecrafts_writing( true );
	try {
		$post = get_post( $post_id );
		$arr  = array(
			'post_type'    => LIVECRAFTS_DRAFT_TYPE,
			'post_status'  => 'draft',
			'post_parent'  => $post_id,
			'page_template' => 'default', // the copy's real template is stored as meta below; WordPress would reject it for this post type
			'post_author'  => $post->post_author,
			'post_title'   => $data['fields']['title'],
			'post_content' => $data['fields']['content'],
			'post_excerpt' => $data['fields']['excerpt'],
		);
		// The copy holds values that were already sanitized when each change was made: store them as they are.
		kses_remove_filters();
		if ( $copy ) {
			$arr['ID'] = $copy;
			$r = wp_update_post( wp_slash( $arr ), true );
		} else {
			$r = wp_insert_post( wp_slash( $arr ), true );
		}
		kses_init();
		if ( is_wp_error( $r ) ) return $r;
		$copy = (int) $r;

		$existing = array_keys( (array) get_post_meta( $copy ) );
		foreach ( $data['meta'] as $key => $value ) update_post_meta( $copy, $key, wp_slash( $value ) );
		foreach ( $existing as $key ) {
			if ( livecrafts_meta_tracked( $key ) && ! array_key_exists( $key, $data['meta'] ) ) delete_post_meta( $copy, $key );
		}
		update_post_meta( $copy, '_livecrafts_status', $data['fields']['status'] );
	} finally {
		livecrafts_writing( false );
	}
	livecrafts_draft_copy_id( $post_id, true );
	return $copy;
}

/** What the preview copy of a post holds now (to prove a draft change really landed). */
function livecrafts_draft_copy_data( $post_id ) {
	$copy = livecrafts_draft_copy_id( $post_id );
	if ( ! $copy ) return null;
	$data = livecrafts_post_data( $copy );
	if ( $data ) $data['fields']['status'] = get_post_meta( $copy, '_livecrafts_status', true );
	return $data;
}

/** After the drafts of an object changed: rebuild its preview copy. */
function livecrafts_draft_refresh( $object_type, $object_id ) {
	return $object_type === 'post' ? livecrafts_draft_rebuild( $object_id ) : true;
}

/** Can the acting person change this object at all? true or WP_Error. */
function livecrafts_check_object( $object_type, $object_id, $actor ) {
	if ( $object_type === 'css' ) return true;
	$post = get_post( $object_id );
	if ( ! $post || ! livecrafts_tracked_post_type( $post->post_type ) ) return new WP_Error( 'livecrafts_no_page', 'That page does not exist (or is not something Livecrafts edits).', array( 'status' => 404 ) );
	if ( ! current_user_can( 'edit_post', $post->ID ) || ! user_can( $actor, 'edit_post', $post->ID ) ) {
		return new WP_Error( 'livecrafts_forbidden', 'You cannot edit this page.', array( 'status' => 403 ) );
	}
	return true;
}

/**
 * Make one draft change. Validates against the draft state, stores it, rebuilds the preview and READS IT BACK
 * ("no fake saves": if the preview does not show the new value, the change is dropped and an error returned).
 *
 * $opts: args (extra kind arguments), actor, source, ref, reverts.
 * Returns array( 'ok' => true, 'change' => public change ) / array( 'ok' => true, 'unchanged' => true ) / WP_Error.
 */
function livecrafts_change_create( $kind_name, $object_type, $object_id, $target, $value, array $opts = array() ) {
	$kind  = livecrafts_kind( $kind_name );
	$actor = isset( $opts['actor'] ) ? (int) $opts['actor'] : get_current_user_id();
	if ( ! $kind || ( $kind['object'] !== 'any' && $kind['object'] !== $object_type ) ) return livecrafts_bad_value( 'Unknown change kind "' . $kind_name . '" for this item.' );
	$object_id = $object_type === 'css' ? 0 : (int) $object_id;
	$ok        = livecrafts_check_object( $object_type, $object_id, $actor );
	if ( is_wp_error( $ok ) ) return $ok;

	$draft = livecrafts_draft_data( $object_type, $object_id );
	if ( is_wp_error( $draft ) ) return $draft;
	$args = isset( $opts['args'] ) ? (array) $opts['args'] : array();
	$args['object_type'] = $object_type;
	$p = call_user_func( $kind['prepare'], $object_id, $target, $value, $draft, $args, $actor );
	if ( is_wp_error( $p ) ) return $p;
	if ( ! empty( $p['unchanged'] ) ) return array( 'ok' => true, 'unchanged' => true, 'message' => 'It already is that way.' );

	$before = array_key_exists( 'before', $p ) ? $p['before'] : call_user_func( $kind['read'], $draft, $p['target'], $p['payload'] );
	$same   = isset( $kind['equal'] ) ? call_user_func( $kind['equal'], $before, $p['after'], $p['target'] ) : livecrafts_same( $before, $p['after'] );
	if ( $same ) return array( 'ok' => true, 'unchanged' => true, 'message' => 'It already has that value.' );

	$test = $draft; // try it before storing anything
	$r    = call_user_func_array( $kind['apply'], array( &$test, $p['target'], $p['after'], $p['payload'] ) );
	if ( is_wp_error( $r ) ) return $r;
	// What the live site has now for this change: if that differs when deploying, someone changed it meanwhile.
	$base = livecrafts_kind_base( $kind, livecrafts_object_data( $object_type, $object_id ), $p['target'], $p['payload'] );

	$id = livecrafts_change_insert( array(
		'status'      => 'draft',
		'source'      => isset( $opts['source'] ) ? $opts['source'] : 'assistant',
		'user_id'     => $actor,
		'object_type' => $object_type,
		'object_id'   => $object_id,
		'kind'        => $kind_name,
		'target'      => $p['target'],
		'summary'     => $p['summary'],
		'payload'     => array_merge( $p['payload'], array( 'before' => $before, 'after' => $p['after'], 'base' => $base ) ),
		'ref'         => isset( $opts['ref'] ) ? $opts['ref'] : '',
		'reverts'     => isset( $opts['reverts'] ) ? $opts['reverts'] : null,
	) );
	if ( ! $id ) return new WP_Error( 'livecrafts_store_failed', 'The change could not be stored.', array( 'status' => 500 ) );

	$refresh = livecrafts_draft_refresh( $object_type, $object_id );
	$landed  = ! is_wp_error( $refresh );
	if ( $landed && $object_type === 'post' && $kind_name !== 'object.restore' ) {
		$landed = livecrafts_kind_landed( $kind, livecrafts_draft_copy_data( $object_id ), $test, $p['target'], $p['payload'] );
	}
	if ( ! $landed ) {
		livecrafts_change_update( $id, array( 'status' => 'discarded' ) );
		livecrafts_draft_refresh( $object_type, $object_id );
		$why = is_wp_error( $refresh ) ? $refresh->get_error_message() : 'the preview does not show the new value';
		return new WP_Error( 'livecrafts_verify_failed', 'NOT SAVED: ' . $why . '. Nothing was changed.', array( 'status' => 500 ) );
	}
	return array( 'ok' => true, 'change' => livecrafts_change_public( livecrafts_change_get( $id ) ) );
}

/**
 * Create a new page/post. It is created at once as a WordPress draft (visitors cannot see drafts), recorded as a
 * draft change, and published on deploy. Discarding it moves it to the Trash.
 */
function livecrafts_page_create( array $a, array $opts = array() ) {
	$actor = isset( $opts['actor'] ) ? (int) $opts['actor'] : get_current_user_id();
	$type  = isset( $a['type'] ) && $a['type'] === 'post' ? 'post' : 'page';
	if ( ! current_user_can( $type === 'post' ? 'edit_posts' : 'edit_pages' ) || ! user_can( $actor, $type === 'post' ? 'edit_posts' : 'edit_pages' ) ) {
		return new WP_Error( 'livecrafts_forbidden', 'You cannot create ' . $type . 's.', array( 'status' => 403 ) );
	}
	$title = trim( sanitize_text_field( isset( $a['title'] ) ? (string) $a['title'] : '' ) );
	if ( $title === '' ) return livecrafts_bad_value( 'A new page needs a title.' );
	$arr = array(
		'post_type'    => $type,
		'post_status'  => 'draft',
		'post_title'   => $title,
		'post_content' => isset( $a['content'] ) ? (string) $a['content'] : '',
		'post_excerpt' => isset( $a['excerpt'] ) ? (string) $a['excerpt'] : '',
		'post_author'  => $actor,
	);
	if ( ! empty( $a['slug'] ) )   $arr['post_name']   = sanitize_title( $a['slug'] );
	if ( ! empty( $a['parent'] ) ) $arr['post_parent'] = (int) $a['parent'];
	livecrafts_writing( true );
	$id = wp_insert_post( wp_slash( $arr ), true );
	if ( ! is_wp_error( $id ) && ! empty( $a['template'] ) ) update_post_meta( $id, '_wp_page_template', sanitize_text_field( $a['template'] ) );
	livecrafts_writing( false );
	if ( is_wp_error( $id ) ) return $id;

	$cid = livecrafts_change_insert( array(
		'status' => 'draft', 'source' => isset( $opts['source'] ) ? $opts['source'] : 'assistant', 'user_id' => $actor,
		'object_type' => 'post', 'object_id' => $id, 'kind' => 'post.create', 'target' => '',
		'summary' => 'New ' . $type . ' “' . $title . '”', 'payload' => array( 'type' => $type, 'title' => $title ),
		'ref' => isset( $opts['ref'] ) ? $opts['ref'] : '',
	) );
	return array( 'ok' => true, 'id' => (int) $id, 'preview' => get_preview_post_link( $id ), 'change' => livecrafts_change_public( livecrafts_change_get( $cid ) ) );
}

/**
 * Drop draft changes (one object, or all). New pages that were never deployed go to the Trash.
 * Dropping drafts does not touch the live site - but "all" also puts back the theme files changed since the last release
 * (they are live at once, file-changes.php); $report then says how many and which could not be restored.
 * Returns the number of dropped draft changes.
 */
function livecrafts_discard_drafts( $object = null, $user_id = 0, $why = '', &$report = null ) {
	$args = array( 'status' => 'draft', 'oldest_first' => true, 'limit' => 500 );
	if ( $object ) { $args['object_type'] = $object[0]; $args['object_id'] = $object[1]; }
	$touched = array();
	$count   = 0;
	do {
		$batch = livecrafts_changes_query( $args );
		foreach ( $batch as $c ) {
			$payload = $c['payload'];
			$payload['discarded'] = array( 'by' => (int) $user_id, 'why' => $why );
			livecrafts_change_update( $c['id'], array( 'status' => 'discarded', 'payload' => $payload ) );
			if ( $c['kind'] === 'post.create' && get_post_status( $c['object_id'] ) === 'draft' ) {
				livecrafts_writing( true );
				wp_trash_post( $c['object_id'] );
				livecrafts_writing( false );
			}
			$touched[ $c['object_type'] . ':' . $c['object_id'] ] = array( $c['object_type'], $c['object_id'] );
			$count++;
		}
	} while ( count( $batch ) === 500 );
	foreach ( $touched as $o ) livecrafts_draft_refresh( $o[0], $o[1] );
	$report = array( 'files' => 0, 'file_errors' => array() );
	if ( ! $object ) livecrafts_files_discard( $user_id, $report );
	return $count;
}

/**
 * Revert one change.
 *   draft change -> dropped from the draft (a new page that was never deployed goes to the Trash)
 *   live change  -> a NEW draft change that puts the old value back; it reaches visitors when deployed, like any change
 */
function livecrafts_change_revert( $id, array $opts = array() ) {
	$c = livecrafts_change_get( $id );
	if ( ! $c ) return new WP_Error( 'livecrafts_unknown', 'Unknown change.', array( 'status' => 404 ) );
	$actor = isset( $opts['actor'] ) ? (int) $opts['actor'] : get_current_user_id();

	if ( $c['status'] === 'discarded' ) return new WP_Error( 'livecrafts_already', 'This change was already dropped.', array( 'status' => 409 ) );
	if ( $c['kind'] === LIVECRAFTS_FILE_KIND ) { // a theme file is live at once: the old content goes back at once
		if ( $c['reverts'] ) return new WP_Error( 'livecrafts_no_revert', 'This change already is a revert. Revert the change it undid again instead.', array( 'status' => 409 ) );
		return livecrafts_file_revert( $c, $opts + array( 'actor' => $actor ) );
	}
	if ( $c['status'] === 'draft' ) {
		$ok = livecrafts_check_object( $c['object_type'], $c['object_id'], $actor );
		if ( is_wp_error( $ok ) ) return $ok;
		if ( $c['kind'] === 'post.create' ) {
			livecrafts_discard_drafts( array( 'post', $c['object_id'] ), $actor, 'revert' );
		} else {
			$payload = $c['payload'];
			$payload['discarded'] = array( 'by' => $actor, 'why' => 'revert' );
			livecrafts_change_update( $c['id'], array( 'status' => 'discarded', 'payload' => $payload ) );
			livecrafts_draft_refresh( $c['object_type'], $c['object_id'] );
		}
		return array( 'ok' => true, 'dropped' => $c['id'], 'note' => 'Removed from the draft. The live site was never changed by it.' );
	}

	// live: put the old value back as a new draft change
	$o    = $opts + array( 'actor' => $actor, 'reverts' => $c['id'] );
	$kind = livecrafts_kind( $c['kind'] );
	if ( $kind && $c['kind'] !== 'object.restore' ) {
		// By default the inverse is the same kind with the old value; structure changes (insert/remove/move) say otherwise.
		$inverse = isset( $kind['revert'] ) ? call_user_func( $kind['revert'], $c ) : array( $c['kind'], $c['target'], $c['payload']['before'], $c['payload'] );
		if ( is_wp_error( $inverse ) ) return $inverse;
		list( $k, $target, $value, $args ) = $inverse;
		$r = livecrafts_change_create( $k, $c['object_type'], $c['object_id'], $target, $value, $o + array( 'args' => (array) $args ) );
	} elseif ( $c['kind'] === 'post.create' ) {
		$r = livecrafts_change_create( 'post.field', 'post', $c['object_id'], 'status', 'draft', $o );
	} else {
		$before = livecrafts_change_snapshot( $c['id'], 'before' );
		if ( ! $before ) return new WP_Error( 'livecrafts_no_snapshot', 'There is no recorded earlier state for this change, so it cannot be reverted automatically.', array( 'status' => 409 ) );
		$r = livecrafts_change_create( 'object.restore', $c['object_type'], $c['object_id'], '*', $before, $o + array( 'args' => array( 'from_change' => $c['id'], 'summary' => 'Revert: ' . $c['summary'] ) ) );
	}
	if ( is_array( $r ) && ! empty( $r['unchanged'] ) ) return array( 'ok' => true, 'unchanged' => true, 'note' => 'The site already has the old value.' );
	if ( is_array( $r ) ) $r['note'] = 'The old value is back in the draft. Deploy to put it on the live site.';
	return $r;
}
