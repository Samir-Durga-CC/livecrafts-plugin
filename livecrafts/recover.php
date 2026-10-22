<?php
/**
 * Livecrafts: repair a plugin update that was cut off (for example by the host's time limit).
 *
 * The Livecrafts backend calls this only when the "install" request of a plugin update did not answer. It starts
 * WordPress WITHOUT plugins (SHORTINIT), so even a broken plugin cannot stop it, then:
 *   - looks at the plugin on disk and loads the homepage,
 *   - puts the backup from before the update back when the plugin is missing, half-installed or breaks the site,
 *   - writes the history entry and ends the update job.
 * It needs the one-time key the backend got when the update started; without it nothing happens.
 */

header( 'Content-Type: application/json; charset=utf-8' );
header( 'Cache-Control: no-store' );
header( 'X-Robots-Tag: noindex' );

function lc_rc_out( $code, $data ) { http_response_code( $code ); echo json_encode( $data ); exit; }

if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) lc_rc_out( 405, array( 'error' => 'Use POST.' ) );
$key   = isset( $_POST['key'] ) ? (string) $_POST['key'] : '';
$jobId = isset( $_POST['job'] ) ? (string) $_POST['job'] : '';
if ( strlen( $key ) < 30 || ! preg_match( '/^[A-Za-z0-9]{6,40}$/', $jobId ) ) lc_rc_out( 403, array( 'error' => 'Forbidden.' ) );

// find WordPress (this file sits in wp-content/plugins/livecrafts)
$load = '';
for ( $d = __DIR__, $i = 0; $i < 6; $i++ ) { $d = dirname( $d ); if ( file_exists( $d . '/wp-load.php' ) ) { $load = $d . '/wp-load.php'; break; } }
if ( ! $load && ! empty( $_SERVER['DOCUMENT_ROOT'] ) && file_exists( rtrim( $_SERVER['DOCUMENT_ROOT'], '/' ) . '/wp-load.php' ) ) $load = rtrim( $_SERVER['DOCUMENT_ROOT'], '/' ) . '/wp-load.php';
if ( ! $load ) lc_rc_out( 500, array( 'error' => 'WordPress not found.' ) );

// WordPress refuses every request while a recent ".maintenance" file exists (an update cut off half way): set it aside
// for this request; it is put back below unless this really is our update.
$maint = dirname( $load ) . '/.maintenance';
$held  = '';
if ( file_exists( $maint ) && time() - filemtime( $maint ) < 20 * 60 ) { $held = $maint . '.lc-hold'; @rename( $maint, $held ); }

define( 'SHORTINIT', true );
require $load;

$job = get_option( 'livecrafts_plugin_job' );
if ( ! is_array( $job ) || empty( $job['key'] ) || $job['id'] !== $jobId || ! hash_equals( (string) $job['key'], hash( 'sha256', $key ) ) ) {
	if ( $held ) @rename( $held, $maint );
	lc_rc_out( 409, array( 'error' => 'This update is not running any more.', 'gone' => true ) );
}
// ours only if it appeared after the update started; otherwise (another update) put it back
if ( $held ) { if ( filemtime( $held ) >= (int) $job['started'] - 5 ) @unlink( $held ); else @rename( $held, $maint ); }
@set_time_limit( 300 );

$pluginsDir = defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : WP_CONTENT_DIR . '/plugins';
$plugin     = (string) $job['plugin'];
if ( false !== strpos( $plugin, '..' ) ) lc_rc_out( 400, array( 'error' => 'Bad plugin.' ) );
$single = '.' === dirname( $plugin );
$slug   = $single ? basename( $plugin, '.php' ) : dirname( $plugin );
$main   = $pluginsDir . '/' . $plugin;

