<?php
/**
 * Offline check of the theme-file ledger (includes/file-changes.php + the write/delete routes of theme-files.php):
 * a fake in-memory change table and a temporary theme folder - no WordPress, no database.
 *
 *   php tests/file-changes-offline.php
 */
if ( PHP_SAPI !== 'cli' ) exit( 1 );

$root = sys_get_temp_dir() . '/lc-files-' . getmypid();
$theme = $root . '/wp-content/themes/t';
mkdir( $theme, 0777, true );
define( 'ABSPATH', str_replace( '\\', '/', realpath( $root ) ) . '/' );

class WP_Error { public $c; public $m; public $d; function __construct( $c = '', $m = '', $d = null ) { $this->c = $c; $this->m = $m; $this->d = $d; } function get_error_message() { return $this->m; } function get_error_code() { return $this->c; } }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
class WP_REST_Request { public $p; function __construct( $p = array() ) { $this->p = $p; } function get_param( $k ) { return isset( $this->p[ $k ] ) ? $this->p[ $k ] : null; } function get_header( $k ) { return null; } }
define( 'ARRAY_A', 'ARRAY_A' );
if ( ! function_exists( 'mb_strlen' ) ) { function mb_strlen( $s ) { return strlen( $s ); } }
function add_action() {}
if ( ! function_exists( 'mb_substr' ) ) { function mb_substr( $s, $a, $l = null ) { return substr( $s, $a, $l ); } } // WordPress provides it when mbstring is missing
function wp_json_encode( $v ) { return json_encode( $v, JSON_UNESCAPED_UNICODE ); }
function sanitize_text_field( $s ) { return trim( (string) $s ); }
function get_current_user_id() { return 7; }
function get_userdata( $id ) { return (object) array( 'ID' => $id, 'user_login' => 'sam', 'display_name' => 'Sam' ); }
function get_post( $id ) { return null; }
function get_permalink( $id ) { return ''; }
function wp_normalize_path( $p ) { return str_replace( '\\', '/', $p ); }
function trailingslashit( $s ) { return rtrim( $s, '/\\' ) . '/'; }
function get_stylesheet_directory() { global $theme; return $theme; }
function get_template_directory() { global $theme; return $theme; }
function current_user_can( $c ) { return true; }
function livecrafts_actor( $req = null ) { return get_current_user_id(); }
function livecrafts_request_source( $req ) { return 'assistant'; }
function livecrafts_rest_change_opts( $req ) { return array( 'actor' => 7, 'source' => 'assistant', 'ref' => (string) $req->get_param( 'ref' ) ); }

class FakeDB {
	public $prefix = 'wp_'; public $rows = array(); public $insert_id = 0;
	function insert( $t, $row ) { $this->insert_id = count( $this->rows ) + 1; $row['id'] = $this->insert_id; $this->rows[ $this->insert_id ] = $row; return 1; }
	function update( $t, $f, $w ) { foreach ( $f as $k => $v ) $this->rows[ $w['id'] ][ $k ] = $v; return 1; }
	function prepare( $sql, ...$a ) { $vals = isset( $a[0] ) && is_array( $a[0] ) ? $a[0] : $a; $i = 0; return preg_replace_callback( '/%[ds]/', function ( $m ) use ( &$i, $vals ) { $v = $vals[ $i++ ]; return $m[0] === '%d' ? (string) (int) $v : "'" . $v . "'"; }, $sql ); }
	function get_row( $sql, $o = null ) { preg_match( '/id = (\d+)/', $sql, $m ); return isset( $this->rows[ (int) $m[1] ] ) ? $this->rows[ (int) $m[1] ] : null; }
	function get_var( $sql ) { preg_match( "/status = '(\w+)'/", $sql, $m ); $n = 0; foreach ( $this->rows as $r ) if ( $r['status'] === $m[1] ) $n++; return $n; }
	function get_results( $sql, $o = null ) {
		$out = array();
		foreach ( $this->rows as $r ) {
			if ( $r['kind'] !== 'file.write' || $r['status'] !== 'live' || $r['reverts'] !== null ) continue;
			if ( strpos( $sql, 'release_id IS NULL' ) !== false && $r['release_id'] !== null ) continue;
			if ( preg_match( '/release_id > (\d+)/', $sql, $m ) && ( $r['release_id'] === null || $r['release_id'] <= (int) $m[1] ) ) continue;
			$out[] = $r;
		}
		return array_reverse( $out );
	}
}
$wpdb = new FakeDB();

