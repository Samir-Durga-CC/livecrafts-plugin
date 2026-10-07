<?php
/**
 * Elementor: change the REAL Elementor data behind an element, never an overlay.
 *
 * The element tree is the post's _elementor_data meta (JSON): sections > columns > widgets, or containers > widgets.
 * Every element has the id Elementor prints as data-id on the page, so a click maps to its element exactly.
 *
 *   el.setting    target "<element id>:<control>"   any control the element has, e.g. 3f2a1c:title, 3f2a1c:title_color,
 *                 3f2a1c:typography_font_size_mobile, 3f2a1c:_animation, 3f2a1c:hide_mobile. The value is checked
 *                 against the control's own definition from Elementor (type, options, units). A global colour or font
 *                 is "global:globals/colors?id=primary".
 *   el.insert     target "<parent id | root>:<index | end>"   value: an element (or a list) as Elementor JSON
 *   el.remove / el.duplicate / el.move   target "<element id>"
 *
 * Drafts change a copy of the tree (drafts.php). Deploying saves the final tree with Elementor's own
 * Document::save(), so Elementor makes its revision and rebuilds the page CSS exactly as when someone clicks
 * "Update" in the Elementor editor.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function livecrafts_el_active() {
	return class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance ) && \Elementor\Plugin::$instance->documents;
}

/** The element tree from a post data array ([] when the post is not built with Elementor). */
function livecrafts_el_tree( array $data ) {
	$raw  = isset( $data['meta']['_elementor_data'] ) ? $data['meta']['_elementor_data'] : '';
	$tree = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
	return is_array( $tree ) ? $tree : array();
}

function livecrafts_el_set_tree( array &$data, array $tree ) {
	$data['meta']['_elementor_data'] = wp_json_encode( $tree );
}

/** "widget:heading", "container", "section" ... */
function livecrafts_el_type( array $node ) {
	$el = isset( $node['elType'] ) ? (string) $node['elType'] : '';
	return $el === 'widget' ? 'widget:' . ( isset( $node['widgetType'] ) ? $node['widgetType'] : '' ) : $el;
}

/** "Heading", "Container" ... */
function livecrafts_el_label( array $node ) {
	$t = isset( $node['widgetType'] ) && $node['widgetType'] !== '' ? $node['widgetType'] : ( isset( $node['elType'] ) ? $node['elType'] : 'element' );
	return ucfirst( str_replace( array( '-', '_', '.' ), ' ', $t ) );
}

/* ------------------------------------------------------------------ tree helpers */

/** Run $cb( &$node ) on the node with this id. Returns the callback's result, or null if not found. */
function livecrafts_el_apply( &$elements, $id, $cb ) {
	foreach ( $elements as &$node ) {
		if ( isset( $node['id'] ) && (string) $node['id'] === (string) $id ) return $cb( $node );
		if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
			$r = livecrafts_el_apply( $node['elements'], $id, $cb );
			if ( $r !== null ) return $r;
		}
	}
	unset( $node );
	return null;
}

function livecrafts_el_node( array $tree, $id ) {
	$found = null;
	livecrafts_el_apply( $tree, $id, function ( &$node ) use ( &$found ) { $found = $node; return true; } );
	return $found;
}

/** Where an element is: array( parent id ('' = page root), index ), or null. */
function livecrafts_el_locate( array $tree, $id, $parent = '' ) {
	foreach ( array_values( $tree ) as $i => $node ) {
		if ( isset( $node['id'] ) && (string) $node['id'] === (string) $id ) return array( $parent, $i );
		if ( ! empty( $node['elements'] ) ) {
			$r = livecrafts_el_locate( $node['elements'], $id, (string) $node['id'] );
			if ( $r ) return $r;
		}
	}
	return null;
}

/** The children of the root ('') or of an element. null when the parent does not exist. */
function livecrafts_el_children( array $tree, $parent ) {
	if ( $parent === '' ) return $tree;
	$node = livecrafts_el_node( $tree, $parent );
	return $node ? ( isset( $node['elements'] ) ? (array) $node['elements'] : array() ) : null;
}

/** Remove $remove children at $index of a parent and insert $insert there. New tree or WP_Error. */
function livecrafts_el_splice( array $tree, $parent, $index, $remove, array $insert ) {
	$do = function ( array $list ) use ( $index, $remove, $insert ) {
		$list = array_values( $list );
		if ( $index < 0 || $index > count( $list ) || $index + $remove > count( $list ) ) return null;
		array_splice( $list, $index, $remove, $insert );
		return $list;
	};
	if ( $parent === '' ) {
		$r = $do( $tree );
		return $r === null ? new WP_Error( 'livecrafts_bad_place', 'That position is outside the page.', array( 'status' => 400 ) ) : $r;
	}
	$ok  = null;
	$res = livecrafts_el_apply( $tree, $parent, function ( &$node ) use ( $do, &$ok ) {
		$r = $do( isset( $node['elements'] ) ? (array) $node['elements'] : array() );
		if ( $r === null ) return false;
		$node['elements'] = $r;
		return true;
	} );
	if ( $res === null ) return new WP_Error( 'livecrafts_no_element', 'That Elementor element is not on this page (any more).', array( 'status' => 404 ) );
	if ( $res === false ) return new WP_Error( 'livecrafts_bad_place', 'That position is outside this element.', array( 'status' => 400 ) );
	return $tree;
}

