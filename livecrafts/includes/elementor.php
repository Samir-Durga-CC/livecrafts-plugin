<?php
/**
 * Elementor: edit the REAL Elementor setting behind an element, never an overlay.
 *
 * Target id:  el:<post_id>:<element_id>:<setting>     e.g. el:9:ba9fcdd:title   el:9:3f2a1c:link.url   el:9:7d1e0b:image
 *   - <element_id> is the data-id Elementor prints on every element, so the match is exact (no text guessing).
 *   - <setting> is one of the settings in livecrafts_el_paths() and must ALREADY exist on that element
 *     (settings are edited, never invented) - except background_image on a container/section.
 *
 * The element tree is the post's _elementor_data meta (JSON). Drafts change a copy of the tree (see drafts.php);
 * deploying saves the final tree with Elementor's own Document::save(), so Elementor validates it, makes its revision
 * and rebuilds the page CSS exactly as when someone clicks "Update" in the Elementor editor.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function livecrafts_el_active() {
	return class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance ) && \Elementor\Plugin::$instance->documents;
}

/** setting path => value kind. */
function livecrafts_el_paths() {
	return array(
		'title'            => 'text',
		'text'             => 'text',
		'title_text'       => 'text',
		'description_text' => 'text',
		'editor'           => 'html',
		'link.url'         => 'url',
		'image'            => 'image',
		'background_image' => 'image',
	);
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

/** Read a setting (supports "link.url"). Returns null when absent. */
function livecrafts_el_get( $settings, $path ) {
	if ( $path === 'link.url' ) return ( isset( $settings['link'] ) && is_array( $settings['link'] ) && isset( $settings['link']['url'] ) ) ? $settings['link']['url'] : null;
	return isset( $settings[ $path ] ) ? $settings[ $path ] : null;
}

/** Walk the element tree; run $cb(&$node) on the node with this id. Returns the callback's result, or null if not found. */
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

/** array( 'found' => bool, 'type' => widget|elType, 'value' => mixed ) for one element setting in a tree. */
function livecrafts_el_read( array $tree, $id, $path ) {
	$out = array( 'found' => false, 'type' => '', 'value' => null );
	livecrafts_el_apply( $tree, $id, function ( &$node ) use ( $path, &$out ) {
		$out['found'] = true;
		$out['type']  = isset( $node['widgetType'] ) ? $node['widgetType'] : ( isset( $node['elType'] ) ? $node['elType'] : '' );
		$out['value'] = livecrafts_el_get( isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : array(), $path );
		return true;
	} );
	return $out;
}

/** Set one setting in a tree. $value is already validated ('' / array() for an image = remove it). true or WP_Error. */
function livecrafts_el_write_tree( array &$tree, $id, $path, $value ) {
	$res = livecrafts_el_apply( $tree, $id, function ( &$node ) use ( $path, $value ) {
		if ( ! isset( $node['settings'] ) || ! is_array( $node['settings'] ) ) $node['settings'] = array();
		$s = &$node['settings'];
		if ( $path === 'image' || $path === 'background_image' ) {
			$cur = ( isset( $s[ $path ] ) && is_array( $s[ $path ] ) ) ? $s[ $path ] : array();
			if ( $path === 'image' && empty( $cur ) ) return new WP_Error( 'livecrafts_no_setting', 'This element has no image setting.', array( 'status' => 404 ) );
			if ( empty( $value ) ) { unset( $s[ $path ] ); return true; }
			$s[ $path ] = array_merge( $cur, array( 'url' => $value['url'], 'id' => (int) $value['id'], 'source' => 'library' ) );
			if ( ! isset( $s[ $path ]['size'] ) ) $s[ $path ]['size'] = '';
			if ( $path === 'background_image' && empty( $s['background_background'] ) ) $s['background_background'] = 'classic';
			return true;
		}
		if ( $path === 'link.url' ) {
			if ( ! isset( $s['link'] ) || ! is_array( $s['link'] ) ) return new WP_Error( 'livecrafts_no_setting', 'This element has no link setting.', array( 'status' => 404 ) );
			$s['link']['url'] = $value;
			return true;
		}
		if ( ! isset( $s[ $path ] ) || ! is_string( $s[ $path ] ) ) return new WP_Error( 'livecrafts_no_setting', 'This element has no "' . $path . '" setting.', array( 'status' => 404 ) );
		$s[ $path ] = $value;
		return true;
	} );
	if ( $res === null ) return new WP_Error( 'livecrafts_no_element', 'That Elementor element is not on this page (any more).', array( 'status' => 404 ) );
	return $res;
}

/** Validate a new value for a setting. Returns the clean value (string, or array(id,url) for images) or WP_Error. */
function livecrafts_el_validate( $path, $value, $current ) {
	$paths = livecrafts_el_paths();
	$kind  = isset( $paths[ $path ] ) ? $paths[ $path ] : '';
	if ( $kind === '' ) return livecrafts_bad_value( 'That Elementor setting cannot be edited yet.' );
	if ( $kind === 'image' ) {
		if ( $value === '' || $value === null || ( is_array( $value ) && empty( $value ) ) ) return array(); // remove (background only)
		$id = is_array( $value ) && isset( $value['id'] ) ? absint( $value['id'] ) : absint( $value );
		if ( ! $id || ! wp_attachment_is_image( $id ) ) return livecrafts_bad_value( 'That is not an image from the Media Library.' );
		return array( 'id' => $id, 'url' => (string) wp_get_attachment_url( $id ) );
	}
	if ( ! is_string( $value ) && ! is_numeric( $value ) ) return livecrafts_bad_value( 'Invalid value.' );
	$value = (string) $value;
	if ( $kind === 'url' ) {
		$v = esc_url_raw( $value );
		if ( $v === '' && $value !== '' ) return livecrafts_bad_value( 'Not a valid URL.' );
		return $v;
	}
	if ( $kind === 'html' ) {
		// Keep a single-paragraph text editor a single paragraph when the new value is plain text.
		if ( is_string( $current ) && preg_match( '/^\s*<p>.*<\/p>\s*$/s', $current ) && strpos( $value, '<' ) === false ) {
			return '<p>' . esc_html( $value ) . '</p>';
		}
		return wp_kses_post( $value );
	}
	return sanitize_text_field( $value );
}

/** Image settings compare by attachment id (and url); others as text. */
function livecrafts_el_values_equal( $path, $a, $b ) {
	if ( $path === 'image' || $path === 'background_image' ) {
		$ai = is_array( $a ) && isset( $a['id'] ) ? (int) $a['id'] : 0;
		$bi = is_array( $b ) && isset( $b['id'] ) ? (int) $b['id'] : 0;
		return $ai === $bi;
	}
	return (string) $a === (string) $b;
}

/* ------------------------------------------------------------------ scan (list of editable settings) */

function livecrafts_el_collect( $elements, $post, &$out ) {
	$paths = livecrafts_el_paths();
	foreach ( (array) $elements as $node ) {
		if ( ! is_array( $node ) ) continue;
		$settings = ( isset( $node['settings'] ) && is_array( $node['settings'] ) ) ? $node['settings'] : array();
		$type     = isset( $node['widgetType'] ) ? $node['widgetType'] : ( isset( $node['elType'] ) ? $node['elType'] : '' );
		if ( ! empty( $node['id'] ) ) {
			foreach ( $paths as $path => $kind ) {
				$v = livecrafts_el_get( $settings, $path );
				if ( $v === null ) continue;
				$entry = array(
					'kind' => 'el', 'tid' => 'el:' . $post . ':' . $node['id'] . ':' . $path, 'key' => '', 'name' => $path,
					'label' => ucfirst( str_replace( array( '-', '_' ), ' ', $type ) ), 'ftype' => $kind, 'post' => (int) $post,
					'id' => (string) $node['id'], 'widget' => $type, 'value' => '', 'url' => '',
				);
				if ( $kind === 'image' ) {
					if ( ! is_array( $v ) || empty( $v['url'] ) ) continue;
					$entry['value'] = isset( $v['id'] ) ? (int) $v['id'] : 0;
					$entry['url']   = (string) $v['url'];
				} else {
					if ( ! is_string( $v ) || $v === '' ) continue;
					$entry['value'] = mb_substr( $v, 0, 2000 );
				}
				$out[] = $entry;
			}
		}
		if ( ! empty( $node['elements'] ) ) livecrafts_el_collect( $node['elements'], $post, $out );
	}
}

/** Every editable Elementor setting of a post, from a post data array (live or draft). */
function livecrafts_el_scan( $post_id, array $data ) {
	$out = array();
	livecrafts_el_collect( livecrafts_el_tree( $data ), $post_id, $out );
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
