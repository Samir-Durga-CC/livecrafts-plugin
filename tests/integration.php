<?php
/**
 * Livecrafts integration test - runs against a real local WordPress (never a live site: it creates and deletes content).
 *
 *   php tests/integration.php <path to WordPress> [admin user id]
 *
 * Needs: Livecrafts active, ACF active (the ACF part is skipped otherwise). Elementor is not required.
 * It creates a test page, makes draft changes through the REST API exactly like the backend/widget do, checks the
 * preview vs the live site (front-end renders in separate processes, see render.php), deploys, reverts, resets,
 * migrates an old overlay, and removes everything it created at the end.
 */

if ( PHP_SAPI !== 'cli' ) exit( 1 );
$wp = rtrim( $argv[1] ?? '', '/\\' );
if ( ! $wp || ! file_exists( $wp . '/wp-load.php' ) ) { fwrite( STDERR, "Usage: php tests/integration.php <path to WordPress> [admin user id]\n" ); exit( 1 ); }
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
define( 'WP_USE_THEMES', false );
require $wp . '/wp-load.php';

$admin = (int) ( $argv[2] ?? 0 );
if ( ! $admin ) { $admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) ); $admin = (int) ( $admins[0] ?? 0 ); }
wp_set_current_user( $admin );

$fails = 0; $passes = 0;
function check( $label, $ok, $detail = '' ) {
	global $fails, $passes;
	if ( $ok ) { $passes++; echo "  ok   $label\n"; }
	else { $fails++; echo "  FAIL $label" . ( $detail !== '' ? "\n       " . ( is_string( $detail ) ? $detail : wp_json_encode( $detail ) ) : '' ) . "\n"; }
}
function api( $method, $route, array $params = array(), array $headers = array() ) {
	$req = new WP_REST_Request( $method, '/livecrafts/v1/' . $route );
	foreach ( $params as $k => $v ) $req->set_param( $k, $v );
	foreach ( $headers as $k => $v ) $req->set_header( $k, $v );
	$res = rest_do_request( $req );
	return array( $res->get_status(), $res->get_data() );
}
/** Render a page in a fresh PHP process, as a logged-in editor ($user) or a visitor (0). */
function render( $url, $user, $view = 'draft' ) {
	global $wp;
	$cmd = escapeshellarg( PHP_BINARY ) . ' ' . implode( ' ', array_map( 'escapeshellarg', array_merge(
		array( '-n', '-d', 'extension_dir=' . ini_get( 'extension_dir' ), '-d', 'extension=mysqli', '-d', 'extension=mbstring', '-d', 'mysqli.default_port=' . ini_get( 'mysqli.default_port' ) ),
		array( __DIR__ . '/render.php', $wp, $url, (string) $user, $view )
	) ) );
	return (string) shell_exec( $cmd );
}

echo "Livecrafts " . LIVECRAFTS_VERSION . " on WordPress " . get_bloginfo( 'version' ) . " (user #$admin)\n";
if ( ! function_exists( 'livecrafts_change_create' ) ) { echo "Livecrafts is not active.\n"; exit( 1 ); }

// ------------------------------------------------------------------ setup
$acf = function_exists( 'acf_add_local_field_group' );
$stamp = 'lc-test-' . substr( md5( microtime() ), 0, 6 );
$page = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Livecrafts Test ' . $stamp, 'post_name' => $stamp,
	'post_content' => "<!-- wp:paragraph -->\n<p>Original paragraph</p>\n<!-- /wp:paragraph -->" ) );
