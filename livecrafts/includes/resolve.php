<?php
/**
 * POST /livecrafts/v1/resolve - "what is this element, and what can be done to it?" for a click on the page.
 *
 * No guessing and no AI: the element is matched to its real source by ids the page carries (Elementor data-id,
 * data-lc-block for block content, menu-item-<id> classes), or - for ACF values printed by the theme - by an exact,
 * unique match of its text / image / link against the page's ACF values. Then only the actions that source really
 * supports are returned, each with the exact change to make (POST /changes), so the widget can apply it directly.
 *
 * Request: { page, device: desktop|tablet|mobile, elementor: {doc, id}, block: "<post>:<path>", menuItem,
 *            tag, text, imageSrc, bgImage, href, selector }
 * Answer:  { source: {kind, label, post, ...}, actions: [ {id, group, label, input, value, options?, units?, change,
 *            requires?, note?} ], parent?, css_scopes?, notes: [] }
 *   input  = text | html | url | image | color | size | select | spacing | switch | confirm | ask
 *   change = the POST /changes body without its value; the widget sets value to what the person entered, or fills the
 *            "{value}" placeholder inside it. requires = a change to make first (e.g. give a block a class).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'rest_api_init', function () {
	register_rest_route( 'livecrafts/v1', '/resolve', array( 'methods' => 'POST', 'callback' => 'livecrafts_rest_resolve', 'permission_callback' => 'livecrafts_rest_permission' ) );
} );

function livecrafts_action( $id, $group, $label, $input, $value, array $change, array $extra = array() ) {
	return array_merge( array( 'id' => $id, 'group' => $group, 'label' => $label, 'input' => $input, 'value' => $value, 'change' => $change ), $extra );
}

function livecrafts_norm_text( $s ) {
	return strtolower( trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( (string) $s ), ENT_QUOTES, 'UTF-8' ) ) ) );
}

function livecrafts_file_base( $url ) {
	$name = strtolower( rawurldecode( basename( strtok( (string) $url, '?#' ) ) ) );
	return preg_replace( '/-\d+x\d+(?=\.[a-z0-9]+$)|-scaled(?=\.[a-z0-9]+$)/', '', $name );
}

function livecrafts_rest_resolve( WP_REST_Request $req ) {
	$device = in_array( $req->get_param( 'device' ), array( 'tablet', 'mobile' ), true ) ? $req->get_param( 'device' ) : 'desktop';
	$page   = (int) $req->get_param( 'page' );
	$el     = $req->get_param( 'elementor' );
	$block  = (string) $req->get_param( 'block' );
	$menu   = (int) $req->get_param( 'menuItem' );

	$r = null;
	if ( $menu && get_post_type( $menu ) === 'nav_menu_item' && current_user_can( 'edit_theme_options' ) ) {
		$r = livecrafts_resolve_menu( $menu );
	}
	if ( ! $r && is_array( $el ) && ! empty( $el['doc'] ) && ! empty( $el['id'] ) && current_user_can( 'edit_post', (int) $el['doc'] ) ) {
		$r = livecrafts_resolve_elementor( (int) $el['doc'], (string) $el['id'], $device );
	}
	if ( ! $r && preg_match( '/^(\d+):(\d{1,4}(?:\.\d{1,4}){0,9})$/', $block, $m ) && current_user_can( 'edit_post', (int) $m[1] ) ) {
		// A block that shows an ACF field (block bindings, source "acf/field"): the source is the field, not the block.
		$data  = livecrafts_draft_data( 'post', (int) $m[1] );
		$b     = is_wp_error( $data ) ? null : livecrafts_block_get( parse_blocks( $data['fields']['content'] ), $m[2] );
		$bound = $b && isset( $b['attrs']['metadata']['bindings'] ) && is_array( $b['attrs']['metadata']['bindings'] ) ? $b['attrs']['metadata']['bindings'] : array();
		foreach ( $bound as $binding ) {
			if ( isset( $binding['source'], $binding['args']['key'] ) && $binding['source'] === 'acf/field' ) {
				$r = livecrafts_resolve_acf_name( (int) $m[1], (string) $binding['args']['key'] );
				if ( $r ) break;
			}
		}
		if ( ! $r ) $r = livecrafts_resolve_block( (int) $m[1], $m[2], $device );
	}
	if ( ! $r && $page && current_user_can( 'edit_post', $page ) ) {
		$r = livecrafts_resolve_acf( $page, $req );
	}
	if ( ! $r ) $r = livecrafts_resolve_theme( $page, (string) $req->get_param( 'selector' ), $device, (string) $req->get_param( 'tag' ) );
	$r['ok']     = true;
	$r['device'] = $device;
	return $r;
}

/** Media query of a css.rule for edits made while looking at a screen size: desktop edits apply to every screen. */
function livecrafts_device_media( $device ) {
	return $device === 'desktop' ? '' : $device;
}

