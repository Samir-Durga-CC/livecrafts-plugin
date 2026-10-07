<?php
/**
 * Settings → Livecrafts: deploy security, the old overlay (if any), recent changes and releases.
 * The full history, logs and resets for developers live in the Livecrafts admin panel; this page is the
 * site-side summary and the settings that must stay in WordPress (the deploy password).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', function () {
	add_options_page( 'Livecrafts', 'Livecrafts', 'manage_options', 'livecrafts', 'livecrafts_admin_page' );
} );

add_action( 'admin_post_livecrafts_deploy_password', function () {
	if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Not allowed' );
	check_admin_referer( 'livecrafts_deploy_password' );
	$remove = ! empty( $_POST['remove'] );
	$pw     = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- a password is hashed, never printed
	$again  = isset( $_POST['password_again'] ) ? (string) wp_unslash( $_POST['password_again'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$msg    = 'saved';
	if ( $remove ) {
		livecrafts_set_deploy_password( '' );
		$msg = 'removed';
	} elseif ( $pw !== $again ) {
		$msg = 'mismatch';
	} else {
		$r = livecrafts_set_deploy_password( $pw );
		if ( is_wp_error( $r ) ) $msg = 'weak';
	}
	wp_safe_redirect( add_query_arg( 'lc', $msg, admin_url( 'options-general.php?page=livecrafts' ) ) );
	exit;
} );

add_action( 'admin_post_livecrafts_legacy_remove', function () {
	if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Not allowed' );
	check_admin_referer( 'livecrafts_legacy_remove' );
	livecrafts_legacy_remove( true );
	wp_safe_redirect( add_query_arg( 'lc', 'legacy', admin_url( 'options-general.php?page=livecrafts' ) ) );
	exit;
} );

function livecrafts_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) return;
	$notices = array(
		'saved'    => array( 'success', 'Deploy password saved. Deploying now asks for it.' ),
		'removed'  => array( 'success', 'Deploy password removed. Deploying now asks for the person’s own WordPress password.' ),
		'mismatch' => array( 'error', 'The two passwords are not the same. Nothing was changed.' ),
		'weak'     => array( 'error', 'The deploy password needs at least 8 characters. Nothing was changed.' ),
		'legacy'   => array( 'success', 'The old overlay was removed.' ),
	);
	$lc = isset( $_GET['lc'] ) ? sanitize_key( $_GET['lc'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- display only
	echo '<div class="wrap"><h1>Livecrafts</h1>';
	if ( isset( $notices[ $lc ] ) ) echo '<div class="notice notice-' . esc_attr( $notices[ $lc ][0] ) . ' is-dismissible"><p>' . esc_html( $notices[ $lc ][1] ) . '</p></div>';
	echo '<p>Changes made with Livecrafts are drafts that only logged-in editors see. Visitors see them after someone deploys.</p>';

	// ---- deploy security
	echo '<h2>Deploy password</h2>';
	echo livecrafts_has_deploy_password()
		? '<p>Deploying and resetting ask for the <strong>deploy password</strong> set here.</p>'
		: '<p>Deploying and resetting ask for the person’s <strong>own WordPress password</strong>. Set a separate deploy password if only some people should know how to publish.</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'livecrafts_deploy_password' );
	echo '<input type="hidden" name="action" value="livecrafts_deploy_password"><table class="form-table" role="presentation">';
	echo '<tr><th scope="row"><label for="lc-pw">New deploy password</label></th><td><input id="lc-pw" type="password" name="password" class="regular-text" autocomplete="new-password" minlength="8"></td></tr>';
	echo '<tr><th scope="row"><label for="lc-pw2">Repeat it</label></th><td><input id="lc-pw2" type="password" name="password_again" class="regular-text" autocomplete="new-password" minlength="8"></td></tr>';
	echo '</table><p class="submit"><button class="button button-primary">Save deploy password</button>';
	if ( livecrafts_has_deploy_password() ) echo ' &nbsp; <button class="button" name="remove" value="1">Remove it (use WordPress passwords)</button>';
	echo '</p></form>';
	echo '<p class="description">Who may deploy: users with the <code>livecrafts_deploy</code> capability (administrators by default). Who may edit drafts: <code>livecrafts_edit</code> (administrators and editors).</p>';

	// ---- old overlay
	$report = livecrafts_migration_report();
	if ( $report ) {
		echo '<h2>Old overlay (Livecrafts 0.9)</h2><p>' . esc_html( $report['note'] ) . '</p>';
		echo '<p>Styles: <strong>' . esc_html( $report['styles'] ) . '</strong></p>';
		if ( $report['texts'] ) {
			echo '<table class="widefat striped"><thead><tr><th>Where</th><th>Element</th><th>Text shown by the overlay</th></tr></thead><tbody>';
			foreach ( $report['texts'] as $t ) echo '<tr><td>' . esc_html( $t['where'] ) . '</td><td><code>' . esc_html( $t['selector'] ) . '</code></td><td>' . esc_html( $t['text'] ) . '</td></tr>';
			echo '</tbody></table>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:12px">';
		wp_nonce_field( 'livecrafts_legacy_remove' );
		echo '<input type="hidden" name="action" value="livecrafts_legacy_remove"><button class="button button-link-delete" onclick="return confirm(\'Remove the old overlay now? Visitors will no longer get its styles and texts.\')">Remove the old overlay now</button></form>';
	}

	// ---- releases + recent changes
	echo '<h2 style="margin-top:32px">Releases</h2>';
	$releases = livecrafts_releases( 10 );
	if ( ! $releases ) {
		echo '<p>Nothing deployed yet.</p>';
	} else {
		echo '<table class="widefat striped"><thead><tr><th>#</th><th>When</th><th>Who</th><th>What</th><th>Notes</th></tr></thead><tbody>';
		foreach ( $releases as $r ) {
			echo '<tr><td>' . (int) $r['id'] . '</td><td>' . esc_html( human_time_diff( strtotime( $r['at'] ) ) ) . ' ago</td><td>' . esc_html( $r['user'] ? $r['user']['name'] : '-' ) . '</td><td>' . esc_html( ucfirst( $r['kind'] ) . ': ' . $r['summary'] ) . '</td><td>' . esc_html( mb_substr( $r['notes'], 0, 120 ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	echo '<h2 style="margin-top:32px">Recent changes</h2>';
	$changes = livecrafts_changes_query( array( 'limit' => 25 ) );
	if ( ! $changes ) {
		echo '<p>Nothing yet.</p>';
	} else {
		echo '<table class="widefat striped"><thead><tr><th>When</th><th>Status</th><th>Who / from</th><th>Item</th><th>Change</th></tr></thead><tbody>';
		foreach ( $changes as $c ) {
			$p = livecrafts_change_public( $c );
			echo '<tr><td>' . esc_html( human_time_diff( strtotime( $p['at'] ) ) ) . ' ago</td><td>' . esc_html( $p['status'] ) . '</td><td>' . esc_html( ( $p['user'] ? $p['user']['name'] : '-' ) . ' · ' . $p['source'] ) . '</td><td>' . esc_html( $p['object']['label'] ) . '</td><td>' . esc_html( $p['summary'] ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
	echo '</div>';
}
