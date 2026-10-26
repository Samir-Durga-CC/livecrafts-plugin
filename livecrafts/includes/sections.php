<?php
/**
 * Sections on pages the THEME builds itself.
 *
 * Some themes do not print the page's body at all: front-page.php (and many "home" or landing templates) call
 * get_template_part() for a fixed list of parts - hero, why, products - and ignore everything the person writes in
 * WordPress. On those pages a section saved in the page content would never appear, which used to leave only one
 * answer: "ask a developer to edit the theme".
 *
 * So Livecrafts renders them itself, at the seams of the theme's own template. WordPress fires an action every time a
 * template calls get_template_part(), and we print the section there. Nothing in the theme is touched, nothing is
 * overwritten by a theme update, and the site owner needs no developer.
 *
 *   livecrafts_sections   post meta: array of array( 'anchor' => 'before:template-parts/why', 'content' => '[ai_...]' )
 *
 * It is ordinary tracked post meta, so a section is a DRAFT like every other change: editors see it, visitors do not
 * until someone deploys, the ledger records it, and revert removes it.
 *
 * Anchors:
 *   before:<template part>   right before the theme loads that part   (e.g. before:template-parts/why)
 *   after:<template part>    right after it (printed when the next part, or the footer, starts)
 *   start                    right after <body> opens
 *   end                      after the page content, before the footer
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'LIVECRAFTS_SECTIONS_META', 'livecrafts_sections' );

/** The sections of a page, as the viewer should see them (drafts for editors, live for visitors). */
function livecrafts_sections_get( $post_id ) {
	$raw = get_post_meta( (int) $post_id, LIVECRAFTS_SECTIONS_META, true );
	$out = array();
	foreach ( (array) $raw as $row ) {
		if ( ! is_array( $row ) || empty( $row['anchor'] ) || ! isset( $row['content'] ) ) continue;
		$anchor = livecrafts_sections_clean_anchor( $row['anchor'] );
		if ( $anchor && (string) $row['content'] !== '' ) $out[] = array( 'anchor' => $anchor, 'content' => (string) $row['content'] );
	}
	return $out;
}

/** A valid anchor, or '' - "before:"/"after:" plus a template-part slug, or "start"/"end". */
function livecrafts_sections_clean_anchor( $anchor ) {
	$anchor = strtolower( trim( (string) $anchor ) );
	if ( $anchor === 'start' || $anchor === 'end' ) return $anchor;
	if ( ! preg_match( '#^(before|after):([a-z0-9/_-]{1,80})$#', $anchor, $m ) ) return '';
	return $m[1] . ':' . trim( $m[2], '/' );
}

/** Write the sections back (used by the change kind through the snapshot, never called directly on save). */
function livecrafts_sections_set( array $sections, $anchor, $content ) {
	$out = array();
	foreach ( $sections as $row ) {
		if ( $row['anchor'] !== $anchor ) $out[] = $row;
	}
	if ( (string) $content !== '' ) $out[] = array( 'anchor' => $anchor, 'content' => (string) $content );
	usort( $out, function ( $a, $b ) { return strcmp( $a['anchor'], $b['anchor'] ); } );
	return $out;
}

/* ------------------------------------------------------------------ rendering */

/** The post whose sections this request shows (a singular page, or the page WordPress uses as the front page). */
function livecrafts_sections_post() {
	if ( is_admin() || ! did_action( 'template_redirect' ) ) return 0;
	if ( is_front_page() && 'page' === get_option( 'show_on_front' ) ) return (int) get_option( 'page_on_front' );
	if ( is_singular() ) return (int) get_queried_object_id();
	return 0;
}

/** Print one section's content (shortcodes run; the markup is the library's own). */
function livecrafts_sections_print( $content ) {
	echo '<div class="livecrafts-section" data-livecrafts-section="1">' . do_shortcode( (string) $content ) . '</div>';
}

/**
 * Every template part the page loads, in order, remembered for the pages an editor opens: it is the list of places a
 * section can go, so the assistant never has to read the theme's PHP to find out.
 */
function livecrafts_sections_seen( $post_id, $slug = null ) {
	static $seen = array();
	$post_id = (int) $post_id;
	if ( $slug === null ) {
		$saved = get_post_meta( $post_id, '_livecrafts_parts', true );
		return is_array( $saved ) ? $saved : array();
	}
	$seen[ $post_id ] = isset( $seen[ $post_id ] ) ? $seen[ $post_id ] : array();
	if ( in_array( $slug, $seen[ $post_id ], true ) ) return $seen[ $post_id ];
	$seen[ $post_id ][] = $slug;
	// Only an editor's own view writes the list: a visitor's page view must never cost a database write.
	if ( livecrafts_user_can_edit() ) {
		$saved = get_post_meta( $post_id, '_livecrafts_parts', true );
		if ( ! is_array( $saved ) || array_values( $saved ) !== $seen[ $post_id ] ) {
			update_post_meta( $post_id, '_livecrafts_parts', $seen[ $post_id ] );
		}
	}
	return $seen[ $post_id ];
}

