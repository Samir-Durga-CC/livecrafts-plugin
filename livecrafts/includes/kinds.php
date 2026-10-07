<?php
/**
 * Change kinds: what Livecrafts can change, and how. Each kind works on an object data array (snapshots.php), so the
 * same code builds the draft preview (live + draft changes) and the deploy (the same changes on top of the live site).
 *
 *   kind => array(
 *     'object'  => 'post' | 'css',
 *     'prepare' => fn( $object_id, $target, $value, array $draft, array $args, $actor )
 *                  -> array( 'target', 'after', 'payload' => array(...), 'summary' [, 'before'] ) | WP_Error
 *                  validates and cleans the new value against the draft state (sanitizing like WordPress would on save)
 *     'read'    => fn( array $data, $target, array $payload ) -> the current value of that target
 *     'apply'   => fn( array &$data, $target, $after, array $payload ) -> true | WP_Error
 *     'equal'   => optional fn( $a, $b, $target ) -> bool (default: livecrafts_same)
 *     'group'   => optional string or Closure( $target ): changes in one group are checked for conflicts together
 *                  (default: each target on its own)
 *     'base'    => optional fn( array $data, $target, array $payload ): what a conflict check compares (default: read)
 *     'landed'  => optional fn( array $saved, array $expected ) -> bool: did the write really land (default: read + equal)
 *     'revert'  => optional fn( array $change ) -> array( kind, target, value, args ): the opposite of a live change
 *                  (default: the same kind with the old value)
 *   )
 *   prepare may return 'unchanged' => true when the value is already what was asked.
 *
 * A draft change stores before (the value in the draft when it was made), after, and base: a fingerprint of the LIVE
 * value when it was made. When deploying, the live value must still match base - otherwise someone changed it
 * meanwhile and the person decides (conflict).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function livecrafts_kinds() {
	return array(
		'post.field' => array(
			'object'  => 'post',
			'group'   => function ( $target ) { return $target === 'content' ? 'content' : 'field:' . $target; },
			'prepare' => 'livecrafts_kind_post_field_prepare',
			'read'    => function ( $data, $target ) { return isset( $data['fields'][ $target ] ) ? $data['fields'][ $target ] : ''; },
			'apply'   => function ( &$data, $target, $after ) { $data['fields'][ $target ] = (string) $after; return true; },
		),
		'acf.field' => array(
			'object'  => 'post',
			'prepare' => 'livecrafts_kind_acf_prepare',
			'read'    => function ( $data, $target, $payload ) { return isset( $data['meta'][ $payload['name'] ] ) ? $data['meta'][ $payload['name'] ] : ''; },
			'apply'   => function ( &$data, $target, $after, $payload ) {
				$data['meta'][ $payload['name'] ]       = $after;
				$data['meta'][ '_' . $payload['name'] ] = $target; // ACF's reference from the value to its field
				return true;
			},
		),
		'el.setting' => array(
			'object'  => 'post',
			'prepare' => 'livecrafts_kind_el_prepare',
			'read'    => function ( $data, $target ) {
				list( $id, $path ) = explode( ':', $target, 2 );
				$r = livecrafts_el_read( livecrafts_el_tree( $data ), $id, $path );
				return $r['value'];
			},
			'apply'   => function ( &$data, $target, $after, $payload ) {
				list( $id, $path ) = explode( ':', $target, 2 );
				$tree = livecrafts_el_tree( $data );
				$r    = livecrafts_el_write_tree( $tree, $id, $path, $after, isset( $payload['also'] ) && $after !== '' ? (array) $payload['also'] : array() );
				if ( is_wp_error( $r ) ) return $r;
				livecrafts_el_set_tree( $data, $tree );
				return true;
			},
			'equal'   => function ( $a, $b, $target ) {
				$path = substr( $target, strpos( $target, ':' ) + 1 );
				return livecrafts_el_values_equal( $path, $a, $b );
			},
		),
		// Elementor structure: add, remove, duplicate, move elements (elementor.php).
		'el.insert'    => livecrafts_el_structure_kind( 'insert' ),
		'el.remove'    => livecrafts_el_structure_kind( 'remove' ),
		'el.duplicate' => livecrafts_el_structure_kind( 'duplicate' ),
		'el.move'      => livecrafts_el_structure_kind( 'move' ),
		'css.block' => array(
			'object'  => 'css',
			'prepare' => 'livecrafts_kind_css_prepare',
			'read'    => function ( $data, $target ) { return livecrafts_css_block_get( $data['css'], $target ); },
			'apply'   => function ( &$data, $target, $after, $payload ) {
				$data['css'] = livecrafts_css_block_set( $data['css'], $target, (string) $after, isset( $payload['label'] ) ? $payload['label'] : '' );
				return true;
			},
		),
		// One style rule (selector + screen size) in its own Additional CSS block. Values merge; '' removes a property.
		'css.rule' => array(
			'object'  => 'css',
			'prepare' => 'livecrafts_kind_css_rule_prepare',
			'read'    => function ( $data, $target ) { return livecrafts_css_rule_parse( livecrafts_css_block_get( $data['css'], $target ) ); },
			'apply'   => function ( &$data, $target, $after, $payload ) {
				$after = (array) $after;
				$text  = $after ? livecrafts_css_rule_text( $payload['selector'], $payload['media'], $after ) : '';
				$data['css'] = livecrafts_css_block_set( $data['css'], $target, $text, $payload['label'] );
				$animated = ! empty( $after['animation-name'] ) && $after['animation-name'] !== 'none';
				if ( $animated && livecrafts_css_block_get( $data['css'], 'lc-animations' ) === '' ) {
					$data['css'] = livecrafts_css_block_set( $data['css'], 'lc-animations', livecrafts_css_animations(), 'Livecrafts entrance animations' );
				}
				return true;
			},
			'revert'  => function ( $c ) {
				$p = $c['payload'];
				return array( 'css.rule', $c['target'], array( 'selector' => $p['selector'], 'media' => $p['media'], 'declarations' => (array) $p['before'], 'replace' => true ), array( 'label' => $p['label'] ) );
			},
		),
		// An ACF value anywhere: top level, inside a group, or in a repeater / flexible content row. Target = its meta name.
		'acf.value' => array(
			'object'  => 'post',
			'prepare' => 'livecrafts_kind_acf_value_prepare',
			'read'    => function ( $data, $target ) { return isset( $data['meta'][ $target ] ) ? $data['meta'][ $target ] : ''; },
			'apply'   => function ( &$data, $target, $after, $payload ) {
				$data['meta'][ $target ]       = $after;
				$data['meta'][ '_' . $target ] = $payload['key'];
				return true;
			},
		),
		// Rows of a repeater / flexible content field: add, remove, move, duplicate. Target = the field's meta name.
		'acf.rows' => array(
			'object'  => 'post',
			'group'   => function ( $target ) { return 'acf-rows:' . $target; },
			'prepare' => 'livecrafts_kind_acf_rows_prepare',
			'read'    => function ( $data, $target ) {
				$out     = array();
				$pattern = '/^_?' . preg_quote( $target, '/' ) . '(_\d+_|$)/';
				foreach ( $data['meta'] as $k => $v ) if ( preg_match( $pattern, $k ) ) $out[ $k ] = $v;
				ksort( $out );
				return $out;
			},
			'apply'   => function ( &$data, $target, $after ) {
				$data['meta'] = livecrafts_acf_rows_apply( $data['meta'], $target, (array) $after );
				return true;
			},
			'revert'  => 'livecrafts_acf_rows_revert',
		),
		// A few post settings stored as meta: featured image, page template, menu link address / new tab.
		'post.meta' => array(
			'object'  => 'post',
			'prepare' => 'livecrafts_kind_post_meta_prepare',
			'read'    => function ( $data, $target ) { return isset( $data['meta'][ $target ] ) ? $data['meta'][ $target ] : ''; },
			'apply'   => function ( &$data, $target, $after ) {
				if ( (string) $after === '' ) unset( $data['meta'][ $target ] ); else $data['meta'][ $target ] = $after;
				return true;
			},
		),
		// Blocks inside the page content (blocks.php).
		'block.text'      => livecrafts_block_kind( 'text' ),
		'block.link'      => livecrafts_block_kind( 'link' ),
		'block.image'     => livecrafts_block_kind( 'image' ),
		'block.class'     => livecrafts_block_kind( 'class' ),
		'block.replace'   => livecrafts_block_kind( 'replace' ),
		'block.insert'    => livecrafts_block_kind( 'insert' ),
		'block.remove'    => livecrafts_block_kind( 'remove' ),
		'block.move'      => livecrafts_block_kind( 'move' ),
		'block.duplicate' => livecrafts_block_kind( 'duplicate' ),
		// Put a whole object back to a recorded state (revert of a change made outside Livecrafts, or of a reset).
		'object.restore' => array(
			'object'  => 'any',
			'prepare' => 'livecrafts_kind_restore_prepare',
			'read'    => function ( $data ) { return livecrafts_data_hash( $data ); },
			'apply'   => function ( &$data, $target, $after ) { $data = $after; return true; },
		),
	);
}

function livecrafts_kind( $name ) {
	static $all = null;
	if ( $all === null ) $all = livecrafts_kinds();
	return isset( $all[ $name ] ) ? $all[ $name ] : null;
}

/** The conflict group of a change (see the kind interface above). */
function livecrafts_kind_group( array $kind, $target ) {
	if ( ! isset( $kind['group'] ) ) return 'target:' . $target;
	return $kind['group'] instanceof Closure ? call_user_func( $kind['group'], $target ) : $kind['group'];
}

