<?php
/**
 * Divi (and any et_pb_* shortcode builder): edit the text and links of the modules a Divi page is made of.
 *
 * Divi stores a page as nested shortcodes in post_content:
 *   [et_pb_section][et_pb_row][et_pb_column type="4_4"][et_pb_text]<h1>Hi</h1>[/et_pb_text]...[/et_pb_section]
 *
 * A module is addressed by its PATH (positions among the et_pb_* shortcodes, from the top, joined by dots) plus the
 * FIELD to change, as "<path>:<field>":
 *   field "text"         the module's own content between its tags - a Text module's rich text
 *   field "<attribute>"  one of the module's text/link attributes (title, button_text, button_url, ...)
 * e.g. "1.0.0.0:text" or "1.0.0.1:button_text".
 *
 * Writes NEVER re-serialize the page. Only the addressed field's own byte range is replaced, so every surrounding
 * shortcode stays byte-identical and the builder's nesting cannot break - the same guarantee an Elementor edit gets
 * from writing one node of its JSON tree.
 *
 * For logged-in editors (and previews opened with a preview token) every module gets module_class="lcd-<path>", so a
 * click on the page finds its module exactly. Visitors never get it.
 *
 * Changes are checked against the live content as a whole when deploying (group "content", like block changes).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function livecrafts_divi_has( $content ) {
	return is_string( $content ) && strpos( $content, '[et_pb_' ) !== false;
}

/** Is a Divi builder (the Divi/Extra theme or the Divi Builder plugin) running on this site? */
function livecrafts_divi_active() {
	return shortcode_exists( 'et_pb_section' ) || function_exists( 'et_setup_theme' ) || defined( 'ET_BUILDER_PLUGIN_VERSION' );
}

/** "1.0.0:text" -> array( "1.0.0", "text" ); anything else -> array( '', '' ). */
function livecrafts_divi_split( $target ) {
	if ( ! preg_match( '/^(\d{1,4}(?:\.\d{1,4}){0,9}):([a-z][a-z0-9_]{0,39})$/', (string) $target, $m ) ) return array( '', '' );
	return array( $m[1], $m[2] );
}

/**
 * The et_pb_* shortcodes of $content, in order, with byte offsets.
 * The attribute part allows [ and ] inside double quotes, but never outside them.
 */
function livecrafts_divi_tokens( $content ) {
	$out = array();
	$re  = '/\[(\/?)(et_pb_[a-z0-9_]+)((?:[^\[\]"]|"[^"]*")*)(\/)?\]/';
	if ( ! preg_match_all( $re, (string) $content, $all, PREG_OFFSET_CAPTURE | PREG_SET_ORDER ) ) return $out;
	foreach ( $all as $t ) {
		// The attribute part may legally contain "/" (in a URL), so it can swallow the "/" of a self-closing tag -
		// take it back, with the space in front of it, so rebuilding the tag as atts + " /]" is byte-identical.
		$atts = $t[3][0];
		$self = isset( $t[4] ) && $t[4][0] === '/';
		if ( ! $self && preg_match( '#\s*/\s*$#', $atts ) ) {
			$self = true;
			$atts = preg_replace( '#\s*/\s*$#', '', $atts );
		} elseif ( $self ) {
			$atts = preg_replace( '#\s+$#', '', $atts );
		}
		$out[] = array(
			'close' => $t[1][0] === '/',
			'tag'   => $t[2][0],
			'atts'  => $atts,
			'self'  => $self,
			'start' => $t[0][1],
			'len'   => strlen( $t[0][0] ),
		);
	}
	return $out;
}

/** The module tree of a page's content. Each node: tag, atts, self, children + byte ranges of the tag and its content. */
function livecrafts_divi_tree( $content ) {
	$tokens = livecrafts_divi_tokens( $content );
	$i      = 0;
	return livecrafts_divi_build( $tokens, $i, '' );
}

