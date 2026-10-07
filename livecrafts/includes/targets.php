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
		foreach ( (array) acf_get_fields( $group ) as $field ) {
			$name = isset( $field['name'] ) ? $field['name'] : '';
			$raw  = ( $name !== '' && isset( $data['meta'][ $name ] ) ) ? $data['meta'][ $name ] : '';
			$e    = livecrafts_acf_entry( $field, $post_id, $raw );
			if ( $e ) { $e['group'] = isset( $group['title'] ) ? $group['title'] : ''; $out[] = $e; }
		}
	}
	return $out;
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