$lc = dirname( __DIR__ ) . '/livecrafts/includes/';
require $lc . 'schema.php';
require $lc . 'ledger.php';
require $lc . 'file-changes.php';
require $lc . 'theme-files.php';

$fails = 0;
function check( $label, $ok, $got = '' ) { global $fails; echo ( $ok ? '  ok   ' : '  FAIL ' ) . $label . ( $ok ? '' : "\n       " . ( is_string( $got ) ? $got : json_encode( $got ) ) ) . "\n"; if ( ! $ok ) $fails++; }
function write( $path, $content, $sha = '', $extra = array() ) { return livecrafts_rest_theme_file_write( new WP_REST_Request( array( 'path' => $path, 'content' => $content, 'expectedSha1' => $sha, 'ref' => 'job#1' ) + $extra ) ); }
function fileOf( $rel ) { $p = ABSPATH . $rel; return file_exists( $p ) ? file_get_contents( $p ) : null; }

$css = 'wp-content/themes/t/style.css';
$new = 'wp-content/themes/t/part.php';
file_put_contents( $theme . '/style.css', "a{color:red}\n" );

echo "writes are recorded\n";
$r = write( $css, "a{color:blue}\n", sha1( "a{color:red}\n" ) );
check( 'edit is written and recorded', $r['ok'] && $r['change'] && $r['change']['status'] === 'live' && $r['change']['kind'] === 'file.write' && $r['change']['summary'] === 'Edit ' . $css, $r );
check( 'the change belongs to the file, credited to the person', $r['change']['object']['label'] === 'Theme file ' . $css && $r['change']['user']['name'] === 'Sam' && $r['change']['ref'] === 'job#1' );
$r2 = write( $new, "<?php echo 1;\n", 'new' );
check( 'creating a file is recorded as Create', $r2['ok'] && $r2['change']['summary'] === 'Create ' . $new, $r2 );
$r3 = write( $css, "a{color:green}\n", sha1( "a{color:blue}\n" ) );
check( 'three pending file changes, counted as unpublished', count( livecrafts_files_pending() ) === 3 && livecrafts_draft_count() === 3, array( count( livecrafts_files_pending() ), livecrafts_draft_count() ) );
$bad = write( $css, "\xff\xfe broken", sha1( "a{color:green}\n" ) );
check( 'content that cannot be stored for undo is refused, file untouched', is_wp_error( $bad ) && fileOf( $css ) === "a{color:green}\n", $bad );
$php = write( 'wp-content/themes/t/bad.php', "<?php echo ;", 'new' );
check( 'PHP with a syntax error is still refused', is_wp_error( $php ) && fileOf( 'wp-content/themes/t/bad.php' ) === null );

echo "automatic rollback leaves no trace\n";
$a = write( $css, "a{color:black}\n", sha1( "a{color:green}\n" ) );
$back = write( $css, "a{color:green}\n", sha1( "a{color:black}\n" ), array( 'rollback_of' => $a['change']['id'] ) );
check( 'rollback restores the file and drops the change', $back['ok'] && $back['change'] === null && fileOf( $css ) === "a{color:green}\n" && $wpdb->rows[ $a['change']['id'] ]['status'] === 'discarded' );
check( 'still three pending', count( livecrafts_files_pending() ) === 3 );

