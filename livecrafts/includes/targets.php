<?php
/**
 * ACF fields: the REAL source of many values shown on a page.
 *
 * Target id (used by the map and the assistant):  acf:<field_key>:<post_id>      e.g. acf:field_aero_hero_title:5
 * A change to an ACF field is the kind "acf.field" (see kinds.php); here are the helpers it uses:
 *   scan     every ACF field of a post with its value, read from a post's data array (live or draft)
 *   validate a new value for the field's own type (text, url, image ...)
 * Sub-fields (inside a repeater / group / flexible content) are not editable yet: never guess.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function livecrafts_bad_value( $msg ) {
	return new WP_Error( 'livecrafts_bad_value', $msg, array( 'status' => 400 ) );
}

function livecrafts_acf_active() {
	return function_exists( 'acf_get_field_groups' ) && function_exists( 'acf_get_fields' ) && function_exists( 'get_field_object' );
}

function livecrafts_parse_target( $id ) {
	if ( preg_match( '/^acf:(field_[A-Za-z0-9_]+):(\d+)$/', (string) $id, $m ) ) {
		return array( 'type' => 'acf', 'key' => $m[1], 'post' => (int) $m[2] );
	}
	// ACF value inside a group or a row, by its meta name:  acfv:<meta name>:<post>
	if ( preg_match( '/^acfv:([A-Za-z0-9_-]{1,190}):(\d+)$/', (string) $id, $m ) ) {
		return array( 'type' => 'acfv', 'name' => $m[1], 'post' => (int) $m[2] );
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
		'mime'            => get_post_mime_type( $id ),
		'width'           => isset( $meta['width'] ) ? $meta['width'] : null,
		'height'          => isset( $meta['height'] ) ? $meta['height'] : null,
		'alt'             => get_post_meta( $id, '_wp_attachment_image_alt', true ),
		'title'           => get_the_title( $id ),
		'media_library'   => admin_url( 'post.php?post=' . $id . '&action=edit' ),
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

/** The ACF field definition behind a key, only when it belongs to this post's field groups and can be edited. */
function livecrafts_acf_field( $key, $post_id ) {
	if ( ! livecrafts_acf_active() ) return null;
	foreach ( (array) acf_get_field_groups( array( 'post_id' => $post_id ) ) as $group ) {
		foreach ( (array) acf_get_fields( $group ) as $field ) {
			if ( isset( $field['key'] ) && $field['key'] === $key ) {
				$field['group_title'] = isset( $group['title'] ) ? $group['title'] : '';
				return $field;
			}
		}
	}
	return null;
}

/** One map entry for a field + its stored (raw) value. */
function livecrafts_acf_entry( $field, $post_id, $raw ) {
	if ( empty( $field['key'] ) || empty( $field['type'] ) || ! in_array( $field['type'], livecrafts_supported_types(), true ) ) return null;
	if ( livecrafts_is_subfield( $field ) ) return null;
	$entry = array(
		'kind'  => 'acf',
		'tid'   => 'acf:' . $field['key'] . ':' . (int) $post_id,
		'key'   => $field['key'],
		'name'  => isset( $field['name'] ) ? $field['name'] : '',
		'label' => ! empty( $field['label'] ) ? $field['label'] : ( isset( $field['name'] ) ? $field['name'] : '' ),
		'ftype' => $field['type'],
		'post'  => (int) $post_id,
		'value' => '',
		'url'   => '',
	);
	if ( $field['type'] === 'image' ) {
		$id = is_numeric( $raw ) ? (int) $raw : ( is_array( $raw ) && isset( $raw['ID'] ) ? (int) $raw['ID'] : 0 );
		if ( $id ) { $entry['value'] = $id; $entry['url'] = (string) wp_get_attachment_url( $id ); }
	} elseif ( is_string( $raw ) || is_numeric( $raw ) ) {
		$entry['value'] = mb_substr( (string) $raw, 0, 2000 );
	}
	return $entry;
}

/** Every editable ACF field of a post, with values from a post data array (live or draft). */
function livecrafts_acf_scan( $post_id, array $data ) {
	$out = array();
	if ( ! livecrafts_acf_active() ) return $out;
	foreach ( (array) acf_get_field_groups( array( 'post_id' => $post_id ) ) as $group ) {
		livecrafts_acf_walk( (array) acf_get_fields( $group ), '', isset( $group['title'] ) ? $group['title'] : '', $post_id, $data, $out );
	}
	return $out;
}

