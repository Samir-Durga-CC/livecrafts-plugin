<?php
/**
 * Template library: 25 ready, responsive sections (hero, pricing, FAQ, header, footer ...) that the assistant fills with
 * content instead of writing markup. The library lives in templates-lib/ (shortcodes [ai_hero ...], PHP ai_component()).
 *
 *   GET /livecrafts/v1/templates            catalogue: every template with its attributes, list-item fields, example, and
 *                                           where it is used (pages/posts that contain it + URLs where it was rendered)
 *   ?lc_templates=1 on any page             editors only: every template section is outlined and labelled, so you can SEE
 *                                           which parts of the page come from a template
 *   Settings > Livecrafts > Templates       the same usage table
 *
 * Nothing here changes content. A site-wide design fit (colours, container width, section spacing, radius) is stored in the
 * option "livecrafts_template_fit" and applied as CSS variables, or per section with the fit attributes (max, py, radius ...).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// The standalone "AI Components" plugin wins when it is active (it defines the constant first).
if ( ! defined( 'AI_COMPONENTS_DIR' ) ) {
	require_once LIVECRAFTS_DIR . 'templates-lib/ai-components.php';
}

function livecrafts_templates_active() {
	return defined( 'AI_COMPONENTS_DIR' ) && function_exists( 'ai_component' ) && function_exists( 'ai_components_registry' ) && function_exists( 'ai_fit_style' ); // an older standalone copy lacks the fit helpers
}

/** Site-wide fit: the values the assistant measured on the site (stored sanitized; see ai_fit_keys for the allowed names). */
function livecrafts_template_fit() {
	$saved = get_option( 'livecrafts_template_fit', array() );
	return is_array( $saved ) ? $saved : array();
}

add_action( 'init', function () {
	if ( ! livecrafts_templates_active() ) return;
	$css = ai_fit_style( livecrafts_template_fit() );
	if ( $css && wp_style_is( 'ai-components', 'registered' ) ) {
		wp_add_inline_style( 'ai-components', ':root{' . $css . '}' );
	}
}, 20 );

/* ------------------------------------------------------------------ catalogue */

/** Read the docblock of one template file: title, params line, shortcode example. */
function livecrafts_template_doc( $file ) {
	$head = (string) file_get_contents( $file, false, null, 0, 4000 );
	$doc  = array( 'title' => '', 'params' => '', 'shortcode' => '', 'items' => '' );
	if ( preg_match( '/Component:\s*(.+)/', $head, $m ) ) $doc['title'] = trim( $m[1] );
	if ( preg_match( '/Params:\s*(.+)/', $head, $m ) ) $doc['params'] = trim( $m[1] );
	if ( preg_match( '/Shortcode:\s*(.+)/', $head, $m ) ) $doc['shortcode'] = trim( $m[1] );
	if ( preg_match( '/items\s*\[([^\]]*)\]/', $doc['params'], $m ) ) $doc['items'] = trim( $m[1] );
	return $doc;
}

function livecrafts_templates_catalogue() {
	if ( ! livecrafts_templates_active() ) return array();
	$out = array();
	foreach ( ai_components_registry() as $name => $def ) {
		$file = AI_COMPONENTS_DIR . 'templates/' . str_replace( '_', '-', $name ) . '.php';
		$doc  = file_exists( $file ) ? livecrafts_template_doc( $file ) : livecrafts_template_doc( '' );
		$out[ $name ] = array(
			'id'         => $name,
			'shortcode'  => 'ai_' . $name,
			'title'      => $doc['title'],
			'params'     => $doc['params'],
			'example'    => $doc['shortcode'],
			'attributes' => array_keys( $def['defaults'] ),
			'defaults'   => $def['defaults'],
			'list'       => (bool) $def['items'],
			'item_fields' => $doc['items'],
		);
	}
	return $out;
}

/* ------------------------------------------------------------------ usage */