/** Consume tokens into a list of sibling nodes, stopping at the closing tag of $parent (which the caller consumes). */
function livecrafts_divi_build( array $tokens, &$i, $parent ) {
	$out   = array();
	$count = count( $tokens );
	while ( $i < $count ) {
		$t = $tokens[ $i ];
		if ( $t['close'] ) {
			if ( $parent !== '' && $t['tag'] === $parent ) return $out;
			$i++; // a closing tag with nothing open: ignore it rather than guess
			continue;
		}
		$i++;
		$node = array(
			'tag'         => $t['tag'],
			'atts'        => $t['atts'],
			'self'        => $t['self'],
			'start'       => $t['start'],
			'open_len'    => $t['len'],
			'inner_start' => $t['start'] + $t['len'],
			'inner_len'   => 0,
			'len'         => $t['len'],
			'children'    => array(),
		);
		if ( ! $t['self'] ) {
			$node['children'] = livecrafts_divi_build( $tokens, $i, $t['tag'] );
			if ( $i < $count && $tokens[ $i ]['close'] && $tokens[ $i ]['tag'] === $t['tag'] ) {
				$close               = $tokens[ $i ];
				$node['inner_len']   = $close['start'] - $node['inner_start'];
				$node['len']         = ( $close['start'] + $close['len'] ) - $node['start'];
				$i++;
			}
		}
		$out[] = $node;
	}
	return $out;
}

/** The node at a path ("1.0.2"), or null. */
function livecrafts_divi_get( array $nodes, $path ) {
	if ( (string) $path === '' ) return null;
	$list = $nodes;
	$node = null;
	foreach ( explode( '.', (string) $path ) as $n ) {
		$n = (int) $n;
		if ( ! isset( $list[ $n ] ) ) return null;
		$node = $list[ $n ];
		$list = $node['children'];
	}
	return $node;
}

/** "Text", "Blurb", "Fullwidth header" ... */
function livecrafts_divi_label( array $node ) {
	$name = preg_replace( '/^et_pb_/', '', $node['tag'] );
	return ucfirst( trim( str_replace( '_', ' ', $name ) ) );
}

/** Is this a layout wrapper rather than something with its own content? */
function livecrafts_divi_is_layout( array $node ) {
	return in_array( $node['tag'], array( 'et_pb_section', 'et_pb_row', 'et_pb_row_inner', 'et_pb_column', 'et_pb_column_inner' ), true );
}

/** Text fields Livecrafts may change, per module. "text" = the module's own content between its tags. */
function livecrafts_divi_fields() {
	return array(
		'et_pb_text'             => array( 'text' ),
		'et_pb_code'             => array( 'text' ),
		'et_pb_fullwidth_code'   => array( 'text' ),
		'et_pb_blurb'            => array( 'text', 'title' ),
		'et_pb_testimonial'      => array( 'text', 'author', 'job_title', 'company_name' ),
		'et_pb_cta'              => array( 'text', 'title', 'button_text' ),
		'et_pb_button'           => array( 'button_text' ),
		'et_pb_heading'          => array( 'title' ),
		'et_pb_number_counter'   => array( 'title', 'number' ),
		'et_pb_counter'          => array( 'title' ),
		'et_pb_toggle'           => array( 'text', 'title' ),
		'et_pb_accordion_item'   => array( 'text', 'title' ),
		'et_pb_tab'              => array( 'text', 'title' ),
		'et_pb_slide'            => array( 'text', 'heading', 'button_text' ),
		'et_pb_fullwidth_header' => array( 'text', 'title', 'subhead', 'button_one_text', 'button_two_text' ),
		'et_pb_pricing_table'    => array( 'title', 'subtitle', 'sum', 'button_text' ),
		'et_pb_team_member'      => array( 'text', 'name', 'position' ),
		'et_pb_contact_form'     => array( 'title', 'submit_button_text' ),
		'et_pb_contact_field'    => array( 'field_title' ),
		'et_pb_image'            => array( 'alt', 'title_text' ),
		'et_pb_video'            => array( 'alt' ),
	);
}

/** Link fields Livecrafts may change, per module. */
function livecrafts_divi_link_fields() {
	return array(
		'et_pb_button'           => array( 'button_url' ),
		'et_pb_cta'              => array( 'button_url' ),
		'et_pb_blurb'            => array( 'url' ),
		'et_pb_image'            => array( 'url' ),
		'et_pb_slide'            => array( 'button_link' ),
		'et_pb_pricing_table'    => array( 'button_url' ),
		'et_pb_team_member'      => array( 'facebook_url', 'twitter_url', 'linkedin_url' ),
		'et_pb_fullwidth_header' => array( 'button_one_url', 'button_two_url' ),
	);
}

/** The fields of one module for an operation ('text' or 'link'). */
function livecrafts_divi_fields_for( $op, array $node ) {
	$map = $op === 'link' ? livecrafts_divi_link_fields() : livecrafts_divi_fields();
	return isset( $map[ $node['tag'] ] ) ? $map[ $node['tag'] ] : array();
}