/**
 * Walk fields the way ACF stores them in post meta:
 *   group            "<group>_<sub>"
 *   repeater         "<name>" = row count,               rows "<name>_<i>_<sub>"
 *   flexible content "<name>" = list of layout names,     rows "<name>_<i>_<sub>"   (sub fields of that row's layout)
 * Values inside groups and rows get target ids "acfv:<meta name>:<post>"; repeaters and flexible content get an
 * "acf-rows" entry (rows can be added, removed, moved, duplicated).
 */
function livecrafts_acf_walk( array $fields, $prefix, $where, $post_id, array $data, array &$out ) {
	foreach ( $fields as $field ) {
		if ( empty( $field['name'] ) || empty( $field['type'] ) ) continue;
		$meta  = $prefix . $field['name'];
		$label = trim( $where . ' › ' . ( $field['label'] !== '' ? $field['label'] : $field['name'] ), ' ›' );
		$type  = $field['type'];
		if ( in_array( $type, livecrafts_supported_types(), true ) ) {
			$raw = isset( $data['meta'][ $meta ] ) ? $data['meta'][ $meta ] : '';
			$e   = livecrafts_acf_entry( array_merge( $field, array( 'parent' => '' ) ), $post_id, $raw );
			if ( ! $e ) continue;
			if ( $prefix !== '' ) { $e['tid'] = 'acfv:' . $meta . ':' . (int) $post_id; $e['name'] = $meta; }
			$e['label'] = $label;
			$out[]      = $e;
		} elseif ( $type === 'group' && ! empty( $field['sub_fields'] ) ) {
			livecrafts_acf_walk( $field['sub_fields'], $meta . '_', $label, $post_id, $data, $out );
		} elseif ( $type === 'repeater' || $type === 'flexible_content' ) {
			$rows    = livecrafts_acf_row_layouts( $field, isset( $data['meta'][ $meta ] ) ? $data['meta'][ $meta ] : '' );
			$entry   = array( 'kind' => 'acf-rows', 'tid' => 'acfrows:' . $meta . ':' . (int) $post_id, 'key' => $field['key'], 'name' => $meta, 'label' => $label,
				'ftype' => $type, 'post' => (int) $post_id, 'rows' => count( $rows ), 'min' => isset( $field['min'] ) ? (int) $field['min'] : 0, 'max' => isset( $field['max'] ) ? (int) $field['max'] : 0 );
			if ( $type === 'flexible_content' ) {
				$entry['layouts']      = array_values( array_map( function ( $l ) { return array( 'name' => $l['name'], 'label' => $l['label'] ); }, (array) $field['layouts'] ) );
				$entry['row_layouts'] = $rows;
			}
			$out[] = $entry;
			foreach ( $rows as $i => $layout ) {
				$subs = livecrafts_acf_row_fields( $field, $layout );
				livecrafts_acf_walk( $subs, $meta . '_' . $i . '_', $label . ' › ' . ( $type === 'flexible_content' ? $layout . ' ' : 'Row ' ) . ( $i + 1 ), $post_id, $data, $out );
			}
		}
	}
}

/** Rows of a repeater / flexible content field from its stored value: index => layout name ('' for a repeater). */
function livecrafts_acf_row_layouts( array $field, $stored ) {
	if ( $field['type'] === 'flexible_content' ) return array_values( array_map( 'strval', (array) maybe_unserialize( $stored ) ) );
	$n = max( 0, (int) $stored );
	return $n ? array_fill( 0, $n, '' ) : array();
}

/** The sub fields of one row (a flexible content row uses its layout's sub fields). */
function livecrafts_acf_row_fields( array $field, $layout ) {
	if ( $field['type'] !== 'flexible_content' ) return isset( $field['sub_fields'] ) ? (array) $field['sub_fields'] : array();
	foreach ( (array) $field['layouts'] as $l ) {
		if ( isset( $l['name'] ) && $l['name'] === $layout ) return isset( $l['sub_fields'] ) ? (array) $l['sub_fields'] : array();
	}
	return array();
}

/** The field behind a stored meta value, from its reference "_<meta name>" => field key. */
function livecrafts_acf_field_of( array $data, $meta ) {
	$ref = isset( $data['meta'][ '_' . $meta ] ) ? $data['meta'][ '_' . $meta ] : '';
	if ( ! is_string( $ref ) || strpos( $ref, 'field_' ) !== 0 || ! function_exists( 'acf_get_field' ) ) return null;
	$field = acf_get_field( $ref );
	return is_array( $field ) ? $field : null;
}