/** Fingerprint of what a change depends on, in a data array (stored as payload base, compared when deploying). */
function livecrafts_kind_base( array $kind, array $data, $target, array $payload ) {
	$value = isset( $kind['base'] ) ? call_user_func( $kind['base'], $data, $target, $payload ) : call_user_func( $kind['read'], $data, $target, $payload );
	return md5( (string) wp_json_encode( $value ) );
}

/** Did a change land in $saved (the preview copy, or the live site after deploy) as in $expected? */
function livecrafts_kind_landed( array $kind, $saved, array $expected, $target, array $payload ) {
	if ( ! $saved ) return false;
	if ( isset( $kind['landed'] ) ) return (bool) call_user_func( $kind['landed'], $saved, $expected );
	$a = call_user_func( $kind['read'], $saved, $target, $payload );
	$b = call_user_func( $kind['read'], $expected, $target, $payload );
	return isset( $kind['equal'] ) ? (bool) call_user_func( $kind['equal'], $a, $b, $target ) : livecrafts_same( $a, $b );
}

function livecrafts_data_hash( $data ) {
	return md5( (string) wp_json_encode( $data ) );
}

function livecrafts_quote( $v, $max = 60 ) {
	if ( is_array( $v ) ) $v = isset( $v['url'] ) ? $v['url'] : wp_json_encode( $v );
	$t = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $v ) ) );
	return '“' . ( mb_strlen( $t ) > $max ? mb_substr( $t, 0, $max - 1 ) . '…' : $t ) . '”';
}

