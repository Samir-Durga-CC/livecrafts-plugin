<?php
/**
 * Read-only endpoints that tell the Livecrafts backend what a site is built with and what it can reuse:
 *
 *   GET /livecrafts/v1/site-profile            theme, Elementor (version, containers, free/Pro widgets, global colours/fonts),
 *                                              ACF (flexible-content layouts), block patterns, installed form plugins' forms
 *   GET /livecrafts/v1/components?terms=a,b    things the site already has that match: Elementor templates and page sections,
 *                                              block patterns (theme/plugin/synced), ACF flexible layouts, Elementor widgets
 *   GET /livecrafts/v1/components/source?id=   the source of one of them, cleaned so el.insert / block.insert accepts it
 *
 * Nothing here changes the site.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'rest_api_init', function () {
	foreach ( array( 'site-profile' => 'livecrafts_rest_site_profile', 'components' => 'livecrafts_rest_components', 'components/source' => 'livecrafts_rest_component_source' ) as $path => $cb ) {
		register_rest_route( 'livecrafts/v1', '/' . $path, array( 'methods' => 'GET', 'callback' => $cb, 'permission_callback' => 'livecrafts_rest_permission' ) );
	}
} );

/** Run a scan; a failure inside a third-party API must never take the endpoint down. */
function livecrafts_comp_try( $fn, $fallback = array() ) {
	try { return $fn(); } catch ( \Throwable $e ) { return $fallback; }
}

/* ------------------------------------------------------------------ site profile */

function livecrafts_rest_site_profile() {
	$theme  = wp_get_theme();
	$parent = $theme->parent();
	return array(
		'ok'       => true,
		'version'  => LIVECRAFTS_VERSION,
		'wp'       => get_bloginfo( 'version' ),
		'theme'    => array(
			'name' => $theme->get( 'Name' ), 'slug' => get_stylesheet(), 'version' => $theme->get( 'Version' ),
			'parent' => $parent ? $parent->get( 'Name' ) : '', 'block_theme' => function_exists( 'wp_is_block_theme' ) ? wp_is_block_theme() : false,
		),
		'builders' => array(
			'elementor' => livecrafts_comp_try( 'livecrafts_profile_elementor', array( 'active' => false ) ),
			'acf'       => livecrafts_comp_try( 'livecrafts_profile_acf', array( 'active' => false ) ),
			'blocks'    => array( 'patterns' => count( livecrafts_comp_try( 'livecrafts_comp_registered_patterns' ) ) ),
		),
		'forms'    => livecrafts_comp_try( 'livecrafts_profile_forms' ),
	);
}

function livecrafts_profile_elementor() {
	if ( ! livecrafts_el_active() ) return array( 'active' => false );
	$plugin = \Elementor\Plugin::$instance;
	$out    = array(
		'active' => true, 'version' => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '', 'pro' => defined( 'ELEMENTOR_PRO_VERSION' ),
		'containers' => false, 'widgets' => array(), 'colors' => array(), 'fonts' => array(),
	);
	$out['containers'] = (bool) livecrafts_comp_try( function () use ( $plugin ) { return $plugin->experiments->is_feature_active( 'container' ); }, false );
	$out['widgets']    = livecrafts_comp_try( function () use ( $plugin ) { return array_values( array_keys( (array) $plugin->widgets_manager->get_widget_types() ) ); } );
	$out['colors']     = livecrafts_comp_try( function () use ( $plugin ) {
		$rows = array();
		foreach ( array( 'system_colors', 'custom_colors' ) as $set ) {
			foreach ( (array) $plugin->kits_manager->get_current_settings( $set ) as $c ) {
				if ( ! empty( $c['_id'] ) && ! empty( $c['color'] ) ) $rows[] = array( 'id' => $c['_id'], 'title' => isset( $c['title'] ) ? $c['title'] : $c['_id'], 'value' => $c['color'] );
			}
		}
		return $rows;
	} );
	$out['fonts']      = livecrafts_comp_try( function () use ( $plugin ) {
		$rows = array();
		foreach ( (array) $plugin->kits_manager->get_current_settings( 'system_typography' ) as $f ) {
			if ( ! empty( $f['_id'] ) ) $rows[] = array( 'id' => $f['_id'], 'title' => isset( $f['title'] ) ? $f['title'] : $f['_id'], 'family' => isset( $f['typography_font_family'] ) ? $f['typography_font_family'] : '' );
		}
		return $rows;
	} );
	return $out;
}

