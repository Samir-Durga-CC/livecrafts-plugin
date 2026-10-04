<?php
/**
 * Elementor adapter (v0.4): edit the REAL Elementor setting behind an element, not an overlay.
 *
 * Target id:  el:<post_id>:<widget_id>:<setting>     e.g. el:9:ba9fcdd:title   el:9:3f2a1c:link.url   el:9:7d1e0b:image
 *   - <widget_id> is the `data-id` Elementor prints on every element, so the match is exact (no text guessing).
 *   - <setting> is one of a short allowlist (see livecrafts_el_paths) and must ALREADY exist on that element
 *     (we edit settings, we never invent them) - except `background_image` on a container.
 *
 * Read:  \Elementor\Plugin::$instance->documents->get($id)->get_elements_data()
 * Write: ...->save(['elements' => $tree])   (Elementor itself deletes its cached CSS for the post)
 * Then READ BACK the raw `_elementor_data` meta and roll back + error if it does not match ("no fake saves").
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

/** Read a setting (supports "link.url"). Returns null when absent. */
function livecrafts_el_get( $settings, $path ) {
	if ( $path === 'link.url' ) return ( isset( $settings['link'] ) && is_array( $settings['link'] ) && isset( $settings['link']['url'] ) ) ? $settings['link']['url'] : null;
	return isset( $settings[ $path ] ) ? $settings[ $path ] : null;
}

/** Walk the element tree; run $cb(&$node) on the node with this id. Returns the callback's result or null if not found. */
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