if ( $acf ) {
	acf_add_local_field_group( array( 'key' => 'group_lc_test', 'title' => 'LC Test', 'fields' => array(
		array( 'key' => 'field_lc_test_hero', 'name' => 'lc_test_hero', 'label' => 'Hero title', 'type' => 'text' ),
	), 'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'page' ) ) ) ) );
	update_field( 'field_lc_test_hero', 'Original hero', $page );
}
$css_before = wp_get_custom_css();
$old_password = get_option( LIVECRAFTS_DEPLOY_PASSWORD, '' );
livecrafts_set_deploy_password( 'test-deploy-' . $stamp );
$baseline = livecrafts_mark_baseline( $admin, 'Before the integration test' );
check( 'baseline release created', ! empty( $baseline['id'] ) );

// ------------------------------------------------------------------ ping + map
list( $s, $d ) = api( 'GET', 'ping' );
check( 'ping', $s === 200 && $d['ok'] && $d['capabilities']['drafts'], $d );

// ------------------------------------------------------------------ drafts
list( $s, $d ) = api( 'POST', 'changes', array( 'kind' => 'post.field', 'post' => $page, 'target' => 'title', 'value' => 'Draft title ' . $stamp, 'source' => 'widget' ) );
check( 'draft title change accepted', $s === 200 && ! empty( $d['change']['id'] ), $d );
$title_change = $d['change']['id'] ?? 0;
check( 'live title unchanged', get_post_field( 'post_title', $page ) === 'Livecrafts Test ' . $stamp );
check( 'preview copy exists', livecrafts_draft_copy_id( $page ) > 0 );
list( , $d ) = api( 'GET', 'post', array( 'id' => $page ) );
check( 'GET post (draft view) shows the draft title', ( $d['title'] ?? '' ) === 'Draft title ' . $stamp, $d );
list( , $d ) = api( 'GET', 'post', array( 'id' => $page, 'view' => 'live' ) );
check( 'GET post (live view) shows the live title', ( $d['title'] ?? '' ) === 'Livecrafts Test ' . $stamp, $d );

list( $s, $d ) = api( 'POST', 'changes', array( 'kind' => 'post.field', 'post' => $page, 'target' => 'title', 'value' => 'Draft title ' . $stamp ) );
check( 'same value again = unchanged, nothing stored', $s === 200 && ! empty( $d['unchanged'] ), $d );

$content = "<!-- wp:paragraph -->\n<p>Draft paragraph</p>\n<!-- /wp:paragraph -->";
list( $s, $d ) = api( 'POST', 'changes', array( 'kind' => 'post.field', 'post' => $page, 'target' => 'content', 'value' => $content ) );
check( 'draft content change accepted', $s === 200 && ! empty( $d['change']['id'] ), $d );

if ( $acf ) {
	list( $s, $d ) = api( 'POST', 'changes', array( 'kind' => 'acf.field', 'post' => $page, 'target' => 'field_lc_test_hero', 'value' => 'Draft hero' ) );
	check( 'draft ACF change accepted', $s === 200 && ! empty( $d['change']['id'] ), $d );
	check( 'live ACF value unchanged', get_field( 'field_lc_test_hero', $page, false ) === 'Original hero', get_field( 'field_lc_test_hero', $page, false ) );
	list( , $d ) = api( 'GET', 'map', array( 'post' => $page ) );
	$hero = array_values( array_filter( $d['acf'] ?? array(), function ( $e ) { return $e['key'] === 'field_lc_test_hero'; } ) );
	check( 'map (draft view) shows the draft ACF value', ( $hero[0]['value'] ?? '' ) === 'Draft hero', $hero );
	list( , $d ) = api( 'GET', 'map', array( 'post' => $page, 'view' => 'live' ) );
	$hero = array_values( array_filter( $d['acf'] ?? array(), function ( $e ) { return $e['key'] === 'field_lc_test_hero'; } ) );
	check( 'map (live view) shows the live ACF value', ( $hero[0]['value'] ?? '' ) === 'Original hero', $hero );
	list( $s, $d ) = api( 'POST', 'changes', array( 'kind' => 'acf.field', 'post' => $page, 'target' => 'field_does_not_exist', 'value' => 'x' ) );
	check( 'unknown ACF field refused', $s === 404, array( $s, $d ) );
}

list( $s, $d ) = api( 'POST', 'changes', array( 'kind' => 'css.block', 'target' => $stamp, 'value' => '.x { color: red !important; }' ) );
check( '!important refused', $s === 400, array( $s, $d ) );
list( $s, $d ) = api( 'POST', 'changes', array( 'kind' => 'css.block', 'target' => $stamp, 'value' => '.x { color: red; ', 'label' => 'Test' ) );
check( 'unbalanced braces refused', $s === 400, array( $s, $d ) );
list( $s, $d ) = api( 'POST', 'changes', array( 'kind' => 'css.block', 'target' => $stamp, 'value' => 'body.page-id-' . $page . ' .lc-test { color: #0b3d91; }', 'label' => 'Test colour' ) );
check( 'draft CSS block accepted', $s === 200 && ! empty( $d['change']['id'] ), $d );
check( 'live Additional CSS unchanged', wp_get_custom_css() === $css_before );

list( $s, $d ) = api( 'GET', 'status' );
check( 'status lists the drafts', $s === 200 && $d['drafts']['count'] === ( $acf ? 4 : 3 ), $d['drafts'] ?? $d );
check( 'status: no conflicts yet', empty( $d['conflicts'] ), $d['conflicts'] ?? null );

// ------------------------------------------------------------------ preview vs live on the front end
$url = get_permalink( $page );
$as_editor  = render( $url, $admin );
$as_visitor = render( $url, 0 );
$as_live    = render( $url, $admin, 'live' );
check( 'editor preview shows the draft title', strpos( $as_editor, 'Draft title ' . $stamp ) !== false, substr( strip_tags( $as_editor ), 0, 300 ) );
check( 'editor preview shows the draft content', strpos( $as_editor, 'Draft paragraph' ) !== false );
check( 'editor preview has the draft CSS', strpos( $as_editor, 'livecrafts:start ' . $stamp ) !== false );
check( 'visitor sees the live title', strpos( $as_visitor, 'Livecrafts Test ' . $stamp ) !== false && strpos( $as_visitor, 'Draft title' ) === false, substr( strip_tags( $as_visitor ), 0, 300 ) );
check( 'visitor sees the live content and no draft CSS', strpos( $as_visitor, 'Original paragraph' ) !== false && strpos( $as_visitor, 'livecrafts:start ' . $stamp ) === false );
check( 'editor with "Show live" sees the live site', strpos( $as_live, 'Draft title' ) === false && strpos( $as_live, 'Original paragraph' ) !== false );

// ------------------------------------------------------------------ a change made outside Livecrafts -> conflict
wp_update_post( array( 'ID' => $page, 'post_title' => 'Changed in WP admin ' . $stamp ) );
livecrafts_watch_record(); // normally runs at the end of the request
$outside = livecrafts_changes_query( array( 'kind' => 'external.edit', 'object_type' => 'post', 'object_id' => $page, 'limit' => 1 ) );
check( 'outside change recorded with snapshots', $outside && livecrafts_change_snapshot( $outside[0]['id'], 'before' ) && livecrafts_change_snapshot( $outside[0]['id'], 'after' ), $outside );
list( , $d ) = api( 'GET', 'status' );
check( 'status shows the title conflict', count( $d['conflicts'] ?? array() ) === 1, $d['conflicts'] ?? null );

// ------------------------------------------------------------------ deploy
list( $s, $d ) = api( 'POST', 'deploy', array( 'password' => 'wrong', 'notes' => 'x' ) );
check( 'deploy with a wrong password refused', $s === 403, array( $s, $d ) );
list( $s, $d ) = api( 'POST', 'deploy', array( 'password' => 'test-deploy-' . $stamp, 'notes' => 'Test deploy' ) );
check( 'deploy refused while there is a conflict', $s === 409 && ( $d['code'] ?? '' ) === 'livecrafts_conflict', array( $s, $d ) );
check( 'nothing deployed after the refusal', get_post_field( 'post_title', $page ) === 'Changed in WP admin ' . $stamp );
list( $s, $d ) = api( 'POST', 'deploy', array( 'password' => 'test-deploy-' . $stamp, 'notes' => 'Test deploy', 'force' => true ) );
check( 'deploy with force', $s === 200 && ! empty( $d['ok'] ), $d );
$release = $d['release']['id'] ?? 0;
clean_post_cache( $page );
check( 'live title is the draft title', get_post_field( 'post_title', $page ) === 'Draft title ' . $stamp, get_post_field( 'post_title', $page ) );
check( 'live content is the draft content', strpos( get_post_field( 'post_content', $page ), 'Draft paragraph' ) !== false );
if ( $acf ) check( 'live ACF value is the draft value', get_field( 'field_lc_test_hero', $page, false ) === 'Draft hero', get_field( 'field_lc_test_hero', $page, false ) );
check( 'live Additional CSS has the block', strpos( wp_get_custom_css(), 'livecrafts:start ' . $stamp ) !== false );
check( 'no drafts left, preview copy gone', livecrafts_draft_count() === 0 && ! livecrafts_draft_copy_id( $page ), livecrafts_draft_count() );
$c = livecrafts_change_get( $title_change );
check( 'change is live and belongs to the release', $c['status'] === 'live' && $c['release_id'] === $release, $c );
check( 'change credited to the widget source', $c['source'] === 'widget' );
$as_visitor = render( $url, 0 );
check( 'visitor now sees the deployed title', strpos( $as_visitor, 'Draft title ' . $stamp ) !== false );

// ------------------------------------------------------------------ revert a live change (becomes a draft, then deploy)
list( $s, $d ) = api( 'POST', 'changes/' . $title_change . '/revert' );
check( 'revert of a live change creates a draft', $s === 200 && ( $d['change']['reverts'] ?? 0 ) === $title_change, $d );
check( 'revert did not touch the live site yet', get_post_field( 'post_title', $page ) === 'Draft title ' . $stamp );
list( $s, $d ) = api( 'POST', 'deploy', array( 'password' => 'test-deploy-' . $stamp, 'notes' => 'Revert title' ) );
clean_post_cache( $page );
check( 'deployed revert puts the title from before that change back', $s === 200 && get_post_field( 'post_title', $page ) === 'Livecrafts Test ' . $stamp, array( $s, get_post_field( 'post_title', $page ) ) );

// ------------------------------------------------------------------ discard
api( 'POST', 'changes', array( 'kind' => 'post.field', 'post' => $page, 'target' => 'excerpt', 'value' => 'Draft excerpt' ) );
list( $s, $d ) = api( 'POST', 'pages', array( 'title' => 'New test page ' . $stamp, 'content' => '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->' ) );
$new_page = $d['id'] ?? 0;
check( 'new page created as a WordPress draft', $s === 200 && get_post_status( $new_page ) === 'draft', $d );
list( $s, $d ) = api( 'POST', 'drafts/discard' );
check( 'discard all', $s === 200 && $d['discarded'] === 2, $d );
check( 'discarded new page went to the Trash', get_post_status( $new_page ) === 'trash' );
check( 'discard left the live page alone', get_post_field( 'post_excerpt', $page ) === '' );

// ------------------------------------------------------------------ reset to the baseline
list( $s, $d ) = api( 'POST', 'releases/' . $baseline['id'] . '/reset', array( 'password' => 'test-deploy-' . $stamp, 'notes' => 'Back to the baseline' ) );
clean_post_cache( $page );
check( 'reset to the baseline', $s === 200 && ! empty( $d['ok'] ), $d );
check( 'title back to the baseline', get_post_field( 'post_title', $page ) === 'Livecrafts Test ' . $stamp, get_post_field( 'post_title', $page ) );
check( 'content back to the baseline', strpos( get_post_field( 'post_content', $page ), 'Original paragraph' ) !== false );
if ( $acf ) check( 'ACF value back to the baseline', get_field( 'field_lc_test_hero', $page, false ) === 'Original hero', get_field( 'field_lc_test_hero', $page, false ) );
check( 'Additional CSS back to the baseline', strpos( wp_get_custom_css(), 'livecrafts:start ' . $stamp ) === false );
list( , $d ) = api( 'GET', 'changes', array( 'post' => $page, 'limit' => 50 ) );
check( 'history of the page is complete', count( $d['changes'] ) >= 5, count( $d['changes'] ) );

// ------------------------------------------------------------------ Phase 2: blocks, style rules, settings, click resolver
list( $s, $d ) = api( 'POST', 'changes', array( 'kind' => 'block.text', 'post' => $page, 'target' => '0', 'value' => 'Block <strong>text</strong> ' . $stamp ) );
check( 'block.text draft', $s === 200 && ! empty( $d['change']['id'] ), $d );
list( $s, $d ) = api( 'POST', 'changes', array( 'kind' => 'block.insert', 'post' => $page, 'target' => ':end', 'value' => "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Added heading</h2>\n<!-- /wp:heading -->" ) );
check( 'block.insert draft', $s === 200 && ! empty( $d['change']['id'] ), $d );
list( $s, $d ) = api( 'POST', 'changes', array( 'kind' => 'block.insert', 'post' => $page, 'target' => ':0', 'value' => '<p>loose html</p>' ) );
check( 'loose HTML refused for blocks', $s === 400, array( $s, $d ) );
$as_editor = render( $url, $admin );
check( 'editor preview shows block changes', strpos( $as_editor, 'Block <strong>text</strong> ' . $stamp ) !== false && strpos( $as_editor, 'Added heading' ) !== false );
check( 'editor page carries data-lc-block markers', strpos( $as_editor, 'data-lc-block="' . $page . ':0"' ) !== false );
check( 'visitors get no markers and no drafts', strpos( render( $url, 0 ), 'data-lc-block' ) === false );

list( $s, $d ) = api( 'POST', 'resolve', array( 'page' => $page, 'block' => $page . ':0', 'device' => 'desktop' ) );
$ids = wp_list_pluck( $d['actions'] ?? array(), 'id' );
check( 'resolve: a paragraph block can be edited, styled, moved', $s === 200 && ( $d['source']['kind'] ?? '' ) === 'block' && in_array( 'block:text', $ids, true ) && in_array( 'css:color', $ids, true ) && in_array( 'block:down', $ids, true ) && ! in_array( 'block:up', $ids, true ), $d );
$color = array_values( array_filter( $d['actions'], function ( $a ) { return $a['id'] === 'css:color'; } ) )[0];
list( $s1 ) = api( 'POST', 'changes', $color['requires'] );
$value = $color['change']['value'];
$value['declarations']['color'] = '#0b3d91';
list( $s2, $d2 ) = api( 'POST', 'changes', array( 'kind' => 'css.rule', 'value' => $value, 'label' => 'Test paragraph' ) );
check( 'resolver action applied without AI (class, then style rule)', $s1 === 200 && $s2 === 200 && ! empty( $d2['change']['id'] ), $d2 );
list( $s, $d ) = api( 'POST', 'resolve', array( 'page' => $page, 'selector' => '.site-footer a', 'tag' => 'a', 'device' => 'mobile' ) );
check( 'resolve: theme element gets style actions for mobile only', $s === 200 && ( $d['source']['kind'] ?? '' ) === 'theme' && ( $d['actions'][1]['change']['value']['media'] ?? '' ) === 'mobile', $d );
list( $s, $d ) = api( 'POST', 'changes', array( 'kind' => 'post.meta', 'post' => $page, 'target' => '_wp_page_template', 'value' => 'no-such-template.php' ) );
check( 'unknown page template refused', $s === 400, array( $s, $d ) );

list( $s, $d ) = api( 'POST', 'deploy', array( 'password' => 'test-deploy-' . $stamp, 'notes' => 'Phase 2 deploy' ) );
clean_post_cache( $page );
$live_content = get_post_field( 'post_content', $page );
check( 'Phase 2 deploy', $s === 200 && ! empty( $d['ok'] ), $d );
check( 'live content has the block changes and the class', strpos( $live_content, 'Block <strong>text</strong> ' . $stamp ) !== false && strpos( $live_content, 'Added heading' ) !== false && preg_match( '/"className":"lc-[a-z0-9]+"/', $live_content ), $live_content );
check( 'live Additional CSS has the style rule', strpos( wp_get_custom_css(), 'color: #0b3d91;' ) !== false );

// ------------------------------------------------------------------ notes + widget token
list( $s, $d ) = api( 'POST', 'notes', array( 'post' => $page, 'text' => 'Hero title is the ACF field lc_test_hero.' ) );
check( 'page notes saved', $s === 200 && $d['notes']['text'] === 'Hero title is the ACF field lc_test_hero.', $d );
list( , $d ) = api( 'GET', 'notes', array( 'post' => $page ) );
check( 'page notes read back', ( $d['page']['text'] ?? '' ) === 'Hero title is the ACF field lc_test_hero.' );
$token = livecrafts_widget_token( $admin );
check( 'widget token verifies', ( livecrafts_verify_token( $token )['uid'] ?? 0 ) === $admin );
check( 'tampered token refused', livecrafts_verify_token( $token . 'x' ) === null );

// ------------------------------------------------------------------ old overlay migration
$had_legacy = get_option( LIVECRAFTS_LEGACY_OPT, null );
$had_migration = get_option( LIVECRAFTS_MIGRATION, null );
delete_option( LIVECRAFTS_MIGRATION );
update_option( LIVECRAFTS_LEGACY_OPT, array( 'p' . $page => array( '.lc-old h1' => array( 'styles' => array( 'color' => '#ff0000' ), 'styles_mobile' => array( 'font-size' => '20px' ), 'text' => 'Old overlay text' ) ) ), false );
livecrafts_migrate();
$m = get_option( LIVECRAFTS_MIGRATION );
$mc = ! empty( $m['change'] ) ? livecrafts_change_get( $m['change'] ) : null;
check( 'old overlay styles became a draft CSS block', $mc && $mc['status'] === 'draft' && $mc['target'] === 'legacy-overlay', $m );
check( 'migrated CSS has no !important and is scoped to the page', $mc && strpos( $mc['payload']['after'], '!important' ) === false && strpos( $mc['payload']['after'], 'body.page-id-' . $page . ' .lc-old h1' ) !== false, $mc['payload']['after'] ?? '' );
check( 'old overlay text is in the report', ( $m['texts'][0]['text'] ?? '' ) === 'Old overlay text', $m['texts'] ?? null );
livecrafts_discard_drafts( null, $admin, 'test cleanup' );
$had_legacy === null ? delete_option( LIVECRAFTS_LEGACY_OPT ) : update_option( LIVECRAFTS_LEGACY_OPT, $had_legacy, false );
$had_migration === null ? delete_option( LIVECRAFTS_MIGRATION ) : update_option( LIVECRAFTS_MIGRATION, $had_migration, false );

// ------------------------------------------------------------------ cleanup
wp_delete_post( $page, true );
wp_delete_post( $new_page, true );
$old_password === '' ? delete_option( LIVECRAFTS_DEPLOY_PASSWORD ) : update_option( LIVECRAFTS_DEPLOY_PASSWORD, $old_password, false );
if ( wp_get_custom_css() !== $css_before ) wp_update_custom_css_post( $css_before );
check( 'cleanup: no preview copies left', ! get_posts( array( 'post_type' => LIVECRAFTS_DRAFT_TYPE, 'post_parent' => $page, 'post_status' => 'any', 'fields' => 'ids' ) ) );

echo "\n$passes passed, $fails failed\n";
exit( $fails ? 1 : 0 );
