<?php
/**
 * Plugin updates with a backup, a health check and rollback (0.19).
 *
 *   GET  plugins                    installed plugins, available updates, saved backups (?refresh=1 asks for updates again)
 *   GET  plugins/versions?plugin=   older official versions of a WordPress.org plugin (for rollback without a backup)
 *   POST plugins/update             {plugin}  backup -> update -> health check -> keep, or restore the backup at once
 *   POST plugins/rollback           {plugin, backup? | version?}  restore a saved backup (newest by default) or an official version
 *   GET  plugins/history            the last updates and rollbacks
 *
 * Needs livecrafts_edit and update_plugins. Plugin updates go live at once (there is no draft of a plugin), which is
 * why every update keeps a backup and is checked: the homepage and wp-admin are loaded in a separate request, so a
 * broken new version can never stop the request that puts the old one back. Livecrafts itself is not updated here
 * (a plugin replacing its own files mid-request); it is updated by uploading its zip.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const LIVECRAFTS_PLUGIN_BACKUPS_KEEP = 3;

add_action( 'rest_api_init', function () {
	$r = function ( $path, $methods, $cb ) {
		register_rest_route( 'livecrafts/v1', $path, array( 'methods' => $methods, 'callback' => $cb, 'permission_callback' => 'livecrafts_plugins_permission' ) );
	};
	$r( '/plugins', 'GET', 'livecrafts_rest_plugins' );
	$r( '/plugins/versions', 'GET', 'livecrafts_rest_plugin_versions' );
	$r( '/plugins/update', 'POST', 'livecrafts_rest_plugin_update' );
	$r( '/plugins/rollback', 'POST', 'livecrafts_rest_plugin_rollback' );
	$r( '/plugins/history', 'GET', 'livecrafts_rest_plugin_history' );
} );

function livecrafts_plugins_permission( $request = null ) {
	if ( ! ( livecrafts_can_edit() && current_user_can( 'update_plugins' ) ) ) return false;
	// From the chat widget the backend passes the person's token: that person must be allowed to update plugins too
	$token = $request instanceof WP_REST_Request ? $request->get_header( 'x-livecrafts-actor' ) : '';
	if ( $token ) {
		$p = livecrafts_verify_token( $token );
		if ( ! $p || ! user_can( (int) $p['uid'], 'update_plugins' ) ) return new WP_Error( 'lc_actor', 'Only people who can update plugins in WordPress can do this.', array( 'status' => 403 ) );
	}
	return true;
}

function livecrafts_plugins_load_admin() {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/misc.php';
	require_once ABSPATH . 'wp-admin/includes/update.php';
	require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
}

/** Plugins that change the database when they update: rolling back their files does not undo that. */
function livecrafts_plugins_db_heavy( $slug ) {
	return in_array( $slug, array( 'woocommerce', 'elementor', 'elementor-pro', 'wordpress-seo', 'wordpress-seo-premium', 'wpforms-lite', 'wpforms', 'the-events-calendar', 'learnpress', 'bbpress', 'buddypress', 'wordfence' ), true );
}

function livecrafts_plugin_slug( $plugin ) {
	$d = dirname( $plugin );
	return '.' === $d ? basename( $plugin, '.php' ) : $d;
}

/** A plugin file name from the request, checked against what is installed. */
function livecrafts_plugins_valid( $plugin ) {
	$plugin = is_string( $plugin ) ? wp_unslash( $plugin ) : '';
	if ( '' === $plugin || false !== strpos( $plugin, '..' ) || validate_file( $plugin ) !== 0 ) return false;
	return array_key_exists( $plugin, get_plugins() ) ? $plugin : false;
}

/** wp-content/livecrafts-backups-<random>/plugins, closed to the web. */
function livecrafts_plugins_backup_root() {
	$suffix = get_option( 'livecrafts_backup_suffix' );
	if ( ! $suffix ) { $suffix = strtolower( wp_generate_password( 12, false ) ); update_option( 'livecrafts_backup_suffix', $suffix, false ); }
	$root = WP_CONTENT_DIR . '/livecrafts-backups-' . $suffix;
	if ( ! is_dir( $root . '/plugins' ) ) wp_mkdir_p( $root . '/plugins' );
	if ( ! file_exists( $root . '/index.php' ) ) @file_put_contents( $root . '/index.php', "<?php // Silence.\n" );
	if ( ! file_exists( $root . '/.htaccess' ) ) @file_put_contents( $root . '/.htaccess', "Require all denied\nDeny from all\n" );
	if ( ! file_exists( $root . '/plugins/index.php' ) ) @file_put_contents( $root . '/plugins/index.php', "<?php // Silence.\n" );
	return $root . '/plugins';
}

/** Saved backups of one plugin, newest first: [{id, version, at, size}]. */
function livecrafts_plugin_backups( $slug ) {
	$dir = livecrafts_plugins_backup_root() . '/' . sanitize_file_name( $slug );
	if ( ! is_dir( $dir ) ) return array();
	$out = array();
	foreach ( (array) glob( $dir . '/*.zip' ) as $f ) {
		if ( ! preg_match( '/^(.+)__(\d+)\.zip$/', basename( $f ), $m ) ) continue;
		$out[] = array( 'id' => basename( $f ), 'version' => $m[1], 'at' => gmdate( 'c', (int) $m[2] ), 'size' => filesize( $f ) );
	}
	usort( $out, function ( $a, $b ) { return strcmp( $b['at'], $a['at'] ); } );
	return $out;
}