/** Flexible-content layouts of every ACF field group: field, layout, sub fields. */
function livecrafts_acf_layouts() {
	$out = array();
	if ( ! livecrafts_acf_active() ) return $out;
	$walk = function ( $fields, $group ) use ( &$walk, &$out ) {
		foreach ( (array) $fields as $f ) {
			if ( empty( $f['type'] ) ) continue;
			if ( $f['type'] === 'flexible_content' ) {
				foreach ( (array) ( isset( $f['layouts'] ) ? $f['layouts'] : array() ) as $l ) {
					$subs = array();
					foreach ( (array) ( isset( $l['sub_fields'] ) ? $l['sub_fields'] : array() ) as $s ) $subs[] = array( 'name' => isset( $s['name'] ) ? $s['name'] : '', 'type' => isset( $s['type'] ) ? $s['type'] : '', 'label' => isset( $s['label'] ) ? $s['label'] : '' );
					$out[] = array( 'field' => $f['name'], 'field_key' => $f['key'], 'layout' => $l['name'], 'label' => $l['label'], 'group' => $group, 'sub_fields' => $subs );
				}
			}
			if ( ! empty( $f['sub_fields'] ) ) $walk( $f['sub_fields'], $group );
		}
	};
	foreach ( (array) acf_get_field_groups() as $g ) $walk( acf_get_fields( $g ), isset( $g['title'] ) ? $g['title'] : '' );
	return $out;
}

function livecrafts_profile_acf() {
	if ( ! livecrafts_acf_active() ) return array( 'active' => false );
	return array( 'active' => true, 'pro' => defined( 'ACF_PRO' ) || class_exists( 'acf_pro' ), 'version' => defined( 'ACF_VERSION' ) ? ACF_VERSION : '', 'flexible' => livecrafts_comp_try( 'livecrafts_acf_layouts' ) );
}

/** Forms of the common form plugins with the shortcode that shows them (others: ask the person for the shortcode). */
function livecrafts_profile_forms() {
	$forms = array();
	$list  = array( 'wpcf7_contact_form' => array( 'Contact Form 7', '[contact-form-7 id="%d"]' ), 'wpforms' => array( 'WPForms', '[wpforms id="%d"]' ) );
	foreach ( $list as $type => $info ) {
		if ( ! post_type_exists( $type ) ) continue;
		foreach ( get_posts( array( 'post_type' => $type, 'post_status' => 'publish', 'numberposts' => 20 ) ) as $p ) {
			$forms[] = array( 'plugin' => $info[0], 'id' => $p->ID, 'title' => $p->post_title, 'shortcode' => sprintf( $info[1], $p->ID ) );
		}
	}
	return $forms;
}

/* ------------------------------------------------------------------ components the site already has */

function livecrafts_comp_registered_patterns() {
	return class_exists( 'WP_Block_Patterns_Registry' ) ? (array) WP_Block_Patterns_Registry::get_instance()->get_all_registered() : array();
}

/** Which of the terms appear in the text (case-insensitive). */
function livecrafts_comp_match( $text, array $terms ) {
	$text = strtolower( (string) $text );
	$hit  = array();
	foreach ( $terms as $t ) if ( $t !== '' && strpos( $text, $t ) !== false ) $hit[] = $t;
	return $hit;
}