/** Every element id in a tree. */
function livecrafts_el_ids( array $tree, array &$ids = array() ) {
	foreach ( $tree as $node ) {
		if ( isset( $node['id'] ) ) $ids[ (string) $node['id'] ] = true;
		if ( ! empty( $node['elements'] ) ) livecrafts_el_ids( $node['elements'], $ids );
	}
	return $ids;
}

/** The same element(s) with new ids that are not used in $used (Elementor ids: 7 hex characters). */
function livecrafts_el_reid( array $node, array &$used ) {
	do { $id = substr( bin2hex( random_bytes( 4 ) ), 0, 7 ); } while ( isset( $used[ $id ] ) );
	$used[ $id ] = true;
	$node['id']  = $id;
	if ( ! empty( $node['elements'] ) ) {
		foreach ( $node['elements'] as $k => $child ) $node['elements'][ $k ] = livecrafts_el_reid( $child, $used );
	}
	return $node;
}

/** "parent:index" outline of a tree, for checking that a structure change landed exactly. */
function livecrafts_el_shape( array $tree, $parent = '' ) {
	$out = array();
	foreach ( array_values( $tree ) as $i => $node ) {
		$out[] = $parent . '>' . ( isset( $node['id'] ) ? $node['id'] : '?' ) . '@' . $i;
		if ( ! empty( $node['elements'] ) ) $out = array_merge( $out, livecrafts_el_shape( $node['elements'], isset( $node['id'] ) ? (string) $node['id'] : '?' ) );
	}
	return $out;
}

/** Which element types may sit inside which (Elementor's own rules). */
function livecrafts_el_child_ok( $parent_eltype, $child_eltype ) {
	$rules = array(
		'root'      => array( 'section', 'container' ),
		'section'   => array( 'column' ),
		'column'    => array( 'widget', 'section' ),
		'container' => array( 'container', 'widget' ),
	);
	return isset( $rules[ $parent_eltype ] ) && in_array( $child_eltype, $rules[ $parent_eltype ], true );
}

/* ------------------------------------------------------------------ settings (controls) */

/** The controls Elementor defines for an element type (name => definition), cached per type. */
function livecrafts_el_controls( array $node ) {
	static $cache = array();
	$type = livecrafts_el_type( $node );
	if ( isset( $cache[ $type ] ) ) return $cache[ $type ];
	$controls = array();
	if ( livecrafts_el_active() ) {
		try {
			$instance = \Elementor\Plugin::$instance->elements_manager->create_element_instance( array(
				'id' => 'lc00000', 'elType' => isset( $node['elType'] ) ? $node['elType'] : '', 'widgetType' => isset( $node['widgetType'] ) ? $node['widgetType'] : null,
				'settings' => array(), 'elements' => array(),
			) );
			if ( $instance ) $controls = (array) $instance->get_controls();
		} catch ( \Throwable $e ) {
			$controls = array();
		}
	}
	return $cache[ $type ] = $controls;
}

/** Control types whose values Livecrafts knows how to check. Others (code, HTML, repeaters ...) are refused. */
function livecrafts_el_control_types() {
	return array( 'text', 'textarea', 'wysiwyg', 'number', 'select', 'select2', 'choose', 'color', 'switcher', 'url', 'media', 'slider',
		'dimensions', 'icons', 'font', 'animation', 'exit_animation', 'hover_animation', 'box_shadow', 'text_shadow', 'gaps', 'popover_toggle', 'image_dimensions' );
}

/** Read a setting (path "link.url" = the url inside the link control; a global value reads as "global:<ref>"). */
function livecrafts_el_get( array $settings, $path ) {
	if ( $path === 'link.url' ) return ( isset( $settings['link'] ) && is_array( $settings['link'] ) && isset( $settings['link']['url'] ) ) ? $settings['link']['url'] : null;
	if ( ! empty( $settings['__globals__'][ $path ] ) ) return 'global:' . $settings['__globals__'][ $path ];
	return isset( $settings[ $path ] ) ? $settings[ $path ] : null;
}

/** Write a setting. '' / null / array() = back to Elementor's default. A global replaces the own value, and the other way round. */
function livecrafts_el_put( array &$settings, $path, $value ) {
	if ( $path === 'link.url' ) {
		$link             = isset( $settings['link'] ) && is_array( $settings['link'] ) ? $settings['link'] : array( 'is_external' => '', 'nofollow' => '' );
		$link['url']      = (string) $value;
		$settings['link'] = $link;
		return;
	}
	if ( is_string( $value ) && strpos( $value, 'global:' ) === 0 ) {
		if ( ! isset( $settings['__globals__'] ) || ! is_array( $settings['__globals__'] ) ) $settings['__globals__'] = array();
		$settings['__globals__'][ $path ] = substr( $value, 7 );
		$settings[ $path ]                = '';
		return;
	}
	if ( isset( $settings['__globals__'][ $path ] ) ) unset( $settings['__globals__'][ $path ] );
	if ( $value === null || $value === '' || $value === array() ) unset( $settings[ $path ] );
	else $settings[ $path ] = $value;
}

/** array( 'found' => bool, 'type' => widget|elType, 'value' => mixed ) for one setting in a tree. */
function livecrafts_el_read( array $tree, $id, $path ) {
	$node = livecrafts_el_node( $tree, $id );
	if ( ! $node ) return array( 'found' => false, 'type' => '', 'value' => null );
	return array(
		'found' => true,
		'type'  => isset( $node['widgetType'] ) ? $node['widgetType'] : ( isset( $node['elType'] ) ? $node['elType'] : '' ),
		'value' => livecrafts_el_get( isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : array(), $path ),
	);
}