/** Zip the plugin as it is now (folder plugins and single-file plugins). Keeps the newest few. */
function livecrafts_plugin_backup( $plugin, $version ) {
	$slug   = livecrafts_plugin_slug( $plugin );
	$dir    = livecrafts_plugins_backup_root() . '/' . sanitize_file_name( $slug );
	wp_mkdir_p( $dir );
	if ( ! file_exists( $dir . '/index.php' ) ) @file_put_contents( $dir . '/index.php', "<?php // Silence.\n" );
	$zip    = $dir . '/' . preg_replace( '/[^0-9A-Za-z._-]/', '-', (string) $version ) . '__' . time() . '.zip';
	$single = '.' === dirname( $plugin );
	$src    = $single ? WP_PLUGIN_DIR . '/' . $plugin : WP_PLUGIN_DIR . '/' . $slug;
	if ( ! file_exists( $src ) ) return new WP_Error( 'lc_backup', 'The plugin files were not found.' );

	$files = array();
	if ( $single ) {
		$files[ basename( $plugin ) ] = $src;
	} else {
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $src, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $f ) if ( $f->isFile() ) $files[ $slug . '/' . ltrim( str_replace( '\\', '/', substr( $f->getPathname(), strlen( $src ) ) ), '/' ) ] = $f->getPathname();
	}
	if ( class_exists( 'ZipArchive' ) ) {
		$z = new ZipArchive();
		if ( true !== $z->open( $zip, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) return new WP_Error( 'lc_backup', 'Could not create the backup file.' );
		if ( ! $single ) $z->addEmptyDir( $slug );
		foreach ( $files as $name => $path ) $z->addFile( $path, $name );
		if ( ! $z->close() ) return new WP_Error( 'lc_backup', 'Could not write the backup file.' );
	} else {
		require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
		$z   = new PclZip( $zip );
		$res = $z->create( $single ? array( $src ) : array( $src ), PCLZIP_OPT_REMOVE_PATH, dirname( $src ) );
		if ( ! $res ) return new WP_Error( 'lc_backup', 'Could not create the backup file: ' . $z->errorInfo( true ) );
	}
	if ( ! file_exists( $zip ) || filesize( $zip ) < 22 ) return new WP_Error( 'lc_backup', 'The backup file is empty.' );

	// one backup per version (the newest copy), and only the newest few versions
	$all = livecrafts_plugin_backups( $slug );
	$seen = array(); $keep = 0;
	foreach ( $all as $b ) {
		if ( isset( $seen[ $b['version'] ] ) || $keep >= LIVECRAFTS_PLUGIN_BACKUPS_KEEP ) { if ( $b['id'] !== basename( $zip ) ) @unlink( $dir . '/' . $b['id'] ); continue; }
		$seen[ $b['version'] ] = true; $keep++;
	}
	return basename( $zip );
}

/** Put a zip (a backup, or an official package) in place of the plugin's current files. */
function livecrafts_plugin_restore_zip( $plugin, $zip ) {
	global $wp_filesystem;
	if ( ! WP_Filesystem() || ! $wp_filesystem || 'direct' !== $wp_filesystem->method ) {
		return new WP_Error( 'lc_fs', 'This server asks for FTP details to change files, so plugins cannot be changed from here.' );
	}
	$slug   = livecrafts_plugin_slug( $plugin );
	$single = '.' === dirname( $plugin );
	$work   = WP_CONTENT_DIR . '/upgrade/lc-restore-' . wp_generate_password( 8, false );
	wp_mkdir_p( $work );
	$un = unzip_file( $zip, $work );
	if ( is_wp_error( $un ) ) { $wp_filesystem->delete( $work, true ); return $un; }

	if ( $single ) {
		$new = $work . '/' . basename( $plugin );
		if ( ! file_exists( $new ) ) { $found = glob( $work . '/*/' . basename( $plugin ) ); if ( $found ) $new = $found[0]; }
		if ( ! file_exists( $new ) ) { $wp_filesystem->delete( $work, true ); return new WP_Error( 'lc_restore', 'The backup does not contain the plugin file.' ); }
		$ok = @copy( $new, WP_PLUGIN_DIR . '/' . $plugin );
		$wp_filesystem->delete( $work, true );
		return $ok ? true : new WP_Error( 'lc_restore', 'Could not write the plugin file.' );
	}

	$newdir = $work . '/' . $slug;
	if ( ! is_dir( $newdir ) ) {
		$tops = array_values( array_filter( (array) glob( $work . '/*' ), 'is_dir' ) );
		if ( 1 === count( $tops ) && file_exists( $tops[0] . '/' . basename( $plugin ) ) ) $newdir = $tops[0];
	}
	if ( ! is_dir( $newdir ) || ! file_exists( $newdir . '/' . basename( $plugin ) ) ) { $wp_filesystem->delete( $work, true ); return new WP_Error( 'lc_restore', 'The package does not contain this plugin.' ); }
	$dest = WP_PLUGIN_DIR . '/' . $slug;
	$old  = WP_CONTENT_DIR . '/upgrade/lc-old-' . $slug . '-' . time();
	if ( is_dir( $dest ) && ! @rename( $dest, $old ) ) { $wp_filesystem->delete( $work, true ); return new WP_Error( 'lc_restore', 'Could not move the current plugin folder aside.' ); }
	if ( ! @rename( $newdir, $dest ) ) {
		if ( is_dir( $old ) ) @rename( $old, $dest );   // put the current files back
		$wp_filesystem->delete( $work, true );
		return new WP_Error( 'lc_restore', 'Could not put the plugin folder in place; nothing was changed.' );
	}
	if ( is_dir( $old ) ) $wp_filesystem->delete( $old, true );
	$wp_filesystem->delete( $work, true );
	if ( function_exists( 'opcache_reset' ) ) @opcache_reset();
	wp_clean_plugins_cache( false );
	return true;
}

