<?php
/**
 * Livecrafts updates itself from its own update feed (1.0.0).
 *
 * WordPress asks the host in the plugin header's "Update URI" what the latest version is; the feed is two static
 * files per channel on Hostinger (livecrafts.json + the zip), published by the release workflow. The site then
 * updates Livecrafts from wp-admin > Plugins like any other plugin, with WordPress's own upgrader.
 *
 * This is deliberately NOT the path in plugin-updates.php: that one updates OTHER plugins inside a REST request
 * with a backup and a health check. A plugin cannot replace its own files while it is the code that is running,
 * so Livecrafts itself is left to the admin-side upgrader, which runs after this plugin has finished answering.
 *
 * Channels: stable (client sites) - staging (test sites) - off (nothing is offered, upload the zip by hand).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const LIVECRAFTS_UPDATE_HOST     = 'project-in-progress.com';
const LIVECRAFTS_UPDATE_BASE     = 'https://project-in-progress.com/updates';
const LIVECRAFTS_UPDATE_CHANNELS = array( 'stable', 'staging', 'off' );

function livecrafts_update_channel() {
	$c = (string) get_option( 'livecrafts_update_channel', 'stable' );
	return in_array( $c, LIVECRAFTS_UPDATE_CHANNELS, true ) ? $c : 'stable';
}

function livecrafts_update_forget() {
	foreach ( LIVECRAFTS_UPDATE_CHANNELS as $c ) delete_site_transient( 'livecrafts_update_' . $c );
}

/**
 * The channel's manifest, or null when the channel is off, the feed is unreachable or the answer is not a manifest.
 * Cached for 15 minutes so a site that checks twice in a row does not fetch twice; a failure is cached for 5, so a
 * broken feed is not asked again on every page of wp-admin.
 */
function livecrafts_update_manifest( $force = false ) {
	$channel = livecrafts_update_channel();
	if ( 'off' === $channel ) return null;
	$key = 'livecrafts_update_' . $channel;
	if ( ! $force ) {
		$cached = get_site_transient( $key );
		if ( is_array( $cached ) ) return $cached ? $cached : null;
	}
	// The site and version travel with the request so the feed can show who is on what; nothing else is sent.
	$url = add_query_arg(
		array( 'site' => rawurlencode( untrailingslashit( home_url() ) ), 'version' => LIVECRAFTS_VERSION ),
		LIVECRAFTS_UPDATE_BASE . '/' . $channel . '/livecrafts.json'
	);
	$res  = wp_remote_get( $url, array( 'timeout' => 10, 'headers' => array( 'Accept' => 'application/json' ) ) );
	$data = ( ! is_wp_error( $res ) && 200 === (int) wp_remote_retrieve_response_code( $res ) )
		? json_decode( (string) wp_remote_retrieve_body( $res ), true )
		: null;
	if ( ! is_array( $data ) || empty( $data['version'] ) || empty( $data['download_url'] ) ) {
		set_site_transient( $key, array(), 5 * MINUTE_IN_SECONDS );
		return null;
	}
	set_site_transient( $key, $data, 15 * MINUTE_IN_SECONDS );
	return $data;
}

/** The manifest when it offers something newer than the version running, otherwise null. */
function livecrafts_update_available( $force = false ) {
	$m = livecrafts_update_manifest( $force );
	return ( $m && version_compare( (string) $m['version'], LIVECRAFTS_VERSION, '>' ) ) ? $m : null;
}

// WordPress asks this once per update check, only for plugins whose Update URI host is ours.
add_filter( 'update_plugins_' . LIVECRAFTS_UPDATE_HOST, function ( $update, $plugin_data, $plugin_file ) {
	if ( plugin_basename( LIVECRAFTS_FILE ) !== $plugin_file ) return $update;
	$m = livecrafts_update_available();
	if ( ! $m ) return $update;
	return array(
		'id'           => LIVECRAFTS_UPDATE_HOST . '/livecrafts',
		'slug'         => 'livecrafts',
		'plugin'       => $plugin_file,
		'version'      => (string) $m['version'],
		'url'          => isset( $m['homepage'] ) ? (string) $m['homepage'] : LIVECRAFTS_UPDATE_BASE,
		'package'      => (string) $m['download_url'],
		'tested'       => isset( $m['tested'] ) ? (string) $m['tested'] : '',
		'requires'     => isset( $m['requires'] ) ? (string) $m['requires'] : '',
		'requires_php' => isset( $m['requires_php'] ) ? (string) $m['requires_php'] : '',
	);
}, 10, 3 );