/** Set one (already validated) setting in a tree. true or WP_Error. */
function livecrafts_el_write_tree( array &$tree, $id, $path, $value, array $also = array() ) {
	$res = livecrafts_el_apply( $tree, $id, function ( &$node ) use ( $path, $value, $also ) {
		if ( ! isset( $node['settings'] ) || ! is_array( $node['settings'] ) ) $node['settings'] = array();
		livecrafts_el_put( $node['settings'], $path, $value );
		foreach ( $also as $name => $v ) livecrafts_el_put( $node['settings'], $name, $v );
		return true;
	} );
	return $res === null ? new WP_Error( 'livecrafts_no_element', 'That Elementor element is not on this page (any more).', array( 'status' => 404 ) ) : true;
}

/**
 * The switches Elementor turns on by itself when someone uses a control in the editor: a typography value needs
 * its "custom typography" toggle, a background colour/image needs the "classic" background type. Only switches that
 * exist on this element and are still off. Returns control name => value.
 */
function livecrafts_el_toggles( array $controls, array $settings, $name ) {
	$also = array();
	if ( preg_match( '/^(.*typography)_(font_size|font_weight|font_family|font_style|text_transform|text_decoration|line_height|letter_spacing|word_spacing)(_[a-z_]+)?$/', $name, $m ) ) {
		$toggle = $m[1] . '_typography';
		if ( isset( $controls[ $toggle ] ) && empty( $settings[ $toggle ] ) && empty( $settings['__globals__'][ $toggle ] ) ) $also[ $toggle ] = 'custom';
	}
	if ( preg_match( '/^(.*background)_(color|image)(_[a-z_]+)?$/', $name, $m ) ) {
		$toggle = $m[1] . '_background';
		if ( isset( $controls[ $toggle ] ) && empty( $settings[ $toggle ] ) ) $also[ $toggle ] = 'classic';
	}
	return $also;
}

function livecrafts_el_number( $v ) {
	return is_numeric( $v ) ? $v + 0 : null;
}

/** "32px" / 32 / array(unit,size) -> array(unit, size, sizes) for a slider control. */
function livecrafts_el_slider( array $control, $value ) {
	$units = ! empty( $control['size_units'] ) ? array_values( (array) $control['size_units'] ) : array( 'px' );
	if ( is_array( $value ) ) {
		$size = isset( $value['size'] ) ? $value['size'] : '';
		$unit = isset( $value['unit'] ) ? (string) $value['unit'] : $units[0];
	} elseif ( preg_match( '/^\s*(-?\d*\.?\d+)\s*([a-z%]*)\s*$/i', (string) $value, $m ) ) {
		$size = $m[1];
		$unit = $m[2] !== '' ? strtolower( $m[2] ) : $units[0];
	} else {
		return livecrafts_bad_value( 'A size looks like 32px, 2rem or 50%.' );
	}
	if ( $size !== '' && livecrafts_el_number( $size ) === null ) return livecrafts_bad_value( 'The size must be a number.' );
	if ( ! in_array( $unit, $units, true ) ) return livecrafts_bad_value( 'This setting accepts ' . implode( ', ', $units ) . '.' );
	return array( 'unit' => $unit, 'size' => $size === '' ? '' : livecrafts_el_number( $size ), 'sizes' => array() );
}

/** "10px 20px" / array(top,right,bottom,left,unit) -> Elementor dimensions. */
function livecrafts_el_dimensions( array $control, $value ) {
	$units = ! empty( $control['size_units'] ) ? array_values( (array) $control['size_units'] ) : array( 'px' );
	if ( is_array( $value ) ) {
		$unit = isset( $value['unit'] ) ? (string) $value['unit'] : $units[0];
		$v    = array();
		foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) $v[ $side ] = isset( $value[ $side ] ) ? (string) $value[ $side ] : '';
	} else {
		$parts = preg_split( '/\s+/', trim( (string) $value ) );
		if ( ! $parts || count( $parts ) > 4 ) return livecrafts_bad_value( 'Spacing looks like "20px" or "10px 20px" (top/bottom left/right).' );
		$unit = '';
		$nums = array();
		foreach ( $parts as $p ) {
			if ( ! preg_match( '/^(-?\d*\.?\d+)([a-z%]*)$/i', $p, $m ) ) return livecrafts_bad_value( 'Spacing values are numbers with one unit, e.g. 10px 20px.' );
			if ( $m[2] !== '' && $unit !== '' && strtolower( $m[2] ) !== $unit ) return livecrafts_bad_value( 'Use one unit for all sides.' );
			if ( $m[2] !== '' ) $unit = strtolower( $m[2] );
			$nums[] = $m[1];
		}
		$unit = $unit !== '' ? $unit : $units[0];
		$n    = count( $nums );
		$v    = array( 'top' => $nums[0], 'right' => $nums[ $n > 1 ? 1 : 0 ], 'bottom' => $nums[ $n > 2 ? 2 : 0 ], 'left' => $nums[ $n > 3 ? 3 : ( $n > 1 ? 1 : 0 ) ] );
	}
	foreach ( $v as $side => $num ) if ( $num !== '' && livecrafts_el_number( $num ) === null ) return livecrafts_bad_value( 'The ' . $side . ' value must be a number.' );
	if ( ! in_array( $unit, $units, true ) ) return livecrafts_bad_value( 'This setting accepts ' . implode( ', ', $units ) . '.' );
	return $v + array( 'unit' => $unit, 'isLinked' => count( array_unique( $v ) ) === 1 );
}

