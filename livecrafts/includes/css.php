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

/* ------------------------------------------------------------------ style rules (one rule per element + screen size) */

/** Screen sizes a rule can target; the same breakpoints Elementor uses by default. */
function livecrafts_css_media() {
	return array( '' => '', 'desktop' => '(min-width: 1025px)', 'tablet' => '(max-width: 1024px)', 'mobile' => '(max-width: 767px)' );
}

/** Properties a style rule may set. */
function livecrafts_css_props() {
	return array(
		'color', 'background-color', 'background-image', 'background-size', 'background-position', 'background-repeat',
		'font-family', 'font-size', 'font-weight', 'font-style', 'line-height', 'letter-spacing', 'text-align', 'text-transform', 'text-decoration',
		'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
		'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
		'gap', 'row-gap', 'column-gap', 'width', 'min-width', 'max-width', 'height', 'min-height', 'max-height', 'aspect-ratio', 'object-fit',
		'border', 'border-width', 'border-style', 'border-color', 'border-radius', 'box-shadow', 'opacity',
		'display', 'flex-direction', 'flex-wrap', 'justify-content', 'align-items', 'grid-template-columns',
		'animation-name', 'animation-duration', 'animation-delay', 'animation-timing-function', 'animation-fill-mode', 'transition',
	);
}

/** Entrance animations the style rules may use (animation-name). */
function livecrafts_css_animation_names() {
	return array( 'none', 'lc-fade-in', 'lc-fade-up', 'lc-zoom-in', 'lc-slide-in-left', 'lc-slide-in-right' );
}

function livecrafts_css_animations() {
	return "@keyframes lc-fade-in { from { opacity: 0; } to { opacity: 1; } }\n"
		. "@keyframes lc-fade-up { from { opacity: 0; transform: translateY(24px); } to { opacity: 1; transform: none; } }\n"
		. "@keyframes lc-zoom-in { from { opacity: 0; transform: scale(0.94); } to { opacity: 1; transform: none; } }\n"
		. "@keyframes lc-slide-in-left { from { opacity: 0; transform: translateX(-32px); } to { opacity: 1; transform: none; } }\n"
		. "@keyframes lc-slide-in-right { from { opacity: 0; transform: translateX(32px); } to { opacity: 1; transform: none; } }";
}

/** A selector (or a comma list of them) that cannot break out of its rule. '' when not usable. */
function livecrafts_css_selector_clean( $s ) {
	$s = trim( preg_replace( '/\s+/', ' ', (string) $s ) );
	if ( $s === '' || strlen( $s ) > 300 || preg_match( '/[{};<\\@]|\/\*/', $s ) ) return '';
	return $s;
}

/** One property value, checked. Returns the value or WP_Error. */
function livecrafts_css_value_clean( $prop, $value ) {
	$value = trim( (string) $value );
	if ( strlen( $value ) > 200 ) return livecrafts_bad_value( 'The value for ' . $prop . ' is too long.' );
	if ( preg_match( '/!\s*important/i', $value ) ) return livecrafts_bad_value( 'Do not use !important. Use a more specific selector instead.' );
	if ( preg_match( '/[{};<>\\@]|\/\*|expression\s*\(|javascript:/i', $value ) ) return livecrafts_bad_value( 'The value for ' . $prop . ' contains characters that are not allowed.' );
	if ( preg_match( '/url\(/i', $value ) && ( $prop !== 'background-image' || ! preg_match( '/^url\(\s*([\'"]?)(https?:\/\/|\/)[^\s\'")]+\1\s*\)$/i', $value ) ) ) {
		return livecrafts_bad_value( 'Only background-image may use url(), with an http(s) or site-relative address.' );
	}
	if ( $prop === 'animation-name' && ! in_array( $value, livecrafts_css_animation_names(), true ) ) {
		return livecrafts_bad_value( 'animation-name must be one of: ' . implode( ', ', livecrafts_css_animation_names() ) . '.' );
	}
	return $value;
}

/** Block id of the rule for one selector + screen size, so repeated edits of an element update the same rule. */
function livecrafts_css_rule_id( $selector, $media ) {
	return 'rule-' . substr( md5( $selector . '|' . $media ), 0, 10 );
}

/** CSS text of a rule. Animated elements stop animating for people who ask for reduced motion. */
function livecrafts_css_rule_text( $selector, $media, array $declarations ) {
	$queries = livecrafts_css_media();
	$wrap    = function ( $rule ) use ( $media, $queries ) {
		return $media !== '' ? '@media ' . $queries[ $media ] . " {\n\t" . str_replace( "\n", "\n\t", $rule ) . "\n}" : $rule;
	};
	$body = '';
	foreach ( $declarations as $prop => $value ) $body .= "\t" . $prop . ': ' . $value . ";\n";
	$css = $wrap( $selector . " {\n" . $body . '}' );
	if ( ! empty( $declarations['animation-name'] ) && $declarations['animation-name'] !== 'none' ) {
		$css .= "\n@media (prefers-reduced-motion: reduce) {\n\t" . str_replace( "\n", "\n\t", $wrap( $selector . " {\n\tanimation: none;\n}" ) ) . "\n}";
	}
	return $css;
}

/** The declarations of a rule written by livecrafts_css_rule_text() (its first innermost { } body). */
function livecrafts_css_rule_parse( $text ) {
	$out = array();
	if ( ! preg_match( '/\{([^{}]*)\}/', (string) $text, $m ) ) return $out;
	if ( preg_match_all( '/^\s*([a-z-]+)\s*:\s*(.+?)\s*;\s*$/m', $m[1], $d, PREG_SET_ORDER ) ) {
		foreach ( $d as $line ) $out[ $line[1] ] = $line[2];
	}
	ksort( $out );
	return $out;
}