/** The current value of one field of one module. */
function livecrafts_divi_field_value( $content, array $node, $field ) {
	if ( $field === 'text' ) return substr( (string) $content, $node['inner_start'], $node['inner_len'] );
	$atts = shortcode_parse_atts( $node['atts'] );
	return is_array( $atts ) && isset( $atts[ $field ] ) ? (string) $atts[ $field ] : '';
}

/**
 * Clean a value for a shortcode ATTRIBUTE. WordPress's shortcode parser stops at the first "]", and Divi writes a
 * literal quote as %22, so neither brackets nor quotes may survive here.
 */
function livecrafts_divi_att_value( $value ) {
	$v = wp_strip_all_tags( (string) $value );
	$v = str_replace( array( '"', '[', ']' ), array( '%22', '', '' ), $v );
	return trim( preg_replace( '/\s+/u', ' ', $v ) );
}

/** Set one attribute in a raw attribute string, keeping everything else exactly as it was. */
function livecrafts_divi_set_att( $atts, $name, $value ) {
	$atts = (string) $atts;
	$re   = '/(^|\s)' . preg_quote( $name, '/' ) . '="[^"]*"/';
	$pair = $name . '="' . $value . '"';
	if ( preg_match( $re, $atts, $m, PREG_OFFSET_CAPTURE ) ) {
		$lead = $m[1][0] === '' ? '' : $m[1][0];
		return substr_replace( $atts, $lead . $pair, $m[0][1], strlen( $m[0][0] ) );
	}
	return rtrim( $atts ) . ' ' . $pair;
}

/** Add a CSS class to a module's module_class attribute. */
function livecrafts_divi_add_class( $atts, $class ) {
	$current = preg_match( '/(^|\s)module_class="([^"]*)"/', (string) $atts, $m ) ? $m[2] : '';
	$list    = preg_split( '/\s+/', trim( $current ), -1, PREG_SPLIT_NO_EMPTY );
	if ( in_array( $class, $list, true ) ) return $atts;
	$list[] = $class;
	return livecrafts_divi_set_att( $atts, 'module_class', implode( ' ', $list ) );
}

/** The opening tag of a node, rebuilt from an attribute string (a self-closing module stays self-closing). */
function livecrafts_divi_open_tag( array $node, $atts ) {
	return '[' . $node['tag'] . $atts . ( $node['self'] ? ' /' : '' ) . ']';
}

/**
 * Replace one field of one module in $content. Only that field's own byte range changes.
 * Returns the new content, or WP_Error when the module is no longer there.
 */
function livecrafts_divi_write( $content, $path, $field, $value ) {
	$content = (string) $content;
	$node    = livecrafts_divi_get( livecrafts_divi_tree( $content ), $path );
	if ( ! $node ) return new WP_Error( 'livecrafts_no_module', 'There is no Divi module at ' . $path . ' (any more).', array( 'status' => 404 ) );
	if ( $field === 'text' ) {
		if ( $node['self'] ) return livecrafts_bad_value( 'This module has no content of its own to change.' );
		return substr_replace( $content, (string) $value, $node['inner_start'], $node['inner_len'] );
	}
	$atts = livecrafts_divi_set_att( $node['atts'], $field, livecrafts_divi_att_value( $value ) );
	return substr_replace( $content, livecrafts_divi_open_tag( $node, $atts ), $node['start'], $node['open_len'] );
}

/** Walk the tree, calling $fn( $node, $path ) for every module in document order. */
function livecrafts_divi_walk( array $nodes, $prefix, $fn ) {
	foreach ( $nodes as $i => $node ) {
		$path = $prefix === '' ? (string) $i : $prefix . '.' . $i;
		call_user_func( $fn, $node, $path );
		if ( $node['children'] ) livecrafts_divi_walk( $node['children'], $path, $fn );
	}
}

/**
 * Every editable Divi field of a page, with stable target ids - the Divi half of GET /map, so the assistant can find
 * a module's text without guessing at the shortcode syntax.
 */