/** Meta of a new, empty row: "<suffix>" => value and "_<suffix>" => field key, for every simple/group sub field. */
function livecrafts_acf_blank_row( array $sub_fields, $prefix = '' ) {
	$row = array();
	foreach ( $sub_fields as $f ) {
		if ( empty( $f['name'] ) ) continue;
		$suffix = $prefix . $f['name'];
		$row[ '_' . $suffix ] = $f['key'];
		if ( $f['type'] === 'group' && ! empty( $f['sub_fields'] ) ) {
			$row[ $suffix ] = '';
			$row += livecrafts_acf_blank_row( $f['sub_fields'], $suffix . '_' );
		} elseif ( $f['type'] === 'repeater' || $f['type'] === 'flexible_content' ) {
			$row[ $suffix ] = $f['type'] === 'repeater' ? 0 : array();
		} else {
			$row[ $suffix ] = isset( $f['default_value'] ) && is_scalar( $f['default_value'] ) ? (string) $f['default_value'] : '';
		}
	}
	return $row;
}

/**
 * Do one row operation on post meta. $name = the repeater / flexible content meta name.
 * $op: array( op => add|remove|move|duplicate, index, to?, layout?, row? (suffix => value, for add), type ).
 * Rows are renumbered exactly as ACF numbers them; nested data moves with its row.
 */
function livecrafts_acf_rows_apply( array $meta, $name, array $op ) {
	$flex    = $op['type'] === 'flexible_content';
	$layouts = $flex ? array_values( (array) ( isset( $meta[ $name ] ) ? $meta[ $name ] : array() ) ) : null;
	$count   = $flex ? count( $layouts ) : max( 0, (int) ( isset( $meta[ $name ] ) ? $meta[ $name ] : 0 ) );
	$rows    = $count ? array_fill( 0, $count, array() ) : array();
	$pattern = '/^(_?)' . preg_quote( $name, '/' ) . '_(\d+)_(.+)$/';
	foreach ( array_keys( $meta ) as $key ) {
		if ( ! preg_match( $pattern, $key, $m ) || (int) $m[2] >= $count ) continue;
		$rows[ (int) $m[2] ][ $m[1] . $m[3] ] = $meta[ $key ];
		unset( $meta[ $key ] );
	}
	$i = (int) $op['index'];
	switch ( $op['op'] ) {
		case 'add':
			array_splice( $rows, $i, 0, array( (array) $op['row'] ) );
			if ( $flex ) array_splice( $layouts, $i, 0, array( (string) $op['layout'] ) );
			break;
		case 'remove':
			array_splice( $rows, $i, 1 );
			if ( $flex ) array_splice( $layouts, $i, 1 );
			break;
		case 'duplicate':
			array_splice( $rows, $i + 1, 0, array( $rows[ $i ] ) );
			if ( $flex ) array_splice( $layouts, $i + 1, 0, array( $layouts[ $i ] ) );
			break;
		case 'move':
			$row = array_splice( $rows, $i, 1 );
			array_splice( $rows, (int) $op['to'], 0, $row );
			if ( $flex ) { $l = array_splice( $layouts, $i, 1 ); array_splice( $layouts, (int) $op['to'], 0, $l ); }
			break;
	}
	foreach ( array_values( $rows ) as $n => $row ) {
		foreach ( $row as $suffix => $value ) {
			$key          = $suffix[0] === '_' ? '_' . $name . '_' . $n . '_' . substr( $suffix, 1 ) : $name . '_' . $n . '_' . $suffix;
			$meta[ $key ] = $value;
		}
	}
	$meta[ $name ] = $flex ? $layouts : count( $rows );
	ksort( $meta );
	return $meta;
}

/** All meta of one row: suffix => value (refs as "_<suffix>"). */
function livecrafts_acf_row_meta( array $meta, $name, $index ) {
	$row     = array();
	$pattern = '/^(_?)' . preg_quote( $name, '/' ) . '_' . (int) $index . '_(.+)$/';
	foreach ( $meta as $key => $value ) {
		if ( preg_match( $pattern, $key, $m ) ) $row[ $m[1] . $m[2] ] = $value;
	}
	return $row;
}

/** Validate/sanitize a new value for an ACF field of its own type. Returns the clean value or WP_Error. */
function livecrafts_validate_acf_value( $field, $value ) {
	$type = isset( $field['type'] ) ? $field['type'] : '';

	if ( $type === 'image' ) {
		$id = absint( $value );
		if ( $value === '' || $value === null ) return '';
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
