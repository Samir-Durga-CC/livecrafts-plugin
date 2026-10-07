<?php
/**
 * Site-wide styles live in WordPress's own Additional CSS (Appearance → Customize → Additional CSS), the place a
 * developer looks first. Livecrafts keeps each of its rules in a named block, so it can change or remove exactly its
 * own rules and never touches what someone else wrote there:
 *
 *   /* livecrafts:start hero-title -- Hero title colour *\/
 *   .home .site-hero__title { color: #0b3d91; }
 *   /* livecrafts:end hero-title *\/
 *
 * Rules must not use !important: a rule that does not apply needs a more specific selector (inspect the element to
 * see which rule wins), not a sledgehammer.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function livecrafts_css_block_id( $id ) {
	$id = strtolower( trim( (string) $id ) );
	return preg_match( '/^[a-z0-9][a-z0-9-]{0,59}$/', $id ) ? $id : '';
}

/** Check CSS for a block. Returns the trimmed CSS or WP_Error. */
function livecrafts_css_validate( $css ) {
	$css = trim( str_replace( "\r\n", "\n", (string) $css ) );
	if ( strlen( $css ) > 30000 ) return livecrafts_bad_value( 'That CSS is too long (max 30 000 characters per block).' );
	if ( strpos( $css, '<' ) !== false ) return livecrafts_bad_value( 'CSS cannot contain "<".' );
	if ( preg_match( '/!\s*important/i', $css ) ) {
		return livecrafts_bad_value( 'Do not use !important. Make the selector more specific than the rule that wins now (inspect the element to see it).' );
	}
	if ( preg_match( '/@import|expression\s*\(|javascript:|behavior\s*:|-moz-binding/i', $css ) ) return livecrafts_bad_value( 'That CSS uses something that is not allowed (@import, expression, javascript:, behavior).' );
	if ( stripos( $css, 'livecrafts:' ) !== false ) return livecrafts_bad_value( 'CSS cannot contain Livecrafts block markers.' );
	if ( substr_count( $css, '/*' ) !== substr_count( $css, '*/' ) ) return livecrafts_bad_value( 'A CSS comment is not closed.' );
	$depth = 0;
	$plain = preg_replace( '#/\*.*?\*/#s', '', $css );
	$plain = preg_replace( '/"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'/', '""', $plain );
	foreach ( str_split( $plain ) as $ch ) {
		if ( $ch === '{' ) $depth++;
		if ( $ch === '}' && --$depth < 0 ) break;
	}
	if ( $depth !== 0 ) return livecrafts_bad_value( 'The CSS braces { } do not match.' );
	return $css;
}

function livecrafts_css_block_pattern( $id ) {
	$q = preg_quote( $id, '#' );
	return '#\n?/\* livecrafts:start ' . $q . '(?: -- [^*]*)? \*/\n.*?\n/\* livecrafts:end ' . $q . ' \*/\n?#s';
}

/** The CSS inside one Livecrafts block ('' when there is no such block). */
function livecrafts_css_block_get( $css, $id ) {
	$q = preg_quote( $id, '#' );
	return preg_match( '#/\* livecrafts:start ' . $q . '(?: -- [^*]*)? \*/\n(.*?)\n/\* livecrafts:end ' . $q . ' \*/#s', (string) $css, $m ) ? $m[1] : '';
}

/** Set ('' = remove) one Livecrafts block in the full Additional CSS text. Other text is never changed. */
function livecrafts_css_block_set( $css, $id, $block, $label = '' ) {
	$css   = (string) $css;
	$label = trim( preg_replace( '/[^A-Za-z0-9 .,:;()#&\'\/+-]/', '', (string) $label ) );
	$label = mb_substr( $label, 0, 80 );
	$found = preg_match( livecrafts_css_block_pattern( $id ), $css );
	if ( $block === '' ) {
		if ( ! $found ) return $css;
		$out = rtrim( preg_replace( livecrafts_css_block_pattern( $id ), "\n", $css, 1 ) );
		return $out === '' ? '' : $out . "\n";
	}
	$text = "\n/* livecrafts:start $id" . ( $label !== '' ? " -- $label" : '' ) . " */\n" . $block . "\n/* livecrafts:end $id */\n";
	if ( $found ) return preg_replace_callback( livecrafts_css_block_pattern( $id ), function () use ( $text ) { return $text; }, $css, 1 );
	return rtrim( $css ) . ( trim( $css ) !== '' ? "\n" : '' ) . $text;
}

/** Every Livecrafts block in the CSS: id => array( label, css ). */
function livecrafts_css_blocks( $css ) {
	$out = array();
	if ( preg_match_all( '#/\* livecrafts:start ([a-z0-9-]+)(?: -- ([^*]*))? \*/\n(.*?)\n/\* livecrafts:end \1 \*/#s', (string) $css, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $b ) $out[ $b[1] ] = array( 'label' => isset( $b[2] ) ? trim( $b[2] ) : '', 'css' => $b[3] );
	}
	return $out;
}
