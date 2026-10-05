<?php
/**
 * Plugin Name: Livecrafts
 * Description: Click any element on any page and edit its text and style visually. Works on any theme or page builder because edits are saved as non-destructive "patches" (CSS + text) instead of changing theme files.
 * Version: 0.9.2
 * Author: Livecrafts
 * Text Domain: livecrafts
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'LIVECRAFTS_VERSION', '0.9.2' );
define( 'LIVECRAFTS_DIR', plugin_dir_path( __FILE__ ) );
define( 'LIVECRAFTS_URL', plugin_dir_url( __FILE__ ) );

require_once LIVECRAFTS_DIR . 'includes/store.php';
require_once LIVECRAFTS_DIR . 'includes/targets.php';
require_once LIVECRAFTS_DIR . 'includes/elementor.php';
require_once LIVECRAFTS_DIR . 'includes/rest.php';
require_once LIVECRAFTS_DIR . 'includes/audit.php';
require_once LIVECRAFTS_DIR . 'includes/bridge.php';
require_once LIVECRAFTS_DIR . 'includes/theme-files.php';
require_once LIVECRAFTS_DIR . 'includes/assistant.php';
require_once LIVECRAFTS_DIR . 'includes/admin.php';

/**
 * Who may use the editor: any logged-in user who can edit pages.
 * Never load it inside builder previews (Elementor editor iframe, Customizer).
 */
function livecrafts_user_can_edit() {
	if ( ! is_user_logged_in() || ! current_user_can( 'edit_pages' ) ) return false;
	if ( isset( $_GET['elementor-preview'] ) || is_customize_preview() ) return false;
	return true;
}

/**
 * 1) Saved style patches -> real CSS in <head> for EVERY visitor (server-rendered, no flash).
 *    !important so patches beat theme / Elementor / inline styles.
 */
add_action( 'wp_head', function () {
	$css = livecrafts_build_css( livecrafts_patches_for_page() );
	if ( $css !== '' ) echo "<style id=\"livecrafts-patches\">\n" . $css . "</style>\n";
}, 100 );

/**
 * 2) Saved text patches, without a "flash of old text".
 *    a) <head>: hide ONLY the elements that have a text patch (visibility keeps their layout, so nothing jumps).
 *    b) end of <body>: an inline script (no network request) swaps the text, then un-hides them.
 *    c) failsafe: if that script never runs, un-hide after 3s so content can never stay hidden.
 */
add_action( 'wp_head', function () {
	$text = livecrafts_text_patches( livecrafts_patches_for_page() );
	if ( ! $text ) return;
	$css = '';
	foreach ( array_keys( $text ) as $sel ) $css .= $sel . '{visibility:hidden!important}';
	echo '<style id="livecrafts-hide">' . $css . "</style>\n";
	echo '<script>setTimeout(function(){var s=document.getElementById("livecrafts-hide");if(s)s.remove();},3000);</script>' . "\n";
}, 99 );

add_action( 'wp_footer', function () {
	$text = livecrafts_text_patches( livecrafts_patches_for_page() );
	if ( ! $text ) return;
	$json = wp_json_encode( $text, JSON_HEX_TAG | JSON_HEX_AMP );
	echo '<script id="livecrafts-apply">(function(){var m=' . $json . ';Object.keys(m).forEach(function(s){try{var e=document.querySelector(s);if(e)e.textContent=m[s];}catch(x){}});var h=document.getElementById("livecrafts-hide");if(h)h.remove();})();</script>' . "\n";
}, 1 );

/**
 * 3) The editor itself -> only for logged-in editors.
 */
add_action( 'wp_enqueue_scripts', function () {
	if ( is_admin() || ! livecrafts_user_can_edit() || ! livecrafts_classic_enabled() ) return; // the chat widget replaces it unless turned on

	wp_enqueue_media(); // WordPress Media Library picker (wp.media) for image fields
	wp_enqueue_script( 'livecrafts-editor', LIVECRAFTS_URL . 'assets/editor.js', array(), LIVECRAFTS_VERSION, true );

	$key = livecrafts_page_key();
	$all = livecrafts_get_all();
	wp_localize_script( 'livecrafts-editor', 'LIVECRAFTS', array(
		'restUrl' => esc_url_raw( rest_url( 'livecrafts/v1' ) ),
		'nonce'   => wp_create_nonce( 'wp_rest' ),
		'pageKey' => $key,
		'patches' => array(
			'site' => isset( $all['site'] ) ? (object) $all['site'] : new stdClass(),
			'page' => isset( $all[ $key ] ) ? (object) $all[ $key ] : new stdClass(),
		),
		'props'   => livecrafts_allowed_props(),
	) );
}, 30 );
