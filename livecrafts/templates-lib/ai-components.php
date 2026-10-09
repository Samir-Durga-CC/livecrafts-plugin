<?php
/**
 * Plugin Name: AI Components
 * Description: 25 reusable, theme-independent UI components (hero, cards, pricing, FAQ, header, footer and more) as template parts and shortcodes. Works in Elementor, Divi, WPBakery and custom/ACF themes. One brand color variable re-themes everything.
 * Version: 1.1.0
 * Requires at least: 6.1
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * Text Domain: ai-components
 */

defined( 'ABSPATH' ) || exit;

define( 'AI_COMPONENTS_DIR', plugin_dir_path( __FILE__ ) );
define( 'AI_COMPONENTS_URL', plugin_dir_url( __FILE__ ) );
define( 'AI_COMPONENTS_VERSION', '1.1.0' );

require_once AI_COMPONENTS_DIR . 'includes/helpers.php';

/**
 * Brand color: set once per site. Either define it with the filter
 *   add_filter( 'ai_components_brand', fn() => '#0f766e' );
 * or store a hex value in the option "ai_components_brand". Components read it as --ai-brand.
 */
add_action(
	'init',
	function () {
		wp_register_style( 'ai-components', AI_COMPONENTS_URL . 'assets/css/ai-components.css', array(), AI_COMPONENTS_VERSION );

		$brand = sanitize_hex_color( (string) apply_filters( 'ai_components_brand', get_option( 'ai_components_brand', '' ) ) );
		if ( $brand ) {
			wp_add_inline_style( 'ai-components', ':root{--ai-brand:' . $brand . '}' );
		}
	}
);

/** component => default attributes. Names with an items flag take [ai_item] children. */
function ai_components_registry() {
	$cta = array( 'primary_label' => '', 'primary_url' => '#', 'secondary_label' => '', 'secondary_url' => '#' );
	return array(
		'button'        => array( 'items' => false, 'defaults' => array( 'label' => '', 'url' => '#', 'variant' => 'solid', 'size' => 'md', 'icon' => 'arrow-right' ) ),
		'badge'         => array( 'items' => false, 'defaults' => array( 'label' => '', 'variant' => 'brand' ) ),
		'alert'         => array( 'items' => false, 'defaults' => array( 'type' => 'info', 'title' => '', 'text' => '' ) ),
		'announcement'  => array( 'items' => false, 'defaults' => array( 'text' => '', 'link_label' => '', 'url' => '#' ) ),
		'breadcrumbs'   => array( 'items' => true, 'defaults' => array() ),
		'pagination'    => array( 'items' => false, 'defaults' => array( 'current' => 1, 'total' => 1, 'url_pattern' => '?paged=%d' ) ),
		'empty_state'   => array( 'items' => false, 'defaults' => array( 'icon' => 'search', 'title' => '', 'text' => '', 'label' => '', 'url' => '#' ) ),
		'hero'          => array( 'items' => false, 'defaults' => array_merge( array( 'eyebrow' => '', 'heading' => '', 'text' => '', 'image' => '' ), $cta ) ),
		'hero_centered' => array( 'items' => false, 'defaults' => array_merge( array( 'eyebrow' => '', 'heading' => '', 'text' => '', 'note' => '' ), $cta ) ),
		'cta'           => array( 'items' => false, 'defaults' => array_merge( array( 'heading' => '', 'text' => '', 'style' => 'brand' ), $cta ) ),
		'card'          => array( 'items' => false, 'defaults' => array( 'variant' => 'article', 'title' => '', 'url' => '#', 'image' => '', 'author' => '', 'excerpt' => '', 'date' => '', 'read_time' => '', 'category' => '', 'link_label' => 'Read more' ) ),
		'blog_grid'     => array( 'items' => true, 'defaults' => array( 'heading' => '', 'text' => '' ) ),
		'feature_grid'  => array( 'items' => true, 'defaults' => array( 'heading' => '', 'text' => '' ) ),
		'pricing'       => array( 'items' => true, 'defaults' => array( 'heading' => '', 'text' => '' ) ),
		'testimonials'  => array( 'items' => true, 'defaults' => array( 'heading' => '' ) ),
		'logo_cloud'    => array( 'items' => true, 'defaults' => array( 'heading' => '' ) ),
		'stats'         => array( 'items' => true, 'defaults' => array( 'heading' => '', 'text' => '' ) ),
		'faq'           => array( 'items' => true, 'defaults' => array( 'heading' => '', 'text' => '' ) ),
		'newsletter'    => array( 'items' => false, 'defaults' => array( 'heading' => '', 'text' => '', 'action' => '', 'field_name' => 'email', 'button_label' => 'Subscribe', 'placeholder' => 'Enter your email', 'note' => '' ) ),
		'contact_form'  => array( 'items' => false, 'defaults' => array( 'heading' => '', 'text' => '', 'action' => '', 'button_label' => 'Send message', 'email' => '', 'phone' => '', 'address' => '', 'hours' => '' ) ),
		'header'        => array( 'items' => true, 'defaults' => array( 'logo_text' => '', 'logo_image' => '', 'home_url' => '/', 'cta_label' => '', 'cta_url' => '#' ) ),
		'footer'        => array( 'items' => true, 'defaults' => array( 'logo_text' => '', 'text' => '', 'copyright' => '' ) ),
		'team'          => array( 'items' => true, 'defaults' => array( 'heading' => '', 'text' => '' ) ),
		'steps'         => array( 'items' => true, 'defaults' => array( 'heading' => '', 'text' => '' ) ),
		'sidebar'       => array( 'items' => true, 'defaults' => array( 'heading' => '', 'box_title' => '', 'box_text' => '', 'box_label' => '', 'box_url' => '#', 'search_action' => '' ) ),
	);
}

/** Parse the [ai_item] children of a list component. */
function ai_components_parse_items( $content ) {
	$items = array();
	if ( '' === trim( (string) $content ) ) {
		return $items;
	}
	$regex = get_shortcode_regex( array( 'ai_item' ) );
	if ( ! preg_match_all( '/' . $regex . '/', $content, $matches, PREG_SET_ORDER ) ) {
		return $items;
	}
	foreach ( $matches as $m ) {
		$atts = shortcode_parse_atts( $m[3] );
		$item = array();
		foreach ( is_array( $atts ) ? $atts : array() as $key => $value ) {
			$item[ sanitize_key( $key ) ] = sanitize_text_field( $value );
		}
		$inner = isset( $m[5] ) ? trim( wp_strip_all_tags( $m[5] ) ) : '';
		if ( '' !== $inner ) {
			$item['text']  = $item['text'] ?? $inner;
			$item['quote'] = $item['quote'] ?? $inner;
		}
		$items[] = $item;
	}
	return $items;
}

add_action(
	'init',
	function () {
		add_shortcode( 'ai_item', '__return_empty_string' ); // children are parsed by their parent.

		foreach ( ai_components_registry() as $name => $def ) {
			$template = str_replace( '_', '-', $name );
			add_shortcode(
				'ai_' . $name,
				function ( $atts, $content = '' ) use ( $template, $def, $name ) {
					$atts = shortcode_atts( $def['defaults'], $atts, 'ai_' . $name );
					$args = array_map( 'sanitize_text_field', $atts );
					if ( $def['items'] ) {
						$args['items'] = ai_components_parse_items( $content );
					}
					return ai_component( $template, $args, false );
				}
			);
		}
	}
);
