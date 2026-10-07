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
 *   )
 *
 * A draft change stores before (the value when it was made) and after. When deploying, the first "before" of each
 * target must still match the live site - otherwise someone changed it meanwhile and the person decides (conflict).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function livecrafts_kinds() {
	return array(
		'post.field' => array(
			'object'  => 'post',
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
			'apply'   => function ( &$data, $target, $after ) {
				list( $id, $path ) = explode( ':', $target, 2 );
				$tree = livecrafts_el_tree( $data );
				$r    = livecrafts_el_write_tree( $tree, $id, $path, $after );
				if ( is_wp_error( $r ) ) return $r;
				livecrafts_el_set_tree( $data, $tree );
				return true;
			},
			'equal'   => function ( $a, $b, $target ) {
				$path = substr( $target, strpos( $target, ':' ) + 1 );
				return livecrafts_el_values_equal( $path, $a, $b );
			},
		),
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
	$all = livecrafts_kinds();
	return isset( $all[ $name ] ) ? $all[ $name ] : null;
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

function livecrafts_kind_el_prepare( $post_id, $target, $value, array $draft ) {
	if ( ! livecrafts_el_active() ) return new WP_Error( 'livecrafts_no_elementor', 'Elementor is not active on this site.', array( 'status' => 400 ) );
	if ( ! preg_match( '/^([A-Za-z0-9]{3,16}):([a-z_]+(?:\.[a-z_]+)?)$/', (string) $target, $m ) ) return livecrafts_bad_value( 'An Elementor target looks like <element id>:<setting>, e.g. 3f2a1c:title.' );
	$tree = livecrafts_el_tree( $draft );
	if ( ! $tree ) return livecrafts_bad_value( 'This page is not built with Elementor.' );
	$cur = livecrafts_el_read( $tree, $m[1], $m[2] );
	if ( ! $cur['found'] ) return new WP_Error( 'livecrafts_no_element', 'That Elementor element is not on this page.', array( 'status' => 404 ) );
	if ( $cur['value'] === null && $m[2] !== 'background_image' ) return new WP_Error( 'livecrafts_no_setting', 'This element has no "' . $m[2] . '" setting.', array( 'status' => 404 ) );
	$clean = livecrafts_el_validate( $m[2], $value, is_string( $cur['value'] ) ? $cur['value'] : '' );
	if ( is_wp_error( $clean ) ) return $clean;
	$what = ucfirst( str_replace( array( '-', '_', '.' ), ' ', $cur['type'] ) ) . ' ' . str_replace( '_', ' ', $m[2] );
	return array(
		'target'  => $target,
		'after'   => $clean,
		'payload' => array( 'widget' => $cur['type'] ),
		'summary' => 'Elementor ' . $what . ': ' . livecrafts_quote( $cur['value'] ) . ' → ' . livecrafts_quote( $clean ),
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
