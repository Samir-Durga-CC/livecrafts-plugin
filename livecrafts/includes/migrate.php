<?php
/**
 * Moving data from Livecrafts 0.9 and older.
 *
 * Up to 0.9, style edits were an "overlay": CSS printed with !important on every page, and text swapped by JavaScript
 * after the page loaded. 0.10 never creates those. Existing ones are moved carefully:
 *   - Style overlays become ONE draft CSS block "legacy-overlay" in Additional CSS, without !important (page rules are
 *     scoped with the page's body class). It is a draft: check it in the preview, fix what no longer wins, deploy it.
 *   - Text overlays cannot be converted automatically (where the text really lives is unknown): they are listed in the
 *     migration report (status endpoint, Settings → Livecrafts) to be fixed at the source.
 *   - Until the "legacy-overlay" draft is deployed, the old overlay keeps printing for visitors exactly as before, so
 *     the upgrade itself changes nothing they see. Editors previewing drafts see the site without it.
 *   - Text overlays keep printing until an administrator removes the overlay (Settings → Livecrafts).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const LIVECRAFTS_LEGACY_OPT = 'livecrafts_patches';
const LIVECRAFTS_MIGRATION  = 'livecrafts_migration';

function livecrafts_legacy_patches() {
	$all = get_option( LIVECRAFTS_LEGACY_OPT, array() );
	return is_array( $all ) ? $all : array();
}

/** CSS scope of an old patch key: '' = whole site, 'body.page-id-12' = one page, null = an archive URL we cannot scope. */
function livecrafts_legacy_scope( $key ) {
	if ( $key === 'site' ) return '';
	if ( preg_match( '/^p(\d+)$/', (string) $key, $m ) ) return get_post_type( (int) $m[1] ) === 'page' ? 'body.page-id-' . $m[1] : 'body.postid-' . $m[1];
	return null;
}

function livecrafts_legacy_selector_ok( $s ) {
	$s = trim( (string) $s );
	return $s !== '' && strlen( $s ) <= 400 && ! preg_match( '/[{};<\\\\,@]|\/\*/', $s );
}

/** "prop: value;" lines of an old style list, keeping only what the old overlay allowed. */
function livecrafts_legacy_declarations( $styles, $important ) {
	static $allowed = array(
		'color', 'background-color', 'background-image', 'background-size', 'background-position',
		'font-size', 'font-weight', 'font-family', 'font-style', 'text-align', 'text-decoration', 'text-transform',
		'letter-spacing', 'line-height', 'padding', 'margin', 'border-radius', 'opacity',
		'width', 'max-width', 'height', 'min-height', 'display', 'gap',
		'padding-top', 'padding-right', 'padding-bottom', 'padding-left', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
		'border-color', 'border-width', 'border-style', 'box-shadow', 'justify-content', 'align-items', 'flex-direction',
	);
	$out = array();
	foreach ( (array) $styles as $prop => $value ) {
		$value = trim( (string) $value );
		if ( ! in_array( $prop, $allowed, true ) || $value === '' || strlen( $value ) > 300 ) continue;
		if ( preg_match( '/[{};<>\\\\@]|\/\*|expression|javascript:/i', $value ) ) continue;
		if ( preg_match( '/url\(/i', $value ) && ( $prop !== 'background-image' || ! preg_match( '/^url\(\s*([\'"]?)(https?:\/\/|\/)[^\s\'")]+\1\s*\)$/i', $value ) ) ) continue;
		$out[] = $prop . ': ' . $value . ( $important ? ' !important' : '' ) . ';';
	}
	return $out;
}