function livecrafts_divi_scan( $post_id, array $data ) {
	$content = isset( $data['fields']['content'] ) ? (string) $data['fields']['content'] : '';
	$out     = array();
	if ( ! livecrafts_divi_has( $content ) ) return $out;
	livecrafts_divi_walk( livecrafts_divi_tree( $content ), '', function ( $node, $path ) use ( $content, $post_id, &$out ) {
		foreach ( array( 'text' => 'divi.text', 'link' => 'divi.link' ) as $op => $kind ) {
			foreach ( livecrafts_divi_fields_for( $op, $node ) as $field ) {
				$value = livecrafts_divi_field_value( $content, $node, $field );
				if ( trim( (string) $value ) === '' ) continue;
				$out[] = array(
					'kind'   => 'divi',
					'tid'    => 'divi:' . (int) $post_id . ':' . $path . ':' . $field,
					'change' => $kind,
					'target' => $path . ':' . $field,
					'module' => $node['tag'],
					'label'  => livecrafts_divi_label( $node ) . ' › ' . ( $field === 'text' ? 'text' : str_replace( '_', ' ', $field ) ),
					'path'   => $path,
					'field'  => $field,
					'post'   => (int) $post_id,
					'value'  => mb_substr( (string) $value, 0, 2000 ),
				);
			}
		}
	} );
	return $out;
}

/** A short outline of the page's Divi structure, for the assistant. */
function livecrafts_divi_outline( $content ) {
	$out = array();
	if ( ! livecrafts_divi_has( $content ) ) return $out;
	livecrafts_divi_walk( livecrafts_divi_tree( $content ), '', function ( $node, $path ) use ( &$out ) {
		if ( count( $out ) >= 400 ) return;
		$out[] = array( 'path' => $path, 'module' => $node['tag'], 'label' => livecrafts_divi_label( $node ), 'layout' => livecrafts_divi_is_layout( $node ) );
	} );
	return $out;
}

/* ------------------------------------------------------------------ editor markers (module_class="lcd-<path>") */

/** Give every module a class carrying its path, so a click on the rendered page finds it. */
function livecrafts_divi_mark( $content ) {
	$edits = array();
	livecrafts_divi_walk( livecrafts_divi_tree( $content ), '', function ( $node, $path ) use ( &$edits ) {
		$edits[] = array( 'node' => $node, 'path' => $path );
	} );
	usort( $edits, function ( $a, $b ) { return $b['node']['start'] - $a['node']['start']; } ); // from the end, so offsets stay valid
	foreach ( $edits as $e ) {
		$node = $e['node'];
		$atts = livecrafts_divi_add_class( $node['atts'], 'lcd-' . str_replace( '.', '-', $e['path'] ) );
		$content = substr_replace( $content, livecrafts_divi_open_tag( $node, $atts ), $node['start'], $node['open_len'] );
	}
	return $content;
}

// Before Divi turns the shortcodes into HTML (WordPress runs do_shortcode on the_content at 11).
add_filter( 'the_content', function ( $content ) {
	if ( ! ( livecrafts_user_can_edit() || livecrafts_preview_token_request() ) || ! livecrafts_divi_has( $content ) ) return $content;
	if ( ! in_the_loop() || ! is_main_query() ) return $content;
	$post_id = (int) get_the_ID();
	if ( ! $post_id || $post_id !== (int) get_queried_object_id() ) return $content;
	return livecrafts_divi_mark( $content );
}, 8 );

/* ------------------------------------------------------------------ Divi's own page cache */

/**
 * Divi writes a page's generated CSS to wp-content/et-cache/<post id>/. That file is keyed on the post, not on its
 * content, so it keeps serving the old design after the content changed - drop it and let Divi build it again.
 */