/** css.rule actions for a selector (style, animation, hide). */
function livecrafts_css_actions( $selector, $device, $label ) {
	$media = livecrafts_device_media( $device );
	$on    = $device === 'desktop' ? ' (all screens)' : ' (' . $device . ')';
	$rule  = function ( $prop ) use ( $selector, $media, $label ) {
		return array( 'kind' => 'css.rule', 'label' => $label, 'value' => array( 'selector' => $selector, 'media' => $media, 'declarations' => array( $prop => '{value}' ) ) );
	};
	$a = array(
		livecrafts_action( 'css:color', 'style', 'Text colour' . $on, 'color', null, $rule( 'color' ) ),
		livecrafts_action( 'css:background', 'style', 'Background' . $on, 'color', null, $rule( 'background-color' ) ),
		livecrafts_action( 'css:font-size', 'style', 'Font size' . $on, 'size', null, $rule( 'font-size' ), array( 'units' => array( 'px', 'rem', 'em', '%' ) ) ),
		livecrafts_action( 'css:font-weight', 'style', 'Font weight' . $on, 'select', null, $rule( 'font-weight' ), array( 'options' => array( '300' => 'Light', '400' => 'Regular', '500' => 'Medium', '600' => 'Semi-bold', '700' => 'Bold', '800' => 'Extra bold' ) ) ),
		livecrafts_action( 'css:text-align', 'style', 'Alignment' . $on, 'select', null, $rule( 'text-align' ), array( 'options' => array( 'left' => 'Left', 'center' => 'Center', 'right' => 'Right' ) ) ),
		livecrafts_action( 'css:padding', 'style', 'Inner spacing' . $on, 'spacing', null, $rule( 'padding' ) ),
		livecrafts_action( 'css:margin', 'style', 'Outer spacing' . $on, 'spacing', null, $rule( 'margin' ) ),
		livecrafts_action( 'css:border-radius', 'style', 'Corner radius' . $on, 'size', null, $rule( 'border-radius' ), array( 'units' => array( 'px', '%', 'rem' ) ) ),
		livecrafts_action( 'css:animation', 'motion', 'Entrance animation', 'select', null, $rule( 'animation-name' ), array( 'options' => array(
			'none' => 'None', 'lc-fade-in' => 'Fade in', 'lc-fade-up' => 'Fade up', 'lc-zoom-in' => 'Zoom in', 'lc-slide-in-left' => 'Slide in from left', 'lc-slide-in-right' => 'Slide in from right' ) ) ),
	);
	$hide = array( 'kind' => 'css.rule', 'label' => $label, 'value' => array( 'selector' => $selector, 'media' => $device, 'declarations' => array( 'display' => 'none' ) ) );
	$a[]  = livecrafts_action( 'css:hide', 'visibility', 'Hide on ' . $device, 'confirm', null, $hide );
	return $a;
}

/* ------------------------------------------------------------------ Elementor */

