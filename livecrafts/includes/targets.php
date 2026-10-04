<?php
/**
 * Targets = the REAL source of a value shown on the page (v0.3: ACF fields).
 *
 * Target id:  acf:<field_key>:<post_id>      e.g. acf:field_aero_hero_title:5
 *
 *  A) SCAN   - ask ACF for every field of the current post (acf_get_field_groups + acf_get_fields + get_field).
 *              Works even from a cached page; includes empty fields.
 *  B) TRACE  - for logged-in editors, remember every ACF value the page READS while it renders (acf/format_value).
 *              Knows render order and values from other posts / option pages.
 *  The editor script merges both (trace first, scan as fallback) and matches the clicked element against them.
 *  C) WRITE-BACK - POST /livecrafts/v1/target validates the value for that field TYPE, writes with update_field(),
 *              READS IT BACK and rolls back + errors if the stored value is not what we wrote ("no fake saves").
 *  D) DEBUG  - GET /livecrafts/v1/debug/{fields,target} return what the server sees, for testing.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function livecrafts_parse_target( $id ) {
	if ( preg_match( '/^acf:(field_[A-Za-z0-9_]+):(\d+)$/', (string) $id, $m ) ) {
		return array( 'type' => 'acf', 'key' => $m[1], 'post' => (int) $m[2] );
	}
	// Elementor:  el:<post>:<widget id>:<setting>   (setting may be "link.url")
	if ( preg_match( '/^el:(\d+):([A-Za-z0-9]{3,16}):([a-z_]+(?:\.[a-z_]+)?)$/', (string) $id, $m ) ) {
		return array( 'type' => 'el', 'post' => (int) $m[1], 'id' => $m[2], 'path' => $m[3] );
	}
	return null;
}

/** Everything we can tell an editor about an image attachment: where the file really lives. */
function livecrafts_attachment_info( $id ) {
	$id = (int) $id;
	if ( ! $id || get_post_type( $id ) !== 'attachment' ) return null;
	$file = get_attached_file( $id );
	$meta = wp_get_attachment_metadata( $id );
	return array(
		'attachment_id'   => $id,
		'url'             => wp_get_attachment_url( $id ),
		'path_in_uploads' => get_post_meta( $id, '_wp_attached_file', true ),
		'file_exists'     => (bool) ( $file && file_exists( $file ) ),
		'bytes'           => ( $file && file_exists( $file ) ) ? filesize( $file ) : null,
		'mime'            => get_post_mime_type( $id ),
		'width'           => isset( $meta['width'] ) ? $meta['width'] : null,
		'height'          => isset( $meta['height'] ) ? $meta['height'] : null,
		'generated_sizes' => ( isset( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) ? array_keys( $meta['sizes'] ) : array(),
		'title'           => get_the_title( $id ),
		'uploaded_gmt'    => get_post_field( 'post_date_gmt', $id ),
		'media_library'   => admin_url( 'post.php?post=' . $id . '&action=edit' ),
		'where_stored'    => 'The file is in wp-content/uploads/; a row in wp_posts (post_type attachment) holds its ID; wp_postmeta keys _wp_attached_file and _wp_attachment_metadata hold its path and sizes.',
	);
}

function livecrafts_supported_types() {
	return array( 'text', 'textarea', 'wysiwyg', 'url', 'email', 'number', 'image' );
}

/** Sub-fields (inside a repeater / group / flexible content) are not supported yet: never guess. */
function livecrafts_is_subfield( $field ) {
	$parent = isset( $field['parent'] ) ? $field['parent'] : '';
	if ( is_string( $parent ) && strpos( $parent, 'field_' ) === 0 ) return true;
	if ( is_numeric( $parent ) && get_post_type( (int) $parent ) === 'acf-field' ) return true;
	return false;
}

/** The post the current page belongs to (from the page key "p<ID>"), or 0. */
function livecrafts_current_post_id() {
	$key = livecrafts_page_key();
	return preg_match( '/^p(\d+)$/', $key, $m ) ? (int) $m[1] : 0;
}

/** One normalized record for a field + value; used by both the scan and the trace. */
function livecrafts_make_entry( $field, $post_id, $value, $source ) {
	if ( empty( $field['key'] ) || empty( $field['type'] ) || ! in_array( $field['type'], livecrafts_supported_types(), true ) ) return null;
	if ( livecrafts_is_subfield( $field ) ) return null;

	$type  = $field['type'];
	$entry = array(
		'kind'  => 'acf',
		'tid'   => 'acf:' . $field['key'] . ':' . (int) $post_id,
		'key'   => $field['key'],
		'name'  => isset( $field['name'] ) ? $field['name'] : '',
		'label' => ! empty( $field['label'] ) ? $field['label'] : ( isset( $field['name'] ) ? $field['name'] : '' ),
		'ftype' => $type,
		'post'  => (int) $post_id,
		'value' => '',
		'url'   => '',
		'src'   => $source,
	);

	if ( $type === 'image' ) {
		$id = 0;
		if ( is_array( $value ) && isset( $value['ID'] ) )   $id = (int) $value['ID'];
		elseif ( is_numeric( $value ) )                      $id = (int) $value;
		elseif ( is_string( $value ) && $value !== '' )      $id = (int) attachment_url_to_postid( $value );
		if ( $id ) { $entry['value'] = $id; $entry['url'] = (string) wp_get_attachment_url( $id ); }
	} elseif ( is_string( $value ) || is_numeric( $value ) ) {
		$entry['value'] = mb_substr( (string) $value, 0, 2000 );
	}
	return $entry;
}

/* ------------------------------------------------------------------ A) scan */

function livecrafts_scan_fields( $post_id ) {
	$out = array();
	if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) || ! function_exists( 'get_field' ) ) return $out;
	foreach ( (array) acf_get_field_groups( array( 'post_id' => $post_id ) ) as $group ) {
		foreach ( (array) acf_get_fields( $group ) as $field ) {
			$e = livecrafts_make_entry( $field, $post_id, get_field( $field['key'], $post_id, false ), 'scan' );
			if ( $e ) { $e['group'] = isset( $group['title'] ) ? $group['title'] : ''; $out[] = $e; }
		}
	}
	return $out;
}