/** Clean a post field the way wp_update_post() will (kses for people without unfiltered_html, etc.). */
function livecrafts_sanitize_post_field( $field, $value, $post_id ) {
	return wp_unslash( sanitize_post_field( $field, wp_slash( (string) $value ), $post_id, 'db' ) );
}

/* ------------------------------------------------------------------ prepare */

function livecrafts_kind_post_field_prepare( $post_id, $target, $value, array $draft ) {
	$columns = array( 'title' => 'post_title', 'content' => 'post_content', 'excerpt' => 'post_excerpt', 'status' => 'post_status' );
	if ( ! isset( $columns[ $target ] ) ) return livecrafts_bad_value( 'Unknown field "' . $target . '" (title, content, excerpt or status).' );
	if ( ! is_string( $value ) ) return livecrafts_bad_value( 'The value must be text.' );
	if ( $target === 'status' ) {
		if ( ! in_array( $value, array( 'publish', 'draft', 'private' ), true ) ) return livecrafts_bad_value( 'Status must be publish, draft or private.' );
		$clean = $value;
	} else {
		$clean = livecrafts_sanitize_post_field( $columns[ $target ], $value, $post_id );
	}
	$label = array( 'title' => 'Title', 'content' => 'Content', 'excerpt' => 'Excerpt', 'status' => 'Status' );
	$old   = isset( $draft['fields'][ $target ] ) ? $draft['fields'][ $target ] : '';
	return array(
		'target'  => $target,
		'after'   => $clean,
		'payload' => array(),
		'summary' => $target === 'content' ? 'Content changed' : $label[ $target ] . ': ' . livecrafts_quote( $old ) . ' → ' . livecrafts_quote( $clean ),
	);
}