function livecrafts_resolve_elementor( $doc, $id, $device ) {
	if ( ! livecrafts_el_active() ) return null;
	$data = livecrafts_draft_data( 'post', $doc );
	if ( is_wp_error( $data ) ) return null;
	$tree = livecrafts_el_tree( $data );
	$node = livecrafts_el_node( $tree, $id );
	if ( ! $node ) return null;
	$controls = livecrafts_el_controls( $node );
	$settings = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : array();
	$label    = livecrafts_el_label( $node );
	$change   = function ( $name ) use ( $doc, $id ) { return array( 'kind' => 'el.setting', 'post' => $doc, 'target' => $id . ':' . $name ); };
	$ctl_name = function ( $c, $name ) { return wp_strip_all_tags( isset( $c['label'] ) && $c['label'] !== '' ? $c['label'] : $name ); };
	$actions  = array();

	// content: the texts, links and images this element really has
	foreach ( $controls as $name => $c ) {
		if ( count( $actions ) >= 6 ) break;
		if ( $name[0] === '_' || ( isset( $c['tab'] ) && $c['tab'] !== 'content' ) ) continue;
		$type = isset( $c['type'] ) ? $c['type'] : '';
		$val  = isset( $settings[ $name ] ) ? $settings[ $name ] : null;
		if ( in_array( $type, array( 'text', 'textarea', 'wysiwyg' ), true ) && is_string( $val ) && $val !== '' ) {
			$actions[] = livecrafts_action( 'text:' . $name, 'content', 'Edit ' . strtolower( $ctl_name( $c, $name ) ), $type === 'wysiwyg' ? 'html' : 'text', $val, $change( $name ) );
		} elseif ( $type === 'url' && is_array( $val ) && ! empty( $val['url'] ) ) {
			$actions[] = livecrafts_action( 'link:' . $name, 'content', 'Change link', 'url', $val['url'], $change( $name ) );
		} elseif ( $type === 'media' && is_array( $val ) && ! empty( $val['url'] ) ) {
			$actions[] = livecrafts_action( 'image:' . $name, 'content', 'Replace image', 'image', array( 'id' => isset( $val['id'] ) ? (int) $val['id'] : 0, 'url' => $val['url'] ), $change( $name ) );
		}
	}

	// style: Elementor's own controls; the variant for the screen size being looked at when the control is responsive
	$pick = function ( array $names ) use ( $controls, $device ) {
		foreach ( $names as $n ) {
			if ( $device !== 'desktop' && isset( $controls[ $n . '_' . $device ] ) ) return $n . '_' . $device;
			if ( isset( $controls[ $n ] ) && in_array( $controls[ $n ]['type'], livecrafts_el_control_types(), true ) ) return $n;
		}
		return null;
	};
	$quick = array(
		array( 'color', 'Text colour', 'color', array( 'title_color', 'text_color', 'button_text_color', 'description_color', 'primary_color', 'icon_color' ) ),
		array( 'background', 'Background', 'color', array( 'background_color', 'button_background_color' ) ),
		array( 'font-size', 'Font size', 'size', array( 'typography_font_size', 'title_typography_font_size', 'button_typography_font_size' ) ),
		array( 'font-weight', 'Font weight', 'select', array( 'typography_font_weight', 'title_typography_font_weight' ) ),
		array( 'align', 'Alignment', 'select', array( 'align', 'text_align', 'alignment', 'content_align' ) ),
		array( 'padding', 'Inner spacing', 'spacing', array( 'padding', '_padding' ) ),
		array( 'margin', 'Outer spacing', 'spacing', array( 'margin', '_margin' ) ),
		array( 'radius', 'Corner radius', 'spacing', array( 'border_radius', '_border_radius', 'image_border_radius' ) ),
	);
	foreach ( $quick as $q ) {
		$name = $pick( $q[3] );
		if ( ! $name ) continue;
		$c     = $controls[ $name ];
		$input = $c['type'] === 'select' || $c['type'] === 'choose' ? 'select' : ( $c['type'] === 'slider' ? 'size' : ( $c['type'] === 'dimensions' ? 'spacing' : $q[2] ) );
		$extra = array();
		if ( $input === 'select' && ! empty( $c['options'] ) ) {
			$extra['options'] = array();
			foreach ( (array) $c['options'] as $k => $o ) $extra['options'][ $k ] = is_array( $o ) ? ( isset( $o['title'] ) ? $o['title'] : $k ) : $o;
		}
		if ( ! empty( $c['size_units'] ) ) $extra['units'] = array_values( (array) $c['size_units'] );
		$suffix = preg_match( '/_(tablet|mobile)$/', $name, $d ) ? ' (' . $d[1] . ')' : ( empty( $c['responsive'] ) ? '' : ' (desktop)' );
		$actions[] = livecrafts_action( 'el:' . $q[0], 'style', $q[1] . $suffix, $input, livecrafts_el_get( $settings, $name ), $change( $name ), $extra );
	}

	// visibility + motion
	if ( isset( $controls[ 'hide_' . $device ] ) ) {
		$actions[] = livecrafts_action( 'el:hide', 'visibility', 'Hide on ' . $device, 'switch', ! empty( $settings[ 'hide_' . $device ] ), $change( 'hide_' . $device ) );
	}
	$anim = $pick( array( '_animation', 'animation' ) );
	if ( $anim ) {
		$actions[] = livecrafts_action( 'el:animation', 'motion', 'Entrance animation', 'select', isset( $settings[ $anim ] ) ? $settings[ $anim ] : '', $change( $anim ), array( 'options' => array(
			'' => 'None', 'fadeIn' => 'Fade in', 'fadeInUp' => 'Fade in up', 'fadeInDown' => 'Fade in down', 'fadeInLeft' => 'Fade in left', 'fadeInRight' => 'Fade in right',
			'zoomIn' => 'Zoom in', 'slideInUp' => 'Slide in up', 'bounceIn' => 'Bounce in' ) ) );
	}

	// structure
	list( $parent, $index ) = livecrafts_el_locate( $tree, $id );
	$siblings = livecrafts_el_children( $tree, $parent );
	$st       = function ( $kind, $value = null ) use ( $doc, $id ) { return array( 'kind' => $kind, 'post' => $doc, 'target' => $id ) + ( $value === null ? array() : array( 'value' => $value ) ); };
	$actions[] = livecrafts_action( 'el:duplicate', 'layout', 'Duplicate', 'confirm', null, $st( 'el.duplicate' ) );
	if ( $index > 0 ) $actions[] = livecrafts_action( 'el:up', 'layout', 'Move up', 'confirm', null, $st( 'el.move', 'up' ) );
	if ( $index < count( $siblings ) - 1 ) $actions[] = livecrafts_action( 'el:down', 'layout', 'Move down', 'confirm', null, $st( 'el.move', 'down' ) );
	$actions[] = livecrafts_action( 'el:remove', 'layout', 'Remove', 'confirm', null, $st( 'el.remove' ) );

	$out = array(
		'source'  => array( 'kind' => 'elementor', 'label' => $label, 'post' => $doc, 'id' => $id, 'type' => isset( $node['widgetType'] ) ? $node['widgetType'] : $node['elType'],
			'where' => livecrafts_object_label( 'post', $doc ), 'edit_link' => admin_url( 'post.php?post=' . $doc . '&action=elementor' ) ),
		'actions' => $actions,
		'notes'   => array(),
	);
	if ( $parent !== '' ) {
		$p             = livecrafts_el_node( $tree, $parent );
		$out['parent'] = array( 'label' => livecrafts_el_label( $p ), 'elementor' => array( 'doc' => $doc, 'id' => $parent ) );
	}
	return $out;
}