/**
 * Load the homepage and wp-admin in separate requests. ok: true (fine), false (an error page), null (the server cannot
 * load its own pages - the update is kept, the person should look themselves).
 */
function livecrafts_site_health() {
	$checks = array(
		'homepage' => add_query_arg( 'lc_health', time(), home_url( '/' ) ),
		'wp-admin' => add_query_arg( 'lc_health', time(), admin_url( 'admin-ajax.php' ) ),
	);
	$out = array( 'ok' => true, 'checks' => array() );
	foreach ( $checks as $name => $url ) {
		$res = wp_remote_get( $url, array( 'timeout' => 25, 'redirection' => 3, 'sslverify' => false, 'headers' => array( 'Cache-Control' => 'no-cache' ), 'reject_unsafe_urls' => false ) );
		if ( is_wp_error( $res ) ) {
			$out['checks'][] = array( 'name' => $name, 'ok' => null, 'detail' => 'Could not load it from the server: ' . $res->get_error_message() );
			if ( true === $out['ok'] ) $out['ok'] = null;
			continue;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = (string) wp_remote_retrieve_body( $res );
		$bad  = $code >= 500 || false !== stripos( $body, 'critical error on this website' ) || false !== stripos( $body, 'critical error on your website' )
			|| preg_match( '#<b>\s*(Fatal|Parse) error\s*</b>\s*:#i', $body ) || preg_match( '#PHP (Fatal|Parse) error#', $body );
		$out['checks'][] = array( 'name' => $name, 'ok' => ! $bad, 'detail' => $bad ? "Error page (HTTP $code)" : "OK (HTTP $code)" );
		if ( $bad ) $out['ok'] = false;
	}
	return $out;
}

function livecrafts_plugin_history_add( $entry ) {
	$h = get_option( 'livecrafts_plugin_history', array() );
	if ( ! is_array( $h ) ) $h = array();
	$u = wp_get_current_user();
	array_unshift( $h, array_merge( array( 'at' => gmdate( 'c' ), 'by' => $u && $u->exists() ? $u->display_name : '' ), $entry ) );
	update_option( 'livecrafts_plugin_history', array_slice( $h, 0, 50 ), false );
}

/** One person at a time changes plugins (two updates at once could restore the wrong files). */
function livecrafts_plugins_lock() {
	if ( get_transient( 'livecrafts_plugins_lock' ) ) return new WP_Error( 'lc_busy', 'Another plugin update or rollback is running. Try again in a minute.', array( 'status' => 409 ) );
	set_transient( 'livecrafts_plugins_lock', 1, 10 * MINUTE_IN_SECONDS );
	return true;
}
function livecrafts_plugins_unlock() { delete_transient( 'livecrafts_plugins_lock' ); }

function livecrafts_plugins_can_modify() {
	if ( ! wp_is_file_mod_allowed( 'livecrafts_plugin_update' ) ) return new WP_Error( 'lc_file_mods', 'File changes are turned off on this site (DISALLOW_FILE_MODS), so plugins cannot be updated from here.', array( 'status' => 403 ) );
	if ( is_multisite() && ! current_user_can( 'manage_network_plugins' ) ) return new WP_Error( 'lc_multisite', 'On a multisite network only a network admin can update plugins.', array( 'status' => 403 ) );
	return true;
}

// ------------------------------------------------------------------ list

function livecrafts_rest_plugins( WP_REST_Request $req ) {
	livecrafts_plugins_load_admin();
	if ( $req->get_param( 'refresh' ) ) { wp_clean_plugins_cache( true ); wp_update_plugins(); }
	$updates = get_site_transient( 'update_plugins' );
	if ( ! is_object( $updates ) ) { wp_update_plugins(); $updates = get_site_transient( 'update_plugins' ); }
	$self = plugin_basename( LIVECRAFTS_FILE );
	$list = array();
	foreach ( get_plugins() as $file => $p ) {
		$slug = livecrafts_plugin_slug( $file );
		$u    = is_object( $updates ) && isset( $updates->response[ $file ] ) ? $updates->response[ $file ] : null;
		$wporg = ( $u && isset( $u->id ) && false !== strpos( (string) $u->id, 'w.org' ) ) || ( is_object( $updates ) && isset( $updates->no_update[ $file ]->id ) && false !== strpos( (string) $updates->no_update[ $file ]->id, 'w.org' ) );
		$list[] = array(
			'plugin'  => $file,
			'slug'    => $slug,
			'name'    => wp_strip_all_tags( $p['Name'] ),
			'version' => $p['Version'],
			'author'  => wp_strip_all_tags( $p['AuthorName'] ?? $p['Author'] ),
			'active'  => is_plugin_active( $file ),
			'self'    => $file === $self,
			'wporg'   => (bool) $wporg,
			'dbHeavy' => livecrafts_plugins_db_heavy( $slug ),
			'update'  => $u ? array(
				'version'     => (string) $u->new_version,
				'requires'    => isset( $u->requires ) ? (string) $u->requires : '',
				'requiresPhp' => isset( $u->requires_php ) ? (string) $u->requires_php : '',
				'tested'      => isset( $u->tested ) ? (string) $u->tested : '',
				'compatible'  => ( empty( $u->requires ) || is_wp_version_compatible( $u->requires ) ) && ( empty( $u->requires_php ) || is_php_version_compatible( $u->requires_php ) ),
				'package'     => ! empty( $u->package ),
			) : null,
			'backups' => livecrafts_plugin_backups( $slug ),
		);
	}
	usort( $list, function ( $a, $b ) { return ( $b['update'] ? 1 : 0 ) - ( $a['update'] ? 1 : 0 ) ?: strcasecmp( $a['name'], $b['name'] ); } );
	$mods = livecrafts_plugins_can_modify();
	return array(
		'plugins'     => $list,
		'wp'          => get_bloginfo( 'version' ),
		'php'         => PHP_VERSION,
		'canModify'   => true === $mods,
		'blocked'     => is_wp_error( $mods ) ? $mods->get_error_message() : '',
		'checkedAt'   => is_object( $updates ) && ! empty( $updates->last_checked ) ? gmdate( 'c', (int) $updates->last_checked ) : null,
	);
}

function livecrafts_rest_plugin_history() {
	$h = get_option( 'livecrafts_plugin_history', array() );
	return array( 'history' => is_array( $h ) ? $h : array() );
}

function livecrafts_rest_plugin_versions( WP_REST_Request $req ) {
	livecrafts_plugins_load_admin();
	$plugin = livecrafts_plugins_valid( $req->get_param( 'plugin' ) );
	if ( ! $plugin ) return new WP_Error( 'lc_plugin', 'Unknown plugin.', array( 'status' => 404 ) );
	$info = plugins_api( 'plugin_information', array( 'slug' => livecrafts_plugin_slug( $plugin ), 'fields' => array( 'versions' => true, 'sections' => false ) ) );
	if ( is_wp_error( $info ) || empty( $info->versions ) ) return array( 'versions' => array(), 'note' => 'This plugin is not on WordPress.org, so only saved backups can be restored.' );
	$current = get_plugins()[ $plugin ]['Version'];
	$v = array_filter( array_keys( (array) $info->versions ), function ( $x ) use ( $current ) { return 'trunk' !== $x && version_compare( $x, $current, '<' ); } );
	usort( $v, 'version_compare' );
	return array( 'versions' => array_slice( array_reverse( array_values( $v ) ), 0, 10 ) );
}

// ------------------------------------------------------------------ update

function livecrafts_rest_plugin_update( WP_REST_Request $req ) {
	livecrafts_plugins_load_admin();
	$step = (string) $req->get_param( 'step' );
	if ( in_array( $step, array( 'prepare', 'install' ), true ) ) return livecrafts_plugin_update_step( $req, $step );
	$plugin = livecrafts_plugins_valid( $req->get_param( 'plugin' ) );
	if ( ! $plugin ) return new WP_Error( 'lc_plugin', 'Unknown plugin.', array( 'status' => 404 ) );
	if ( $plugin === plugin_basename( LIVECRAFTS_FILE ) ) return new WP_Error( 'lc_self', 'Livecrafts is updated by uploading its zip (Plugins > Add New > Upload), not from here.', array( 'status' => 400 ) );
	$mods = livecrafts_plugins_can_modify();
	if ( is_wp_error( $mods ) ) return $mods;

	$updates = get_site_transient( 'update_plugins' );
	if ( ! is_object( $updates ) || ! isset( $updates->response[ $plugin ] ) ) { wp_update_plugins(); $updates = get_site_transient( 'update_plugins' ); }
	if ( ! is_object( $updates ) || ! isset( $updates->response[ $plugin ] ) ) return new WP_Error( 'lc_no_update', 'There is no update for this plugin.', array( 'status' => 400 ) );
	$u = $updates->response[ $plugin ];
	if ( empty( $u->package ) ) return new WP_Error( 'lc_no_package', 'The update package is not available (premium plugins need an active licence).', array( 'status' => 400 ) );
	if ( ! empty( $u->requires ) && ! is_wp_version_compatible( $u->requires ) ) return new WP_Error( 'lc_wp', sprintf( 'The new version needs WordPress %s.', $u->requires ), array( 'status' => 400 ) );
	if ( ! empty( $u->requires_php ) && ! is_php_version_compatible( $u->requires_php ) ) return new WP_Error( 'lc_php', sprintf( 'The new version needs PHP %s.', $u->requires_php ), array( 'status' => 400 ) );

	$lock = livecrafts_plugins_lock();
	if ( is_wp_error( $lock ) ) return $lock;
	@set_time_limit( 300 );
	try {
		$data = get_plugins()[ $plugin ];
		$from = $data['Version'];
		$to   = (string) $u->new_version;
		$name = wp_strip_all_tags( $data['Name'] );

		// 0. the site must be healthy before, or a failed check would blame the update
		$pre = livecrafts_site_health();
		if ( false === $pre['ok'] ) return new WP_Error( 'lc_unhealthy', 'The site already shows an error page before the update, so the update was not started. Fix that first.', array( 'status' => 409, 'health' => $pre ) );

		// 1. backup
		$backup = livecrafts_plugin_backup( $plugin, $from );
		if ( is_wp_error( $backup ) ) return $backup;

		// 2. download and swap in the new version (one folder rename: no maintenance mode, never half-deleted)
		$one = livecrafts_plugin_install_package( $plugin, (string) $u->package );
		wp_clean_plugins_cache( false );
		if ( is_wp_error( $one ) ) {
			$msg = $one->get_error_message();
			// the updater may have removed the old files half way: put the backup back
			$restored = ! file_exists( WP_PLUGIN_DIR . '/' . $plugin ) || ( get_plugins()[ $plugin ]['Version'] ?? '' ) !== $from
				? livecrafts_plugin_restore_zip( $plugin, livecrafts_plugins_backup_root() . '/' . sanitize_file_name( livecrafts_plugin_slug( $plugin ) ) . '/' . $backup ) : true;
			livecrafts_plugin_history_add( array( 'plugin' => $plugin, 'name' => $name, 'action' => 'update', 'from' => $from, 'to' => $to, 'result' => 'failed', 'note' => $msg ) );
			return new WP_Error( 'lc_update_failed', 'The update failed: ' . wp_strip_all_tags( $msg ) . ( true === $restored ? ' The plugin is unchanged.' : '' ), array( 'status' => 500 ) );
		}

		// 3. health check in separate requests
		$now     = get_plugins()[ $plugin ]['Version'] ?? '';
		$missing = ! file_exists( WP_PLUGIN_DIR . '/' . $plugin );
		$health  = $missing ? array( 'ok' => false, 'checks' => array( array( 'name' => 'plugin file', 'ok' => false, 'detail' => 'The plugin\'s main file is gone after the update.' ) ) ) : livecrafts_site_health();

		if ( false === $health['ok'] ) {
			// 4a. broken: restore the backup at once
			$zip = livecrafts_plugins_backup_root() . '/' . sanitize_file_name( livecrafts_plugin_slug( $plugin ) ) . '/' . $backup;
			$r   = livecrafts_plugin_restore_zip( $plugin, $zip );
			$after = livecrafts_site_health();
			livecrafts_plugin_history_add( array( 'plugin' => $plugin, 'name' => $name, 'action' => 'auto_rollback', 'from' => $from, 'to' => $to, 'result' => true === $r ? 'rolled_back' : 'rollback_failed', 'note' => true === $r ? 'The site showed an error after the update; the backup was restored.' : $r->get_error_message() ) );
			return array(
				'ok' => false, 'rolledBack' => true === $r, 'plugin' => $plugin, 'name' => $name, 'from' => $from, 'to' => $to,
				'health' => $health, 'healthAfter' => $after,
				'message' => true === $r
					? sprintf( '%1$s %2$s broke the site (%3$s), so %4$s was restored automatically.', $name, $to, livecrafts_health_summary( $health ), $from )
					: sprintf( '%1$s %2$s broke the site and restoring %3$s failed: %4$s. Restore it from Hostinger or roll back again.', $name, $to, $from, $r->get_error_message() ),
			);
		}

		// 4b. fine (or could not be checked from the server)
		livecrafts_plugin_history_add( array( 'plugin' => $plugin, 'name' => $name, 'action' => 'update', 'from' => $from, 'to' => $now ?: $to, 'result' => null === $health['ok'] ? 'unverified' : 'ok', 'note' => null === $health['ok'] ? 'The server could not load its own pages to check them.' : '' ) );
		return array(
			'ok' => true, 'verified' => true === $health['ok'], 'plugin' => $plugin, 'name' => $name, 'from' => $from, 'to' => $now ?: $to, 'backup' => $backup, 'health' => $health,
			'dbHeavy' => livecrafts_plugins_db_heavy( livecrafts_plugin_slug( $plugin ) ),
			'message' => true === $health['ok']
				? sprintf( '%1$s updated to %2$s. Homepage and wp-admin load fine. Version %3$s is saved for rollback.', $name, $now ?: $to, $from )
				: sprintf( '%1$s updated to %2$s, but the server could not load its own pages to check them - please open the site and look. Version %3$s is saved for rollback.', $name, $now ?: $to, $from ),
		);
	} finally {
		livecrafts_plugins_unlock();
	}
}

function livecrafts_health_summary( $h ) {
	$bad = array();
	foreach ( $h['checks'] as $c ) if ( false === $c['ok'] ) $bad[] = $c['name'] . ': ' . $c['detail'];
	return $bad ? implode( '; ', $bad ) : 'error page';
}

// ------------------------------------------------------------------ rollback

function livecrafts_rest_plugin_rollback( WP_REST_Request $req ) {
	livecrafts_plugins_load_admin();
	$plugin = livecrafts_plugins_valid( $req->get_param( 'plugin' ) );
	if ( ! $plugin ) return new WP_Error( 'lc_plugin', 'Unknown plugin.', array( 'status' => 404 ) );
	if ( $plugin === plugin_basename( LIVECRAFTS_FILE ) ) return new WP_Error( 'lc_self', 'Livecrafts is rolled back by uploading the older zip.', array( 'status' => 400 ) );
	$mods = livecrafts_plugins_can_modify();
	if ( is_wp_error( $mods ) ) return $mods;

	$slug    = livecrafts_plugin_slug( $plugin );
	$backups = livecrafts_plugin_backups( $slug );
	$id      = sanitize_file_name( (string) $req->get_param( 'backup' ) );
	$version = preg_replace( '/[^0-9A-Za-z._-]/', '', (string) $req->get_param( 'version' ) );
	$data    = get_plugins()[ $plugin ];
	$from    = $data['Version'];
	$name    = wp_strip_all_tags( $data['Name'] );

	$zip = ''; $tmp = ''; $to = '';
	if ( $id ) {
		$b = array_values( array_filter( $backups, function ( $x ) use ( $id ) { return $x['id'] === $id; } ) );
		if ( ! $b ) return new WP_Error( 'lc_backup', 'That backup is not there any more.', array( 'status' => 404 ) );
		$zip = livecrafts_plugins_backup_root() . '/' . sanitize_file_name( $slug ) . '/' . $id; $to = $b[0]['version'];
	} elseif ( $version ) {
		$b = array_values( array_filter( $backups, function ( $x ) use ( $version ) { return $x['version'] === $version; } ) );
		if ( $b ) { $zip = livecrafts_plugins_backup_root() . '/' . sanitize_file_name( $slug ) . '/' . $b[0]['id']; $to = $version; }
		else {
			// an official older version from WordPress.org
			$tmp = download_url( 'https://downloads.wordpress.org/plugin/' . rawurlencode( $slug ) . '.' . rawurlencode( $version ) . '.zip', 120 );
			if ( is_wp_error( $tmp ) ) return new WP_Error( 'lc_download', 'Could not download version ' . $version . ' from WordPress.org: ' . $tmp->get_error_message(), array( 'status' => 502 ) );
			$zip = $tmp; $to = $version;
		}
	} else {
		// default: the newest saved version that is not the one installed now
		$b = array_values( array_filter( $backups, function ( $x ) use ( $from ) { return $x['version'] !== $from; } ) );
		if ( ! $b ) return new WP_Error( 'lc_backup', 'There is no saved older version of this plugin yet. Pick an official version instead.', array( 'status' => 404 ) );
		$zip = livecrafts_plugins_backup_root() . '/' . sanitize_file_name( $slug ) . '/' . $b[0]['id']; $to = $b[0]['version'];
	}
	if ( $to === $from ) { if ( $tmp ) @unlink( $tmp ); return new WP_Error( 'lc_same', sprintf( '%1$s is already on %2$s.', $name, $from ), array( 'status' => 400 ) ); }
	// work from a copy: saving the current version below may prune old backups
	if ( ! $tmp ) {
		$copy = wp_tempnam( 'lc-rollback.zip' );
		if ( ! $copy || ! @copy( $zip, $copy ) ) return new WP_Error( 'lc_backup', 'Could not read the backup file.', array( 'status' => 500 ) );
		$zip = $tmp = $copy;
	}

	$lock = livecrafts_plugins_lock();
	if ( is_wp_error( $lock ) ) { if ( $tmp ) @unlink( $tmp ); return $lock; }
	@set_time_limit( 300 );
	try {
		// the version being replaced is saved too, so a rollback can itself be undone
		$saved = livecrafts_plugin_backup( $plugin, $from );
		$r     = livecrafts_plugin_restore_zip( $plugin, $zip );
		if ( is_wp_error( $r ) ) {
			livecrafts_plugin_history_add( array( 'plugin' => $plugin, 'name' => $name, 'action' => 'rollback', 'from' => $from, 'to' => $to, 'result' => 'failed', 'note' => $r->get_error_message() ) );
			return $r;
		}
		$health = livecrafts_site_health();
		$now    = get_plugins()[ $plugin ]['Version'] ?? $to;
		livecrafts_plugin_history_add( array( 'plugin' => $plugin, 'name' => $name, 'action' => 'rollback', 'from' => $from, 'to' => $now, 'result' => false === $health['ok'] ? 'error_after' : ( null === $health['ok'] ? 'unverified' : 'ok' ), 'note' => '' ) );
		return array(
			'ok' => false !== $health['ok'], 'plugin' => $plugin, 'name' => $name, 'from' => $from, 'to' => $now, 'health' => $health,
			'backup' => is_wp_error( $saved ) ? null : $saved,
			'dbHeavy' => livecrafts_plugins_db_heavy( $slug ),
			'message' => false === $health['ok']
				? sprintf( '%1$s is back on %2$s, but the site still shows an error (%3$s). The cause may be elsewhere.', $name, $now, livecrafts_health_summary( $health ) )
				: sprintf( '%1$s rolled back from %2$s to %3$s.%4$s', $name, $from, $now, livecrafts_plugins_db_heavy( $slug ) ? ' Its database changes from the newer version stay.' : '' ),
		);
	} finally {
		livecrafts_plugins_unlock();
		if ( $tmp ) @unlink( $tmp );
	}
}

// ------------------------------------------------------------------ update in steps (0.19.1)
//
// prepare: checks, backup, a job with a one-time recovery key.  install: download the new version, swap it in with one
// folder rename (no maintenance mode, never half-deleted), check the site, put the backup back if it broke - all in one
// request where the old code is already loaded, so a broken new version cannot stop it.  If the host cuts that request
// off (time limits), the backend calls recover.php: it starts WordPress WITHOUT plugins and repairs from the backup.

function livecrafts_plugin_job_get() { $j = get_option( 'livecrafts_plugin_job' ); return is_array( $j ) ? $j : null; }
function livecrafts_plugin_job_set( $j ) { if ( $j ) update_option( 'livecrafts_plugin_job', $j, false ); else delete_option( 'livecrafts_plugin_job' ); }

/** Download (or copy a local test package) and swap it in for the plugin's files. */
function livecrafts_plugin_install_package( $plugin, $package ) {
	$tmp = ( ! preg_match( '#^https?://#i', $package ) && file_exists( $package ) ) ? wp_tempnam( 'lc-pkg.zip' ) : '';
	if ( $tmp ) { if ( ! @copy( $package, $tmp ) ) return new WP_Error( 'lc_download', 'Could not read the update package.' ); }
	else { $tmp = download_url( $package, 300 ); if ( is_wp_error( $tmp ) ) return new WP_Error( 'lc_download', 'Could not download the update: ' . $tmp->get_error_message() ); }
	$r = livecrafts_plugin_restore_zip( $plugin, $tmp );
	@unlink( $tmp );
	if ( is_wp_error( $r ) ) return $r;
	// tell other plugins an update happened (some run their upgrade routines on this), and forget the shown update
	$t = get_site_transient( 'update_plugins' );
	if ( is_object( $t ) && isset( $t->response[ $plugin ] ) ) { unset( $t->response[ $plugin ] ); set_site_transient( 'update_plugins', $t ); }
	do_action( 'upgrader_process_complete', new Plugin_Upgrader( new Automatic_Upgrader_Skin() ), array( 'action' => 'update', 'type' => 'plugin', 'bulk' => true, 'plugins' => array( $plugin ) ) );
	return true;
}

function livecrafts_plugin_update_step( WP_REST_Request $req, $step ) {
	$plugin = livecrafts_plugins_valid( $req->get_param( 'plugin' ) );
	if ( ! $plugin ) return new WP_Error( 'lc_plugin', 'Unknown plugin.', array( 'status' => 404 ) );
	if ( $plugin === plugin_basename( LIVECRAFTS_FILE ) ) return new WP_Error( 'lc_self', 'Livecrafts is updated by uploading its zip (Plugins > Add New > Upload), not from here.', array( 'status' => 400 ) );

	if ( 'prepare' === $step ) {
		$mods = livecrafts_plugins_can_modify();
		if ( is_wp_error( $mods ) ) return $mods;
		$updates = get_site_transient( 'update_plugins' );
		if ( ! is_object( $updates ) || ! isset( $updates->response[ $plugin ] ) ) { wp_update_plugins(); $updates = get_site_transient( 'update_plugins' ); }
		if ( ! is_object( $updates ) || ! isset( $updates->response[ $plugin ] ) ) return new WP_Error( 'lc_no_update', 'There is no update for this plugin.', array( 'status' => 400 ) );
		$u = $updates->response[ $plugin ];
		if ( empty( $u->package ) ) return new WP_Error( 'lc_no_package', 'The update package is not available (premium plugins need an active licence).', array( 'status' => 400 ) );
		if ( ! empty( $u->requires ) && ! is_wp_version_compatible( $u->requires ) ) return new WP_Error( 'lc_wp', sprintf( 'The new version needs WordPress %s.', $u->requires ), array( 'status' => 400 ) );
		if ( ! empty( $u->requires_php ) && ! is_php_version_compatible( $u->requires_php ) ) return new WP_Error( 'lc_php', sprintf( 'The new version needs PHP %s.', $u->requires_php ), array( 'status' => 400 ) );

		$old = livecrafts_plugin_job_get();
		if ( $old && time() - (int) $old['started'] < 15 * MINUTE_IN_SECONDS ) return new WP_Error( 'lc_busy', 'Another plugin update is still running. Try again in a minute.', array( 'status' => 409 ) );
		if ( $old ) { livecrafts_plugin_job_set( null ); livecrafts_plugins_unlock(); }
		$lock = livecrafts_plugins_lock();
		if ( is_wp_error( $lock ) ) return $lock;
		set_transient( 'livecrafts_plugins_lock', 1, 15 * MINUTE_IN_SECONDS );

		$pre = livecrafts_site_health();
		if ( false === $pre['ok'] ) { livecrafts_plugins_unlock(); return new WP_Error( 'lc_unhealthy', 'The site already shows an error page before the update, so the update was not started. Fix that first.', array( 'status' => 409, 'health' => $pre ) ); }
		@set_time_limit( 300 );
		$data   = get_plugins()[ $plugin ];
		$backup = livecrafts_plugin_backup( $plugin, $data['Version'] );
		if ( is_wp_error( $backup ) ) { livecrafts_plugins_unlock(); return $backup; }
		$key = wp_generate_password( 40, false );
		$me  = wp_get_current_user();
		$job = array(
			'id' => wp_generate_password( 12, false ), 'key' => hash( 'sha256', $key ), 'plugin' => $plugin, 'name' => wp_strip_all_tags( $data['Name'] ),
			'from' => $data['Version'], 'to' => (string) $u->new_version, 'package' => (string) $u->package, 'backup' => $backup,
			'backupPath' => livecrafts_plugins_backup_root() . '/' . sanitize_file_name( livecrafts_plugin_slug( $plugin ) ) . '/' . $backup,
			'started' => time(), 'by' => $me && $me->exists() ? $me->display_name : '',
		);
		livecrafts_plugin_job_set( $job );
		return array( 'ok' => true, 'job' => $job['id'], 'key' => $key, 'recoverUrl' => plugins_url( 'recover.php', LIVECRAFTS_FILE ),
			'name' => $job['name'], 'from' => $job['from'], 'to' => $job['to'], 'backup' => $backup, 'dbHeavy' => livecrafts_plugins_db_heavy( livecrafts_plugin_slug( $plugin ) ) );
	}

	// install: everything else in this one request
	$job = livecrafts_plugin_job_get();
	if ( ! $job || $job['id'] !== (string) $req->get_param( 'job' ) || $job['plugin'] !== $plugin ) return new WP_Error( 'lc_job', 'This update is not running any more.', array( 'status' => 409 ) );
	@set_time_limit( 300 );
	$name = $job['name']; $from = $job['from']; $to = $job['to'];
	$done = function ( $result ) { livecrafts_plugin_job_set( null ); livecrafts_plugins_unlock(); return $result; };

	$job['phase'] = 'installing'; livecrafts_plugin_job_set( $job );
	$inst = livecrafts_plugin_install_package( $plugin, $job['package'] );
	if ( is_wp_error( $inst ) ) {
		// the swap is all or nothing, so the old version is still in place - make sure
		$ok = file_exists( WP_PLUGIN_DIR . '/' . $plugin ) || true === livecrafts_plugin_restore_zip( $plugin, $job['backupPath'] );
		livecrafts_plugin_history_add( array( 'plugin' => $plugin, 'name' => $name, 'action' => 'update', 'from' => $from, 'to' => $to, 'result' => 'failed', 'note' => $inst->get_error_message() ) );
		return $done( array( 'ok' => false, 'rolledBack' => false, 'plugin' => $plugin, 'name' => $name, 'from' => $from, 'to' => $to,
			'message' => sprintf( '%1$s was not updated: %2$s%3$s', $name, rtrim( $inst->get_error_message(), '.' ), $ok ? '. Nothing changed.' : '. The old files could not be checked; open the site.' ) ) );
	}
	$job['phase'] = 'checking'; livecrafts_plugin_job_set( $job );
	$now    = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin, false, false )['Version'] ?? '';
	$exists = file_exists( WP_PLUGIN_DIR . '/' . $plugin );
	$health = $exists ? livecrafts_site_health() : array( 'ok' => false, 'checks' => array( array( 'name' => 'plugin file', 'ok' => false, 'detail' => 'The plugin\'s main file is missing in the new version.' ) ) );
	if ( false === $health['ok'] ) {
		$r     = livecrafts_plugin_restore_zip( $plugin, $job['backupPath'] );
		$after = livecrafts_site_health();
		livecrafts_plugin_history_add( array( 'plugin' => $plugin, 'name' => $name, 'action' => 'auto_rollback', 'from' => $from, 'to' => $to, 'result' => true === $r ? 'rolled_back' : 'rollback_failed', 'note' => true === $r ? 'The site showed an error after the update; the backup was restored.' : $r->get_error_message() ) );
		return $done( array( 'ok' => false, 'rolledBack' => true === $r, 'plugin' => $plugin, 'name' => $name, 'from' => $from, 'to' => $to, 'health' => $health, 'healthAfter' => $after,
			'message' => true === $r ? sprintf( '%1$s %2$s broke the site (%3$s), so %4$s was restored automatically.', $name, $to, livecrafts_health_summary( $health ), $from )
				: sprintf( '%1$s %2$s broke the site and restoring %3$s failed: %4$s. Restore it from your hosting backup, or try Roll back again.', $name, $to, $from, $r->get_error_message() ) ) );
	}
	livecrafts_plugin_history_add( array( 'plugin' => $plugin, 'name' => $name, 'action' => 'update', 'from' => $from, 'to' => $now ?: $to, 'result' => null === $health['ok'] ? 'unverified' : 'ok', 'note' => null === $health['ok'] ? 'The server could not load its own pages to check them.' : '' ) );
	return $done( array(
		'ok' => true, 'verified' => true === $health['ok'], 'plugin' => $plugin, 'name' => $name, 'from' => $from, 'to' => $now ?: $to, 'backup' => $job['backup'], 'health' => $health,
		'dbHeavy' => livecrafts_plugins_db_heavy( livecrafts_plugin_slug( $plugin ) ),
		'message' => true === $health['ok']
			? sprintf( '%1$s updated to %2$s. Homepage and wp-admin load fine. Version %3$s is saved for rollback.', $name, $now ?: $to, $from )
			: sprintf( '%1$s updated to %2$s, but the server could not load its own pages to check them - please open the site and look. Version %3$s is saved for rollback.', $name, $now ?: $to, $from ),
	) );
}