/** The overlay as CSS. $important = true reproduces the old output exactly (legacy printing only). */
function livecrafts_legacy_css( array $all, $important, $scoped ) {
	$blocks = array( 'styles' => '', 'styles_tablet' => '', 'styles_mobile' => '' );
	foreach ( $all as $key => $patches ) {
		$scope = $scoped ? livecrafts_legacy_scope( $key ) : '';
		if ( $scope === null || ! is_array( $patches ) ) continue;
		foreach ( $patches as $selector => $patch ) {
			if ( ! livecrafts_legacy_selector_ok( $selector ) || ! is_array( $patch ) ) continue;
			foreach ( $blocks as $k => $unused ) {
				$decl = isset( $patch[ $k ] ) ? livecrafts_legacy_declarations( $patch[ $k ], $important ) : array();
				if ( $decl ) $blocks[ $k ] .= ( $scope !== '' ? $scope . ' ' : '' ) . trim( $selector ) . " {\n\t" . implode( "\n\t", $decl ) . "\n}\n";
			}
		}
	}
	return trim( $blocks['styles']
		. ( $blocks['styles_tablet'] ? "@media (max-width: 1024px) {\n" . $blocks['styles_tablet'] . "}\n" : '' )
		. ( $blocks['styles_mobile'] ? "@media (max-width: 767px) {\n" . $blocks['styles_mobile'] . "}\n" : '' ) );
}

/** Run once: style overlay -> a draft CSS block; text overlays -> the report. */
function livecrafts_migrate() {
	$all = livecrafts_legacy_patches();
	if ( ! $all || get_option( LIVECRAFTS_MIGRATION ) ) return;

	$texts = array(); $unscoped = array();
	foreach ( $all as $key => $patches ) {
		foreach ( (array) $patches as $selector => $patch ) {
			$where = $key === 'site' ? 'every page' : ( preg_match( '/^p(\d+)$/', $key, $m ) ? livecrafts_object_label( 'post', (int) $m[1] ) : 'an archive page' );
			if ( isset( $patch['text'] ) && $patch['text'] !== null ) $texts[] = array( 'where' => $where, 'selector' => (string) $selector, 'text' => mb_substr( (string) $patch['text'], 0, 300 ) );
			if ( livecrafts_legacy_scope( $key ) === null && ( ! empty( $patch['styles'] ) || ! empty( $patch['styles_tablet'] ) || ! empty( $patch['styles_mobile'] ) ) ) $unscoped[] = (string) $selector;
		}
	}

	$change = 0;
	$css    = livecrafts_legacy_css( $all, false, true );
	$error  = '';
	if ( $css !== '' ) {
		$clean = livecrafts_css_validate( $css );
		if ( is_wp_error( $clean ) ) {
			$error = $clean->get_error_message();
		} else {
			$current = livecrafts_css_block_get( wp_get_custom_css(), 'legacy-overlay' );
			$change  = livecrafts_change_insert( array(
				'status' => 'draft', 'source' => 'system', 'user_id' => 0, 'object_type' => 'css', 'object_id' => 0,
				'kind' => 'css.block', 'target' => 'legacy-overlay',
				'summary' => 'Move the old style overlay into Additional CSS (review in the preview, then deploy)',
				'payload' => array( 'label' => 'Moved from the Livecrafts 0.9 style overlay', 'before' => $current, 'after' => $clean ),
			) );
		}
	}
	update_option( LIVECRAFTS_MIGRATION, array( 'at' => time(), 'change' => $change, 'texts' => $texts, 'unscoped' => $unscoped, 'error' => $error ), false );
}

/** What is left of the old overlay, for the status endpoint and the settings page (null = nothing left). */
function livecrafts_migration_report() {
	$all = livecrafts_legacy_patches();
	if ( ! $all ) return null;
	$m      = get_option( LIVECRAFTS_MIGRATION, array() );
	$change = ! empty( $m['change'] ) ? livecrafts_change_get( (int) $m['change'] ) : null;
	return array(
		'styles'        => $change ? $change['status'] : ( ! empty( $m['error'] ) ? 'not converted: ' . $m['error'] : 'none' ),
		'styles_change' => $change ? $change['id'] : null,
		'texts'         => isset( $m['texts'] ) ? $m['texts'] : array(),
		'unscoped'      => isset( $m['unscoped'] ) ? $m['unscoped'] : array(),
		'note'          => 'Old overlay from Livecrafts 0.9. Its styles were moved into a draft CSS block "legacy-overlay" (without !important - check that each rule still wins). Its texts are still swapped by JavaScript on the live site: change each text at its real source (block content, ACF/Elementor field or theme template), then an administrator removes the old overlay in Settings → Livecrafts.',
	);
}