/* ------------------------------------------------------------------ blocks */

function livecrafts_resolve_block( $post, $path, $device ) {
	$data = livecrafts_draft_data( 'post', $post );
	if ( is_wp_error( $data ) ) return null;
	$blocks = parse_blocks( $data['fields']['content'] );
	$b      = livecrafts_block_get( $blocks, $path );
	if ( ! $b ) return null;
	$label   = livecrafts_block_label( $b );
	$change  = function ( $kind, $value = null ) use ( $post, $path ) { return array( 'kind' => $kind, 'post' => $post, 'target' => $path ) + ( $value === null ? array() : array( 'value' => $value ) ); };
	$actions = array();
	$notes   = array();

	$parts = livecrafts_block_text_parts( $b );
	if ( $parts ) $actions[] = livecrafts_action( 'block:text', 'content', 'Edit text', 'html', $parts[1], $change( 'block.text' ) );
	$link = livecrafts_block_link( $b );
	if ( $link !== null ) $actions[] = livecrafts_action( 'block:link', 'content', 'Change link', 'url', $link, $change( 'block.link' ) );
	if ( in_array( $b['blockName'], array( 'core/image', 'core/cover', 'core/media-text' ), true ) ) {
		$img = isset( $b['attrs']['id'] ) ? (int) $b['attrs']['id'] : ( isset( $b['attrs']['mediaId'] ) ? (int) $b['attrs']['mediaId'] : 0 );
		$actions[] = livecrafts_action( 'block:image', 'content', 'Replace image', 'image', array( 'id' => $img, 'url' => $img ? wp_get_attachment_url( $img ) : '' ), $change( 'block.image' ) );
	}

	// Style rules need a stable hook: the block's own Livecrafts class (added once, like a class set in the editor).
	$class = '';
	foreach ( preg_split( '/\s+/', isset( $b['attrs']['className'] ) ? (string) $b['attrs']['className'] : '', -1, PREG_SPLIT_NO_EMPTY ) as $c ) {
		if ( preg_match( '/^lc-[a-z0-9]{6,12}$/', $c ) ) { $class = $c; break; }
	}
	$requires = null;
	if ( $class === '' ) {
		$class    = 'lc-' . substr( md5( $post . ':' . $path . ':' . wp_rand() ), 0, 8 );
		$requires = $change( 'block.class', $class );
	}
	foreach ( livecrafts_css_actions( '.' . $class, $device, $label ) as $a ) {
		if ( $requires ) $a['requires'] = $requires;
		$actions[] = $a;
	}
	if ( ! empty( $b['attrs']['style'] ) || ! empty( $b['attrs']['textColor'] ) || ! empty( $b['attrs']['backgroundColor'] ) ) {
		$notes[] = 'This block has its own colours/sizes from the block editor. Those are set on the element itself and win over style rules for the same property: change them in the block editor, or ask the assistant.';
	}

	list( $parent, $index ) = livecrafts_path_split( $path );
	$count     = livecrafts_block_count( $blocks, $parent );
	$actions[] = livecrafts_action( 'block:duplicate', 'layout', 'Duplicate', 'confirm', null, $change( 'block.duplicate' ) );
	if ( $index > 0 ) $actions[] = livecrafts_action( 'block:up', 'layout', 'Move up', 'confirm', null, $change( 'block.move', 'up' ) );
	if ( $index < $count - 1 ) $actions[] = livecrafts_action( 'block:down', 'layout', 'Move down', 'confirm', null, $change( 'block.move', 'down' ) );
	$actions[] = livecrafts_action( 'block:remove', 'layout', 'Remove', 'confirm', null, $change( 'block.remove', 1 ) );

	$out = array(
		'source'  => array( 'kind' => 'block', 'label' => $label, 'post' => $post, 'path' => $path, 'type' => $b['blockName'], 'where' => livecrafts_object_label( 'post', $post ), 'edit_link' => get_edit_post_link( $post, 'raw' ) ),
		'actions' => $actions,
		'notes'   => $notes,
	);
	if ( $parent !== '' ) {
		$p             = livecrafts_block_get( $blocks, $parent );
		$out['parent'] = array( 'label' => $p ? livecrafts_block_label( $p ) : 'Parent block', 'block' => $post . ':' . $parent );
	}
	return $out;
}