/* ------------------------------------------------------------------ B) render trace */

/** Get (no arg) or add (array arg) trace entries. First read of a field wins (ACF may re-read the same field). */
function livecrafts_trace_store( $entry = null ) {
	static $trace = array(), $seen = array();
	if ( $entry === null ) return $trace;
	$id = $entry['key'] . ':' . $entry['post'];
	if ( isset( $seen[ $id ] ) ) return $trace;
	$seen[ $id ] = true;
	$trace[]     = $entry;
	return $trace;
}

add_filter( 'acf/format_value', function ( $value, $post_id, $field ) {
	if ( is_admin() || ! is_numeric( $post_id ) || ! livecrafts_user_can_edit() ) return $value;
	$entry = livecrafts_make_entry( $field, $post_id, $value, 'trace' );
	if ( $entry ) livecrafts_trace_store( $entry );
	return $value;
}, 99, 3 );

/** Hand trace + scan to the editor script (printed just before it, in the footer). */
add_action( 'wp_footer', function () {
	if ( ! livecrafts_user_can_edit() || ! wp_script_is( 'livecrafts-editor', 'enqueued' ) ) return;
	$pid   = livecrafts_current_post_id();
	$flags = JSON_HEX_TAG | JSON_HEX_AMP;
	$trace = wp_json_encode( livecrafts_trace_store(), $flags );
	$scan  = wp_json_encode( $pid ? livecrafts_scan_fields( $pid ) : array(), $flags );
	$el    = wp_json_encode( $pid ? livecrafts_el_scan( $pid ) : array(), $flags );
	wp_add_inline_script( 'livecrafts-editor', 'window.LIVECRAFTS_TRACE=' . ( $trace ? $trace : '[]' ) . ';window.LIVECRAFTS_SCAN=' . ( $scan ? $scan : '[]' ) . ';window.LIVECRAFTS_EL=' . ( $el ? $el : '[]' ) . ';', 'before' );
}, 5 );

/* ------------------------------------------------------------------ C) write-back (verified) */

function livecrafts_bad_value( $msg ) {
	return new WP_Error( 'livecrafts_bad_value', $msg, array( 'status' => 400 ) );
}

