<?php
/**
 * Offline check of the component-source adapter in includes/lcs-edit.php - no database, no running site.
 *
 *   php tests/lcs-src-offline.php
 *
 * What it covers: reading a component address (theme / el / block / divi / content), the conflict group each one maps
 * to, and a full round trip through the kind - read the stored markup, edit one part of it, write it back - for the
 * two stores that need nothing of WordPress's own parsers. The Elementor, block and Divi stores are exercised against
 * a real site (see the verification steps in the plan), because they go through Elementor's tree, the block parser and
 * Divi's tokenizer.
 */
if ( PHP_SAPI !== 'cli' ) { fwrite( STDERR, "Run this from the command line.\n" ); exit( 1 ); }

define( 'ABSPATH', 'x' );

class WP_Error {
	public $code;
	public $message;
	function __construct( $code = '', $message = '', $data = null ) { $this->code = $code; $this->message = $message; }
	function get_error_message() { return $this->message; }
}
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function livecrafts_bad_value( $m ) { return new WP_Error( 'livecrafts_bad_value', $m ); }

function add_action() {}
function add_filter() {}
function do_shortcode( $s ) { return $s; }
function get_post_meta() { return ''; }
function update_post_meta() {}
function get_option( $n, $d = '' ) { return $d; }
function update_option() {}
function is_admin() { return false; }
function did_action() { return true; }
function is_front_page() { return false; }
function is_singular() { return false; }
function get_queried_object_id() { return 0; }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }
function wp_kses_post( $s ) { return $s; }
function esc_url_raw( $u ) { return (string) $u; }
function absint( $v ) { return abs( (int) $v ); }
// kinds.php declares livecrafts_sanitize_post_field itself, so stub what WordPress gives it instead.
function sanitize_post_field( $field, $value, $post_id, $context ) { return (string) $value; }
function wp_slash( $v ) { return $v; }
function wp_unslash( $v ) { return $v; }
function livecrafts_user_can_edit() { return true; }
function livecrafts_preview_token_request() { return false; }
function in_the_loop() { return false; }
function is_main_query() { return false; }
function has_blocks( $c ) { return strpos( (string) $c, '<!-- wp:' ) !== false; }
function get_the_ID() { return 0; }
function livecrafts_el_active() { return false; }

// This PHP build may be without mbstring; the plugin only ever uses these two for trimming summaries.
if ( ! function_exists( 'mb_substr' ) ) { function mb_substr( $s, $start, $len = null ) { return $len === null ? substr( (string) $s, $start ) : substr( (string) $s, $start, $len ); } }
if ( ! function_exists( 'mb_strlen' ) ) { function mb_strlen( $s ) { return strlen( (string) $s ); } }

function user_can() { return true; }

require_once __DIR__ . '/../livecrafts/includes/sections.php';
require_once __DIR__ . '/../livecrafts/includes/css.php';
require_once __DIR__ . '/../livecrafts/includes/kinds.php';
require_once __DIR__ . '/../livecrafts/includes/lcs-edit.php';

$fails = 0;
function ok( $what, $cond, $got = null ) {
	global $fails;
	if ( $cond ) { echo "  ok   $what\n"; return; }
	$fails++;
	echo "  FAIL $what" . ( $got === null ? '' : ' -- got ' . var_export( $got, true ) ) . "\n";
}

/* ------------------------------------------------------------------ addresses */

echo "component addresses\n";
$cases = array(
	'theme:end'                         => array( 'theme', 7, 'end' ),
	'theme:before:template-parts/hero'  => array( 'theme', 7, 'before:template-parts/hero' ),
	'content:5'                         => array( 'content', 5, '' ),
	'el:12:3f2a1c'                      => array( 'el', 12, '3f2a1c' ),
	'block:5:2.1'                       => array( 'block', 5, '2.1' ),
	'divi:5:1.0.0'                      => array( 'divi', 5, '1.0.0' ),
);
foreach ( $cases as $src => $want ) {
	ok( 'read ' . $src, livecrafts_src_parse( $src, 7 ) === $want, livecrafts_src_parse( $src, 7 ) );
}
foreach ( array( 'theme:nowhere', 'el:12:$$', 'block:5:x', 'nonsense', '', 'content:' ) as $bad ) {
	ok( 'refuse ' . var_export( $bad, true ), livecrafts_src_parse( $bad, 7 ) === null );
}