/* ------------------------------------------------------------------ ACF values printed by the theme */

function livecrafts_resolve_acf( $page, WP_REST_Request $req ) {
	if ( ! livecrafts_acf_active() ) return null;
	$data = livecrafts_draft_data( 'post', $page );
	if ( is_wp_error( $data ) ) return null;
	$all  = livecrafts_acf_scan( $page, $data );
	$text = livecrafts_norm_text( $req->get_param( 'text' ) );
	$img  = livecrafts_file_base( $req->get_param( 'imageSrc' ) ?: $req->get_param( 'bgImage' ) );
	$href = (string) $req->get_param( 'href' );
	$hits = array();
	foreach ( $all as $e ) {
		if ( $e['kind'] !== 'acf' ) continue;
		if ( $e['ftype'] === 'image' && $img !== '' && $e['url'] !== '' && livecrafts_file_base( $e['url'] ) === $img ) $hits[] = $e;
		elseif ( in_array( $e['ftype'], array( 'url', 'email' ), true ) && $href !== '' && rtrim( (string) $e['value'], '/' ) === rtrim( $href, '/' ) ) $hits[] = $e;
		elseif ( in_array( $e['ftype'], array( 'text', 'textarea', 'wysiwyg' ), true ) && $text !== '' && livecrafts_norm_text( $e['value'] ) === $text ) $hits[] = $e;
	}
	if ( count( $hits ) !== 1 ) {
		if ( count( $hits ) > 1 ) return array( 'source' => array( 'kind' => 'ambiguous', 'label' => 'ACF' ), 'actions' => array( livecrafts_action( 'ask', 'content', 'Ask the assistant', 'ask', null, array() ) ),
			'notes' => array( 'This value appears in ' . count( $hits ) . ' ACF fields of this page, so it is not safe to pick one. Edit it in the page\'s field list or ask the assistant.' ) );
		return null;
	}
	$e      = $hits[0];
	$nested = strpos( $e['tid'], 'acfv:' ) === 0;
	$change = $nested ? array( 'kind' => 'acf.value', 'post' => $page, 'target' => $e['name'] ) : array( 'kind' => 'acf.field', 'post' => $page, 'target' => $e['key'] );
	$input  = $e['ftype'] === 'image' ? 'image' : ( $e['ftype'] === 'wysiwyg' ? 'html' : ( in_array( $e['ftype'], array( 'url', 'email' ), true ) ? 'url' : 'text' ) );
	$value  = $e['ftype'] === 'image' ? array( 'id' => $e['value'], 'url' => $e['url'] ) : $e['value'];
	$actions = array( livecrafts_action( 'acf:value', 'content', $e['ftype'] === 'image' ? 'Replace image' : 'Edit ' . strtolower( $e['label'] ), $input, $value, $change ) );

	// a value inside a repeater / flexible content row: the row can be duplicated, moved, removed
	$rows = null;
	foreach ( $all as $r ) {
		if ( $r['kind'] === 'acf-rows' && preg_match( '/^' . preg_quote( $r['name'], '/' ) . '_(\d+)_/', $e['name'], $m ) && ( ! $rows || strlen( $r['name'] ) > strlen( $rows[0]['name'] ) ) ) $rows = array( $r, (int) $m[1] );
	}
	if ( $rows ) {
		list( $r, $i ) = $rows;
		$rc = function ( $op, $extra = array() ) use ( $page, $r, $i ) { return array( 'kind' => 'acf.rows', 'post' => $page, 'target' => $r['name'], 'value' => array_merge( array( 'op' => $op, 'index' => $i ), $extra ) ); };
		$actions[] = livecrafts_action( 'acf:row-duplicate', 'layout', 'Duplicate this row', 'confirm', null, $rc( 'duplicate' ) );
		if ( $i > 0 ) $actions[] = livecrafts_action( 'acf:row-up', 'layout', 'Move row up', 'confirm', null, $rc( 'move', array( 'to' => 'up' ) ) );
		if ( $i < $r['rows'] - 1 ) $actions[] = livecrafts_action( 'acf:row-down', 'layout', 'Move row down', 'confirm', null, $rc( 'move', array( 'to' => 'down' ) ) );
		$actions[] = livecrafts_action( 'acf:row-remove', 'layout', 'Remove this row', 'confirm', null, $rc( 'remove' ) );
	}
	return array(
		'source'  => array( 'kind' => 'acf', 'label' => $e['label'], 'post' => $page, 'field' => $e['key'], 'meta' => $e['name'], 'type' => $e['ftype'], 'where' => livecrafts_object_label( 'post', $page ), 'edit_link' => get_edit_post_link( $page, 'raw' ) ),
		'actions' => $actions,
		'notes'   => array(),
	);
}