/** Validate/sanitize a new value for an ACF field of its own type. Returns the clean value or WP_Error. */
function livecrafts_validate_acf_value( $field, $value ) {
	$type = isset( $field['type'] ) ? $field['type'] : '';

	if ( $type === 'image' ) {
		$id = absint( $value );
		if ( ! $id || ! wp_attachment_is_image( $id ) ) return livecrafts_bad_value( 'That is not an image from the Media Library.' );
		return $id;
	}

	if ( ! is_string( $value ) && ! is_numeric( $value ) ) return livecrafts_bad_value( 'Invalid value.' );
	$value = (string) $value;

	switch ( $type ) {
		case 'text':     $v = sanitize_text_field( $value ); break;
		case 'textarea': $v = sanitize_textarea_field( $value ); break;
		case 'wysiwyg':  $v = wp_kses_post( $value ); break;
		case 'url':
			$v = esc_url_raw( $value );
			if ( $v === '' && $value !== '' ) return livecrafts_bad_value( 'Not a valid URL.' );
			break;
		case 'email':
			$v = sanitize_email( $value );
			if ( $value !== '' && ! is_email( $v ) ) return livecrafts_bad_value( 'Not a valid email address.' );
			break;
		case 'number':
			if ( $value !== '' && ! is_numeric( $value ) ) return livecrafts_bad_value( 'Not a number.' );
			$v = $value === '' ? '' : $value + 0;
			break;
		default:
			return livecrafts_bad_value( 'Field type "' . $type . '" cannot be edited yet.' );
	}

	if ( ! empty( $field['maxlength'] ) && is_string( $v ) && mb_strlen( $v ) > (int) $field['maxlength'] ) {
		return livecrafts_bad_value( 'Too long (max ' . (int) $field['maxlength'] . ' characters for this field).' );
	}
	return $v;
}

function livecrafts_values_equal( $type, $a, $b ) {
	if ( $type === 'image' )  return (int) $a === (int) $b;
	if ( $type === 'number' ) return (string) $a === (string) $b || ( is_numeric( $a ) && is_numeric( $b ) && (float) $a === (float) $b );
	return (string) $a === (string) $b;
}

/** POST /livecrafts/v1/target  {targetId, value} -> update the ACF field, READ IT BACK, roll back if it did not stick. */
function livecrafts_rest_target( WP_REST_Request $req ) {
	$raw = (string) $req->get_param( 'targetId' );
	$t   = livecrafts_parse_target( $raw );
	if ( ! $t ) return new WP_Error( 'livecrafts_bad_request', 'Invalid target.', array( 'status' => 400 ) );
	if ( ! current_user_can( 'edit_post', $t['post'] ) ) return new WP_Error( 'livecrafts_forbidden', 'You cannot edit this content.', array( 'status' => 403 ) );
	if ( $t['type'] === 'el' ) return livecrafts_el_rest_target( $req, $t, $raw ); // Elementor adapter
	if ( ! function_exists( 'get_field_object' ) || ! function_exists( 'update_field' ) ) return new WP_Error( 'livecrafts_no_acf', 'ACF is not active on this site.', array( 'status' => 500 ) );

	$field = get_field_object( $t['key'], $t['post'], false, false );
	if ( ! $field ) return new WP_Error( 'livecrafts_no_field', 'That ACF field no longer exists.', array( 'status' => 404 ) );
	$type = isset( $field['type'] ) ? $field['type'] : '';

	$clean = livecrafts_validate_acf_value( $field, $req->get_param( 'value' ) );
	if ( is_wp_error( $clean ) ) return $clean;

	$before = get_field( $t['key'], $t['post'], false ); // raw stored value, for Undo + rollback
	$url    = $type === 'image' ? (string) wp_get_attachment_url( $clean ) : '';
	if ( livecrafts_values_equal( $type, $before, $clean ) ) {
		return array( 'ok' => true, 'unchanged' => true, 'value' => $clean, 'url' => $url, 'verified' => array( 'database' => true ) );
	}

	update_field( $t['key'], $clean, $t['post'] );
	clean_post_cache( $t['post'] );

	// READ BACK from the database - never trust the write call alone.
	$after = get_field( $t['key'], $t['post'], false );
	if ( ! livecrafts_values_equal( $type, $after, $clean ) ) {
		update_field( $t['key'], $before === null ? '' : $before, $t['post'] ); // best-effort rollback
		clean_post_cache( $t['post'] );
		return new WP_Error(
			'livecrafts_verify_failed',
			'NOT SAVED: ACF accepted the write but the stored value is "' . mb_substr( is_scalar( $after ) ? (string) $after : wp_json_encode( $after ), 80 ) . '" instead of the new value. Nothing was changed.',
			array( 'status' => 500 )
		);
	}

	livecrafts_log_add(
		'p' . $t['post'], $raw,
		array( 'text' => (string) $before ), array( 'text' => (string) $clean ),
		array( 'type' => 'acf', 'field_key' => $t['key'], 'post' => $t['post'], 'raw_before' => $before, 'label' => isset( $field['label'] ) ? $field['label'] : '' )
	);
	return array(
		'ok' => true, 'value' => $clean, 'url' => $url, 'field' => isset( $field['name'] ) ? $field['name'] : '',
		'verified' => array( 'database' => true ),
	);
}