function livecrafts_divi_purge_cache( $post_id ) {
	$post_id = (int) $post_id;
	$dir     = WP_CONTENT_DIR . '/et-cache/' . $post_id;
	if ( $post_id <= 0 || ! is_dir( $dir ) ) return;
	foreach ( (array) glob( $dir . '/*' ) as $file ) {
		if ( is_file( $file ) ) @unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
	@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
}

add_action( 'save_post', function ( $post_id, $post ) {
	if ( ! $post || wp_is_post_revision( $post_id ) ) return;
	if ( livecrafts_divi_has( (string) $post->post_content ) ) livecrafts_divi_purge_cache( $post_id );
}, 20, 2 );

/* ------------------------------------------------------------------ change kinds divi.* (see kinds.php) */

/** The kind definition for one Divi field operation ('text' or 'link'). */
function livecrafts_divi_kind( $op ) {
	return array(
		'object'  => 'post',
		'group'   => 'content', // conflicts: the page content as a whole, like block changes
		'prepare' => function ( $post_id, $target, $value, $draft ) use ( $op ) { return livecrafts_divi_prepare( $op, $post_id, $target, $value, $draft ); },
		'read'    => function ( $data, $target ) {
			list( $path, $field ) = livecrafts_divi_split( $target );
			if ( $path === '' ) return '';
			$content = isset( $data['fields']['content'] ) ? (string) $data['fields']['content'] : '';
			$node    = livecrafts_divi_get( livecrafts_divi_tree( $content ), $path );
			return $node ? livecrafts_divi_field_value( $content, $node, $field ) : '';
		},
		'base'    => function ( $data ) { return isset( $data['fields']['content'] ) ? $data['fields']['content'] : ''; },
		'apply'   => function ( &$data, $target, $after ) {
			list( $path, $field ) = livecrafts_divi_split( $target );
			if ( $path === '' ) return livecrafts_bad_value( 'Invalid Divi target.' );
			$new = livecrafts_divi_write( (string) $data['fields']['content'], $path, $field, (string) $after );
			if ( is_wp_error( $new ) ) return $new;
			$data['fields']['content'] = $new;
			return true;
		},
		'landed'  => function ( $copy, $expected ) { return $copy['fields']['content'] === $expected['fields']['content']; },
	);
}

/** Check one Divi field change against the draft content. */
function livecrafts_divi_prepare( $op, $post_id, $target, $value, array $draft ) {
	$content = isset( $draft['fields']['content'] ) ? (string) $draft['fields']['content'] : '';
	if ( ! livecrafts_divi_has( $content ) ) return livecrafts_bad_value( 'This page is not built with Divi.' );

	list( $path, $field ) = livecrafts_divi_split( $target );
	if ( $path === '' ) {
		return livecrafts_bad_value( 'A Divi field is addressed as "<path>:<field>", e.g. 1.0.0.0:text or 1.0.0.1:button_text (GET /map lists them).' );
	}
	$node = livecrafts_divi_get( livecrafts_divi_tree( $content ), $path );
	if ( ! $node ) return new WP_Error( 'livecrafts_no_module', 'There is no Divi module at ' . $path . ' (any more).', array( 'status' => 404 ) );

	$label  = livecrafts_divi_label( $node );
	$fields = livecrafts_divi_fields_for( $op, $node );
	if ( ! in_array( $field, $fields, true ) ) {
		$what = $op === 'link' ? 'link' : 'text';
		return livecrafts_bad_value( $label . ' has no ' . $what . ' “' . $field . '” that can be changed here.'
			. ( $fields ? ' It has: ' . implode( ', ', $fields ) . '.' : ' Divi modules of this type have no ' . $what . ' Livecrafts can edit yet.' ) );
	}

	$old = livecrafts_divi_field_value( $content, $node, $field );
	if ( $op === 'link' ) {
		$clean = esc_url_raw( (string) $value );
		if ( $clean === '' ) return livecrafts_bad_value( 'Not a valid address.' );
		$clean = livecrafts_divi_att_value( $clean );
	} elseif ( $field === 'text' ) {
		if ( ! is_string( $value ) ) return livecrafts_bad_value( 'The value must be text.' );
		$clean = livecrafts_sanitize_post_field( 'post_content', $value, $post_id );
		if ( trim( $clean ) === '' ) return livecrafts_bad_value( 'The new text is empty. To take the module away, use the Divi editor.' );
		if ( preg_match( '/\[\/?et_pb_/', $clean ) ) return livecrafts_bad_value( 'The content of a module cannot contain Divi shortcodes.' );
	} else {
		$clean = livecrafts_divi_att_value( $value );
		if ( $clean === '' ) return livecrafts_bad_value( 'The new text is empty.' );
	}

	if ( (string) $clean === (string) $old ) {
		return array( 'unchanged' => true, 'target' => $target, 'after' => null, 'payload' => array( 'op' => $op, 'module' => $node['tag'], 'field' => $field ), 'summary' => '' );
	}
	$what = $field === 'text' ? 'text' : str_replace( '_', ' ', $field );
	return array(
		'target'  => $target,
		'after'   => $clean,
		'payload' => array( 'op' => $op, 'module' => $node['tag'], 'field' => $field ),
		'summary' => 'Divi ' . $label . ' ' . $what . ': ' . livecrafts_quote( $old ) . ' → ' . livecrafts_quote( $clean ),
	);
}
