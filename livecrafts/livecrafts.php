<?php
/**
 * Plugin Name: Livecrafts
 * Description: Edit your live site with an AI assistant or by clicking on the page. Every change is saved as a draft that only editors see; visitors see it after you deploy. Edits go into the real source (Elementor, blocks, ACF, Additional CSS) and every change can be reverted.
 * Version: 0.12.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: Livecrafts
 * Text Domain: livecrafts
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'LIVECRAFTS_VERSION', '0.12.0' );
define( 'LIVECRAFTS_FILE', __FILE__ );
define( 'LIVECRAFTS_DIR', plugin_dir_path( __FILE__ ) );
define( 'LIVECRAFTS_URL', plugin_dir_url( __FILE__ ) );

require_once LIVECRAFTS_DIR . 'includes/schema.php';
require_once LIVECRAFTS_DIR . 'includes/auth.php';
require_once LIVECRAFTS_DIR . 'includes/ledger.php';
require_once LIVECRAFTS_DIR . 'includes/snapshots.php';
require_once LIVECRAFTS_DIR . 'includes/targets.php';
require_once LIVECRAFTS_DIR . 'includes/elementor.php';
require_once LIVECRAFTS_DIR . 'includes/css.php';
require_once LIVECRAFTS_DIR . 'includes/blocks.php';
require_once LIVECRAFTS_DIR . 'includes/kinds.php';
require_once LIVECRAFTS_DIR . 'includes/drafts.php';
require_once LIVECRAFTS_DIR . 'includes/preview.php';
require_once LIVECRAFTS_DIR . 'includes/watch.php';
require_once LIVECRAFTS_DIR . 'includes/deploy.php';
require_once LIVECRAFTS_DIR . 'includes/notes.php';
require_once LIVECRAFTS_DIR . 'includes/rest.php';
require_once LIVECRAFTS_DIR . 'includes/resolve.php';
require_once LIVECRAFTS_DIR . 'includes/audit.php';
require_once LIVECRAFTS_DIR . 'includes/bridge.php';
require_once LIVECRAFTS_DIR . 'includes/components.php';
require_once LIVECRAFTS_DIR . 'includes/theme-files.php';
require_once LIVECRAFTS_DIR . 'includes/assistant.php';
require_once LIVECRAFTS_DIR . 'includes/migrate.php';
require_once LIVECRAFTS_DIR . 'includes/admin.php';

/**
 * Diagnostics: WordPress hides PHP fatals behind "There has been a critical error". Remember the last one (message, file,
 * line, address) so it can be read from GET /livecrafts/v1/status (last_fatal) by an editor or the Livecrafts backend.
 */
register_shutdown_function( function () {
	$e = error_get_last();
	if ( ! $e || ! in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_RECOVERABLE_ERROR ), true ) ) return;
	if ( ! function_exists( 'update_option' ) ) return;
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? preg_replace( '/(lc_preview|_wpnonce)=[^&]*/', '$1=…', (string) $_SERVER['REQUEST_URI'] ) : '';
	update_option( 'livecrafts_last_fatal', array(
		'at' => gmdate( 'c' ), 'url' => substr( $uri, 0, 300 ), 'type' => $e['type'],
		'message' => substr( (string) $e['message'], 0, 2000 ), 'file' => str_replace( ABSPATH, '', (string) $e['file'] ), 'line' => (int) $e['line'],
	), false );
} );

register_activation_hook( __FILE__, 'livecrafts_activate' );

/** Tables, capabilities and the site secret; then move data from older versions. Safe to run more than once. */
function livecrafts_activate() {
	livecrafts_install();
	livecrafts_add_caps();
	livecrafts_secret();
	livecrafts_migrate();
}

// Updating the plugin files does not run the activation hook: catch up when the stored version is older.
add_action( 'plugins_loaded', function () {
	if ( get_option( 'livecrafts_version' ) !== LIVECRAFTS_VERSION ) {
		livecrafts_activate();
		update_option( 'livecrafts_version', LIVECRAFTS_VERSION );
	}
} );

/**
 * Who may use the editor on the site: logged-in users with the livecrafts_edit capability.
 * Never inside builder previews (the Elementor editor iframe, the Customizer).
 */
function livecrafts_user_can_edit() {
	if ( ! is_user_logged_in() || ! livecrafts_can_edit() ) return false;
	if ( isset( $_GET['elementor-preview'] ) || is_customize_preview() ) return false;
	return true;
}

/** Key identifying the page being viewed: "p<ID>" for posts/pages, "u:<md5 of path>" for archives and other URLs. */
function livecrafts_page_key() {
	if ( is_singular() || is_front_page() ) {
		$id = ( is_front_page() && get_option( 'page_on_front' ) ) ? (int) get_option( 'page_on_front' ) : (int) get_queried_object_id();
		if ( $id ) return 'p' . $id;
	}
	$path = wp_parse_url( add_query_arg( array() ), PHP_URL_PATH );
	return 'u:' . md5( $path ? $path : '/' );
}

/** The post the current page belongs to, or 0. */
function livecrafts_current_post_id() {
	return preg_match( '/^p(\d+)$/', livecrafts_page_key(), $m ) ? (int) $m[1] : 0;
}