/** Read straight from the database (not Elementor's cache): ['found'=>bool,'type'=>widget|elType,'value'=>mixed]. */
function livecrafts_el_read_raw( $post, $id, $path ) {
	$meta = get_post_meta( $post, '_elementor_data', true );
	$tree = is_string( $meta ) ? json_decode( $meta, true ) : $meta;
	if ( ! is_array( $tree ) ) return array( 'found' => false, 'type' => '', 'value' => null );
	$out = array( 'found' => false, 'type' => '', 'value' => null );
	livecrafts_el_apply( $tree, $id, function ( &$node ) use ( $path, &$out ) {
		$out['found'] = true;
		$out['type']  = isset( $node['widgetType'] ) ? $node['widgetType'] : ( isset( $node['elType'] ) ? $node['elType'] : '' );
		$out['value'] = livecrafts_el_get( isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : array(), $path );
		return true;
	} );
	return $out;
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
					'id' => (string) $node['id'], 'widget' => $type, 'value' => '', 'url' => '', 'src' => 'scan',
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

function livecrafts_el_scan( $post ) {
	$out = array();
	if ( ! $post || ! livecrafts_el_active() ) return $out;
	$doc = \Elementor\Plugin::$instance->documents->get( $post );
	if ( ! $doc || ! method_exists( $doc, 'get_elements_data' ) ) return $out;
	livecrafts_el_collect( $doc->get_elements_data(), $post, $out );
	return $out;
}

/* ------------------------------------------------------------------ write (verified) */

/** Validate a new value for a setting. Returns the clean value (string, or ['id','url'] for images) or WP_Error. */
function livecrafts_el_validate( $path, $value, $current ) {
	$paths = livecrafts_el_paths();
	$kind  = isset( $paths[ $path ] ) ? $paths[ $path ] : '';
	if ( $kind === 'image' ) {
		$id = absint( $value );
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
		// Keep a single-paragraph editor as a single paragraph when the new value is plain text.
		if ( is_string( $current ) && preg_match( '/^\s*<p>.*<\/p>\s*$/s', $current ) && strpos( $value, '<' ) === false ) {
			return '<p>' . esc_html( $value ) . '</p>';
		}
		return wp_kses_post( $value );
	}
	return sanitize_text_field( $value );
}

/** Write one setting. $value is already validated. Returns ['before'=>mixed] or WP_Error. */
function livecrafts_el_write( $post, $id, $path, $value ) {
	if ( ! livecrafts_el_active() ) return new WP_Error( 'livecrafts_no_elementor', 'Elementor is not active.', array( 'status' => 500 ) );
	$doc = \Elementor\Plugin::$instance->documents->get( $post, false );
	if ( ! $doc || ! method_exists( $doc, 'get_elements_data' ) ) return new WP_Error( 'livecrafts_no_doc', 'That page is not an Elementor page.', array( 'status' => 404 ) );

	$tree = $doc->get_elements_data();
	$res  = livecrafts_el_apply( $tree, $id, function ( &$node ) use ( $path, $value ) {
		if ( ! isset( $node['settings'] ) || ! is_array( $node['settings'] ) ) $node['settings'] = array();
		if ( $path === 'image' || $path === 'background_image' ) {
			$cur    = ( isset( $node['settings'][ $path ] ) && is_array( $node['settings'][ $path ] ) ) ? $node['settings'][ $path ] : array();
			$before = $cur;
			if ( $path === 'image' && empty( $cur ) ) return new WP_Error( 'livecrafts_no_setting', 'This element has no image setting.', array( 'status' => 404 ) );
			if ( empty( $value ) ) { unset( $node['settings'][ $path ] ); return array( 'before' => $before ); } // undo of "no image before"
			$new = array_merge( $cur, array( 'url' => $value['url'], 'id' => (int) $value['id'] ) );
			if ( ! isset( $new['size'] ) )   $new['size']   = '';
			if ( ! isset( $new['source'] ) ) $new['source'] = 'library';
			$node['settings'][ $path ] = $new;
			if ( $path === 'background_image' && empty( $node['settings']['background_background'] ) ) $node['settings']['background_background'] = 'classic';
			return array( 'before' => $before );
		}
		if ( $path === 'link.url' ) {
			$before = ( isset( $node['settings']['link'] ) && is_array( $node['settings']['link'] ) && isset( $node['settings']['link']['url'] ) ) ? $node['settings']['link']['url'] : null;
			if ( $before === null ) return new WP_Error( 'livecrafts_no_setting', 'This element has no link setting.', array( 'status' => 404 ) );
			$node['settings']['link']['url'] = $value;
			return array( 'before' => $before );
		}
		if ( ! isset( $node['settings'][ $path ] ) || ! is_string( $node['settings'][ $path ] ) ) return new WP_Error( 'livecrafts_no_setting', 'This element has no "' . $path . '" setting.', array( 'status' => 404 ) );
		$before = $node['settings'][ $path ];
		$node['settings'][ $path ] = $value;
		return array( 'before' => $before );
	} );

	if ( $res === null ) return new WP_Error( 'livecrafts_no_element', 'That Elementor element no longer exists on the page.', array( 'status' => 404 ) );
	if ( is_wp_error( $res ) ) return $res;

	if ( ! $doc->save( array( 'elements' => $tree ) ) ) return new WP_Error( 'livecrafts_el_save_failed', 'Elementor refused to save (permission or locked document).', array( 'status' => 500 ) );
	clean_post_cache( $post );
	return $res;
}

function livecrafts_el_values_equal( $path, $a, $b ) {
	if ( $path === 'image' || $path === 'background_image' ) {
		$ai = is_array( $a ) && isset( $a['id'] ) ? (int) $a['id'] : 0;
		$bi = is_array( $b ) && isset( $b['id'] ) ? (int) $b['id'] : 0;
		return $ai === $bi && ( ! $ai || ( isset( $a['url'] ) && isset( $b['url'] ) && $a['url'] === $b['url'] ) );
	}
	return (string) $a === (string) $b;
}

/** POST /target for an el: id. Called from livecrafts_rest_target(). */
function livecrafts_el_rest_target( WP_REST_Request $req, $t, $raw ) {
	$paths = livecrafts_el_paths();
	if ( ! isset( $paths[ $t['path'] ] ) ) return new WP_Error( 'livecrafts_bad_request', 'That Elementor setting cannot be edited yet.', array( 'status' => 400 ) );

	$cur = livecrafts_el_read_raw( $t['post'], $t['id'], $t['path'] );
	if ( ! $cur['found'] ) return new WP_Error( 'livecrafts_no_element', 'That Elementor element was not found on this page.', array( 'status' => 404 ) );
	if ( $cur['value'] === null && $t['path'] !== 'background_image' ) return new WP_Error( 'livecrafts_no_setting', 'This element has no "' . $t['path'] . '" setting.', array( 'status' => 404 ) );

	$clean = livecrafts_el_validate( $t['path'], $req->get_param( 'value' ), is_string( $cur['value'] ) ? $cur['value'] : '' );
	if ( is_wp_error( $clean ) ) return $clean;

	$kind_image = ( $paths[ $t['path'] ] === 'image' );
	$wanted     = $kind_image ? array( 'id' => $clean['id'], 'url' => $clean['url'] ) : $clean;
	if ( livecrafts_el_values_equal( $t['path'], $cur['value'], $wanted ) ) {
		return array( 'ok' => true, 'unchanged' => true, 'value' => $kind_image ? $clean['id'] : $clean, 'url' => $kind_image ? $clean['url'] : '', 'verified' => array( 'database' => true ) );
	}

	$res = livecrafts_el_write( $t['post'], $t['id'], $t['path'], $clean );
	if ( is_wp_error( $res ) ) return $res;

	// READ BACK from the database; roll back if it did not stick.
	$after = livecrafts_el_read_raw( $t['post'], $t['id'], $t['path'] );
	if ( ! $after['found'] || ! livecrafts_el_values_equal( $t['path'], $after['value'], $wanted ) ) {
		livecrafts_el_write( $t['post'], $t['id'], $t['path'], livecrafts_el_before_to_value( $t['path'], $res['before'] ) );
		return new WP_Error( 'livecrafts_verify_failed', 'NOT SAVED: Elementor accepted the write but the stored value did not change. Nothing was changed.', array( 'status' => 500 ) );
	}

	livecrafts_log_add(
		'p' . $t['post'], $raw,
		array( 'text' => is_scalar( $res['before'] ) ? (string) $res['before'] : wp_json_encode( $res['before'] ) ),
		array( 'text' => $kind_image ? (string) $clean['url'] : (string) $clean ),
		array( 'type' => 'el', 'post' => $t['post'], 'el_id' => $t['id'], 'path' => $t['path'], 'raw_before' => $res['before'], 'label' => $t['id'] . ' · ' . $t['path'] )
	);
	return array( 'ok' => true, 'value' => $kind_image ? $clean['id'] : $clean, 'url' => $kind_image ? $clean['url'] : '', 'field' => $t['path'], 'verified' => array( 'database' => true ) );
}

/** Turn a logged "before" value back into something livecrafts_el_write() accepts. */
function livecrafts_el_before_to_value( $path, $before ) {
	$paths = livecrafts_el_paths();
	$kind  = isset( $paths[ $path ] ) ? $paths[ $path ] : 'text';
	if ( $kind === 'image' ) {
		if ( is_array( $before ) && ! empty( $before['id'] ) ) {
			return array( 'id' => (int) $before['id'], 'url' => isset( $before['url'] ) ? (string) $before['url'] : '' );
		}
		return array(); // there was no image before: remove the setting
	}
	return $before === null ? '' : (string) $before;
}

/** Undo support: restore a logged Elementor value. */
function livecrafts_el_restore( $entry ) {
	$before = isset( $entry['raw_before'] ) ? $entry['raw_before'] : null;
	return livecrafts_el_write( (int) $entry['post'], $entry['el_id'], $entry['path'], livecrafts_el_before_to_value( $entry['path'], $before ) );
}

/** GET /debug/target for an el: id. */
function livecrafts_el_debug_target( $t, $raw ) {
	$cur = livecrafts_el_read_raw( $t['post'], $t['id'], $t['path'] );
	$out = array(
		'target'      => $raw,
		'kind'        => 'elementor',
		'found'       => $cur['found'],
		'widget'      => $cur['type'],
		'setting'     => $t['path'],
		'stored_raw'  => $cur['value'],
		'where_stored'=> 'wp_postmeta, meta_key "_elementor_data" (one JSON tree for the whole page) of post ' . $t['post'],
		'post'        => array( 'id' => $t['post'], 'type' => get_post_type( $t['post'] ), 'title' => get_the_title( $t['post'] ), 'edit_link' => get_edit_post_link( $t['post'], 'raw' ) ),
		'recent_changes' => array(),
	);
	if ( is_array( $cur['value'] ) && ! empty( $cur['value']['id'] ) ) $out['attachment'] = livecrafts_attachment_info( $cur['value']['id'] );
	foreach ( array_reverse( (array) get_option( LIVECRAFTS_LOG, array() ) ) as $e ) {
		if ( isset( $e['selector'] ) && $e['selector'] === $raw ) $out['recent_changes'][] = array( 'when' => gmdate( 'c', $e['ts'] ), 'user' => $e['user'], 'before' => $e['before'], 'after' => $e['after'] );
		if ( count( $out['recent_changes'] ) >= 5 ) break;
	}
	return $out;
}

/** GET /debug/elementor?post=ID -> every editable Elementor setting on that page. */
function livecrafts_rest_debug_elementor( WP_REST_Request $req ) {
	$post = (int) $req->get_param( 'post' );
	if ( ! $post || ! current_user_can( 'edit_post', $post ) ) return new WP_Error( 'livecrafts_forbidden', 'Unknown post or not allowed.', array( 'status' => 403 ) );
	$items = livecrafts_el_scan( $post );
	return array( 'post' => $post, 'elementor_active' => livecrafts_el_active(), 'built_with_elementor' => (bool) get_post_meta( $post, '_elementor_data', true ), 'count' => count( $items ), 'items' => $items );
}
