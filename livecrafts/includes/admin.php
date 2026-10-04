<?php
/**
 * Settings > Livecrafts: see every saved patch and the activity log; remove patches.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', function () {
	add_options_page( 'Livecrafts', 'Livecrafts', 'manage_options', 'livecrafts', 'livecrafts_admin_page' );
} );

add_action( 'admin_post_livecrafts_remove', function () {
	if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Not allowed' );
	check_admin_referer( 'livecrafts_remove' );
	$key = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
	$sel = isset( $_POST['selector'] ) ? wp_unslash( $_POST['selector'] ) : '';
	if ( $key === '__all__' ) {
		delete_option( LIVECRAFTS_OPT );
	} elseif ( livecrafts_valid_key( $key ) ) {
		livecrafts_set_patch( $key, livecrafts_clean_selector( $sel ), null );
	}
	wp_safe_redirect( admin_url( 'options-general.php?page=livecrafts' ) );
	exit;
} );

function livecrafts_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) return;
	$all = livecrafts_get_all();
	$log = array_reverse( (array) get_option( LIVECRAFTS_LOG, array() ) );
	echo '<div class="wrap"><h1>Livecrafts</h1>';
	echo '<p>Log in, open any page on the site, and click <strong>Edit</strong> (bottom-left) to select and change elements.</p>';

	echo '<h2>Saved patches</h2>';
	if ( ! $all ) {
		echo '<p>No patches yet.</p>';
	} else {
		echo '<table class="widefat striped"><thead><tr><th>Applies to</th><th>Element (selector)</th><th>Changes</th><th></th></tr></thead><tbody>';
		foreach ( $all as $key => $patches ) {
			foreach ( $patches as $sel => $patch ) {
				$what = array();
				if ( ! empty( $patch['styles'] ) ) foreach ( $patch['styles'] as $p => $v ) $what[] = esc_html( "$p: $v" );
				if ( isset( $patch['text'] ) ) $what[] = 'text: “' . esc_html( mb_substr( $patch['text'], 0, 60 ) ) . '”';
				$label = $key === 'site' ? 'Whole site' : ( $key[0] === 'p' ? 'Page #' . substr( $key, 1 ) : 'URL' );
				echo '<tr><td>' . esc_html( $label ) . '</td><td><code>' . esc_html( $sel ) . '</code></td><td>' . implode( '<br>', $what ) . '</td><td>';
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
				wp_nonce_field( 'livecrafts_remove' );
				echo '<input type="hidden" name="action" value="livecrafts_remove"><input type="hidden" name="key" value="' . esc_attr( $key ) . '"><input type="hidden" name="selector" value="' . esc_attr( $sel ) . '">';
				echo '<button class="button">Remove</button></form></td></tr>';
			}
		}
		echo '</tbody></table>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:12px">';
		wp_nonce_field( 'livecrafts_remove' );
		echo '<input type="hidden" name="action" value="livecrafts_remove"><input type="hidden" name="key" value="__all__">';
		echo '<button class="button button-link-delete" onclick="return confirm(\'Remove ALL Livecrafts patches?\')">Remove all patches</button></form>';
	}

	echo '<h2 style="margin-top:32px">Activity log</h2>';
	if ( ! $log ) {
		echo '<p>Nothing yet.</p>';
	} else {
		echo '<table class="widefat striped"><thead><tr><th>When</th><th>User</th><th>Scope</th><th>Element</th><th>Action</th></tr></thead><tbody>';
		foreach ( array_slice( $log, 0, 50 ) as $e ) {
			$action = empty( $e['after'] ) ? 'removed' : ( empty( $e['before'] ) ? 'created' : 'changed' );
			echo '<tr><td>' . esc_html( human_time_diff( $e['ts'] ) ) . ' ago</td><td>' . esc_html( $e['user'] ) . '</td><td>' . esc_html( $e['key'] ) . '</td><td><code>' . esc_html( $e['selector'] ) . '</code></td><td>' . esc_html( $action ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
	echo '</div>';
}