function livecrafts_kind_acf_prepare( $post_id, $target, $value, array $draft ) {
	if ( ! livecrafts_acf_active() ) return new WP_Error( 'livecrafts_no_acf', 'ACF is not active on this site.', array( 'status' => 400 ) );
	if ( ! preg_match( '/^field_[A-Za-z0-9_]+$/', (string) $target ) ) return livecrafts_bad_value( 'An ACF target is a field key like field_abc123.' );
	$field = livecrafts_acf_field( $target, $post_id );
	if ( ! $field ) return new WP_Error( 'livecrafts_no_field', 'That ACF field is not part of this page.', array( 'status' => 404 ) );
	if ( ! in_array( $field['type'], livecrafts_supported_types(), true ) || livecrafts_is_subfield( $field ) ) {
		return livecrafts_bad_value( 'ACF field type "' . $field['type'] . '" cannot be edited yet.' );
	}
	$clean = livecrafts_validate_acf_value( $field, $value );
	if ( is_wp_error( $clean ) ) return $clean;
	$label = ! empty( $field['label'] ) ? $field['label'] : $field['name'];
	$old   = isset( $draft['meta'][ $field['name'] ] ) ? $draft['meta'][ $field['name'] ] : '';
	$show  = function ( $v ) use ( $field ) { return $field['type'] === 'image' ? livecrafts_quote( $v ? wp_get_attachment_url( (int) $v ) : '(none)' ) : livecrafts_quote( $v ); };
	return array(
		'target'  => $target,
		'after'   => $clean,
		'payload' => array( 'name' => $field['name'], 'label' => $label, 'type' => $field['type'] ),
		'summary' => 'ACF ' . $label . ': ' . $show( $old ) . ' → ' . $show( $clean ),
	);
}

/** Any setting of an Elementor element, checked against the control Elementor defines for it. */
function livecrafts_kind_el_prepare( $post_id, $target, $value, array $draft ) {
	if ( ! livecrafts_el_active() ) return new WP_Error( 'livecrafts_no_elementor', 'Elementor is not active on this site.', array( 'status' => 400 ) );
	if ( ! preg_match( '/^([A-Za-z0-9]{3,16}):([a-z0-9_]{1,80}|link\.url)$/', (string) $target, $m ) ) {
		return livecrafts_bad_value( 'An Elementor setting is "<element id>:<setting>", e.g. 3f2a1c:title or 3f2a1c:title_color.' );
	}
	$tree = livecrafts_el_tree( $draft );
	if ( ! $tree ) return livecrafts_bad_value( 'This page is not built with Elementor.' );
	$node = livecrafts_el_node( $tree, $m[1] );
	if ( ! $node ) return new WP_Error( 'livecrafts_no_element', 'That Elementor element is not on this page.', array( 'status' => 404 ) );
	$name     = $m[2] === 'link.url' ? 'link' : $m[2];
	$controls = livecrafts_el_controls( $node );
	if ( ! $controls ) return livecrafts_bad_value( 'Elementor does not know this element type (the add-on that provides it may be inactive).' );
	$usable = function ( $c ) { return isset( $c['type'] ) && in_array( $c['type'], livecrafts_el_control_types(), true ); };
	if ( ! isset( $controls[ $name ] ) || ! $usable( $controls[ $name ] ) ) {
		$names = array_keys( array_filter( $controls, $usable ) );
		return livecrafts_bad_value( livecrafts_el_label( $node ) . ' has no setting "' . $name . '" that can be changed here. Its settings include: ' . implode( ', ', array_slice( $names, 0, 40 ) ) . '.' );
	}
	$settings = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : array();
	$current  = livecrafts_el_get( $settings, $m[2] );
	if ( $m[2] === 'link.url' ) {
		$clean = esc_url_raw( (string) $value );
		if ( $clean === '' && (string) $value !== '' ) return livecrafts_bad_value( 'Not a valid address.' );
	} else {
		$clean = livecrafts_el_value( $controls[ $name ], $value, isset( $settings[ $name ] ) ? $settings[ $name ] : null, $name );
		if ( is_wp_error( $clean ) ) return $clean;
	}
	$what = livecrafts_el_label( $node ) . ' › ' . wp_strip_all_tags( isset( $controls[ $name ]['label'] ) && $controls[ $name ]['label'] !== '' ? $controls[ $name ]['label'] : $name );
	if ( preg_match( '/_(tablet|mobile|widescreen|laptop|tablet_extra|mobile_extra)$/', $name, $d ) ) $what .= ' (' . str_replace( '_', ' ', $d[1] ) . ')';
	return array(
		'target'  => $target,
		'after'   => $clean,
		'payload' => array( 'widget' => isset( $node['widgetType'] ) ? $node['widgetType'] : $node['elType'], 'control' => $controls[ $name ]['type'], 'also' => livecrafts_el_toggles( $controls, $settings, $name ) ),
		'summary' => 'Elementor ' . $what . ': ' . livecrafts_quote( $current === null ? '(default)' : $current ) . ' → ' . livecrafts_quote( $clean === '' ? '(default)' : $clean ),
	);
}