echo "conflict groups match the store's own kind\n";
ok( 'a section row groups by its anchor', livecrafts_src_group( 'theme:end|0|1.2|text' ) === 'target:end' );
ok( 'a widget groups by its setting', livecrafts_src_group( 'el:12:3f2a1c|0||text' ) === 'target:3f2a1c:editor' );
ok( 'a block groups with the page content', livecrafts_src_group( 'block:5:2.1|0||text' ) === 'content' );
ok( 'raw content groups with the page content', livecrafts_src_group( 'content:5|1||text' ) === 'content' );

/* ------------------------------------------------------------------ a theme-section row */

$two = '<div class="lcs-host"><section class="lcs lcs-a" data-lcs="a"><div class="lcs-in"><h2 class="lcs-h">First</h2></div></section></div>'
	. '<div class="lcs-host"><section class="lcs lcs-b" data-lcs="b"><div class="lcs-in"><h2 class="lcs-h">Second</h2><a href="/old">Go</a></div></section></div>';
$draft = array( 'fields' => array( 'content' => '' ), 'meta' => array( LIVECRAFTS_SECTIONS_META => array( array( 'anchor' => 'end', 'content' => $two ) ) ) );

echo "a component in a theme-section row\n";
ok( 'the row is found', livecrafts_src_markup( array( 'theme', 7, 'end' ), $draft ) === $two );

$r = livecrafts_kind_lcs_edit_prepare( 7, 'theme:end|1|0.0|text', 'Changed', $draft );
ok( 'editing the second section is prepared', is_array( $r ) && isset( $r['after'] ), is_wp_error( $r ) ? $r->get_error_message() : null );
ok( 'the second section got the new text', is_array( $r ) && strpos( $r['after'], '>Changed<' ) !== false );
ok( 'the first section is untouched', is_array( $r ) && strpos( $r['after'], '>First<' ) !== false );

$data = $draft;
ok( 'writing it back lands in the row', livecrafts_src_write( array( 'theme', 7, 'end' ), $data, $r['after'] ) === true
	&& $data['meta'][ LIVECRAFTS_SECTIONS_META ][0]['content'] === $r['after'] );

$link = livecrafts_kind_lcs_edit_prepare( 7, 'theme:end|1|0.1|link', '/new', $draft );
ok( 'a link is changed at its source', is_array( $link ) && strpos( $link['after'], 'href="/new"' ) !== false, is_wp_error( $link ) ? $link->get_error_message() : null );

$gone = livecrafts_kind_lcs_edit_prepare( 7, 'theme:end|0||remove', '', $draft );
ok( 'removing one of two leaves the other', is_array( $gone ) && strpos( $gone['after'], 'lcs-a' ) === false && strpos( $gone['after'], 'lcs-b' ) !== false );

$one  = array( 'fields' => array( 'content' => '' ), 'meta' => array( LIVECRAFTS_SECTIONS_META => array( array( 'anchor' => 'end', 'content' => '<div class="lcs-host"><section class="lcs" data-lcs="a"><p>Only</p></section></div>' ) ) ) );
$last = livecrafts_kind_lcs_edit_prepare( 7, 'theme:end|0||remove', '', $one );
ok( 'removing the only one is refused, not silently emptied', is_wp_error( $last ) );