function livecrafts_el_color( $value ) {
	$v = trim( (string) $value );
	if ( $v === '' || $v === 'transparent' ) return $v;
	if ( preg_match( '/^#([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $v ) ) return $v;
	if ( preg_match( '/^(rgb|rgba|hsl|hsla)\(\s*[\d.%\s,\/-]+\)$/i', $v ) ) return $v;
	return livecrafts_bad_value( 'A colour looks like #1d4ed8, rgba(0,0,0,.5) or a global colour.' );
}

/**
 * Check a value for one control. Returns the clean value or WP_Error.
 * $current = the element's current value (url / media controls keep their other keys).
 */
function livecrafts_el_value( array $control, $value, $current, $name ) {
	$type = isset( $control['type'] ) ? $control['type'] : '';
	if ( is_string( $value ) && strpos( $value, 'global:' ) === 0 ) {
		if ( ! preg_match( '#^global:globals/(colors|typography)\?id=[A-Za-z0-9_-]+$#', $value ) ) return livecrafts_bad_value( 'A global looks like global:globals/colors?id=primary.' );
		if ( ! in_array( $type, array( 'color', 'popover_toggle' ), true ) ) return livecrafts_bad_value( 'Only colours and typography can use a global.' );
		return $value;
	}
	if ( $value === null || $value === '' ) return ''; // back to Elementor's default
	switch ( $type ) {
		case 'text':
		case 'textarea':
			if ( ! is_scalar( $value ) ) return livecrafts_bad_value( 'This setting is text.' );
			if ( $name === '_css_classes' || substr( $name, -12 ) === '_css_classes' ) return trim( preg_replace( '/[^A-Za-z0-9 _-]/', '', (string) $value ) );
			if ( $name === '_element_id' ) return sanitize_html_class( (string) $value );
			return wp_kses_post( (string) $value );
		case 'wysiwyg':
			if ( ! is_scalar( $value ) ) return livecrafts_bad_value( 'This setting is text.' );
			if ( is_string( $current ) && preg_match( '/^\s*<p>.*<\/p>\s*$/s', $current ) && strpos( (string) $value, '<' ) === false ) return '<p>' . esc_html( (string) $value ) . '</p>';
			return wp_kses_post( (string) $value );
		case 'number':
			$n = livecrafts_el_number( $value );
			if ( $n === null ) return livecrafts_bad_value( 'This setting is a number.' );
			if ( isset( $control['min'] ) && $control['min'] !== '' && $n < $control['min'] ) return livecrafts_bad_value( 'The smallest allowed value is ' . $control['min'] . '.' );
			if ( isset( $control['max'] ) && $control['max'] !== '' && $n > $control['max'] ) return livecrafts_bad_value( 'The largest allowed value is ' . $control['max'] . '.' );
			return $n;
		case 'select':
		case 'choose':
		case 'select2':
			$options = ! empty( $control['options'] ) && is_array( $control['options'] ) ? array_map( 'strval', array_keys( $control['options'] ) ) : array();
			$list    = is_array( $value ) ? array_map( 'strval', $value ) : array( (string) $value );
			if ( $type !== 'select2' || empty( $control['multiple'] ) ) $list = array_slice( $list, 0, 1 );
			foreach ( $list as $v ) {
				if ( $options && ! in_array( $v, $options, true ) ) return livecrafts_bad_value( 'Choose one of: ' . implode( ', ', array_filter( $options, 'strlen' ) ) . '.' );
				if ( ! $options && ! preg_match( '/^[A-Za-z0-9 _.:-]{0,100}$/', $v ) ) return livecrafts_bad_value( 'That is not a valid option.' );
			}
			return $type === 'select2' && ! empty( $control['multiple'] ) ? $list : $list[0];
		case 'color':
			return livecrafts_el_color( $value );
		case 'switcher':
			$yes = isset( $control['return_value'] ) && $control['return_value'] !== '' ? (string) $control['return_value'] : 'yes';
			return in_array( $value, array( true, 'yes', 'on', '1', 1, $yes ), true ) ? $yes : '';
		case 'popover_toggle':
			return in_array( $value, array( 'custom', 'yes' ), true ) ? $value : '';
		case 'url':
			$url  = is_array( $value ) ? ( isset( $value['url'] ) ? $value['url'] : '' ) : $value;
			$link = esc_url_raw( (string) $url );
			if ( $link === '' && (string) $url !== '' ) return livecrafts_bad_value( 'Not a valid address.' );
			$base = is_array( $current ) ? $current : array( 'is_external' => '', 'nofollow' => '' );
			$out  = array_merge( $base, array( 'url' => $link ) );
			if ( is_array( $value ) ) {
				foreach ( array( 'is_external', 'nofollow' ) as $k ) if ( isset( $value[ $k ] ) ) $out[ $k ] = $value[ $k ] && $value[ $k ] !== 'off' ? 'on' : '';
			}
			return $out;
		case 'media':
			$id = is_array( $value ) && isset( $value['id'] ) ? absint( $value['id'] ) : absint( $value );
			if ( ! $id || ! wp_attachment_is_image( $id ) ) return livecrafts_bad_value( 'That is not an image from the Media Library.' );
			$base = is_array( $current ) ? $current : array();
			unset( $base['alt'] );
			return array_merge( $base, array( 'url' => (string) wp_get_attachment_url( $id ), 'id' => $id, 'source' => 'library', 'size' => isset( $base['size'] ) ? $base['size'] : '' ) );
		case 'slider':
			return livecrafts_el_slider( $control, $value );
		case 'dimensions':
			return livecrafts_el_dimensions( $control, $value );
		case 'gaps':
			if ( ! is_array( $value ) ) {
				$s = livecrafts_el_slider( array( 'size_units' => isset( $control['size_units'] ) ? $control['size_units'] : array( 'px' ) ), $value );
				if ( is_wp_error( $s ) ) return $s;
				return array( 'row' => (string) $s['size'], 'column' => (string) $s['size'], 'unit' => $s['unit'], 'isLinked' => true );
			}
			$row = isset( $value['row'] ) ? (string) $value['row'] : '';
			$col = isset( $value['column'] ) ? (string) $value['column'] : $row;
			if ( ( $row !== '' && ! is_numeric( $row ) ) || ( $col !== '' && ! is_numeric( $col ) ) ) return livecrafts_bad_value( 'Gaps are numbers.' );
			return array( 'row' => $row, 'column' => $col, 'unit' => isset( $value['unit'] ) ? sanitize_key( $value['unit'] ) : 'px', 'isLinked' => $row === $col );
		case 'icons':
			if ( ! is_array( $value ) || empty( $value['value'] ) ) return livecrafts_bad_value( 'An icon is {value, library}, e.g. {value: "fas fa-star", library: "fa-solid"}.' );
			return array( 'value' => sanitize_text_field( is_array( $value['value'] ) ? '' : $value['value'] ), 'library' => sanitize_key( isset( $value['library'] ) ? $value['library'] : '' ) );
		case 'font':
			return sanitize_text_field( (string) $value );
		case 'animation':
		case 'exit_animation':
		case 'hover_animation':
			if ( ! preg_match( '/^[A-Za-z-]{1,40}$/', (string) $value ) ) return livecrafts_bad_value( 'Not a valid animation name (e.g. fadeInUp).' );
			return (string) $value;
		case 'box_shadow':
		case 'text_shadow':
			if ( ! is_array( $value ) ) return livecrafts_bad_value( 'A shadow is {horizontal, vertical, blur, spread, color}.' );
			$out = array();
			foreach ( array( 'horizontal', 'vertical', 'blur', 'spread' ) as $k ) {
				if ( isset( $value[ $k ] ) && livecrafts_el_number( $value[ $k ] ) === null ) return livecrafts_bad_value( 'Shadow ' . $k . ' must be a number.' );
				if ( isset( $value[ $k ] ) ) $out[ $k ] = livecrafts_el_number( $value[ $k ] );
			}
			$color = livecrafts_el_color( isset( $value['color'] ) ? $value['color'] : 'rgba(0,0,0,0.5)' );
			if ( is_wp_error( $color ) ) return $color;
			return $out + array( 'color' => $color );
		case 'image_dimensions':
			if ( ! is_array( $value ) ) return livecrafts_bad_value( 'Image size is {width, height}.' );
			return array( 'width' => (string) absint( isset( $value['width'] ) ? $value['width'] : 0 ), 'height' => (string) absint( isset( $value['height'] ) ? $value['height'] : 0 ) );
	}
	return livecrafts_bad_value( 'This kind of Elementor setting ("' . $type . '") cannot be changed here.' );
}

/** Two values of one setting the same? Images compare by attachment id. */
function livecrafts_el_values_equal( $path, $a, $b ) {
	if ( ( is_array( $a ) && isset( $a['id'] ) ) || ( is_array( $b ) && isset( $b['id'] ) ) ) {
		return ( is_array( $a ) && isset( $a['id'] ) ? (int) $a['id'] : 0 ) === ( is_array( $b ) && isset( $b['id'] ) ? (int) $b['id'] : 0 );
	}
	return livecrafts_same( $a, $b );
}

/* ------------------------------------------------------------------ new elements (el.insert) */

/**
 * Check elements to insert (Elementor JSON): known element types, registered widgets, allowed nesting, and every
 * setting a control of that element with a valid value. Returns the clean elements with fresh ids, or WP_Error.
 */
function livecrafts_el_clean_new( array $nodes, $parent_eltype, array &$used, $depth = 0 ) {
	if ( $depth > 8 ) return livecrafts_bad_value( 'The new elements are nested too deeply.' );
	$out = array();
	foreach ( $nodes as $node ) {
		if ( ! is_array( $node ) || empty( $node['elType'] ) ) return livecrafts_bad_value( 'Every element needs an elType (container, section, column or widget).' );
		$el = (string) $node['elType'];
		if ( ! livecrafts_el_child_ok( $parent_eltype, $el ) ) return livecrafts_bad_value( 'A ' . $el . ' cannot be placed inside a ' . $parent_eltype . '.' );
		$clean = array( 'id' => '', 'elType' => $el, 'settings' => array(), 'elements' => array(), 'isInner' => ! empty( $node['isInner'] ) );
		if ( $el === 'widget' ) {
			$wt = isset( $node['widgetType'] ) ? (string) $node['widgetType'] : '';
			if ( ! \Elementor\Plugin::$instance->widgets_manager->get_widget_types( $wt ) ) return livecrafts_bad_value( 'Unknown widget "' . $wt . '" on this site.' );
			$clean['widgetType'] = $wt;
		}
		$controls = livecrafts_el_controls( $clean );
		foreach ( isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : array() as $name => $value ) {
			if ( $name === '__globals__' ) {
				foreach ( (array) $value as $gname => $ref ) {
					$g = livecrafts_el_value( isset( $controls[ $gname ] ) ? $controls[ $gname ] : array(), 'global:' . $ref, null, $gname );
					if ( is_wp_error( $g ) ) return $g;
					livecrafts_el_put( $clean['settings'], $gname, $g );
				}
				continue;
			}
			if ( ! isset( $controls[ $name ] ) || ! in_array( $controls[ $name ]['type'], livecrafts_el_control_types(), true ) ) {
				return livecrafts_bad_value( livecrafts_el_label( $clean ) . ' has no setting "' . $name . '" that can be set here.' );
			}
			$v = livecrafts_el_value( $controls[ $name ], $value, null, $name );
			if ( is_wp_error( $v ) ) return new WP_Error( $v->get_error_code(), livecrafts_el_label( $clean ) . ' › ' . $name . ': ' . $v->get_error_message(), array( 'status' => 400 ) );
			livecrafts_el_put( $clean['settings'], $name, $v );
		}
		if ( ! empty( $node['elements'] ) ) {
			if ( $el === 'widget' ) return livecrafts_bad_value( 'A widget cannot contain other elements.' );
			$kids = livecrafts_el_clean_new( (array) $node['elements'], $el, $used, $depth + 1 );
			if ( is_wp_error( $kids ) ) return $kids;
			$clean['elements'] = $kids;
		}
		if ( $el !== 'widget' ) unset( $clean['widgetType'] );
		$out[] = livecrafts_el_reid( $clean, $used );
	}
	return $out;
}

/* ------------------------------------------------------------------ change kinds el.insert / remove / duplicate / move */

/** The kind definition for one structure operation (see kinds.php). Conflicts: the page's element structure as a whole. */
function livecrafts_el_structure_kind( $op ) {
	return array(
		'object'  => 'post',
		'group'   => 'elementor-structure',
		'prepare' => function ( $post_id, $target, $value, $draft, $args ) use ( $op ) { return livecrafts_el_structure_prepare( $op, $target, $value, $draft, $args ); },
		'read'    => function ( $data, $target, $payload ) {
			if ( isset( $payload['op'] ) && $payload['op'] === 'insert' ) return '';
			$node = livecrafts_el_node( livecrafts_el_tree( $data ), $target );
			return $node ? wp_json_encode( $node ) : '';
		},
		'base'    => function ( $data ) { return livecrafts_el_shape( livecrafts_el_tree( $data ) ); },
		'apply'   => function ( &$data, $target, $after, $payload ) {
			$tree = livecrafts_el_structure_apply( livecrafts_el_tree( $data ), $target, $after, $payload );
			if ( is_wp_error( $tree ) ) return $tree;
			livecrafts_el_set_tree( $data, $tree );
			return true;
		},
		'landed'  => function ( $saved, $expected ) { return livecrafts_el_shape( livecrafts_el_tree( $saved ) ) === livecrafts_el_shape( livecrafts_el_tree( $expected ) ); },
		'revert'  => 'livecrafts_el_structure_revert',
	);
}

function livecrafts_el_structure_prepare( $op, $target, $value, array $draft, array $args ) {
	if ( ! livecrafts_el_active() ) return new WP_Error( 'livecrafts_no_elementor', 'Elementor is not active on this site.', array( 'status' => 400 ) );
	$tree = livecrafts_el_tree( $draft );
	if ( ! $tree && $op !== 'insert' ) return livecrafts_bad_value( 'This page is not built with Elementor.' );

	if ( $op === 'insert' ) {
		if ( ! preg_match( '/^(root|[A-Za-z0-9]{3,16}):(\d{1,4}|end)$/', (string) $target, $m ) ) return livecrafts_bad_value( 'Where to add: "<parent element id or root>:<index or end>", e.g. root:end or 3f2a1c:0.' );
		$parent = $m[1] === 'root' ? '' : $m[1];
		$kids   = livecrafts_el_children( $tree, $parent );
		if ( $kids === null ) return new WP_Error( 'livecrafts_no_element', 'There is no element ' . $parent . ' on this page.', array( 'status' => 404 ) );
		$index  = $m[2] === 'end' ? count( $kids ) : (int) $m[2];
		if ( $index > count( $kids ) ) return livecrafts_bad_value( 'That place is past the end (there are ' . count( $kids ) . ' elements there).' );
		$nodes  = is_array( $value ) && isset( $value['elType'] ) ? array( $value ) : ( is_array( $value ) ? array_values( $value ) : array() );
		if ( count( $nodes ) !== 1 ) return livecrafts_bad_value( 'Add one element at a time, as Elementor JSON ({elType, widgetType, settings, elements}). Put several widgets inside one container or section.' );
		$used   = livecrafts_el_ids( $tree );
		if ( ! empty( $args['restore'] ) ) { // a revert puts back elements that were on this page, ids and all
			foreach ( $nodes as $k => $n ) if ( isset( $used[ (string) $n['id'] ] ) ) $nodes[ $k ] = livecrafts_el_reid( $n, $used );
			$clean = $nodes;
		} else {
			$parent_type = $parent === '' ? 'root' : livecrafts_el_node( $tree, $parent )['elType'];
			$clean       = livecrafts_el_clean_new( $nodes, $parent_type, $used );
			if ( is_wp_error( $clean ) ) return $clean;
		}
		return array(
			'target'  => ( $parent === '' ? 'root' : $parent ) . ':' . $index,
			'after'   => $clean,
			'payload' => array( 'op' => 'insert', 'parent' => $parent, 'index' => $index, 'ids' => wp_list_pluck( $clean, 'id' ) ),
			'summary' => 'Add ' . implode( ', ', array_map( 'livecrafts_el_label', $clean ) ),
		);
	}

	$node = preg_match( '/^[A-Za-z0-9]{3,16}$/', (string) $target ) ? livecrafts_el_node( $tree, $target ) : null;
	if ( ! $node ) return new WP_Error( 'livecrafts_no_element', 'There is no element ' . $target . ' on this page.', array( 'status' => 404 ) );
	list( $parent, $index ) = livecrafts_el_locate( $tree, $target );
	$label   = livecrafts_el_label( $node );
	$payload = array( 'op' => $op, 'parent' => $parent, 'index' => $index );

	switch ( $op ) {
		case 'remove':
			$payload['removed'] = $node;
			return array( 'target' => $target, 'after' => $target, 'payload' => $payload, 'summary' => 'Remove ' . $label );
		case 'duplicate':
			$used = livecrafts_el_ids( $tree );
			$copy = livecrafts_el_reid( $node, $used );
			return array( 'target' => $target, 'after' => $copy, 'payload' => $payload, 'summary' => 'Duplicate ' . $label );
		case 'move':
			$siblings = livecrafts_el_children( $tree, $parent );
			if ( $value === 'up' || $value === 'down' ) {
				$to_parent = $parent;
				$to        = $value === 'up' ? $index - 1 : $index + 1;
				$room      = count( $siblings ) - 1;
			} elseif ( is_array( $value ) && isset( $value['index'] ) ) {
				$to_parent = isset( $value['parent'] ) && $value['parent'] !== 'root' ? (string) $value['parent'] : '';
				$list      = livecrafts_el_children( $tree, $to_parent );
				if ( $list === null ) return new WP_Error( 'livecrafts_no_element', 'There is no element ' . $to_parent . ' to move into.', array( 'status' => 404 ) );
				$type = $to_parent === '' ? 'root' : livecrafts_el_node( $tree, $to_parent )['elType'];
				if ( ! livecrafts_el_child_ok( $type, $node['elType'] ) ) return livecrafts_bad_value( 'A ' . $node['elType'] . ' cannot be placed inside a ' . $type . '.' );
				if ( $to_parent === (string) $target || in_array( $to_parent, livecrafts_el_descendant_ids( $node ), true ) ) return livecrafts_bad_value( 'An element cannot be moved into itself.' );
				$to   = (int) $value['index'];
				$room = count( $list ) - ( $to_parent === $parent ? 1 : 0 );
			} else {
				return livecrafts_bad_value( 'Move "up", "down", or {parent: <id or root>, index: n}.' );
			}
			if ( $to < 0 || $to > $room ) return livecrafts_bad_value( 'It cannot move there.' );
			if ( $to_parent === $parent && $to === $index ) return array( 'unchanged' => true, 'target' => $target, 'after' => null, 'payload' => array(), 'summary' => '' );
			return array( 'target' => $target, 'after' => array( 'parent' => $to_parent, 'index' => $to ), 'payload' => $payload,
				'summary' => 'Move ' . $label . ( $to_parent === $parent ? ( $to < $index ? ' up' : ' down' ) : ' to another place' ) );
	}
	return livecrafts_bad_value( 'Unknown Elementor operation.' );
}

function livecrafts_el_descendant_ids( array $node ) {
	$ids = array();
	foreach ( isset( $node['elements'] ) ? (array) $node['elements'] : array() as $child ) {
		$ids[] = (string) $child['id'];
		$ids   = array_merge( $ids, livecrafts_el_descendant_ids( $child ) );
	}
	return $ids;
}

/** Do one structure operation on a tree. Elements are found by id each time, so earlier changes never shift them. */
function livecrafts_el_structure_apply( array $tree, $target, $after, array $payload ) {
	if ( $payload['op'] === 'insert' ) return livecrafts_el_splice( $tree, $payload['parent'], (int) $payload['index'], 0, (array) $after );
	$loc = livecrafts_el_locate( $tree, $target );
	if ( ! $loc ) return new WP_Error( 'livecrafts_no_element', 'That Elementor element is not on this page (any more).', array( 'status' => 404 ) );
	list( $parent, $index ) = $loc;
	switch ( $payload['op'] ) {
		case 'remove':
			return livecrafts_el_splice( $tree, $parent, $index, 1, array() );
		case 'duplicate':
			return livecrafts_el_splice( $tree, $parent, $index + 1, 0, array( $after ) );
		case 'move':
			$node = livecrafts_el_node( $tree, $target );
			$tree = livecrafts_el_splice( $tree, $parent, $index, 1, array() );
			return is_wp_error( $tree ) ? $tree : livecrafts_el_splice( $tree, (string) $after['parent'], (int) $after['index'], 0, array( $node ) );
	}
	return livecrafts_bad_value( 'Unknown Elementor operation.' );
}

/** The opposite of a structure change that went live. */
function livecrafts_el_structure_revert( array $c ) {
	$p = $c['payload'];
	switch ( $p['op'] ) {
		case 'insert':    return array( 'el.remove', $p['ids'][0], null, array() );
		case 'duplicate': return array( 'el.remove', $p['after']['id'], null, array() );
		case 'move':      return array( 'el.move', $c['target'], array( 'parent' => $p['parent'] === '' ? 'root' : $p['parent'], 'index' => $p['index'] ), array() );
		case 'remove':    return array( 'el.insert', ( $p['parent'] === '' ? 'root' : $p['parent'] ) . ':' . $p['index'], array( $p['removed'] ), array( 'restore' => true ) );
	}
	return new WP_Error( 'livecrafts_no_revert', 'This change cannot be reverted automatically.' );
}

/* ------------------------------------------------------------------ the page map + outline */

/** Every content setting (texts, links, images) of a post, from a post data array (live or draft). */
function livecrafts_el_scan( $post_id, array $data ) {
	$out = array();
	livecrafts_el_collect( livecrafts_el_tree( $data ), $post_id, $out );
	return $out;
}

function livecrafts_el_collect( $elements, $post, &$out ) {
	foreach ( (array) $elements as $node ) {
		if ( ! is_array( $node ) || empty( $node['id'] ) ) continue;
		$settings = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : array();
		$controls = livecrafts_el_controls( $node );
		foreach ( $settings as $name => $v ) {
			$c = isset( $controls[ $name ] ) ? $controls[ $name ] : null;
			if ( ! $c || ( isset( $c['tab'] ) && $c['tab'] !== 'content' ) ) continue;
			$kind = in_array( $c['type'], array( 'text', 'textarea' ), true ) ? 'text' : ( $c['type'] === 'wysiwyg' ? 'html' : ( $c['type'] === 'url' ? 'url' : ( $c['type'] === 'media' ? 'image' : '' ) ) );
			if ( $kind === '' || $name[0] === '_' ) continue;
			$entry = array(
				'kind' => 'el', 'tid' => 'el:' . $post . ':' . $node['id'] . ':' . $name, 'key' => '', 'name' => $name,
				'label' => livecrafts_el_label( $node ) . ' › ' . ( isset( $c['label'] ) && $c['label'] !== '' ? $c['label'] : $name ),
				'ftype' => $kind, 'post' => (int) $post, 'id' => (string) $node['id'], 'widget' => isset( $node['widgetType'] ) ? $node['widgetType'] : $node['elType'], 'value' => '', 'url' => '',
			);
			if ( $kind === 'image' ) {
				if ( ! is_array( $v ) || empty( $v['url'] ) ) continue;
				$entry['value'] = isset( $v['id'] ) ? (int) $v['id'] : 0;
				$entry['url']   = (string) $v['url'];
			} elseif ( $kind === 'url' ) {
				if ( ! is_array( $v ) || empty( $v['url'] ) ) continue;
				$entry['value'] = (string) $v['url'];
			} else {
				if ( ! is_string( $v ) || $v === '' ) continue;
				$entry['value'] = mb_substr( $v, 0, 2000 );
			}
			$out[] = $entry;
		}
		if ( ! empty( $node['elements'] ) ) livecrafts_el_collect( $node['elements'], $post, $out );
	}
}

/** The page's element tree as a flat outline: id, type, parent, depth, a short text. */
function livecrafts_el_outline( array $tree, $parent = '', $depth = 0, array &$out = array() ) {
	foreach ( array_values( $tree ) as $i => $node ) {
		if ( ! is_array( $node ) || empty( $node['id'] ) ) continue;
		$s    = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : array();
		$text = '';
		foreach ( array( 'title', 'editor', 'text', 'title_text', 'description_text', 'heading' ) as $k ) {
			if ( ! empty( $s[ $k ] ) && is_string( $s[ $k ] ) ) { $text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $s[ $k ] ) ) ); break; }
		}
		$out[] = array( 'id' => (string) $node['id'], 'type' => isset( $node['widgetType'] ) ? $node['widgetType'] : $node['elType'], 'elType' => $node['elType'],
			'parent' => $parent, 'index' => $i, 'depth' => $depth, 'text' => mb_substr( $text, 0, 80 ) );
		if ( ! empty( $node['elements'] ) ) livecrafts_el_outline( $node['elements'], (string) $node['id'], $depth + 1, $out );
	}
	return $out;
}