function livecrafts_kind_css_prepare( $object_id, $target, $value, array $draft, array $args, $actor ) {
	$id = livecrafts_css_block_id( $target );
	if ( $id === '' ) return livecrafts_bad_value( 'A CSS block name uses a-z, 0-9 and dashes, e.g. hero-title.' );
	if ( ! user_can( $actor, 'edit_css' ) ) return new WP_Error( 'livecrafts_forbidden', 'Your account cannot change the site CSS.', array( 'status' => 403 ) );
	$css = '';
	if ( (string) $value !== '' ) {
		$css = livecrafts_css_validate( $value );
		if ( is_wp_error( $css ) ) return $css;
	}
	$blocks = livecrafts_css_blocks( $draft['css'] );
	$label  = isset( $args['label'] ) && $args['label'] !== '' ? (string) $args['label'] : ( isset( $blocks[ $id ] ) ? $blocks[ $id ]['label'] : '' );
	return array(
		'target'  => $id,
		'after'   => $css,
		'payload' => array( 'label' => mb_substr( $label, 0, 80 ) ),
		'summary' => ( $css === '' ? 'Remove CSS “' : ( isset( $blocks[ $id ] ) ? 'Change CSS “' : 'Add CSS “' ) ) . ( $label !== '' ? $label : $id ) . '”',
	);
}

/** $value = the state to restore (an object data array). "before" is the LIVE state now, so a conflict means it changed again. */
function livecrafts_kind_restore_prepare( $object_id, $target, $value, array $draft, array $args ) {
	if ( ! is_array( $value ) ) return livecrafts_bad_value( 'Nothing to restore.' );
	$type = isset( $args['object_type'] ) ? $args['object_type'] : 'post';
	$live = livecrafts_object_data( $type, $object_id );
	return array(
		'target'  => '*',
		'after'   => $value,
		'before'  => livecrafts_data_hash( $live ),
		'payload' => array( 'from_change' => isset( $args['from_change'] ) ? (int) $args['from_change'] : 0 ),
		'summary' => isset( $args['summary'] ) ? (string) $args['summary'] : 'Restore an earlier state',
	);
}

/**
 * $value = array( 'selector' => '.site-header .menu a', 'media' => ''|'desktop'|'tablet'|'mobile',
 *                 'declarations' => array( prop => value, ... ), 'replace' => false )
 * Declarations merge into the existing rule for that selector + screen size ('' removes one); replace = exactly these.
 */
function livecrafts_kind_css_rule_prepare( $object_id, $target, $value, array $draft, array $args, $actor ) {
	if ( ! user_can( $actor, 'edit_css' ) ) return new WP_Error( 'livecrafts_forbidden', 'Your account cannot change the site CSS.', array( 'status' => 403 ) );
	if ( ! is_array( $value ) || ! isset( $value['declarations'] ) || ! is_array( $value['declarations'] ) ) {
		return livecrafts_bad_value( 'A style rule needs {selector, media, declarations: {property: value}}.' );
	}
	$selector = livecrafts_css_selector_clean( isset( $value['selector'] ) ? $value['selector'] : '' );
	if ( $selector === '' ) return livecrafts_bad_value( 'That selector cannot be used (no braces, semicolons, @ or comments).' );
	$media = isset( $value['media'] ) ? (string) $value['media'] : '';
	if ( ! array_key_exists( $media, livecrafts_css_media() ) ) return livecrafts_bad_value( 'media must be empty (all screens), desktop, tablet or mobile.' );

	$id      = livecrafts_css_rule_id( $selector, $media );
	$current = livecrafts_css_rule_parse( livecrafts_css_block_get( $draft['css'], $id ) );
	$decl    = empty( $value['replace'] ) ? $current : array();
	$changed = array();
	foreach ( $value['declarations'] as $prop => $v ) {
		$prop = strtolower( trim( (string) $prop ) );
		if ( ! in_array( $prop, livecrafts_css_props(), true ) ) return livecrafts_bad_value( 'The property "' . $prop . '" cannot be set by a style rule.' );
		$v = trim( (string) $v );
		if ( $v === '' ) { unset( $decl[ $prop ] ); $changed[] = $prop . ' (reset)'; continue; }
		$clean = livecrafts_css_value_clean( $prop, $v );
		if ( is_wp_error( $clean ) ) return $clean;
		$decl[ $prop ] = $clean;
		$changed[]     = $prop . ' ' . $clean;
	}
	// An animation without a duration does nothing: give it the usual entrance timing.
	if ( ! empty( $decl['animation-name'] ) && $decl['animation-name'] !== 'none' && empty( $decl['animation-duration'] ) ) {
		$decl['animation-duration']  = '0.6s';
		$decl['animation-fill-mode'] = isset( $decl['animation-fill-mode'] ) ? $decl['animation-fill-mode'] : 'both';
	}
	ksort( $decl );
	$blocks = livecrafts_css_blocks( $draft['css'] );
	$label  = ! empty( $args['label'] ) ? (string) $args['label'] : ( isset( $blocks[ $id ] ) && $blocks[ $id ]['label'] !== '' ? $blocks[ $id ]['label'] : $selector );
	$label  = mb_substr( $label, 0, 80 );
	return array(
		'target'  => $id,
		'after'   => $decl,
		'payload' => array( 'selector' => $selector, 'media' => $media, 'label' => $label ),
		'summary' => 'Style ' . $label . ( $media !== '' ? ' (' . $media . ')' : '' ) . ': ' . ( $changed ? implode( ', ', array_slice( $changed, 0, 6 ) ) : 'reset' ),
	);
}