function lc_rc_version( $file ) {
	if ( ! file_exists( $file ) ) return '';
	$head = (string) @file_get_contents( $file, false, null, 0, 8192 );
	return preg_match( '/^[ \t\/*#@]*Version:\s*(.+)$/mi', $head, $m ) ? trim( $m[1] ) : '';
}
function lc_rc_health() {
	$out = array( 'ok' => true, 'checks' => array() );
	foreach ( array( 'homepage' => rtrim( (string) get_option( 'home' ), '/' ) . '/', 'wp-admin' => rtrim( (string) get_option( 'siteurl' ), '/' ) . '/wp-admin/admin-ajax.php' ) as $name => $url ) {
		$url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . 'lc_health=' . time();
		$code = 0; $body = '';
		if ( function_exists( 'curl_init' ) ) {
			$c = curl_init( $url );
			curl_setopt_array( $c, array( CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3, CURLOPT_TIMEOUT => 25, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_HTTPHEADER => array( 'Cache-Control: no-cache' ) ) );
			$body = (string) curl_exec( $c ); $code = (int) curl_getinfo( $c, CURLINFO_RESPONSE_CODE ); curl_close( $c );
		} else {
			$ctx  = stream_context_create( array( 'http' => array( 'timeout' => 25, 'ignore_errors' => true ), 'ssl' => array( 'verify_peer' => false, 'verify_peer_name' => false ) ) );
			$body = (string) @file_get_contents( $url, false, $ctx );
			if ( isset( $http_response_header[0] ) && preg_match( '#\s(\d{3})\s#', $http_response_header[0], $m ) ) $code = (int) $m[1];
		}
		if ( ! $code ) { $out['checks'][] = array( 'name' => $name, 'ok' => null, 'detail' => 'Could not load it from the server.' ); if ( true === $out['ok'] ) $out['ok'] = null; continue; }
		$bad = $code >= 500 || false !== stripos( $body, 'critical error on this website' ) || false !== stripos( $body, 'critical error on your website' ) || preg_match( '#<b>\s*(Fatal|Parse) error\s*</b>\s*:#i', $body );
		$out['checks'][] = array( 'name' => $name, 'ok' => ! $bad, 'detail' => $bad ? "Error page (HTTP $code)" : "OK (HTTP $code)" );
		if ( $bad ) $out['ok'] = false;
	}
	return $out;
}
function lc_rc_rmdir( $dir ) {
	if ( ! file_exists( $dir ) ) return;
	if ( is_file( $dir ) || is_link( $dir ) ) { @unlink( $dir ); return; }
	foreach ( (array) scandir( $dir ) as $f ) if ( '.' !== $f && '..' !== $f ) lc_rc_rmdir( $dir . '/' . $f );
	@rmdir( $dir );
}
function lc_rc_restore( $zip, $pluginsDir, $plugin, $single, $slug ) {
	if ( ! class_exists( 'ZipArchive' ) ) return 'ZipArchive is not available on this server.';
	if ( ! file_exists( $zip ) ) return 'The backup file is missing.';
	$work = WP_CONTENT_DIR . '/upgrade/lc-recover-' . bin2hex( random_bytes( 4 ) );
	@mkdir( $work, 0755, true );
	$z = new ZipArchive();
	if ( true !== $z->open( $zip ) || ! $z->extractTo( $work ) ) { lc_rc_rmdir( $work ); return 'Could not open the backup.'; }
	$z->close();
	if ( $single ) {
		$ok = @copy( $work . '/' . basename( $plugin ), $pluginsDir . '/' . $plugin );
		lc_rc_rmdir( $work );
		return $ok ? true : 'Could not write the plugin file.';
	}
	$dest = $pluginsDir . '/' . $slug;
	$old  = WP_CONTENT_DIR . '/upgrade/lc-broken-' . $slug . '-' . time();
	if ( file_exists( $dest ) && ! @rename( $dest, $old ) ) { lc_rc_rmdir( $work ); return 'Could not move the broken files aside.'; }
	if ( ! @rename( $work . '/' . $slug, $dest ) ) { if ( file_exists( $old ) ) @rename( $old, $dest ); lc_rc_rmdir( $work ); return 'Could not put the backup in place.'; }
	lc_rc_rmdir( $old ); lc_rc_rmdir( $work );
	if ( function_exists( 'opcache_reset' ) ) @opcache_reset();
	return true;
}


// files may have changed under PHP's code cache (the cut-off request did not get to clear it): check the real files
if ( function_exists( 'opcache_reset' ) ) @opcache_reset();
clearstatcache();
$now    = lc_rc_version( $main );
$health = $now ? lc_rc_health() : array( 'ok' => false, 'checks' => array( array( 'name' => 'plugin file', 'ok' => false, 'detail' => 'The plugin is missing (the update stopped half way).' ) ) );
$from   = (string) $job['from']; $to = (string) $job['to']; $name = (string) $job['name'];

$history = get_option( 'livecrafts_plugin_history', array() );
if ( ! is_array( $history ) ) $history = array();
$finish = function ( $entry, $result ) use ( $history ) {
	array_unshift( $history, array_merge( array( 'at' => gmdate( 'c' ), 'by' => '' ), $entry ) );
	update_option( 'livecrafts_plugin_history', array_slice( $history, 0, 50 ), false );
	delete_option( 'livecrafts_plugin_job' );
	delete_transient( 'livecrafts_plugins_lock' );
	lc_rc_out( 200, $result );
};
$base = array( 'plugin' => $plugin, 'name' => $name, 'from' => $from, 'to' => $to, 'health' => $health, 'recovered' => true );

if ( false === $health['ok'] || ( $now !== $to && $now !== $from ) ) {
	$r     = lc_rc_restore( (string) $job['backupPath'], $pluginsDir, $plugin, $single, $slug );
	$after = lc_rc_health();
	$finish(
		array( 'plugin' => $plugin, 'name' => $name, 'action' => 'auto_rollback', 'from' => $from, 'to' => $to, 'result' => true === $r ? 'rolled_back' : 'rollback_failed', 'note' => true === $r ? 'The update was cut off by the server; the backup was restored.' : $r ),
		$base + array( 'ok' => false, 'rolledBack' => true === $r, 'healthAfter' => $after,
			'message' => true === $r ? sprintf( 'The update of %1$s was cut off by the server, so %2$s was restored automatically. The site works again.', $name, $from )
				: sprintf( 'The update of %1$s was cut off and restoring %2$s failed: %3$s Restore it from your hosting backup.', $name, $from, $r ) )
	);
}
if ( $now === $from ) {
	$finish(
		array( 'plugin' => $plugin, 'name' => $name, 'action' => 'update', 'from' => $from, 'to' => $to, 'result' => 'failed', 'note' => 'The server stopped the update before it changed anything.' ),
		$base + array( 'ok' => false, 'rolledBack' => false, 'message' => sprintf( '%1$s was not updated: the server stopped the update before it changed anything. The site works on %2$s.', $name, $from ) )
	);
}
$finish(
	array( 'plugin' => $plugin, 'name' => $name, 'action' => 'update', 'from' => $from, 'to' => $now, 'result' => null === $health['ok'] ? 'unverified' : 'ok', 'note' => 'Finished after the server cut the request off.' ),
	$base + array( 'ok' => true, 'verified' => true === $health['ok'], 'to' => $now, 'message' => sprintf( '%1$s updated to %2$s (the server cut the request off, but the update had finished). Version %3$s is saved for rollback.', $name, $now, $from ) )
);
