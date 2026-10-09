<?php
/**
 * Helpers shared by all templates: class prefixing, icons, small utilities.
 * Icons are Heroicons v2 outline (MIT, https://heroicons.com).
 */

defined( 'ABSPATH' ) || exit;

/** Returns the class string unchanged. tools/prefix.py rewrites the literal at build time (adds ai:). */
function ai_cls( $classes ) {
	return $classes;
}

/** Safe SVG allow-list for wp_kses (icons only). */
function ai_svg_allowed() {
	return array(
		'svg'  => array( 'aria-hidden' => true, 'xmlns' => true, 'fill' => true, 'viewbox' => true, 'stroke-width' => true, 'stroke' => true, 'class' => true ),
		'path' => array( 'stroke-linecap' => true, 'stroke-linejoin' => true, 'd' => true ),
	);
}

/** Icon paths (24x24 outline). A name can have several sub-paths. */
function ai_icon_paths() {
	return array(
		'bolt'          => array( 'm3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z' ),
		'shield'        => array( 'M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z' ),
		'chart'         => array( 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z' ),
		'users'         => array( 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z' ),
		'globe'         => array( 'M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418' ),
		'lock'          => array( 'M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z' ),
		'star'          => array( 'M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z' ),
		'code'          => array( 'M17.25 6.75 22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3-4.5 16.5' ),
		'clock'         => array( 'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z' ),
		'phone'         => array( 'M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z' ),
		'mail'          => array( 'M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75' ),
		'pin'           => array( 'M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z', 'M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z' ),
		'search'        => array( 'm21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z' ),
		'grid'          => array( 'M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z' ),
		'check'         => array( 'm4.5 12.75 6 6 9-13.5' ),
		'check-circle'  => array( 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z' ),
		'x'             => array( 'M6 18 18 6M6 6l12 12' ),
		'x-circle'      => array( 'm9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z' ),
		'info'          => array( 'm11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z' ),
		'warning'       => array( 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z' ),
		'menu'          => array( 'M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5' ),
		'chevron-down'  => array( 'm19.5 8.25-7.5 7.5-7.5-7.5' ),
		'chevron-right' => array( 'm8.25 4.5 7.5 7.5-7.5 7.5' ),
		'chevron-left'  => array( 'M15.75 19.5 8.25 12l7.5-7.5' ),
		'arrow-right'   => array( 'M17.25 8.25 21 12m0 0-3.75 3.75M21 12H3' ),
		'calendar'      => array( 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5' ),
		'book'          => array( 'M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25' ),
	);
}

/**
 * Icon markup. $size must be one of 3, 4, 5, 6, 8 (these classes are safelisted in the CSS).
 * The returned string is built only from constants above, but templates still pass it through wp_kses.
 */
function ai_icon( $name, $size = 5 ) {
	$paths = ai_icon_paths();
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}
	$size = in_array( (int) $size, array( 3, 4, 5, 6, 8 ), true ) ? (int) $size : 5;
	$d    = '';
	foreach ( $paths[ $name ] as $p ) {
		$d .= '<path stroke-linecap="round" stroke-linejoin="round" d="' . $p . '" />';
	}
	return '<svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="ai:size-' . $size . ' ai:shrink-0">' . $d . '</svg>';
}

/** Echo an icon (escaped through the allow-list). */
function ai_e_icon( $name, $size = 5 ) {
	echo wp_kses( ai_icon( $name, $size ), ai_svg_allowed() );
}

/** First letter(s) of a name, for avatar fallbacks. */
function ai_initials( $name ) {
	$parts = preg_split( '/\s+/', trim( (string) $name ) );
	$out   = '';
	foreach ( array_slice( $parts, 0, 2 ) as $p ) {
		$out .= strtoupper( substr( $p, 0, 1 ) );
	}
	return $out;
}

/** "Fit" settings every component accepts: how it adapts to the site it sits in (CSS variable => kind). */
function ai_fit_keys() {
	return array(
		'brand' => array( '--ai-brand', 'color' ), 'surface' => array( '--ai-surface', 'color' ), 'surface_alt' => array( '--ai-surface-alt', 'color' ),
		'ink' => array( '--ai-ink', 'color' ), 'muted' => array( '--ai-muted', 'color' ), 'line' => array( '--ai-line', 'color' ),
		'max' => array( '--ai-max', 'length' ), 'py' => array( '--ai-py', 'length' ), 'py_sm' => array( '--ai-py-sm', 'length' ),
		'radius' => array( '--ai-radius', 'length' ), 'font' => array( '--ai-font', 'font' ),
	);
}

/** A value that is safe inside a style attribute, or ''. Colors: hex / rgb / hsl / var(). Lengths: number+unit / clamp() / var(). */
function ai_css_value( $value, $kind ) {
	$v = trim( (string) $value );
	if ( '' === $v || strlen( $v ) > 120 || preg_match( '/[;{}<>\\\\\x22\x27@]|url\(|expression|\/\*/i', $v ) ) {
		return '';
	}
	$num = '-?\d*\.?\d+(?:px|rem|em|%|vw|vh|ch)?';
	if ( 'color' === $kind ) {
		return preg_match( '/^(#[0-9a-f]{3,8}|(?:rgb|rgba|hsl|hsla|oklch|color-mix)\([0-9a-z\s.,%\/-]+\)|var\(--[a-z0-9-]+(?:,\s*[#a-z0-9(),.%\s-]+)?\))$/i', $v ) ? $v : '';
	}
	if ( 'font' === $kind ) {
		return preg_match( '/^[a-z0-9 ,\-_]+$/i', $v ) || preg_match( '/^var\(--[a-z0-9-]+\)$/i', $v ) ? $v : '';
	}
	return preg_match( '/^(' . $num . '|(?:clamp|min|max|calc)\([0-9a-z\s.,%+*\/()-]+\)|var\(--[a-z0-9-]+\))$/i', $v ) ? $v : '';
}

/** style="" for a fit array like array( 'max' => '1140px', 'brand' => '#0f766e' ). Unknown keys and unsafe values are dropped. */
function ai_fit_style( $fit ) {
	$css = '';
	foreach ( ai_fit_keys() as $key => $def ) {
		if ( empty( $fit[ $key ] ) ) {
			continue;
		}
		$val = ai_css_value( $fit[ $key ], $def[1] );
		if ( '' !== $val ) {
			$css .= $def[0] . ':' . $val . ';';
		}
	}
	return $css;
}

/**
 * Render a component by name. Themes may override with ai-components/{name}.php.
 * Used by shortcodes, by other components (hero renders button) and directly from ACF loops.
 * The outermost call wraps the component in <div class="ai-t" data-lc-template="name"> carrying the "fit" variables
 * ($args['fit']); that marker proves on the page that the template was used (see ?lc_templates=1 for editors).
 */
function ai_component( $name, $args = array(), $echo = true ) {
	static $depth = 0;
	$name = sanitize_key( $name );
	$path = locate_template( 'ai-components/' . $name . '.php' );
	if ( ! $path ) {
		$path = AI_COMPONENTS_DIR . 'templates/' . $name . '.php';
	}
	if ( ! file_exists( $path ) ) {
		return '';
	}

	wp_enqueue_style( 'ai-components' );

	$fit = isset( $args['fit'] ) && is_array( $args['fit'] ) ? $args['fit'] : array();
	++$depth;
	ob_start();
	load_template( $path, false, $args );
	$html = ob_get_clean();
	--$depth;

	if ( 0 === $depth ) {
		do_action( 'ai_component_rendered', $name, $args );
		$style = ai_fit_style( $fit );
		$html  = '<div class="ai-t" data-lc-template="' . esc_attr( str_replace( '-', '_', $name ) ) . '" data-lc-tlib="' . esc_attr( AI_COMPONENTS_VERSION ) . '"' . ( $style ? ' style="' . esc_attr( $style ) . '"' : '' ) . '>' . $html . '</div>';
	}

	if ( $echo ) {
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every template escapes its own output.
		return '';
	}
	return $html;
}

/** Normalise $args['items'] to a list of arrays. */
function ai_items( $args ) {
	$items = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
	return array_values( array_filter( $items, 'is_array' ) );
}

/** Accept "a|b|c" or an array and return a clean list of strings. */
function ai_list( $value ) {
	if ( is_string( $value ) ) {
		$value = explode( '|', $value );
	}
	return is_array( $value ) ? array_values( array_filter( array_map( 'trim', array_map( 'strval', $value ) ), 'strlen' ) ) : array();
}
