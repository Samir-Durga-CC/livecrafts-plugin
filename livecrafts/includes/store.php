<?php
/**
 * Patch storage + validation.
 *
 * Data model (one option, not autoloaded):
 *   livecrafts_patches = [
 *     'site'      => [ '<selector>' => [ 'styles' => [prop => value], 'text' => '...' ] ],   // applies on every page
 *     'p12'       => [ ... ],                                                                 // applies on post/page ID 12
 *     'u:<md5>'   => [ ... ],                                                                 // non-post URLs (archives, etc.)
 *   ]
 * livecrafts_log = activity list (also powers Undo and the admin log).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const LIVECRAFTS_OPT = 'livecrafts_patches';
const LIVECRAFTS_LOG = 'livecrafts_log';

function livecrafts_get_all() {
	$v = get_option( LIVECRAFTS_OPT, array() );
	return is_array( $v ) ? $v : array();
}

/** Key identifying the page currently being viewed. */
function livecrafts_page_key() {
	if ( is_singular() || is_front_page() ) {
		$id = ( is_front_page() && get_option( 'page_on_front' ) ) ? (int) get_option( 'page_on_front' ) : (int) get_queried_object_id();
		if ( $id ) return 'p' . $id;
	}
	$path = wp_parse_url( add_query_arg( array() ), PHP_URL_PATH );
	return 'u:' . md5( $path ? $path : '/' );
}

function livecrafts_valid_key( $k ) {
	return is_string( $k ) && ( $k === 'site' || (bool) preg_match( '/^(p\d+|u:[a-f0-9]{32})$/', $k ) );
}

/** Site-wide patches first, then page patches (page wins on conflict because it prints later). */
function livecrafts_patches_for_page() {
	$all = livecrafts_get_all();
	$key = livecrafts_page_key();
	$out = array();
	foreach ( array( 'site', $key ) as $k ) {
		if ( ! empty( $all[ $k ] ) && is_array( $all[ $k ] ) ) {
			foreach ( $all[ $k ] as $sel => $patch ) $out[ $sel ] = $patch;
		}
	}
	return $out;
}

/** Hard allowlist of CSS properties. Anything else is dropped. */
function livecrafts_allowed_props() {
	return array(
		'color', 'background-color', 'background-image', 'background-size', 'background-position',
		'font-size', 'font-weight', 'font-family', 'font-style', 'text-align', 'text-decoration', 'text-transform',
		'letter-spacing', 'line-height', 'padding', 'margin', 'border-radius', 'opacity',
		'width', 'max-width', 'height', 'min-height', 'display', 'gap',
	);
}

/** A selector is stored and printed into CSS, so it must not be able to break out of a rule. */
function livecrafts_clean_selector( $s ) {
	$s = trim( (string) $s );
	if ( $s === '' || strlen( $s ) > 400 ) return '';
	// '>' is allowed (child combinator). '<' is not (could close the <style> tag); '{', '}', ';' could end the rule.
	if ( preg_match( '/[{};<\\\\,@]|\/\*/', $s ) ) return '';
	return $s;
}

function livecrafts_clean_value( $prop, $v ) {
	$v = trim( (string) $v );
	if ( $v === '' || strlen( $v ) > 300 ) return null;
	if ( preg_match( '/[{};<>\\\\@]|\/\*|expression|javascript:/i', $v ) ) return null;
	if ( $prop === 'background-image' ) {
		if ( $v === 'none' ) return $v;
		if ( ! preg_match( '/^url\(\s*([\'"]?)(https?:\/\/|\/)[^\s\'")]+\1\s*\)$/i', $v ) ) return null;
	} elseif ( preg_match( '/url\(/i', $v ) ) {
		return null;
	}
	return $v;
}

function livecrafts_clean_styles( $styles ) {
	$out = array();
	if ( ! is_array( $styles ) ) return $out;
	$allowed = livecrafts_allowed_props();
	foreach ( $styles as $prop => $value ) {
		if ( ! in_array( $prop, $allowed, true ) ) continue;
		$clean = livecrafts_clean_value( $prop, $value );
		if ( $clean !== null ) $out[ $prop ] = $clean;
	}
	return $out;
}

function livecrafts_build_css( $patches ) {
	$css = '';
	foreach ( $patches as $sel => $patch ) {
		if ( empty( $patch['styles'] ) || ! is_array( $patch['styles'] ) ) continue;
		$sel = livecrafts_clean_selector( $sel );
		if ( $sel === '' ) continue;
		$decl = array();
		foreach ( livecrafts_clean_styles( $patch['styles'] ) as $p => $v ) $decl[] = $p . ':' . $v . ' !important';
		if ( $decl ) $css .= $sel . '{' . implode( ';', $decl ) . "}\n";
	}
	return $css;
}

function livecrafts_text_patches( $patches ) {
	$out = array();
	foreach ( $patches as $sel => $patch ) {
		if ( isset( $patch['text'] ) && $patch['text'] !== null && livecrafts_clean_selector( $sel ) !== '' ) $out[ $sel ] = (string) $patch['text'];
	}
	return $out;
}

/** Append to the activity log (kept to the last 200 entries). */
function livecrafts_log_add( $key, $selector, $before, $after, $extra = array() ) {
	$log   = get_option( LIVECRAFTS_LOG, array() );
	$log   = is_array( $log ) ? $log : array();
	$user  = wp_get_current_user();
	$log[] = array_merge( array(
		'ts' => time(), 'user' => $user && $user->exists() ? $user->user_login : '-',
		'key' => $key, 'selector' => $selector, 'before' => $before, 'after' => $after,
	), $extra );
	if ( count( $log ) > 200 ) $log = array_slice( $log, -200 );
	update_option( LIVECRAFTS_LOG, $log, false );
}

/** Set (or remove, when $patch is null/empty) one patch, and log it. */
function livecrafts_set_patch( $key, $selector, $patch ) {
	$all    = livecrafts_get_all();
	$before = isset( $all[ $key ][ $selector ] ) ? $all[ $key ][ $selector ] : null;
	if ( empty( $patch ) ) {
		unset( $all[ $key ][ $selector ] );
		if ( empty( $all[ $key ] ) ) unset( $all[ $key ] );
		$patch = null;
	} else {
		$all[ $key ][ $selector ] = $patch;
	}
	update_option( LIVECRAFTS_OPT, $all, false );
	livecrafts_log_add( $key, $selector, $before, $patch );
	return $patch;
}
