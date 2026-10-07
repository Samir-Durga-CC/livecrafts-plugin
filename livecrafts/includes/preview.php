<?php
/**
 * Preview: logged-in editors see the site WITH the draft changes; everyone else sees the live site.
 *
 * Active only on normal front-end page views (not wp-admin, AJAX, REST, cron, the Elementor editor or the
 * Customizer), for users who can edit with Livecrafts, unless they switched to "Live" in the widget (cookie
 * livecrafts_view=live). Pages with drafts are sent with no-cache headers.
 *
 * How the draft values reach the page, without touching the live data:
 *   - post meta (Elementor element tree, ACF values ...): get_post_metadata short-circuit -> the preview copy's meta
 *   - title / content / excerpt: the posts of the page's queries get the copy's values, the way WordPress core
 *     previews an autosave (the_preview); the_title / single_post_title cover titles read elsewhere
 *   - Elementor CSS: the copy's CSS is generated with Elementor's own preview CSS class (inline, never cached)
 *   - Additional CSS: wp_get_custom_css returns live CSS + draft CSS blocks
 * Elementor caches (rendered elements, CSS meta) are never written while previewing, so drafts cannot leak into
 * what visitors get.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** While suspended (nestable), every read returns LIVE values - used whenever Livecrafts itself reads the live state. */
function livecrafts_preview_suspend( $on = null ) {
	static $depth = 0;
	if ( $on === true ) $depth++;
	elseif ( $on === false ) $depth = max( 0, $depth - 1 );
	return $depth > 0;
}

function livecrafts_preview_active() {
	static $active = null;
	if ( livecrafts_preview_suspend() || livecrafts_writing() ) return false;
	if ( $active !== null ) return $active;
	if ( ! did_action( 'wp' ) ) return false; // the request is not known yet: decide later
	$active = ! is_admin() && ! wp_doing_ajax() && ! wp_doing_cron()
		&& ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) && ! ( defined( 'WP_CLI' ) && WP_CLI )
		&& ! isset( $_GET['elementor-preview'] ) && ! is_customize_preview()
		&& ( livecrafts_preview_token_request()
			|| ( is_user_logged_in() && livecrafts_can_edit() && ! ( isset( $_COOKIE['livecrafts_view'] ) && $_COOKIE['livecrafts_view'] === 'live' ) ) );
	return $active;
}

// A page opened with a preview token: never cached, never indexed, and its address (with the token) never sent on.
add_action( 'send_headers', function () {
	if ( ! livecrafts_preview_token_request() ) return;
	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow' );
	header( 'Referrer-Policy: no-referrer' );
} );

/** The preview copy to use for a post in this request (0 = show live). */
function livecrafts_preview_copy( $post_id ) {
	if ( ! $post_id || ! livecrafts_preview_active() ) return 0;
	livecrafts_preview_suspend( true );
	$copy = livecrafts_draft_copy_id( $post_id );
	livecrafts_preview_suspend( false );
	return $copy;
}

/* ------------------------------------------------------------------ meta */

add_filter( 'get_post_metadata', 'livecrafts_preview_meta', 1, 4 );
function livecrafts_preview_meta( $value, $object_id, $meta_key, $single ) {
	if ( $value !== null ) return $value;
	$copy = livecrafts_preview_copy( (int) $object_id );
	if ( ! $copy ) return $value;

	livecrafts_preview_suspend( true );
	if ( $meta_key === '' || $meta_key === null ) { // all meta: live bookkeeping + draft content
		$all = (array) get_post_meta( $object_id );
		foreach ( array_keys( $all ) as $k ) if ( livecrafts_meta_tracked( $k ) ) unset( $all[ $k ] );
		foreach ( (array) get_post_meta( $copy ) as $k => $v ) if ( livecrafts_meta_tracked( $k ) ) $all[ $k ] = $v;
		unset( $all['_elementor_element_cache'] );
		livecrafts_preview_suspend( false );
		return $all;
	}
	if ( $meta_key === '_elementor_element_cache' ) { // never serve live-rendered elements in a preview
		livecrafts_preview_suspend( false );
		return $single ? array( '' ) : array();
	}
	if ( ! livecrafts_meta_tracked( $meta_key ) ) {
		livecrafts_preview_suspend( false );
		return $value;
	}
	$values = get_post_meta( $copy, $meta_key, false );
	livecrafts_preview_suspend( false );
	if ( ! $values ) return $single ? array( '' ) : array();
	return $values;
}