/** Meta keys post.meta may change, per post type ('*' = any post type that supports it). */
function livecrafts_meta_allowed( $post_id ) {
	$type = get_post_type( $post_id );
	if ( $type === 'nav_menu_item' ) return array( '_menu_item_url', '_menu_item_target' );
	$keys = array();
	if ( post_type_supports( $type, 'thumbnail' ) ) $keys[] = '_thumbnail_id';
	if ( $type === 'page' || post_type_supports( $type, 'page-attributes' ) || wp_get_theme()->get_page_templates( null, $type ) ) $keys[] = '_wp_page_template';
	return $keys;
}

function livecrafts_kind_post_meta_prepare( $post_id, $target, $value, array $draft ) {
	$allowed = livecrafts_meta_allowed( $post_id );
	if ( ! in_array( $target, $allowed, true ) ) {
		return livecrafts_bad_value( $allowed ? 'This setting can be one of: ' . implode( ', ', $allowed ) . '.' : 'This item has no settings that can be changed here.' );
	}
	$value = is_scalar( $value ) ? trim( (string) $value ) : '';
	$old   = isset( $draft['meta'][ $target ] ) ? $draft['meta'][ $target ] : '';
	switch ( $target ) {
		case '_thumbnail_id':
			if ( $value !== '' && ( ! absint( $value ) || ! wp_attachment_is_image( absint( $value ) ) ) ) return livecrafts_bad_value( 'That is not an image from the Media Library.' );
			$clean = $value === '' ? '' : (string) absint( $value );
			$show  = function ( $v ) { return $v ? livecrafts_quote( wp_get_attachment_url( (int) $v ) ) : '(none)'; };
			return array( 'target' => $target, 'after' => $clean, 'payload' => array(), 'summary' => 'Featured image: ' . $show( $old ) . ' → ' . $show( $clean ) );
		case '_wp_page_template':
			$templates = array_merge( array( 'default' => 'Default template' ), wp_get_theme()->get_page_templates( get_post( $post_id ) ) );
			if ( ! isset( $templates[ $value ] ) ) return livecrafts_bad_value( 'Unknown template. Available: ' . implode( ', ', array_keys( $templates ) ) . '.' );
			return array( 'target' => $target, 'after' => $value, 'payload' => array(), 'summary' => 'Template: ' . livecrafts_quote( $old ? $old : 'default' ) . ' → ' . livecrafts_quote( $templates[ $value ] ) );
		case '_menu_item_url':
			if ( get_post_meta( $post_id, '_menu_item_type', true ) !== 'custom' ) return livecrafts_bad_value( 'This menu link points to a page or post; its address follows that page. Change the page, or replace the link with a custom one.' );
			$url = esc_url_raw( $value );
			if ( $url === '' ) return livecrafts_bad_value( 'Not a valid address.' );
			return array( 'target' => $target, 'after' => $url, 'payload' => array(), 'summary' => 'Menu link “' . get_post_field( 'post_title', $post_id ) . '” address: ' . livecrafts_quote( $old ) . ' → ' . livecrafts_quote( $url ) );
		case '_menu_item_target':
			$clean = in_array( $value, array( '_blank', 'yes', '1', 'true' ), true ) ? '_blank' : '';
			return array( 'target' => $target, 'after' => $clean, 'payload' => array(), 'summary' => 'Menu link “' . get_post_field( 'post_title', $post_id ) . '”: ' . ( $clean ? 'opens in a new tab' : 'opens in the same tab' ) );
	}
	return livecrafts_bad_value( 'Unknown setting.' );
}