echo "revert one change\n";
$c3 = livecrafts_change_get( $r3['change']['id'] );
$out = livecrafts_file_revert( $c3 );
check( 'newest edit reverts to the previous content', ! is_wp_error( $out ) && fileOf( $css ) === "a{color:blue}\n" && $out['change']['reverts'] === $c3['id'], $out );
check( 'the reverted change leaves the pending list; the revert row is not pending', count( livecrafts_files_pending() ) === 2 );
$again = livecrafts_file_revert( livecrafts_change_get( $c3['id'] ) );
check( 'reverting twice is refused', is_wp_error( $again ) && $again->c === 'livecrafts_already' );
echo "discard all / reset\n";
file_put_contents( $theme . '/style.css', "a{color:red}\n" ); // back to the original for a clean run
$wpdb->rows = array(); $wpdb->insert_id = 0;
$w1 = write( $css, "a{color:blue}\n", sha1( "a{color:red}\n" ) );
$w2 = write( $css, "a{color:green}\n", sha1( "a{color:blue}\n" ) );
$w3 = write( $new, "<?php echo 2;\n", sha1( "<?php echo 1;\n" ) );
$report = array();
livecrafts_files_discard( 5, $report );
check( 'Discard all restores every pending file, newest first', $report['files'] === 3 && empty( $report['file_errors'] ) && fileOf( $css ) === "a{color:red}\n" && fileOf( $new ) === "<?php echo 1;\n", array( $report, fileOf( $css ), fileOf( $new ) ) );
check( 'nothing pending any more', count( livecrafts_files_pending() ) === 0 && livecrafts_draft_count() === 0 );
check( 'the revert rows credit the person who discarded', $wpdb->rows[ count( $wpdb->rows ) ]['user_id'] === 5 && $wpdb->rows[ count( $wpdb->rows ) ]['source'] === 'system' );

$x1 = write( $css, "a{color:teal}\n", sha1( "a{color:red}\n" ) );
file_put_contents( $theme . '/style.css', "a{color:pink}\n" ); // someone edits it again outside
$report = array();
livecrafts_files_discard( 5, $report );
check( 'a file edited again by someone else is reported, not overwritten', $report['files'] === 0 && count( $report['file_errors'] ) === 1 && fileOf( $css ) === "a{color:pink}\n", $report );

echo "deploy accepts, reset undoes\n";
file_put_contents( $theme . '/style.css', "a{color:red}\n" );
$wpdb->rows = array(); $wpdb->insert_id = 0;
$d1 = write( $css, "a{color:blue}\n", sha1( "a{color:red}\n" ) );
foreach ( livecrafts_files_pending() as $f ) livecrafts_change_update( $f['id'], array( 'release_id' => 4 ) ); // what livecrafts_deploy does
check( 'a released edit is no longer pending (Discard all leaves it)', count( livecrafts_files_pending() ) === 0 );
$d2 = write( $css, "a{color:gold}\n", sha1( "a{color:blue}\n" ) );
foreach ( livecrafts_files_pending() as $f ) livecrafts_change_update( $f['id'], array( 'release_id' => 6 ) );
check( 'released after release #4: only the later edit', count( livecrafts_files_released_after( 4 ) ) === 1 && livecrafts_files_released_after( 4 )[0]['id'] === $d2['change']['id'] );
foreach ( livecrafts_files_released_after( 4 ) as $c ) livecrafts_file_revert( $c, array( 'bulk' => true, 'release_id' => 9 ) );
check( 'a reset to release #4 brings the file back as it was then', fileOf( $css ) === "a{color:blue}\n" );

echo "delete\n";
$del = livecrafts_rest_theme_file_delete( new WP_REST_Request( array( 'path' => $new, 'expectedSha1' => sha1( fileOf( $new ) ) ) ) );
check( 'deleting is recorded and can be reverted', $del['ok'] && $del['change']['summary'] === 'Delete ' . $new && fileOf( $new ) === null );
$rev = livecrafts_file_revert( livecrafts_change_get( $del['change']['id'] ) );
check( 'reverting a delete brings the file back', ! is_wp_error( $rev ) && fileOf( $new ) !== null, $rev );

// clean up
foreach ( glob( $theme . '/*' ) as $f ) unlink( $f );
rmdir( $theme ); rmdir( dirname( $theme ) ); rmdir( dirname( dirname( $theme ) ) ); rmdir( $root );
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