function livecrafts_comp_first_text( array $node ) {
	$s = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : array();
	foreach ( array( 'title', 'editor', 'text', 'title_text', 'testimonial_content' ) as $k ) {
		if ( ! empty( $s[ $k ] ) && is_string( $s[ $k ] ) ) return mb_substr( trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $s[ $k ] ) ) ), 0, 70 );
	}
	foreach ( isset( $node['elements'] ) && is_array( $node['elements'] ) ? $node['elements'] : array() as $k ) {
		$t = is_array( $k ) ? livecrafts_comp_first_text( $k ) : '';
		if ( $t !== '' ) return $t;
	}
	return '';
}

function livecrafts_rest_components( WP_REST_Request $req ) {
	$terms = array_slice( array_unique( array_filter( array_map( 'trim', explode( ',', strtolower( (string) $req->get_param( 'terms' ) ) ) ) ) ), 0, 30 );
	if ( ! $terms ) return new WP_Error( 'livecrafts_bad_value', 'Pass terms=word,word (the words to look for).', array( 'status' => 400 ) );
	$items = array();

	// Elementor: top-level sections / containers of templates and pages.
	if ( livecrafts_el_active() ) {
		$posts = get_posts( array( 'post_type' => array( 'elementor_library', 'page', 'post' ), 'post_status' => array( 'publish', 'draft', 'private' ), 'meta_key' => '_elementor_data', 'numberposts' => 80, 'orderby' => 'modified' ) );
		foreach ( $posts as $p ) {
			if ( ! current_user_can( 'edit_post', $p->ID ) ) continue;
			if ( $p->post_type === 'elementor_library' && ! in_array( get_post_meta( $p->ID, '_elementor_template_type', true ), array( 'section', 'container', 'page' ), true ) ) continue;
			$raw  = get_post_meta( $p->ID, '_elementor_data', true );
			$tree = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
			if ( ! is_array( $tree ) ) continue;
			foreach ( $tree as $node ) {
				if ( ! is_array( $node ) || empty( $node['id'] ) ) continue;
				$json = wp_json_encode( $node );
				$hit  = livecrafts_comp_match( ( $p->post_type === 'elementor_library' ? $p->post_title . ' ' : '' ) . ( strlen( $json ) > 300000 ? substr( $json, 0, 300000 ) : $json ), $terms );
				if ( ! $hit ) continue;
				$lib = $p->post_type === 'elementor_library';
				$items[] = array(
					'id' => 'el-section:' . $p->ID . ':' . $node['id'], 'kind' => $lib ? 'elementor-template' : 'elementor-section', 'builder' => 'elementor',
					'name' => livecrafts_comp_first_text( $node ) ?: $p->post_title, 'where' => ( $lib ? 'Elementor template “' : ( $p->post_type === 'page' ? 'Page “' : 'Post “' ) ) . $p->post_title . '”',
					'post' => $p->ID, 'matched' => $hit, 'score' => count( $hit ) * 3 + ( $lib ? 2 : 0 ),
				);
			}
		}
		$widgets = livecrafts_comp_try( function () { return \Elementor\Plugin::$instance->widgets_manager->get_widget_types(); } );
		foreach ( (array) $widgets as $name => $w ) {
			$kw  = livecrafts_comp_try( function () use ( $w ) { return implode( ' ', (array) $w->get_keywords() ); }, '' );
			$hit = livecrafts_comp_match( $name . ' ' . $w->get_title() . ' ' . $kw, $terms );
			if ( $hit ) $items[] = array( 'id' => 'el-widget:' . $name, 'kind' => 'elementor-widget', 'builder' => 'elementor', 'name' => $w->get_title(), 'where' => 'Elementor widget' . ( strpos( $name, 'pro' ) !== false ? ' (may need Pro)' : '' ), 'matched' => $hit, 'score' => count( $hit ) * 2 );
		}
	}

	// Block patterns (theme / plugin) and synced patterns.
	foreach ( livecrafts_comp_try( 'livecrafts_comp_registered_patterns' ) as $pat ) {
		$hit = livecrafts_comp_match( $pat['name'] . ' ' . ( isset( $pat['title'] ) ? $pat['title'] : '' ) . ' ' . ( isset( $pat['description'] ) ? $pat['description'] : '' ) . ' ' . implode( ' ', (array) ( isset( $pat['keywords'] ) ? $pat['keywords'] : array() ) ) . ' ' . implode( ' ', (array) ( isset( $pat['categories'] ) ? $pat['categories'] : array() ) ), $terms );
		if ( $hit ) $items[] = array( 'id' => 'pattern:' . $pat['name'], 'kind' => 'block-pattern', 'builder' => 'blocks', 'name' => isset( $pat['title'] ) ? $pat['title'] : $pat['name'], 'where' => 'Block pattern', 'matched' => $hit, 'score' => count( $hit ) * 3 );
	}
	foreach ( get_posts( array( 'post_type' => 'wp_block', 'post_status' => 'publish', 'numberposts' => 50 ) ) as $p ) {
		$hit = livecrafts_comp_match( $p->post_title . ' ' . $p->post_content, $terms );
		if ( $hit ) $items[] = array( 'id' => 'synced:' . $p->ID, 'kind' => 'synced-pattern', 'builder' => 'blocks', 'name' => $p->post_title, 'where' => 'Synced pattern', 'matched' => $hit, 'score' => count( $hit ) * 3 );
	}

	// ACF flexible-content layouts ("modules").
	foreach ( livecrafts_comp_try( 'livecrafts_acf_layouts' ) as $l ) {
		$hit = livecrafts_comp_match( $l['layout'] . ' ' . $l['label'] . ' ' . $l['field'] . ' ' . implode( ' ', wp_list_pluck( $l['sub_fields'], 'name' ) ), $terms );
		if ( $hit ) $items[] = array( 'id' => 'acf-layout:' . $l['field_key'] . ':' . $l['layout'], 'kind' => 'acf-layout', 'builder' => 'acf', 'name' => $l['label'], 'where' => 'ACF layout in “' . $l['group'] . '” (field ' . $l['field'] . ')', 'matched' => $hit, 'score' => count( $hit ) * 3, 'sub_fields' => $l['sub_fields'] );
	}

	usort( $items, function ( $a, $b ) { return $b['score'] - $a['score']; } );
	return array( 'ok' => true, 'terms' => $terms, 'items' => array_slice( $items, 0, 40 ) );
}

