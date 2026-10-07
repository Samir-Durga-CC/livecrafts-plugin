<?php
/**
 * Changes made OUTSIDE Livecrafts (WP admin, the block editor, the Elementor editor, ACF field boxes, the Customizer)
 * are recorded too, so the history is complete and the assistant knows what happened ("git status" for the site).
 *
 * The first time an object is about to change in a request, its state is captured (before). When the request ends,
 * the state is read again (after); if anything differs, one change "external.edit" is recorded with both snapshots.
 * That makes such a change revertable, and lets a reset to an earlier release put it back.
 * Livecrafts' own writes (deploy, reset, preview copies) are not recorded here: they set livecrafts_writing().
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** Objects captured in this request: "type:id" => array( type, id, before, source ). */
function livecrafts_watch_registry( $key = null, $entry = null ) {
	static $touched = array();
	if ( $key !== null && $entry !== null && ! isset( $touched[ $key ] ) ) $touched[ $key ] = $entry;
	return $touched;
}

/** Elementor editor saves also run wp_update_post (plain-text copy of the content): credit those to Elementor. */
function livecrafts_watch_source( $set = null ) {
	static $source = null;
	if ( $set !== null ) $source = $set;
	if ( $source ) return $source;
	if ( wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) return 'system';
	return 'wp-admin';
}

function livecrafts_watch_before( $object_type, $object_id ) {
	if ( livecrafts_writing() ) return;
	if ( $object_type === 'post' ) {
		$post = get_post( $object_id );
		if ( ! $post || ! livecrafts_tracked_post_type( $post->post_type ) ) return;
		if ( ! in_array( $post->post_status, array( 'publish', 'private', 'future' ), true ) ) return; // drafts are not on the site yet
	}
	$key      = $object_type . ':' . (int) $object_id;
	$captured = livecrafts_watch_registry();
	if ( isset( $captured[ $key ] ) ) return;
	livecrafts_watch_registry( $key, array( $object_type, (int) $object_id, livecrafts_object_data( $object_type, $object_id ), livecrafts_watch_source() ) );
}

// Before WordPress updates a post (classic editor, block editor, quick edit, wp_update_post from any plugin).
add_action( 'pre_post_update', function ( $post_id ) {
	livecrafts_watch_before( 'post', $post_id );
}, 1 );

// Additional CSS is stored in a custom_css post.
add_action( 'pre_post_update', function ( $post_id ) {
	if ( get_post_type( $post_id ) === 'custom_css' ) livecrafts_watch_before( 'css', 0 );
}, 1 );
add_action( 'customize_save', function () {
	livecrafts_watch_before( 'css', 0 );
} );

// ACF field boxes (before ACF writes the values).
add_action( 'acf/save_post', function ( $post_id ) {
	if ( is_numeric( $post_id ) ) livecrafts_watch_before( 'post', (int) $post_id );
}, 1 );

// The Elementor editor.
add_action( 'elementor/document/before_save', function ( $document ) {
	if ( livecrafts_writing() || ! is_object( $document ) || ! method_exists( $document, 'get_main_id' ) ) return;
	livecrafts_watch_source( 'elementor' );
	livecrafts_watch_before( 'post', (int) $document->get_main_id() );
} );

// At the end of the request: record what really changed.
add_action( 'shutdown', 'livecrafts_watch_record' );
function livecrafts_watch_record() {
	$touched = livecrafts_watch_registry();
	if ( ! $touched || ! get_option( 'livecrafts_db_version' ) ) return;
	foreach ( $touched as $entry ) {
		list( $type, $id, $before, $source ) = $entry;
		if ( ! $before ) continue;
		$after = livecrafts_object_data( $type, $id );
		if ( ! $after ) continue;
		$diff = livecrafts_data_diff( $before, $after );
		if ( ! $diff ) continue;
		$where = array( 'elementor' => 'Elementor editor', 'system' => 'automatic task', 'wp-admin' => 'WordPress admin' );
		$what  = livecrafts_describe_diff( $diff );
		$cid   = livecrafts_change_insert( array(
			'status'      => 'live',
			'source'      => $source,
			'user_id'     => get_current_user_id(),
			'object_type' => $type,
			'object_id'   => $id,
			'kind'        => 'external.edit',
			'target'      => '',
			'summary'     => 'Edited in ' . $where[ $source ] . ': ' . $what,
			'payload'     => array( 'changed' => $diff, 'revision' => $type === 'post' ? livecrafts_latest_revision( $id ) : 0 ),
		) );
		if ( ! $cid ) continue;
		livecrafts_snapshot_save( $type, $id, 'before', $before, null, $cid );
		livecrafts_snapshot_save( $type, $id, 'after', $after, null, $cid );
	}
}

/** "title, Elementor content, ACF hero_title" from changed paths. */
function livecrafts_describe_diff( array $diff ) {
	$names = array();
	foreach ( $diff as $path ) {
		if ( $path === 'css' ) { $names[] = 'Additional CSS'; continue; }
		list( $part, $key ) = array_pad( explode( '.', $path, 2 ), 2, '' );
		if ( $part === 'fields' ) $names[] = $key;
		elseif ( strpos( $key, '_elementor_' ) === 0 ) $names[] = 'Elementor ' . str_replace( array( '_elementor_', '_' ), array( '', ' ' ), $key );
		elseif ( $key !== '' && $key[0] !== '_' ) $names[] = $key;
		elseif ( in_array( $key, array( '_thumbnail_id', '_wp_page_template' ), true ) ) $names[] = $key === '_thumbnail_id' ? 'featured image' : 'template';
	}
	$names = array_values( array_unique( $names ) );
	if ( ! $names ) return 'settings';
	return implode( ', ', array_slice( $names, 0, 6 ) ) . ( count( $names ) > 6 ? ' …' : '' );
}

function livecrafts_latest_revision( $post_id ) {
	$revs = wp_get_post_revisions( $post_id, array( 'numberposts' => 1, 'fields' => 'ids' ) );
	return $revs ? (int) reset( $revs ) : 0;
}