/* ------------------------------------------------------------------ live write + cache */

/** Save a whole element tree to the LIVE post the way the Elementor editor does. true or WP_Error. */
function livecrafts_el_save( $post_id, array $tree ) {
	if ( ! livecrafts_el_active() ) return new WP_Error( 'livecrafts_no_elementor', 'Elementor is not active, so Elementor content cannot be deployed.', array( 'status' => 500 ) );
	$doc = \Elementor\Plugin::$instance->documents->get( $post_id, false );
	if ( ! $doc ) return new WP_Error( 'livecrafts_no_doc', 'That page is not an Elementor page.', array( 'status' => 404 ) );
	if ( ! $doc->save( array( 'elements' => $tree ) ) ) {
		return new WP_Error( 'livecrafts_el_save_failed', 'Elementor refused to save (your role may not be allowed to edit with Elementor, or the page is locked).', array( 'status' => 500 ) );
	}
	return true;
}

/** Drop Elementor's generated CSS of a post (it is rebuilt on the next view). Used after a reset restored raw data. */
function livecrafts_el_flush( $post_id ) {
	if ( ! livecrafts_el_active() || ! class_exists( '\Elementor\Core\Files\CSS\Post' ) ) return;
	try {
		\Elementor\Core\Files\CSS\Post::create( $post_id )->delete();
	} catch ( \Throwable $e ) {
		// Not fatal: Elementor rebuilds stale CSS when the page is saved next time.
	}
}