/** An ACF field by its name (a block bound to it). */
function livecrafts_resolve_acf_name( $page, $name ) {
	if ( ! livecrafts_acf_active() ) return null;
	$data = livecrafts_draft_data( 'post', $page );
	if ( is_wp_error( $data ) ) return null;
	foreach ( livecrafts_acf_scan( $page, $data ) as $e ) {
		if ( $e['kind'] !== 'acf' || $e['name'] !== $name ) continue;
		$nested = strpos( $e['tid'], 'acfv:' ) === 0;
		$change = $nested ? array( 'kind' => 'acf.value', 'post' => $page, 'target' => $e['name'] ) : array( 'kind' => 'acf.field', 'post' => $page, 'target' => $e['key'] );
		$input  = $e['ftype'] === 'image' ? 'image' : ( $e['ftype'] === 'wysiwyg' ? 'html' : ( in_array( $e['ftype'], array( 'url', 'email' ), true ) ? 'url' : 'text' ) );
		$value  = $e['ftype'] === 'image' ? array( 'id' => $e['value'], 'url' => $e['url'] ) : $e['value'];
		return array(
			'source'  => array( 'kind' => 'acf', 'label' => $e['label'], 'post' => $page, 'field' => $e['key'], 'meta' => $e['name'], 'type' => $e['ftype'], 'where' => livecrafts_object_label( 'post', $page ), 'edit_link' => get_edit_post_link( $page, 'raw' ) ),
			'actions' => array( livecrafts_action( 'acf:value', 'content', $e['ftype'] === 'image' ? 'Replace image' : 'Edit ' . strtolower( $e['label'] ), $input, $value, $change ) ),
			'notes'   => array(),
		);
	}
	return null;
}

