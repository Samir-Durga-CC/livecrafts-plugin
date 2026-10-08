<?php
/**
 * REST API for the ACTIVE theme's files (the Livecrafts backend uses this to add sections, e.g. a new footer):
 *   GET  /livecrafts/v1/theme-files            -> list the files of the active theme (and its parent theme)
 *   GET  /livecrafts/v1/theme-file?path=...     -> exact file content + sha1
 *   POST /livecrafts/v1/theme-file {path, content, expectedSha1}
 *
 * Safety rules (enforced here, on the server):
 *  - Only files inside the active theme / parent theme folder. Never wp-config.php, plugins or WordPress core.
 *  - Only text file types a theme uses: php, css, js, json, html, txt, svg.
 *  - Needs the "edit_themes" capability (an administrator; it is removed when DISALLOW_FILE_EDIT is set).
 *  - PHP is syntax-checked BEFORE it is written (a file with a parse error is refused, nothing is written).
 *  - expectedSha1 must match the current file, so a change made meanwhile is never overwritten blindly.
 * Every write and delete is recorded in the change ledger (file-changes.php) with the full before/after content, so it
 * shows in the site history next to every other change and can be reverted - or discarded - from anywhere.
 * The backend restores a file automatically if the site stops loading (it passes rollback_of, and the change leaves the history).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const LIVECRAFTS_THEME_EXT = '/\.(php|css|js|json|html|txt|svg)$/i';

function livecrafts_theme_files_permission() {
	return current_user_can( 'edit_themes' );
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'livecrafts/v1', '/theme-files', array(
		'methods' => 'GET', 'callback' => 'livecrafts_rest_theme_files', 'permission_callback' => 'livecrafts_theme_files_permission',
	) );
	register_rest_route( 'livecrafts/v1', '/theme-file', array(
		array( 'methods' => 'GET', 'callback' => 'livecrafts_rest_theme_file_read', 'permission_callback' => 'livecrafts_theme_files_permission' ),
		array( 'methods' => 'POST', 'callback' => 'livecrafts_rest_theme_file_write', 'permission_callback' => 'livecrafts_theme_files_permission' ),
		array( 'methods' => 'DELETE', 'callback' => 'livecrafts_rest_theme_file_delete', 'permission_callback' => 'livecrafts_theme_files_permission' ),
	) );
} );

/** Theme roots that may be touched: the active (child) theme and its parent. */
function livecrafts_theme_roots() {
	$roots = array( wp_normalize_path( realpath( get_stylesheet_directory() ) ) );
	$parent = wp_normalize_path( realpath( get_template_directory() ) );
	if ( $parent && ! in_array( $parent, $roots, true ) ) $roots[] = $parent;
	return array_filter( $roots );
}

/** Path relative to the WordPress folder ("wp-content/themes/x/footer.php") for an absolute path. */
function livecrafts_rel_path( $abs ) {
	$base = trailingslashit( wp_normalize_path( realpath( ABSPATH ) ) );
	$abs  = wp_normalize_path( $abs );
	return strpos( $abs, $base ) === 0 ? substr( $abs, strlen( $base ) ) : $abs;
}

/**
 * Resolve a requested path to an absolute path inside an allowed theme root, or a WP_Error.
 * $must_exist=false allows creating a NEW file (e.g. template-parts/blog-section.php) inside an existing folder.
 */