/** When the migrated CSS is deployed, the old style overlay is no longer needed. Texts stay until an admin removes them. */
add_action( 'livecrafts_deployed', function () {
	$m = get_option( LIVECRAFTS_MIGRATION, array() );
	if ( empty( $m['change'] ) ) return;
	$c = livecrafts_change_get( (int) $m['change'] );
	if ( ! $c || $c['status'] !== 'live' ) return;
	livecrafts_legacy_remove( false );
} );

/** Remove the old overlay: only its styles ($texts = false), or everything. */
function livecrafts_legacy_remove( $texts = true ) {
	if ( $texts ) { delete_option( LIVECRAFTS_LEGACY_OPT ); return; }
	$all = livecrafts_legacy_patches();
	foreach ( $all as $key => $patches ) {
		foreach ( (array) $patches as $selector => $patch ) {
			unset( $all[ $key ][ $selector ]['styles'], $all[ $key ][ $selector ]['styles_tablet'], $all[ $key ][ $selector ]['styles_mobile'] );
			if ( empty( $all[ $key ][ $selector ] ) ) unset( $all[ $key ][ $selector ] );
		}
		if ( empty( $all[ $key ] ) ) unset( $all[ $key ] );
	}
	if ( $all ) update_option( LIVECRAFTS_LEGACY_OPT, $all, false );
	else delete_option( LIVECRAFTS_LEGACY_OPT );
}

/* ------------------------------------------------------------------ legacy printing (only while old data exists) */

/** The old overlay entries for the page being viewed (site-wide first, then this page). */
function livecrafts_legacy_for_page() {
	$all = livecrafts_legacy_patches();
	if ( ! $all ) return array();
	$out = array();
	foreach ( array( 'site', livecrafts_page_key() ) as $k ) {
		if ( ! empty( $all[ $k ] ) && is_array( $all[ $k ] ) ) $out[ $k ] = $all[ $k ];
	}
	return $out;
}

add_action( 'wp_head', function () {
	$mine = livecrafts_legacy_for_page();
	if ( ! $mine ) return;
	// Editors previewing drafts see the migrated CSS instead, so they can check it.
	$m = get_option( LIVECRAFTS_MIGRATION, array() );
	if ( livecrafts_preview_active() && ! empty( $m['change'] ) ) {
		$c = livecrafts_change_get( (int) $m['change'] );
		if ( $c && $c['status'] === 'draft' ) return;
	}
	$css = livecrafts_legacy_css( $mine, true, false );
	if ( $css !== '' ) echo "<style id=\"livecrafts-legacy-overlay\">\n" . $css . "\n</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- validated CSS
}, 100 );

add_action( 'wp_footer', function () {
	$text = array();
	foreach ( livecrafts_legacy_for_page() as $patches ) {
		foreach ( $patches as $selector => $patch ) {
			if ( isset( $patch['text'] ) && $patch['text'] !== null && livecrafts_legacy_selector_ok( $selector ) ) $text[ $selector ] = (string) $patch['text'];
		}
	}
	if ( ! $text ) return;
	echo '<script id="livecrafts-legacy-text">(function(){var m=' . wp_json_encode( $text, JSON_HEX_TAG | JSON_HEX_AMP ) . ';Object.keys(m).forEach(function(s){try{var e=document.querySelector(s);if(e)e.textContent=m[s];}catch(x){}});})();</script>' . "\n";
}, 1 );