// Elementor writes caches (CSS, rendered elements) while it renders. In a preview they would hold draft content:
// never let them be stored for a post that has a draft.
foreach ( array( 'update_post_metadata', 'add_post_metadata' ) as $livecrafts_hook ) {
	add_filter( $livecrafts_hook, function ( $check, $object_id, $meta_key ) {
		if ( $check !== null || ! in_array( $meta_key, array( '_elementor_element_cache', '_elementor_css', '_elementor_page_assets' ), true ) ) return $check;
		return livecrafts_preview_copy( (int) $object_id ) ? true : $check;
	}, 1, 3 );
}
unset( $livecrafts_hook );

/* ------------------------------------------------------------------ title, content, excerpt */

add_filter( 'the_posts', function ( $posts, $query ) {
	if ( ! livecrafts_preview_active() || ! is_array( $posts ) ) return $posts;
	foreach ( $posts as $post ) {
		if ( ! $post instanceof WP_Post ) continue;
		$copy = livecrafts_preview_copy( $post->ID );
		if ( ! $copy ) continue;
		$draft = get_post( $copy );
		if ( ! $draft ) continue;
		$post->post_title   = $draft->post_title;
		$post->post_content = $draft->post_content;
		$post->post_excerpt = $draft->post_excerpt;
	}
	return $posts;
}, 1, 2 );

add_filter( 'the_title', function ( $title, $post_id = 0 ) {
	$copy = livecrafts_preview_copy( (int) $post_id );
	return $copy ? get_post_field( 'post_title', $copy, 'raw' ) : $title;
}, 1, 2 );

add_filter( 'single_post_title', function ( $title, $post = null ) {
	$copy = $post instanceof WP_Post ? livecrafts_preview_copy( $post->ID ) : 0;
	return $copy ? get_post_field( 'post_title', $copy, 'raw' ) : $title;
}, 1, 2 );

// Menu links are read without query filters: give a link with a draft its draft label (its address and target are
// meta, which the meta filter above already serves from the draft).
add_filter( 'wp_setup_nav_menu_item', function ( $item ) {
	if ( ! isset( $item->ID ) || ! isset( $item->post_type ) || $item->post_type !== 'nav_menu_item' ) return $item;
	$copy = livecrafts_preview_copy( (int) $item->ID );
	if ( ! $copy ) return $item;
	$title = get_post_field( 'post_title', $copy, 'raw' );
	if ( $title !== '' ) $item->title = $title;
	$item->url    = (string) get_post_meta( $item->ID, '_menu_item_url', true ) ?: $item->url;
	$item->target = (string) get_post_meta( $item->ID, '_menu_item_target', true );
	return $item;
}, 1 );

/* ------------------------------------------------------------------ Elementor CSS */

// Elementor enqueues the page's generated CSS (the LIVE design). For a page with a draft, use Elementor's own preview
// CSS class instead: it builds the CSS from the copy's element tree and prints it inline, without writing any file.
add_action( 'elementor/css-file/post/enqueue', function ( $css_file ) {
	if ( ! livecrafts_preview_active() || ! class_exists( '\Elementor\Core\Files\CSS\Post_Preview' ) ) return;
	if ( $css_file instanceof \Elementor\Core\Files\CSS\Post_Preview || ! method_exists( $css_file, 'get_post_id' ) ) return;
	$copy = livecrafts_preview_copy( (int) $css_file->get_post_id() );
	if ( ! $copy ) return;
	wp_dequeue_style( $css_file->get_file_handle_id() );
	try {
		\Elementor\Core\Files\CSS\Post_Preview::create( $copy )->enqueue();
	} catch ( \Throwable $e ) {
		// The live CSS stays dequeued only if the preview CSS worked.
		wp_enqueue_style( $css_file->get_file_handle_id() );
	}
} );

/* ------------------------------------------------------------------ Additional CSS */

add_filter( 'wp_get_custom_css', function ( $css ) {
	if ( ! livecrafts_preview_active() ) return $css;
	static $draft = null;
	if ( $draft === null ) {
		$changes = livecrafts_draft_changes( 'css', 0 );
		if ( ! $changes ) return $css;
		$data = array( 'css' => (string) $css );
		foreach ( $changes as $c ) livecrafts_apply_change( $data, $c );
		$draft = $data['css'];
	}
	return $draft;
}, 1 );

/* ------------------------------------------------------------------ never cache a preview */

add_action( 'template_redirect', function () {
	if ( livecrafts_preview_active() && livecrafts_draft_count() > 0 ) nocache_headers();
} );