// "View details" in the plugins list.
add_filter( 'plugins_api', function ( $result, $action, $args ) {
	if ( 'plugin_information' !== $action || empty( $args->slug ) || 'livecrafts' !== $args->slug ) return $result;
	$m = livecrafts_update_manifest();
	if ( ! $m ) return $result;
	return (object) array(
		'name'          => isset( $m['name'] ) ? (string) $m['name'] : 'Livecrafts',
		'slug'          => 'livecrafts',
		'version'       => (string) $m['version'],
		'author'        => 'Livecrafts',
		'homepage'      => isset( $m['homepage'] ) ? (string) $m['homepage'] : LIVECRAFTS_UPDATE_BASE,
		'requires'      => isset( $m['requires'] ) ? (string) $m['requires'] : '',
		'requires_php'  => isset( $m['requires_php'] ) ? (string) $m['requires_php'] : '',
		'tested'        => isset( $m['tested'] ) ? (string) $m['tested'] : '',
		'last_updated'  => isset( $m['last_updated'] ) ? (string) $m['last_updated'] : '',
		'download_link' => (string) $m['download_url'],
		'sections'      => isset( $m['sections'] ) && is_array( $m['sections'] ) ? array_map( 'wp_kses_post', $m['sections'] ) : array(),
	);
}, 10, 3 );

// "Check again" on the Updates screen must really ask the feed again, not read our own cache.
add_action( 'admin_init', function () {
	if ( isset( $_GET['force-check'] ) && current_user_can( 'update_plugins' ) ) livecrafts_update_forget(); // phpcs:ignore WordPress.Security.NonceVerification -- clears a cache only
} );

// ---- Settings > Livecrafts: which channel this site follows.
add_action( 'livecrafts_admin_sections', function () {
	$channel = livecrafts_update_channel();
	$m       = livecrafts_update_manifest();
	$new     = livecrafts_update_available();
	echo '<h2 style="margin-top:32px">Updates</h2>';
	echo '<p>This site runs Livecrafts <strong>' . esc_html( LIVECRAFTS_VERSION ) . '</strong>.</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'livecrafts_update_channel' );
	echo '<input type="hidden" name="action" value="livecrafts_update_channel" />';
	echo '<p><label for="lc-channel">Update channel </label><select name="channel" id="lc-channel">';
	$labels = array(
		'stable'  => 'Stable - tested releases (use this on client sites)',
		'staging' => 'Staging - every release being tested (use this on test sites)',
		'off'     => 'Off - never offer updates, install the zip by hand',
	);
	foreach ( $labels as $value => $label ) {
		echo '<option value="' . esc_attr( $value ) . '"' . selected( $channel, $value, false ) . '>' . esc_html( $label ) . '</option>';
	}
	echo '</select> <button class="button button-primary">Save</button> <button class="button" name="check" value="1">Save and check now</button></p>';
	echo '</form>';
	if ( 'off' === $channel ) {
		echo '<p>Updates are switched off for this site.</p>';
	} elseif ( $new ) {
		echo '<p><strong>Version ' . esc_html( $new['version'] ) . ' is available.</strong> Install it from <a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">Plugins</a>.</p>';
	} elseif ( $m ) {
		echo '<p>Up to date - the ' . esc_html( $channel ) . ' channel also offers ' . esc_html( $m['version'] ) . '.</p>';
	} else {
		echo '<p>The update feed could not be reached just now. Nothing is broken; WordPress will ask again later.</p>';
	}
} );

add_action( 'admin_post_livecrafts_update_channel', function () {
	if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Not allowed' );
	check_admin_referer( 'livecrafts_update_channel' );
	$channel = isset( $_POST['channel'] ) ? sanitize_key( wp_unslash( $_POST['channel'] ) ) : 'stable';
	update_option( 'livecrafts_update_channel', in_array( $channel, LIVECRAFTS_UPDATE_CHANNELS, true ) ? $channel : 'stable', false );
	livecrafts_update_forget();
	if ( ! empty( $_POST['check'] ) ) {
		livecrafts_update_available( true );
		delete_site_transient( 'update_plugins' ); // make WordPress ask again too, so Plugins shows it at once
	}
	wp_safe_redirect( add_query_arg( 'lc', 'channel', admin_url( 'options-general.php?page=livecrafts' ) ) );
	exit;
} );