/**
 * The seam between two template parts. WordPress fires this before it loads each part, so "before:x" prints here and
 * "after:x" is held until the next part (or the footer) starts.
 */
add_action( 'get_template_part', 'livecrafts_sections_at_part', 10, 2 );
function livecrafts_sections_at_part( $slug, $name = null ) {
	$post_id = livecrafts_sections_post();
	if ( ! $post_id ) return;
	$slug = trim( (string) $slug, '/' );
	livecrafts_sections_seen( $post_id, $slug );
	livecrafts_sections_flush_pending();
	foreach ( livecrafts_sections_get( $post_id ) as $row ) {
		list( $where, $target ) = array_pad( explode( ':', $row['anchor'], 2 ), 2, '' );
		if ( $target !== $slug ) continue;
		if ( $where === 'before' ) livecrafts_sections_print( $row['content'] );
		if ( $where === 'after' ) livecrafts_sections_pending( $row['content'] );
	}
}

/** "after:" sections wait here until the next seam, so they land below the part they belong to. */
function livecrafts_sections_pending( $content = null ) {
	static $queue = array();
	if ( $content === null ) { $out = $queue; $queue = array(); return $out; }
	$queue[] = $content;
	return $queue;
}
function livecrafts_sections_flush_pending() {
	foreach ( livecrafts_sections_pending( null ) as $content ) livecrafts_sections_print( $content );
}

/** Right after <body> opens. */
add_action( 'wp_body_open', function () {
	$post_id = livecrafts_sections_post();
	if ( ! $post_id ) return;
	foreach ( livecrafts_sections_get( $post_id ) as $row ) {
		if ( $row['anchor'] === 'start' ) livecrafts_sections_print( $row['content'] );
	}
}, 5 );

/** After the page's content, before the footer - and the last chance for a pending "after:" section. */
add_action( 'get_footer', function () {
	$post_id = livecrafts_sections_post();
	if ( ! $post_id ) return;
	livecrafts_sections_flush_pending();
	foreach ( livecrafts_sections_get( $post_id ) as $row ) {
		if ( $row['anchor'] === 'end' ) livecrafts_sections_print( $row['content'] );
	}
}, 5 );

/**
 * Does the theme print this page's own content (the_content)? Many custom templates do not, and then anything saved in
 * the content - blocks, a section from the gallery - never shows. Recorded on an editor's view, like the parts above:
 * '1' when the page's content was printed, '0' when the page finished without it.
 */
add_filter( 'the_content', function ( $content ) {
	$post_id = livecrafts_sections_post();
	if ( $post_id && (int) get_the_ID() === $post_id ) livecrafts_sections_content_seen( true );
	return $content;
}, 1 );
add_action( 'wp_footer', function () {
	$post_id = livecrafts_sections_post();
	if ( ! $post_id || ! livecrafts_user_can_edit() ) return;
	$now = livecrafts_sections_content_seen() ? '1' : '0';
	if ( get_post_meta( $post_id, '_livecrafts_shows_content', true ) !== $now ) update_post_meta( $post_id, '_livecrafts_shows_content', $now );
}, 1 );
function livecrafts_sections_content_seen( $set = null ) {
	static $seen = false;
	if ( $set ) $seen = true;
	return $seen;
}

/* ------------------------------------------------------------------ what the assistant is told */

/**
 * Where a section can go on this page, and what is there already. "parts" is what the theme actually loaded the last
 * time an editor opened the page; it is empty until then (the assistant can still use start / end).
 */
function livecrafts_sections_map( $post_id ) {
	$parts    = livecrafts_sections_seen( $post_id );
	$sections = livecrafts_sections_get( $post_id );
	$places   = array( 'start', 'end' );
	foreach ( $parts as $slug ) {
		$places[] = 'before:' . $slug;
		$places[] = 'after:' . $slug;
	}
	$shows = get_post_meta( $post_id, '_livecrafts_shows_content', true );
	return array(
		'themeParts' => $parts,
		// true / false once an editor has opened the page, null before that.
		'showsContent' => $shows === '' ? null : $shows === '1',
		'places'     => $places,
		'current'    => array_map( function ( $s ) {
			return array( 'anchor' => $s['anchor'], 'content' => mb_substr( $s['content'], 0, 200 ) );
		}, $sections ),
		'note'       => $parts
			? 'This page is built by the theme from these parts. A section placed at one of "places" is rendered by Livecrafts at that seam: no theme file is touched. Use kind theme.section, target = the place, value = the shortcode.'
			: 'No template parts have been recorded yet (an editor has to open the page once). "start" and "end" always work.',
	);
}