echo "mistakes are refused\n";
ok( 'a short target', is_wp_error( livecrafts_kind_lcs_edit_prepare( 7, 'theme:end|0', 'x', $draft ) ) );
ok( 'a path that is not indexes', is_wp_error( livecrafts_kind_lcs_edit_prepare( 7, 'theme:end|0|a.b|text', 'x', $draft ) ) );
ok( 'another page\'s component', is_wp_error( livecrafts_kind_lcs_edit_prepare( 7, 'content:99|0||text', 'x', $draft ) ) );
ok( 'a store that is empty', is_wp_error( livecrafts_kind_lcs_edit_prepare( 7, 'theme:start|0||text', 'x', $draft ) ) );
ok( 'a section index past the end', is_wp_error( livecrafts_kind_lcs_edit_prepare( 7, 'theme:end|9|0.0|text', 'x', $draft ) ) );
ok( 'empty text', is_wp_error( livecrafts_kind_lcs_edit_prepare( 7, 'theme:end|1|0.0|text', '   ', $draft ) ) );

/* ------------------------------------------------------------------ raw page content */

echo "a component written into the page content\n";
$content = '<p>Intro</p>' . $two . '<p>Outro</p>';
$page    = array( 'fields' => array( 'content' => $content ), 'meta' => array() );
ok( 'the whole content is the store', livecrafts_src_markup( array( 'content', 5, '' ), $page ) === $content );

$c = livecrafts_kind_lcs_edit_prepare( 5, 'content:5|1|0.0|text', 'Second edited', $page );
ok( 'the index counts across the whole content', is_array( $c ) && strpos( $c['after'], '>Second edited<' ) !== false, is_wp_error( $c ) ? $c->get_error_message() : null );
ok( 'everything around it survives', is_array( $c ) && strpos( $c['after'], '<p>Intro</p>' ) === 0 && substr( $c['after'], -12 ) === '<p>Outro</p>', is_array( $c ) ? substr( $c['after'], -40 ) : null );

$w = $page;
ok( 'writing it back replaces the content', livecrafts_src_write( array( 'content', 5, '' ), $w, $c['after'] ) === true && $w['fields']['content'] === $c['after'] );

/* ------------------------------------------------------------------ hiding a component */

echo "hiding a component is marked for removal on deploy\n";
$css  = array( 'css' => '' );
$hide = function ( $decl, $component ) use ( $css ) {
	return livecrafts_kind_css_rule_prepare( 0, '', array( 'selector' => 'body.page-id-5 section.lcs', 'media' => '', 'declarations' => $decl, 'component' => $component ), $css, array( 'label' => 'Section' ), 1 );
};

$h = $hide( array( 'display' => 'none' ), '5|theme:end|1' );
ok( 'a hide carries the component it hides', is_array( $h ) && isset( $h['payload']['lc_hide'] ) && $h['payload']['lc_hide'] === '5|theme:end|1', is_wp_error( $h ) ? $h->get_error_message() : ( is_array( $h ) ? $h['payload'] : null ) );
ok( 'and says so in the summary', is_array( $h ) && strpos( $h['summary'], 'taken out of the page when deployed' ) !== false );

$e = $hide( array( 'display' => 'none' ), '' );
ok( 'hiding something that is not a component stays a plain rule', is_array( $e ) && ! isset( $e['payload']['lc_hide'] ) );

$b = $hide( array( 'display' => 'none' ), 'theme:end|1' );
ok( 'an address without a page is not trusted', is_array( $b ) && ! isset( $b['payload']['lc_hide'] ) );

$m = $hide( array( 'display' => 'none', 'color' => '#fff' ), '5|theme:end|1' );
ok( 'a rule that also styles is not a removal', is_array( $m ) && ! isset( $m['payload']['lc_hide'] ), is_wp_error( $m ) ? $m->get_error_message() : null );

$s = $hide( array( 'color' => '#fff' ), '5|theme:end|1' );
ok( 'an ordinary style on a component is untouched', is_array( $s ) && ! isset( $s['payload']['lc_hide'] ) && strpos( $s['summary'], 'Style' ) === 0 );

echo $fails ? "\n$fails failed\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