/* ------------------------------------------------------------------ ACF values and rows */

function livecrafts_kind_acf_value_prepare( $post_id, $target, $value, array $draft ) {
	if ( ! livecrafts_acf_active() ) return new WP_Error( 'livecrafts_no_acf', 'ACF is not active on this site.', array( 'status' => 400 ) );
	if ( ! preg_match( '/^[A-Za-z0-9_-]{1,190}$/', (string) $target ) ) return livecrafts_bad_value( 'An ACF value is addressed by its meta name, e.g. hero_title or sections_0_title.' );
	$field = livecrafts_acf_field_of( $draft, $target );
	if ( ! $field ) return new WP_Error( 'livecrafts_no_field', 'There is no ACF value "' . $target . '" on this page.', array( 'status' => 404 ) );
	if ( ! in_array( $field['type'], livecrafts_supported_types(), true ) ) return livecrafts_bad_value( 'ACF field type "' . $field['type'] . '" cannot be edited this way.' );
	$clean = livecrafts_validate_acf_value( $field, $value );
	if ( is_wp_error( $clean ) ) return $clean;
	$old  = isset( $draft['meta'][ $target ] ) ? $draft['meta'][ $target ] : '';
	$show = function ( $v ) use ( $field ) { return $field['type'] === 'image' ? livecrafts_quote( $v ? wp_get_attachment_url( (int) $v ) : '(none)' ) : livecrafts_quote( $v ); };
	$where = preg_match( '/_(\d+)_[^_]/', $target, $m ) ? ' (row ' . ( (int) $m[1] + 1 ) . ')' : '';
	return array(
		'target'  => $target,
		'after'   => $clean,
		'payload' => array( 'key' => $field['key'], 'label' => $field['label'], 'type' => $field['type'] ),
		'summary' => 'ACF ' . $field['label'] . $where . ': ' . $show( $old ) . ' → ' . $show( $clean ),
	);
}

/**
 * $value = array( 'op' => 'add'|'remove'|'move'|'duplicate', 'index' => n, 'to' => n|'up'|'down', 'layout' => name )
 * 'add' without index adds at the end; a flexible content row needs its layout.
 */
