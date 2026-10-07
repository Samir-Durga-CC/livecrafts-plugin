<?php
/**
 * Offline check of includes/blocks.php with WordPress's own block parser and HTML API - no database, no running site.
 *
 *   php tests/blocks-offline.php <path to a WordPress install (only its wp-includes is read)>
 */
if ( PHP_SAPI !== 'cli' || empty( $argv[1] ) ) { fwrite( STDERR, "Usage: php tests/blocks-offline.php <path to WordPress>\n" ); exit( 1 ); }
$wpinc = rtrim( $argv[1], '/\\' ) . '/wp-includes';
define( 'ABSPATH', 'x' );
class WP_Error { public $c; public $m; function __construct( $c = '', $m = '', $d = null ) { $this->c = $c; $this->m = $m; } function get_error_message() { return $this->m; } }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function apply_filters( $h, $v ) { return $v; }
function add_filter() {} function add_action() {} function _doing_it_wrong() {} function __( $s ) { return $s; } function esc_attr( $s ) { return htmlspecialchars( $s, ENT_QUOTES ); }
function wp_kses( $s ) { return $s; } function esc_url_raw( $u ) { return $u; } function sanitize_html_class( $c ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', $c ); }
function wp_has_noncharacters() { return false; } function wp_check_invalid_utf8( $s ) { return $s; } function wp_kses_bad_protocol( $s ) { return $s; } function esc_url( $u ) { return $u; }
function wp_kses_uri_attributes() { return array( "href", "src" ); }
function absint( $v ) { return abs( (int) $v ); }
function livecrafts_bad_value( $m ) { return new WP_Error( 'bad', $m ); }
function livecrafts_sanitize_post_field( $f, $v ) { return $v; }
function livecrafts_quote( $v ) { return '"' . $v . '"'; }
function wp_attachment_is_image() { return true; } function wp_get_attachment_url( $id ) { return "https://x.test/img$id.jpg"; } function get_post_meta() { return 'Alt'; }
function get_attachment_link() { return ''; } function livecrafts_user_can_edit() { return false; }
class WP_Block_Type_Registry { static function get_instance() { return new self; } function is_registered() { return true; } function get_registered() { return null; } }
require "$wpinc/class-wp-block-parser-block.php";
require "$wpinc/class-wp-block-parser-frame.php";
require "$wpinc/class-wp-block-parser.php";
foreach ( array( 'class-wp-html-attribute-token', 'class-wp-html-span', 'class-wp-html-text-replacement', 'class-wp-html-decoder', 'class-wp-html-tag-processor' ) as $f ) if ( file_exists( "$wpinc/html-api/$f.php" ) ) require "$wpinc/html-api/$f.php";
// the few functions of wp-includes/blocks.php we need
function parse_blocks( $c ) { $p = new WP_Block_Parser(); return $p->parse( $c ); }
function strip_core_block_namespace( $n = null ) { return is_string( $n ) && str_starts_with( $n, 'core/' ) ? substr( $n, 5 ) : $n; }
function serialize_block_attributes( $a ) { $e = json_encode( $a, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); $e = preg_replace( '/--/', '\\u002d\\u002d', $e ); $e = preg_replace( '/</', '\\u003c', $e ); $e = preg_replace( '/>/', '\\u003e', $e ); $e = preg_replace( '/&/', '\\u0026', $e ); $e = preg_replace( '/\\\\"/', '\\u0022', $e ); return $e; }
function get_comment_delimited_block_content( $n, $a, $c ) { if ( is_null( $n ) ) return $c; $s = strip_core_block_namespace( $n ); $sa = empty( $a ) ? '' : serialize_block_attributes( $a ) . ' '; if ( empty( $c ) ) return sprintf( '<!-- wp:%s %s/-->', $s, $sa ); return sprintf( '<!-- wp:%s %s-->%s<!-- /wp:%s -->', $s, $sa, $c, $s ); }
function serialize_block( $b ) { $c = ''; $i = 0; foreach ( $b['innerContent'] as $ch ) { $c .= is_string( $ch ) ? $ch : serialize_block( $b['innerBlocks'][ $i++ ] ); } if ( ! is_array( $b['attrs'] ) ) $b['attrs'] = array(); return get_comment_delimited_block_content( $b['blockName'], $b['attrs'], $c ); }
function serialize_blocks( $bs ) { return implode( '', array_map( 'serialize_block', $bs ) ); }
function has_blocks( $c ) { return false !== strpos( (string) $c, '<!-- wp:' ); }
require dirname( __DIR__ ) . '/livecrafts/includes/blocks.php';

$fails = 0;
function check( $label, $ok, $got = '' ) { global $fails; echo ( $ok ? "  ok   " : "  FAIL " ) . $label . ( $ok ? '' : "\n" . print_r( $got, true ) ) . "\n"; if ( ! $ok ) $fails++; }
function run( $content, $op, $target, $value ) {
	$draft = array( 'fields' => array( 'content' => $content ) );
	$p = livecrafts_block_prepare( $op, 1, $target, $value, $draft );
	if ( is_wp_error( $p ) ) return $p;
	if ( ! empty( $p['unchanged'] ) ) return 'unchanged';
	$blocks = livecrafts_block_apply( parse_blocks( $content ), $p['target'], $p['after'], $p['payload'] );
	return is_wp_error( $blocks ) ? $blocks : array( serialize_blocks( $blocks ), $p );
}
$page = "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Welcome</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>First <strong>para</strong></p>\n<!-- /wp:paragraph -->\n\n<!-- wp:group {\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group\"><!-- wp:paragraph -->\n<p>Inner A</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Inner B</p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:group -->\n\n<!-- wp:buttons -->\n<div class=\"wp-block-buttons\"><!-- wp:button -->\n<div class=\"wp-block-button\"><a class=\"wp-block-button__link wp-element-button\" href=\"https://old.test\">Go</a></div>\n<!-- /wp:button --></div>\n<!-- /wp:buttons -->";

check( 'round trip is byte-identical', serialize_blocks( parse_blocks( $page ) ) === $page );
list( $out ) = run( $page, 'text', '0', 'Hello <em>there</em>' );
check( 'heading text', strpos( $out, '<h2 class="wp-block-heading">Hello <em>there</em></h2>' ) !== false && strpos( $out, 'First <strong>para</strong>' ) !== false, $out );
list( $out ) = run( $page, 'text', '2.1', 'Inner B2' );
check( 'nested paragraph text', strpos( $out, '<p>Inner B2</p>' ) !== false && strpos( $out, '<p>Inner A</p>' ) !== false, $out );
list( $out ) = run( $page, 'text', '3.0', 'Start now' );
check( 'button text', strpos( $out, 'href="https://old.test">Start now</a>' ) !== false, $out );
list( $out ) = run( $page, 'link', '3.0', 'https://new.test/x' );
check( 'button link', strpos( $out, 'href="https://new.test/x"' ) !== false && strpos( $out, 'old.test' ) === false, $out );
check( 'text on a group refused', is_wp_error( run( $page, 'text', '2', 'x' ) ) );
check( 'same text = unchanged', run( $page, 'text', '1', 'First <strong>para</strong>' ) === 'unchanged' );
list( $out ) = run( $page, 'remove', '1', 1 );
check( 'remove top-level block, gap removed too', strpos( $out, 'First' ) === false && substr_count( $out, "\n\n\n" ) === 0 && count( array_filter( parse_blocks( $out ), function ( $b ) { return $b['blockName']; } ) ) === 3, $out );
list( $out ) = run( $page, 'remove', '2.0', 1 );
check( 'remove inner block', strpos( $out, 'Inner A' ) === false && strpos( $out, "<div class=\"wp-block-group\"><!-- wp:paragraph -->\n<p>Inner B</p>" ) !== false, $out );
list( $out ) = run( $page, 'duplicate', '2.1', null );
check( 'duplicate inner block', substr_count( $out, 'Inner B' ) === 2, $out );
list( $out ) = run( $page, 'move', '1', 'up' );
check( 'move up', strpos( $out, 'First' ) < strpos( $out, 'Welcome' ), $out );
list( $out ) = run( $page, 'move', '2.0', 'down' );
check( 'move inner down', strpos( $out, 'Inner B' ) < strpos( $out, 'Inner A' ), $out );
list( $out ) = run( $page, 'insert', ':end', "<!-- wp:paragraph -->\n<p>Last</p>\n<!-- /wp:paragraph -->" );
check( 'insert at the end', substr( $out, -strlen( "<p>Last</p>\n<!-- /wp:paragraph -->" ) ) === "<p>Last</p>\n<!-- /wp:paragraph -->" && strpos( $out, "<!-- /wp:buttons -->\n\n<!-- wp:paragraph -->" ) !== false, $out );
list( $out ) = run( $page, 'insert', ':0', "<!-- wp:paragraph -->\n<p>Top</p>\n<!-- /wp:paragraph -->" );
check( 'insert at the top', strpos( $out, "<p>Top</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->" ) === strpos( $out, '<p>Top</p>' ), $out );
list( $out ) = run( $page, 'insert', '2:1', "<!-- wp:paragraph -->\n<p>Between</p>\n<!-- /wp:paragraph -->" );
check( 'insert inside a group', strpos( $out, 'Inner A' ) < strpos( $out, 'Between' ) && strpos( $out, 'Between' ) < strpos( $out, 'Inner B' ), $out );
check( 'loose HTML refused', is_wp_error( run( $page, 'insert', ':0', '<p>no block</p>' ) ) );
list( $out ) = run( $page, 'class', '0', 'lc-hero' );
check( 'add class (attrs + markup)', strpos( $out, '<!-- wp:heading {"className":"lc-hero"} -->' ) !== false && strpos( $out, 'class="wp-block-heading lc-hero"' ) !== false, $out );
$img = "<!-- wp:image {\"id\":5,\"sizeSlug\":\"large\"} -->\n<figure class=\"wp-block-image size-large\"><img src=\"https://x.test/old.jpg\" alt=\"\" class=\"wp-image-5\"/></figure>\n<!-- /wp:image -->";
list( $out ) = run( $img, 'image', '0', 9 );
check( 'image block', strpos( $out, '"id":9' ) !== false && strpos( $out, 'src="https://x.test/img9.jpg"' ) !== false && strpos( $out, 'wp-image-9' ) !== false && strpos( $out, 'wp-image-5' ) === false, $out );
list( $out, $p ) = run( $page, 'replace', '1', "<!-- wp:paragraph -->\n<p>Replaced</p>\n<!-- /wp:paragraph -->" );
check( 'replace', strpos( $out, 'Replaced' ) !== false && strpos( $out, 'First' ) === false, $out );
// revert round trips: apply, then apply the inverse -> original content
foreach ( array( array( 'remove', '1', 1 ), array( 'move', '1', 'up' ), array( 'duplicate', '2.1', null ), array( 'text', '0', 'X' ), array( 'insert', '2:1', "<!-- wp:paragraph -->\n<p>B</p>\n<!-- /wp:paragraph -->" ) ) as $case ) {
	list( $out, $p ) = run( $page, $case[0], $case[1], $case[2] );
	$c = array( 'target' => $p['target'], 'payload' => array_merge( $p['payload'], array( 'after' => $p['after'] ) ) );
	list( $k, $t, $v ) = livecrafts_block_revert( $c );
	list( $back ) = run( $out, substr( $k, 6 ), $t, $v );
	check( "revert of {$case[0]} gives the original back", $back === $page, $back );
}
echo $fails ? "$fails failed\n" : "all passed\n";
exit( $fails ? 1 : 0 );
