<?php
/**
 * Offline check of the Elementor tree logic (includes/elementor.php) - pure PHP, no WordPress, no Elementor.
 * Covers structure changes and their inverses, setting writes with globals, and value parsing.
 * What needs Elementor itself (its control definitions, Document::save) is not covered here.
 *
 *   php tests/elementor-offline.php
 */

define( 'ABSPATH', __DIR__ );
class WP_Error { public $c; public $m; function __construct( $c = '', $m = '', $d = null ) { $this->c = $c; $this->m = $m; } function get_error_message() { return $this->m; } function get_error_code() { return $this->c; } }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function livecrafts_bad_value( $m ) { return new WP_Error( 'bad', $m ); }
function wp_json_encode( $v ) { return json_encode( $v ); }
function livecrafts_same( $a, $b ) { return json_encode( $a ) === json_encode( $b ); }
function add_action() {} function add_filter() {}
require dirname( __DIR__ ) . '/livecrafts/includes/elementor.php';

$fails = 0;
function check( $label, $ok, $got = null ) { global $fails; echo ( $ok ? '  ok   ' : '  FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; print_r( $got ); } }

$w = function ( $id, $title ) { return array( 'id' => $id, 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => $title ), 'elements' => array() ); };
$tree = array(
	array( 'id' => 'c1', 'elType' => 'container', 'settings' => array(), 'elements' => array( $w( 'h1', 'One' ), $w( 'h2', 'Two' ) ) ),
	array( 'id' => 'c2', 'elType' => 'container', 'settings' => array(), 'elements' => array( $w( 'h3', 'Three' ) ) ),
);
$titles = function ( $t ) { $o = array(); foreach ( $t as $c ) { $o[] = $c['id'] . ':' . implode( ',', array_map( function ( $x ) { return $x['settings']['title']; }, $c['elements'] ) ); } return implode( ' | ', $o ); };

check( 'locate', livecrafts_el_locate( $tree, 'h2' ) === array( 'c1', 1 ) && livecrafts_el_locate( $tree, 'c2' ) === array( '', 1 ) );

$t = livecrafts_el_structure_apply( $tree, 'h1', 'h1', array( 'op' => 'remove' ) );
check( 'remove', $titles( $t ) === 'c1:Two | c2:Three', $t );

$used = livecrafts_el_ids( $tree );
$copy = livecrafts_el_reid( $w( 'h2', 'Two' ), $used );
check( 'new ids are unique 7-hex', preg_match( '/^[0-9a-f]{7}$/', $copy['id'] ) && ! in_array( $copy['id'], array( 'c1', 'c2', 'h1', 'h2', 'h3' ), true ) );
$t = livecrafts_el_structure_apply( $tree, 'h2', $copy, array( 'op' => 'duplicate' ) );
check( 'duplicate after the original', $titles( $t ) === 'c1:One,Two,Two | c2:Three' && $t[0]['elements'][2]['id'] === $copy['id'], $t );

$t = livecrafts_el_structure_apply( $tree, 'h1', array( 'parent' => 'c2', 'index' => 1 ), array( 'op' => 'move' ) );
check( 'move into another container', $titles( $t ) === 'c1:Two | c2:Three,One', $t );
$t = livecrafts_el_structure_apply( $tree, 'h2', array( 'parent' => 'c1', 'index' => 0 ), array( 'op' => 'move' ) );
check( 'move up within its container', $titles( $t ) === 'c1:Two,One | c2:Three', $t );

$t = livecrafts_el_structure_apply( $tree, 'c2:0', array( $w( 'n1', 'New' ) ), array( 'op' => 'insert', 'parent' => 'c2', 'index' => 0 ) );
check( 'insert at the start of a container', $titles( $t ) === 'c1:One,Two | c2:New,Three', $t );
check( 'insert past the end refused', is_wp_error( livecrafts_el_structure_apply( $tree, 'c2:5', array( $w( 'n1', 'x' ) ), array( 'op' => 'insert', 'parent' => 'c2', 'index' => 5 ) ) ) );
check( 'missing element refused', is_wp_error( livecrafts_el_structure_apply( $tree, 'zz', 'zz', array( 'op' => 'remove' ) ) ) );

// inverses (what Revert creates) give the original tree back
$ops = array(
	array( 'h1', 'h1', array( 'op' => 'remove', 'parent' => 'c1', 'index' => 0, 'removed' => $w( 'h1', 'One' ) ) ),
	array( 'h1', array( 'parent' => 'c2', 'index' => 1 ), array( 'op' => 'move', 'parent' => 'c1', 'index' => 0 ) ),
	array( 'h2', $copy, array( 'op' => 'duplicate', 'parent' => 'c1', 'index' => 1 ) ),
	array( 'c2:0', array( $w( 'n1', 'New' ) ), array( 'op' => 'insert', 'parent' => 'c2', 'index' => 0, 'ids' => array( 'n1' ) ) ),
);
foreach ( $ops as $o ) {
	list( $target, $after, $payload ) = $o;
	$t = livecrafts_el_structure_apply( $tree, $target, $after, $payload );
	list( $k, $rt, $rv ) = livecrafts_el_structure_revert( array( 'target' => $target, 'payload' => $payload + array( 'after' => $after ) ) );
	if ( $k === 'el.insert' ) {
		list( $parent, $index ) = explode( ':', $rt );
		$back = livecrafts_el_structure_apply( $t, $rt, $rv, array( 'op' => 'insert', 'parent' => $parent === 'root' ? '' : $parent, 'index' => (int) $index ) );
	} else {
		$op   = substr( $k, 3 );
		$rp   = array( 'op' => $op );
		if ( $op === 'move' ) $rv = array( 'parent' => $rv['parent'] === 'root' ? '' : $rv['parent'], 'index' => $rv['index'] );
		$back = livecrafts_el_structure_apply( $t, $rt, $rv === null ? $rt : $rv, $rp );
	}
	check( 'revert of ' . $payload['op'] . ' gives the original tree', $back === $tree, $back );
}

$s = array( 'title_color' => '#111' );
livecrafts_el_put( $s, 'title_color', 'global:globals/colors?id=primary' );
check( 'a global replaces the own value', $s['__globals__']['title_color'] === 'globals/colors?id=primary' && $s['title_color'] === '' && livecrafts_el_get( $s, 'title_color' ) === 'global:globals/colors?id=primary' );
livecrafts_el_put( $s, 'title_color', '#222' );
check( 'an own value replaces the global', ! isset( $s['__globals__']['title_color'] ) && $s['title_color'] === '#222' );
livecrafts_el_put( $s, 'title_color', '' );
check( 'empty = back to the default', ! isset( $s['title_color'] ) );

$sl = livecrafts_el_slider( array( 'size_units' => array( 'px', 'em', 'rem' ) ), '2.5rem' );
check( 'slider from "2.5rem"', $sl === array( 'unit' => 'rem', 'size' => 2.5, 'sizes' => array() ), $sl );
check( 'slider unit not allowed', is_wp_error( livecrafts_el_slider( array( 'size_units' => array( 'px' ) ), '10vw' ) ) );
$d = livecrafts_el_dimensions( array( 'size_units' => array( 'px', '%' ) ), '10px 20px' );
check( 'dimensions from "10px 20px"', $d['top'] === '10' && $d['right'] === '20' && $d['bottom'] === '10' && $d['left'] === '20' && $d['unit'] === 'px' && $d['isLinked'] === false, $d );
check( 'colour check', livecrafts_el_color( '#1d4ed8' ) === '#1d4ed8' && livecrafts_el_color( 'rgba(0, 0, 0, .5)' ) === 'rgba(0, 0, 0, .5)' && is_wp_error( livecrafts_el_color( 'red; x' ) ) );
check( 'nesting rules', livecrafts_el_child_ok( 'root', 'container' ) && livecrafts_el_child_ok( 'section', 'column' ) && ! livecrafts_el_child_ok( 'section', 'widget' ) && ! livecrafts_el_child_ok( 'root', 'widget' ) );

echo $fails ? "$fails failed\n" : "all passed\n";
exit( $fails ? 1 : 0 );