/* ------------------------------------------------------------------ D) debug endpoints (read-only) */

/** GET /livecrafts/v1/debug/fields?post=ID -> exactly what the scan sees for that post. */
function livecrafts_rest_debug_fields( WP_REST_Request $req ) {
	$post = (int) $req->get_param( 'post' );
	if ( ! $post || ! current_user_can( 'edit_post', $post ) ) return new WP_Error( 'livecrafts_forbidden', 'Unknown post or not allowed.', array( 'status' => 403 ) );
	$fields = livecrafts_scan_fields( $post );
	return array(
		'post'      => $post,
		'post_type' => get_post_type( $post ),
		'title'     => get_the_title( $post ),
		'acf_active'=> function_exists( 'get_field' ),
		'count'     => count( $fields ),
		'fields'    => $fields,
	);
}

/** GET /livecrafts/v1/debug/target?targetId=acf:key:post -> the field definition, the stored value, and recent changes. */
function livecrafts_rest_debug_target( WP_REST_Request $req ) {
	$raw = (string) $req->get_param( 'targetId' );
	$t   = livecrafts_parse_target( $raw );
	if ( ! $t ) return new WP_Error( 'livecrafts_bad_request', 'Invalid target.', array( 'status' => 400 ) );
	if ( ! current_user_can( 'edit_post', $t['post'] ) ) return new WP_Error( 'livecrafts_forbidden', 'You cannot read this content.', array( 'status' => 403 ) );
	if ( $t['type'] === 'el' ) return livecrafts_el_debug_target( $t, $raw );
	if ( ! function_exists( 'get_field_object' ) ) return new WP_Error( 'livecrafts_no_acf', 'ACF is not active.', array( 'status' => 500 ) );

	$field = get_field_object( $t['key'], $t['post'], false, false );
	if ( ! $field ) return new WP_Error( 'livecrafts_no_field', 'Field not found.', array( 'status' => 404 ) );

	$recent = array();
	foreach ( array_reverse( (array) get_option( LIVECRAFTS_LOG, array() ) ) as $e ) {
		if ( isset( $e['selector'] ) && $e['selector'] === $raw ) { $recent[] = array( 'when' => gmdate( 'c', $e['ts'] ), 'user' => $e['user'], 'before' => $e['before'], 'after' => $e['after'] ); }
		if ( count( $recent ) >= 5 ) break;
	}

	$name  = isset( $field['name'] ) ? $field['name'] : '';
	$extra = array();
	if ( isset( $field['type'] ) && $field['type'] === 'image' ) $extra['attachment'] = livecrafts_attachment_info( get_field( $t['key'], $t['post'], false ) );
	return $extra + array(
		'target'      => $raw,
		'field'       => array( 'key' => $field['key'], 'name' => $name, 'label' => isset( $field['label'] ) ? $field['label'] : '', 'type' => isset( $field['type'] ) ? $field['type'] : '', 'parent' => isset( $field['parent'] ) ? $field['parent'] : '', 'maxlength' => isset( $field['maxlength'] ) ? $field['maxlength'] : '', 'is_subfield' => livecrafts_is_subfield( $field ) ),
		'stored_raw'  => get_field( $t['key'], $t['post'], false ),
		'formatted'   => get_field( $t['key'], $t['post'], true ),
		'meta'        => $name ? get_post_meta( $t['post'], $name, true ) : null,
		'meta_ref'    => $name ? get_post_meta( $t['post'], '_' . $name, true ) : null,
		'post'        => array( 'id' => $t['post'], 'type' => get_post_type( $t['post'] ), 'title' => get_the_title( $t['post'] ), 'edit_link' => get_edit_post_link( $t['post'], 'raw' ) ),
		'recent_changes' => $recent,
	);
}