/* ------------------------------------------------------------------ menu links */

function livecrafts_resolve_menu( $item ) {
	$data = livecrafts_draft_data( 'post', $item );
	if ( is_wp_error( $data ) ) return null;
	$custom  = get_post_meta( $item, '_menu_item_type', true ) === 'custom';
	$title   = $data['fields']['title'];
	$actions = array(
		livecrafts_action( 'menu:label', 'content', 'Edit link text', 'text', $title !== '' ? $title : wp_setup_nav_menu_item( get_post( $item ) )->title, array( 'kind' => 'post.field', 'post' => $item, 'target' => 'title' ) ),
	);
	if ( $custom ) $actions[] = livecrafts_action( 'menu:url', 'content', 'Change address', 'url', isset( $data['meta']['_menu_item_url'] ) ? $data['meta']['_menu_item_url'] : '', array( 'kind' => 'post.meta', 'post' => $item, 'target' => '_menu_item_url' ) );
	$actions[] = livecrafts_action( 'menu:target', 'content', 'Open in a new tab', 'switch', ! empty( $data['meta']['_menu_item_target'] ), array( 'kind' => 'post.meta', 'post' => $item, 'target' => '_menu_item_target' ) );
	return array(
		'source'  => array( 'kind' => 'menu', 'label' => 'Menu link', 'post' => $item, 'where' => 'Navigation menu', 'edit_link' => admin_url( 'nav-menus.php' ) ),
		'actions' => $actions,
		'notes'   => $custom ? array() : array( 'This link points to a page; its address follows that page.' ),
	);
}

/* ------------------------------------------------------------------ anything else (theme / plugin output) */

function livecrafts_resolve_theme( $page, $selector, $device, $tag ) {
	$selector = livecrafts_css_selector_clean( $selector );
	$notes    = array( 'This element comes from the theme or a plugin, not from content stored in a page. Its look can be changed with a style rule; to change its text or structure, ask the assistant (it edits the theme template).' );
	$actions  = array( livecrafts_action( 'ask', 'content', 'Change the text or content (assistant)', 'ask', null, array() ) );
	$scopes   = null;
	if ( $selector !== '' && current_user_can( 'edit_css' ) ) {
		$scope = $page ? ( get_post_type( $page ) === 'page' ? 'body.page-id-' . $page : 'body.postid-' . $page ) : '';
		$own   = $scope !== '' ? $scope . ' ' . $selector : $selector;
		$scopes = array( 'page' => $own, 'site' => $selector );
		$actions = array_merge( $actions, livecrafts_css_actions( $own, $device, $tag ) );
	} elseif ( $selector !== '' ) {
		$notes[] = 'Your account cannot change the site CSS.';
	}
	return array(
		'source'     => array( 'kind' => 'theme', 'label' => $tag !== '' ? strtolower( $tag ) : 'element', 'where' => get_stylesheet() ),
		'actions'    => $actions,
		'css_scopes' => $scopes,
		'notes'      => $notes,
	);
}