function livecrafts_kind_acf_rows_prepare( $post_id, $target, $value, array $draft, array $args ) {
	if ( ! livecrafts_acf_active() ) return new WP_Error( 'livecrafts_no_acf', 'ACF is not active on this site.', array( 'status' => 400 ) );
	$field = preg_match( '/^[A-Za-z0-9_-]{1,190}$/', (string) $target ) ? livecrafts_acf_field_of( $draft, $target ) : null;
	if ( ! $field || ! in_array( $field['type'], array( 'repeater', 'flexible_content' ), true ) ) {
		return new WP_Error( 'livecrafts_no_field', 'There is no repeater or flexible content field "' . $target . '" on this page.', array( 'status' => 404 ) );
	}
	if ( ! is_array( $value ) || empty( $value['op'] ) ) return livecrafts_bad_value( 'Say what to do with the rows: {op: add|remove|move|duplicate, index, to, layout}.' );
	$layouts = livecrafts_acf_row_layouts( $field, isset( $draft['meta'][ $target ] ) ? $draft['meta'][ $target ] : '' );
	$count   = count( $layouts );
	$op      = (string) $value['op'];
	$i       = isset( $value['index'] ) && $value['index'] !== '' ? (int) $value['index'] : ( $op === 'add' ? $count : -1 );
	$max     = ! empty( $field['max'] ) ? (int) $field['max'] : 0;
	$min     = ! empty( $field['min'] ) ? (int) $field['min'] : 0;
	$label   = $field['label'] !== '' ? $field['label'] : $field['name'];
	$after   = array( 'op' => $op, 'index' => $i, 'type' => $field['type'] );
	$payload = array( 'key' => $field['key'], 'label' => $label );
	$in      = function ( $n ) use ( $count ) { return $n >= 0 && $n < $count; };

	switch ( $op ) {
		case 'add':
			if ( $i < 0 || $i > $count ) return livecrafts_bad_value( 'A row can be added at positions 0 to ' . $count . '.' );
			if ( $max && $count >= $max ) return livecrafts_bad_value( '“' . $label . '” allows at most ' . $max . ' rows.' );
			$layout = '';
			if ( $field['type'] === 'flexible_content' ) {
				$layout = isset( $value['layout'] ) ? (string) $value['layout'] : '';
				if ( ! livecrafts_acf_row_fields( $field, $layout ) && ! in_array( $layout, wp_list_pluck( (array) $field['layouts'], 'name' ), true ) ) {
					return livecrafts_bad_value( 'Choose a layout: ' . implode( ', ', wp_list_pluck( (array) $field['layouts'], 'name' ) ) . '.' );
				}
			}
			// Only a revert may bring back a row's own data; everything else starts from an empty row.
			$row = ! empty( $args['restore_row'] ) && isset( $value['row'] ) && is_array( $value['row'] ) ? $value['row'] : livecrafts_acf_blank_row( livecrafts_acf_row_fields( $field, $layout ) );
			$after += array( 'layout' => $layout, 'row' => $row );
			$summary = 'Add ' . ( $layout !== '' ? '“' . $layout . '” ' : 'a ' ) . 'row to ' . $label;
			break;
		case 'remove':
			if ( ! $in( $i ) ) return livecrafts_bad_value( 'There is no row ' . ( $i + 1 ) . ' in ' . $label . '.' );
			if ( $min && $count <= $min ) return livecrafts_bad_value( '“' . $label . '” needs at least ' . $min . ' rows.' );
			$payload['removed_row']    = livecrafts_acf_row_meta( $draft['meta'], $target, $i );
			$payload['removed_layout'] = $layouts[ $i ];
			$summary = 'Remove row ' . ( $i + 1 ) . ' of ' . $label;
			break;
		case 'duplicate':
			if ( ! $in( $i ) ) return livecrafts_bad_value( 'There is no row ' . ( $i + 1 ) . ' in ' . $label . '.' );
			if ( $max && $count >= $max ) return livecrafts_bad_value( '“' . $label . '” allows at most ' . $max . ' rows.' );
			$summary = 'Duplicate row ' . ( $i + 1 ) . ' of ' . $label;
			break;
		case 'move':
			$to = isset( $value['to'] ) ? $value['to'] : null;
			$to = $to === 'up' ? $i - 1 : ( $to === 'down' ? $i + 1 : ( is_numeric( $to ) ? (int) $to : -1 ) );
			if ( ! $in( $i ) || ! $in( $to ) ) return livecrafts_bad_value( 'Rows of ' . $label . ' are numbered 0 to ' . ( $count - 1 ) . '.' );
			if ( $to === $i ) return array( 'unchanged' => true, 'target' => $target, 'after' => null, 'payload' => array(), 'summary' => '' );
			$after['to'] = $to;
			$summary = 'Move row ' . ( $i + 1 ) . ' of ' . $label . ' ' . ( $to < $i ? 'up' : 'down' );
			break;
		default:
			return livecrafts_bad_value( 'Unknown row operation "' . $op . '".' );
	}
	return array( 'target' => $target, 'after' => $after, 'payload' => $payload, 'summary' => $summary );
}

/** The opposite of a row change that went live. */
function livecrafts_acf_rows_revert( array $c ) {
	$a = $c['payload']['after'];
	$i = (int) $a['index'];
	switch ( $a['op'] ) {
		case 'add':       return array( 'acf.rows', $c['target'], array( 'op' => 'remove', 'index' => $i ), array() );
		case 'duplicate': return array( 'acf.rows', $c['target'], array( 'op' => 'remove', 'index' => $i + 1 ), array() );
		case 'move':      return array( 'acf.rows', $c['target'], array( 'op' => 'move', 'index' => (int) $a['to'], 'to' => $i ), array() );
		case 'remove':
			return array( 'acf.rows', $c['target'], array( 'op' => 'add', 'index' => $i, 'layout' => $c['payload']['removed_layout'], 'row' => $c['payload']['removed_row'] ), array( 'restore_row' => true ) );
	}
	return new WP_Error( 'livecrafts_no_revert', 'This row change cannot be reverted automatically.' );
}
