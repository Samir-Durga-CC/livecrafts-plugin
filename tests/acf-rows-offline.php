<?php
/**
 * Offline check of the ACF row operations (repeater / flexible content renumbering) - pure PHP, no WordPress.
 *
 *   php tests/acf-rows-offline.php
 */

define( 'ABSPATH', __DIR__ );
function add_action() {} function add_filter() {}
function maybe_unserialize( $v ) { return $v; }
require dirname( __DIR__ ) . '/livecrafts/includes/targets.php';

$fails = 0;
function check( $label, $ok, $got = null ) { global $fails; echo ( $ok ? '  ok   ' : '  FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; print_r( $got ); } }

// A repeater "cards" with 3 rows, each with a title and a nested repeater "links".
$meta = array(
	'cards' => 3, '_cards' => 'field_cards',
	'cards_0_title' => 'A', '_cards_0_title' => 'field_title', 'cards_0_links' => 1, '_cards_0_links' => 'field_links', 'cards_0_links_0_url' => 'https://a', '_cards_0_links_0_url' => 'field_url',
	'cards_1_title' => 'B', '_cards_1_title' => 'field_title', 'cards_1_links' => 0, '_cards_1_links' => 'field_links',
	'cards_2_title' => 'C', '_cards_2_title' => 'field_title', 'cards_2_links' => 0, '_cards_2_links' => 'field_links',
	'cardsextra' => 'untouched', 'other' => 'x',
);
ksort( $meta );
$titles = function ( $m ) { $t = array(); for ( $i = 0; $i < (int) $m['cards']; $i++ ) $t[] = $m[ "cards_{$i}_title" ]; return implode( '', $t ); };

$r = livecrafts_acf_rows_apply( $meta, 'cards', array( 'op' => 'remove', 'index' => 0, 'type' => 'repeater' ) );
check( 'remove first row renumbers', $titles( $r ) === 'BC' && $r['cards'] === 2 && ! isset( $r['cards_2_title'] ) && ! isset( $r['cards_0_links_0_url'] ), $r );
check( 'unrelated meta untouched', $r['cardsextra'] === 'untouched' && $r['other'] === 'x' );

$r = livecrafts_acf_rows_apply( $meta, 'cards', array( 'op' => 'move', 'index' => 0, 'to' => 2, 'type' => 'repeater' ) );
check( 'move row with its nested data', $titles( $r ) === 'BCA' && $r['cards_2_links_0_url'] === 'https://a' && $r['_cards_2_links_0_url'] === 'field_url', $r );

$r = livecrafts_acf_rows_apply( $meta, 'cards', array( 'op' => 'duplicate', 'index' => 1, 'type' => 'repeater' ) );
check( 'duplicate row', $titles( $r ) === 'ABBC' && $r['cards'] === 4, $r );

$blank = livecrafts_acf_blank_row( array( array( 'name' => 'title', 'key' => 'field_title', 'type' => 'text', 'default_value' => 'New' ), array( 'name' => 'links', 'key' => 'field_links', 'type' => 'repeater' ) ) );
$r = livecrafts_acf_rows_apply( $meta, 'cards', array( 'op' => 'add', 'index' => 1, 'row' => $blank, 'type' => 'repeater' ) );
check( 'add a blank row in the middle', $titles( $r ) === 'ANewBC' && $r['_cards_1_title'] === 'field_title' && $r['cards_1_links'] === 0, $r );

$removed = livecrafts_acf_row_meta( $meta, 'cards', 0 );
$r = livecrafts_acf_rows_apply( livecrafts_acf_rows_apply( $meta, 'cards', array( 'op' => 'remove', 'index' => 0, 'type' => 'repeater' ) ), 'cards', array( 'op' => 'add', 'index' => 0, 'row' => $removed, 'type' => 'repeater' ) );
check( 'remove then add the removed row back = original', $r == $meta, array_diff_assoc( $r, $meta ) );

$flex = array( 'sections' => array( 'hero', 'text' ), '_sections' => 'field_sections', 'sections_0_title' => 'Hi', '_sections_0_title' => 'field_hero_title', 'sections_1_body' => 'Body', '_sections_1_body' => 'field_text_body' );
$r = livecrafts_acf_rows_apply( $flex, 'sections', array( 'op' => 'move', 'index' => 1, 'to' => 0, 'type' => 'flexible_content' ) );
check( 'flexible content: layouts and data move together', $r['sections'] === array( 'text', 'hero' ) && $r['sections_0_body'] === 'Body' && $r['sections_1_title'] === 'Hi', $r );

echo $fails ? "$fails failed\n" : "all passed\n";
exit( $fails ? 1 : 0 );