/** Count [ai_xxx] shortcodes in a string. */
function livecrafts_templates_count( $text, array &$into ) {
	if ( false === strpos( $text, '[ai_' ) ) return;
	if ( preg_match_all( '/\[ai_([a-z_]+)/', $text, $m ) ) {
		foreach ( $m[1] as $name ) {
			if ( 'item' === $name ) continue;
			$into[ $name ] = ( isset( $into[ $name ] ) ? $into[ $name ] : 0 ) + 1;
		}
	}
}

/** Where the templates are in the content: template => [ { post, title, url, status, count, in } ]. */
function livecrafts_templates_usage() {
	global $wpdb;
	$by = array();
	$add = function ( $post_id, $counts, $where ) use ( &$by ) {
		$post = get_post( $post_id );
		if ( ! $post ) return;
		$parent = in_array( $post->post_type, array( 'revision' ), true ) && $post->post_parent ? get_post( $post->post_parent ) : $post;
		if ( ! $parent || in_array( $parent->post_status, array( 'trash', 'auto-draft' ), true ) ) return;
		foreach ( $counts as $name => $n ) {
			$key = $parent->ID;
			if ( ! isset( $by[ $name ][ $key ] ) ) {
				$by[ $name ][ $key ] = array( 'post' => (int) $parent->ID, 'title' => get_the_title( $parent ), 'url' => get_permalink( $parent ), 'status' => $parent->post_status, 'count' => 0, 'in' => array() );
			}
			$by[ $name ][ $key ]['count'] = max( $by[ $name ][ $key ]['count'], $n );
			if ( ! in_array( $where, $by[ $name ][ $key ]['in'], true ) ) $by[ $name ][ $key ]['in'][] = $where;
		}
	};
	// Post content (classic editor, blocks, Custom HTML / shortcode blocks) incl. the autosaves Livecrafts uses for drafts.
	$rows = $wpdb->get_results( "SELECT ID, post_type, post_status, post_content FROM {$wpdb->posts} WHERE post_content LIKE '%[ai\\_%' AND post_status NOT IN ('trash','auto-draft') AND post_type NOT IN ('nav_menu_item') LIMIT 500" ); // phpcs:ignore WordPress.DB
	foreach ( (array) $rows as $r ) {
		$c = array();
		livecrafts_templates_count( (string) $r->post_content, $c );
		$add( (int) $r->ID, $c, 'revision' === $r->post_type ? 'draft' : ( 'publish' === $r->post_status ? 'live' : 'draft' ) );
	}
	// Elementor stores a page as JSON in post meta.
	$rows = $wpdb->get_results( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_data' AND meta_value LIKE '%[ai\\_%' LIMIT 500" ); // phpcs:ignore WordPress.DB
	foreach ( (array) $rows as $r ) {
		$c = array();
		livecrafts_templates_count( (string) $r->meta_value, $c );
		$post = get_post( (int) $r->post_id );
		$add( (int) $r->post_id, $c, $post && ( 'revision' === $post->post_type || 'publish' !== $post->post_status ) ? 'draft' : 'live' );
	}
	$out = array();
	foreach ( $by as $name => $list ) $out[ $name ] = array_values( $list );
	return $out;
}

/**
 * Templates rendered from PHP (theme code, ACF loops) cannot be found in content, so the front end notes the first
 * time a template shows on a URL (one write per template and URL per day; at most 20 URLs per template).
 */
add_action( 'ai_component_rendered', function ( $name ) {
	if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) return;
	$path = wp_parse_url( add_query_arg( array() ), PHP_URL_PATH );
	$path = $path ? substr( (string) $path, 0, 200 ) : '/';
	$seen = get_option( 'livecrafts_template_seen', array() );
	$seen = is_array( $seen ) ? $seen : array();
	$name = str_replace( '-', '_', sanitize_key( $name ) );
	if ( isset( $seen[ $name ][ $path ] ) && time() - (int) $seen[ $name ][ $path ] < DAY_IN_SECONDS ) return;
	$seen[ $name ][ $path ] = time();
	if ( count( $seen[ $name ] ) > 20 ) {
		asort( $seen[ $name ] );
		$seen[ $name ] = array_slice( $seen[ $name ], -20, null, true );
	}
	update_option( 'livecrafts_template_seen', $seen, false );
} );

function livecrafts_templates_seen() {
	$seen = get_option( 'livecrafts_template_seen', array() );
	$out  = array();
	foreach ( is_array( $seen ) ? $seen : array() as $name => $paths ) {
		foreach ( (array) $paths as $path => $ts ) $out[ $name ][] = array( 'path' => $path, 'last' => gmdate( 'c', (int) $ts ) );
	}
	return $out;
}

/** Short summary for site_profile. */
function livecrafts_templates_summary() {
	if ( ! livecrafts_templates_active() ) return array( 'active' => false );
	$usage = livecrafts_templates_usage();
	return array(
		'active'     => true,
		'version'    => defined( 'AI_COMPONENTS_VERSION' ) ? AI_COMPONENTS_VERSION : '',
		'components' => array_keys( ai_components_registry() ),
		'fit'        => livecrafts_template_fit(),
		'inUse'      => array_map( function ( $rows ) { return count( $rows ); }, $usage ),
	);
}

/* ------------------------------------------------------------------ REST */

add_action( 'rest_api_init', function () {
	register_rest_route( 'livecrafts/v1', '/templates', array(
		array( 'methods' => 'GET', 'callback' => 'livecrafts_rest_templates', 'permission_callback' => 'livecrafts_rest_permission' ),
	) );
	register_rest_route( 'livecrafts/v1', '/templates/fit', array(
		array( 'methods' => 'POST', 'callback' => 'livecrafts_rest_templates_fit', 'permission_callback' => 'livecrafts_rest_deploy_permission' ),
	) );
} );

function livecrafts_rest_templates() {
	if ( ! livecrafts_templates_active() ) return array( 'ok' => true, 'active' => false, 'components' => array() );
	$usage = livecrafts_templates_usage();
	$seen  = livecrafts_templates_seen();
	$cat   = livecrafts_templates_catalogue();
	foreach ( $cat as $name => &$c ) {
		$c['usedOn']  = isset( $usage[ $name ] ) ? $usage[ $name ] : array();
		$c['renderedAt'] = isset( $seen[ $name ] ) ? $seen[ $name ] : array();
	}
	unset( $c );
	return array(
		'ok' => true, 'active' => true, 'version' => AI_COMPONENTS_VERSION,
		'fitKeys' => array_keys( ai_fit_keys() ), 'fit' => livecrafts_template_fit(),
		'howToCheck' => 'Open any page as an editor with ?lc_templates=1: every template section is outlined and labelled.',
		'components' => array_values( $cat ),
	);
}

/** Save the site-wide fit (CSS variables). Deploy permission: it changes how the live site looks at once. */
function livecrafts_rest_templates_fit( WP_REST_Request $req ) {
	if ( ! livecrafts_templates_active() ) return new WP_Error( 'livecrafts_templates', 'The template library is not active.', array( 'status' => 400 ) );
	$in   = (array) $req->get_json_params();
	$fit  = isset( $in['fit'] ) && is_array( $in['fit'] ) ? $in['fit'] : array();
	$keep = array();
	foreach ( ai_fit_keys() as $key => $def ) {
		if ( ! empty( $fit[ $key ] ) && '' !== ai_css_value( $fit[ $key ], $def[1] ) ) $keep[ $key ] = sanitize_text_field( $fit[ $key ] );
	}
	update_option( 'livecrafts_template_fit', $keep, false );
	return array( 'ok' => true, 'fit' => $keep );
}

/* ------------------------------------------------------------------ see it: ?lc_templates=1 */

add_action( 'wp_head', function () {
	if ( ! isset( $_GET['lc_templates'] ) || ! is_user_logged_in() || ! livecrafts_can_edit() ) return; // phpcs:ignore WordPress.Security.NonceVerification -- display only
	echo '<style id="lc-template-outline">'
		. '.ai-t{position:relative;outline:2px dashed #7c3aed;outline-offset:-2px}'
		. '.ai-t::before{content:"Template: " attr(data-lc-template);position:absolute;z-index:99999;top:0;left:0;background:#7c3aed;color:#fff;font:600 11px/1 system-ui,sans-serif;padding:4px 8px;border-radius:0 0 6px 0;pointer-events:none}'
		. '#lc-template-panel{position:fixed;z-index:99999;right:12px;bottom:12px;max-width:280px;background:#111827;color:#fff;font:13px/1.4 system-ui,sans-serif;padding:10px 12px;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.3)}'
		. '#lc-template-panel b{color:#c4b5fd}'
		. '</style>';
} );

add_action( 'wp_footer', function () {
	if ( ! isset( $_GET['lc_templates'] ) || ! is_user_logged_in() || ! livecrafts_can_edit() ) return; // phpcs:ignore WordPress.Security.NonceVerification -- display only
	?>
	<div id="lc-template-panel" role="status"></div>
	<script>
	(function () {
		var found = document.querySelectorAll('[data-lc-template]'), names = {}, panel = document.getElementById('lc-template-panel');
		for (var i = 0; i < found.length; i++) { var n = found[i].getAttribute('data-lc-template'); names[n] = (names[n] || 0) + 1; }
		var list = Object.keys(names).map(function (n) { return n + (names[n] > 1 ? ' ×' + names[n] : ''); });
		panel.innerHTML = found.length
			? '<b>' + found.length + ' template section' + (found.length > 1 ? 's' : '') + ' on this page</b><br>' + list.join(', ')
			: '<b>No template sections on this page.</b><br>Everything here was built without the template library.';
	})();
	</script>
	<?php
} );

/* ------------------------------------------------------------------ Settings > Livecrafts > Templates */

/** Called by the admin page: the usage table. */
function livecrafts_templates_admin_section() {
	echo '<h2 style="margin-top:32px">Templates</h2>';
	if ( ! livecrafts_templates_active() ) {
		echo '<p>The template library is not loaded.</p>';
		return;
	}
	$usage = livecrafts_templates_usage();
	$seen  = livecrafts_templates_seen();
	echo '<p>The assistant builds sections from these ready templates and only changes their content. Open any page as an editor with <code>?lc_templates=1</code> to see which parts of it come from a template.</p>';
	echo '<table class="widefat striped"><thead><tr><th>Template</th><th>Shortcode</th><th>Used on</th></tr></thead><tbody>';
	foreach ( livecrafts_templates_catalogue() as $name => $c ) {
		$where = array();
		foreach ( isset( $usage[ $name ] ) ? $usage[ $name ] : array() as $u ) {
			$where[] = '<a href="' . esc_url( add_query_arg( 'lc_templates', '1', $u['url'] ) ) . '">' . esc_html( $u['title'] ?: '(no title)' ) . '</a> <small>(' . esc_html( implode( ', ', $u['in'] ) ) . ( $u['count'] > 1 ? ', ×' . (int) $u['count'] : '' ) . ')</small>';
		}
		foreach ( isset( $seen[ $name ] ) ? $seen[ $name ] : array() as $s ) $where[] = '<code>' . esc_html( $s['path'] ) . '</code> <small>(rendered by theme code)</small>';
		echo '<tr><td><strong>' . esc_html( $name ) . '</strong></td><td><code>[ai_' . esc_html( $name ) . ']</code></td><td>' . ( $where ? implode( '<br>', $where ) : '<span style="color:#888">not used</span>' ) . '</td></tr>';
	}
	echo '</tbody></table>';
}