function livecrafts_theme_path( $rel, $must_exist = true ) {
	$rel = ltrim( str_replace( '\\', '/', (string) $rel ), '/' );
	if ( $rel === '' || strpos( $rel, '..' ) !== false || strpos( $rel, "\0" ) !== false ) {
		return new WP_Error( 'livecrafts_bad_path', 'Invalid path.', array( 'status' => 400 ) );
	}
	if ( ! preg_match( LIVECRAFTS_THEME_EXT, $rel ) ) {
		return new WP_Error( 'livecrafts_bad_type', 'Only php, css, js, json, html, txt and svg theme files are allowed.', array( 'status' => 400 ) );
	}
	$abs = wp_normalize_path( ABSPATH . $rel );
	$real = $must_exist ? realpath( $abs ) : realpath( dirname( $abs ) );
	if ( ! $real ) return new WP_Error( 'livecrafts_not_found', $must_exist ? 'File not found: ' . $rel : 'Folder not found for: ' . $rel, array( 'status' => 404 ) );
	$real = wp_normalize_path( $real );
	$check = $must_exist ? $real : trailingslashit( $real ) . basename( $abs );
	foreach ( livecrafts_theme_roots() as $root ) {
		if ( strpos( $check, trailingslashit( $root ) ) === 0 ) return $check;
	}
	return new WP_Error( 'livecrafts_outside_theme', 'Only files of the active theme (or its parent theme) can be read or changed here.', array( 'status' => 403 ) );
}

function livecrafts_rest_theme_files() {
	$out = array();
	foreach ( livecrafts_theme_roots() as $root ) {
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $f ) {
			if ( count( $out ) >= 600 ) break 2;
			$p = wp_normalize_path( $f->getPathname() );
			if ( strpos( $p, '/node_modules/' ) !== false || strpos( $p, '/.git/' ) !== false ) continue;
			if ( ! preg_match( LIVECRAFTS_THEME_EXT, $p ) ) continue;
			$out[] = array( 'path' => livecrafts_rel_path( $p ), 'bytes' => $f->getSize(), 'writable' => is_writable( $p ) );
		}
	}
	$theme = wp_get_theme();
	return array(
		'ok' => true, 'theme' => $theme->get_stylesheet(), 'parent' => $theme->parent() ? $theme->get_template() : null,
		'isBlockTheme' => function_exists( 'wp_is_block_theme' ) ? wp_is_block_theme() : false, 'files' => $out,
	);
}

function livecrafts_rest_theme_file_read( WP_REST_Request $req ) {
	$abs = livecrafts_theme_path( $req->get_param( 'path' ) );
	if ( is_wp_error( $abs ) ) return $abs;
	$content = file_get_contents( $abs );
	if ( $content === false ) return new WP_Error( 'livecrafts_read_failed', 'Could not read the file.', array( 'status' => 500 ) );
	return array( 'ok' => true, 'path' => livecrafts_rel_path( $abs ), 'content' => $content, 'sha1' => sha1( $content ), 'bytes' => strlen( $content ), 'writable' => is_writable( $abs ) );
}

/** PHP syntax check without running the code (PHP 7+: token_get_all with TOKEN_PARSE throws on a parse error). */
function livecrafts_php_syntax_error( $code ) {
	try {
		token_get_all( $code, TOKEN_PARSE );
		return null;
	} catch ( ParseError $e ) {
		return sprintf( 'PHP syntax error on line %d: %s', $e->getLine(), $e->getMessage() );
	}
}