/** An Elementor node cleaned for el.insert: only settings Livecrafts can validate; unknown widgets dropped. */
function livecrafts_comp_el_prepare( array $node, array &$dropped, $depth = 0 ) {
	if ( $depth > 8 ) return null;
	$el  = isset( $node['elType'] ) ? (string) $node['elType'] : '';
	$out = array( 'elType' => $el, 'settings' => array(), 'elements' => array() );
	if ( $el === 'widget' ) {
		$wt = isset( $node['widgetType'] ) ? (string) $node['widgetType'] : '';
		if ( ! \Elementor\Plugin::$instance->widgets_manager->get_widget_types( $wt ) ) { $dropped[] = 'widget ' . $wt . ' is not available on this site'; return null; }
		$out['widgetType'] = $wt;
	}
	$controls = livecrafts_el_controls( $out );
	foreach ( isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : array() as $name => $value ) {
		$ok = $name !== '__globals__' && isset( $controls[ $name ] ) && in_array( $controls[ $name ]['type'], livecrafts_el_control_types(), true ) && ! is_wp_error( livecrafts_el_value( $controls[ $name ], $value, null, $name ) );
		if ( $ok ) $out['settings'][ $name ] = $value; else $dropped[] = ( $el === 'widget' ? $out['widgetType'] : $el ) . ' › ' . $name;
	}
	foreach ( isset( $node['elements'] ) && is_array( $node['elements'] ) ? $node['elements'] : array() as $kid ) {
		$k = is_array( $kid ) ? livecrafts_comp_el_prepare( $kid, $dropped, $depth + 1 ) : null;
		if ( $k ) $out['elements'][] = $k;
	}
	return $out;
}