function livecrafts_rest_theme_file_write( WP_REST_Request $req ) {
	$content = $req->get_param( 'content' );
	if ( ! is_string( $content ) ) return new WP_Error( 'livecrafts_bad_request', 'content must be a string.', array( 'status' => 400 ) );
	if ( strlen( $content ) > 2000000 ) return new WP_Error( 'livecrafts_too_large', 'File too large.', array( 'status' => 413 ) );

	$rel    = $req->get_param( 'path' );
	$exists = ! is_wp_error( livecrafts_theme_path( $rel, true ) );
	$abs    = livecrafts_theme_path( $rel, $exists );
	if ( is_wp_error( $abs ) ) return $abs;

	$expected = (string) $req->get_param( 'expectedSha1' );
	$current  = null;
	if ( $exists ) {
		$current = file_get_contents( $abs );
		if ( $expected !== '' && sha1( $current ) !== $expected ) {
			return new WP_Error( 'livecrafts_changed', 'The file changed since it was read. Read it again before editing.', array( 'status' => 409 ) );
		}
	} elseif ( $expected !== '' && $expected !== 'new' ) {
		return new WP_Error( 'livecrafts_not_found', 'File not found: ' . $rel, array( 'status' => 404 ) );
	}

	if ( preg_match( '/\.php$/i', $abs ) ) {
		$err = livecrafts_php_syntax_error( $content );
		if ( $err ) return new WP_Error( 'livecrafts_php_syntax', $err . ' - nothing was written.', array( 'status' => 400 ) );
	}
	if ( ( $exists && ! is_writable( $abs ) ) || ( ! $exists && ! is_writable( dirname( $abs ) ) ) ) {
		return new WP_Error( 'livecrafts_not_writable', 'The server does not allow writing this file (file permissions).', array( 'status' => 500 ) );
	}
	if ( ! livecrafts_file_storable( $current, $content ) ) {
		return new WP_Error( 'livecrafts_not_utf8', 'The file is not valid UTF-8 text, so the change could not be recorded for undo - nothing was written.', array( 'status' => 400 ) );
	}
	if ( file_put_contents( $abs, $content, LOCK_EX ) === false ) {
		return new WP_Error( 'livecrafts_write_failed', 'Could not write the file.', array( 'status' => 500 ) );
	}
	clearstatcache( true, $abs );
	if ( function_exists( 'opcache_invalidate' ) ) @opcache_invalidate( $abs, true ); // otherwise PHP may keep running the old version for a while

	$after  = file_get_contents( $abs );
	$change = livecrafts_file_ledger( $req, livecrafts_rel_path( $abs ), $current, $after );
	return array( 'ok' => true, 'path' => livecrafts_rel_path( $abs ), 'created' => ! $exists, 'sha1' => sha1( $after ), 'bytes' => strlen( $after ), 'verified' => $after === $content, 'change' => $change );
}

/**
 * Record a write/delete in the ledger. rollback_of = the backend undid its own change at once because the page broke:
 * that change leaves the history instead of adding a second entry.
 */
function livecrafts_file_ledger( WP_REST_Request $req, $rel, $before, $after ) {
	$rollback = (int) $req->get_param( 'rollback_of' );
	if ( $rollback ) {
		livecrafts_file_drop( $rollback, livecrafts_actor( $req ), 'rolled back automatically: the page broke' );
		return null;
	}
	return livecrafts_file_record( $rel, $before, $after, livecrafts_rest_change_opts( $req ) );
}

/** Remove a file the backend CREATED (used to revert "new file"). Only when its content is still exactly what was written. */
function livecrafts_rest_theme_file_delete( WP_REST_Request $req ) {
	$abs = livecrafts_theme_path( $req->get_param( 'path' ) );
	if ( is_wp_error( $abs ) ) return $abs;
	$expected = (string) $req->get_param( 'expectedSha1' );
	if ( $expected === '' || sha1( file_get_contents( $abs ) ) !== $expected ) {
		return new WP_Error( 'livecrafts_changed', 'The file was changed after it was created, so it is not deleted automatically.', array( 'status' => 409 ) );
	}
	$content = file_get_contents( $abs );
	if ( ! livecrafts_file_storable( $content, null ) ) {
		return new WP_Error( 'livecrafts_not_utf8', 'The file is not valid UTF-8 text, so the delete could not be recorded for undo - nothing was deleted.', array( 'status' => 400 ) );
	}
	if ( ! @unlink( $abs ) ) return new WP_Error( 'livecrafts_delete_failed', 'Could not delete the file.', array( 'status' => 500 ) );
	if ( function_exists( 'opcache_invalidate' ) ) @opcache_invalidate( $abs, true );
	return array( 'ok' => true, 'deleted' => livecrafts_rel_path( $abs ), 'change' => livecrafts_file_ledger( $req, livecrafts_rel_path( $abs ), $content, null ) );
}