function livecrafts_rest_component_source( WP_REST_Request $req ) {
	$id = (string) $req->get_param( 'id' );
	try {
		if ( preg_match( '/^el-section:(\d+):([A-Za-z0-9]{3,16})$/', $id, $m ) ) {
			if ( ! livecrafts_el_active() || ! current_user_can( 'edit_post', (int) $m[1] ) ) return new WP_Error( 'livecrafts_forbidden', 'Not available.', array( 'status' => 403 ) );
			$raw  = get_post_meta( (int) $m[1], '_elementor_data', true );
			$tree = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
			$node = is_array( $tree ) ? livecrafts_el_node( $tree, $m[2] ) : null;
			if ( ! $node ) return new WP_Error( 'livecrafts_no_element', 'That section no longer exists.', array( 'status' => 404 ) );
			$dropped = array();
			$clean   = livecrafts_comp_el_prepare( $node, $dropped );
			return array( 'ok' => true, 'builder' => 'elementor', 'kind' => 'el.insert', 'node' => $clean, 'dropped' => array_slice( array_unique( $dropped ), 0, 40 ), 'note' => 'Settings Livecrafts cannot validate (global references, custom CSS, repeaters) were dropped: restyle with el.setting or the text/links only.' );
		}
		if ( preg_match( '/^el-widget:([a-z0-9_-]+)$/', $id, $m ) && livecrafts_el_active() ) {
			return array( 'ok' => true, 'builder' => 'elementor', 'kind' => 'el.insert', 'node' => array( 'elType' => 'container', 'settings' => array( 'content_width' => 'full', 'flex_direction' => 'column' ), 'elements' => array( array( 'elType' => 'widget', 'widgetType' => $m[1], 'settings' => array(), 'elements' => array() ) ) ), 'dropped' => array(), 'note' => 'An empty widget in a container: fill it with el.setting.' );
		}
		if ( preg_match( '/^pattern:(.+)$/', $id, $m ) ) {
			$pat = class_exists( 'WP_Block_Patterns_Registry' ) ? WP_Block_Patterns_Registry::get_instance()->get_registered( $m[1] ) : null;
			if ( ! $pat ) return new WP_Error( 'livecrafts_no_pattern', 'That pattern no longer exists.', array( 'status' => 404 ) );
			return array( 'ok' => true, 'builder' => 'blocks', 'kind' => 'block.insert', 'markup' => (string) $pat['content'] );
		}
		if ( preg_match( '/^synced:(\d+)$/', $id, $m ) ) {
			$p = get_post( (int) $m[1] );
			if ( ! $p || $p->post_type !== 'wp_block' || ! current_user_can( 'edit_post', $p->ID ) ) return new WP_Error( 'livecrafts_no_pattern', 'That pattern no longer exists.', array( 'status' => 404 ) );
			return array( 'ok' => true, 'builder' => 'blocks', 'kind' => 'block.insert', 'markup' => (string) $p->post_content, 'note' => 'A copy of the synced pattern, not a reference to it.' );
		}
		if ( preg_match( '/^acf-layout:(field_[A-Za-z0-9]+):([A-Za-z0-9_-]+)$/', $id, $m ) ) {
			foreach ( livecrafts_acf_layouts() as $l ) if ( $l['field_key'] === $m[1] && $l['layout'] === $m[2] ) return array( 'ok' => true, 'builder' => 'acf', 'kind' => 'acf.rows', 'layout' => $l, 'note' => 'Add a row with make_change kind acf.rows {"op":"add","layout":"' . $l['layout'] . '"} on the field "' . $l['field'] . '", then fill each sub field with acf.value (<field>_<row index>_<sub field>).' );
		}
	} catch ( \Throwable $e ) {
		return new WP_Error( 'livecrafts_component_error', 'Could not read that component: ' . $e->getMessage(), array( 'status' => 500 ) );
	}
	return new WP_Error( 'livecrafts_no_component', 'Unknown component id.', array( 'status' => 404 ) );
}
