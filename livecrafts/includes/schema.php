<?php
/**
 * Database tables. Created on activation and whenever the plugin version changes (dbDelta only adds what is missing).
 *
 *   {prefix}livecrafts_changes    every change to the site, from any source:
 *                                 draft (only editors see it) -> live (deployed) | discarded (dropped from the draft)
 *                                 Changes made outside Livecrafts (WP admin, Elementor editor) are recorded as live.
 *   {prefix}livecrafts_releases   one row per deploy, reset or baseline, with the person's notes.
 *                                 last_snapshot_id marks the release's place in the snapshot history.
 *   {prefix}livecrafts_snapshots  the state of an object right before / right after a release or an outside change,
 *                                 so the site can be reset to any release
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const LIVECRAFTS_DB_VERSION = '1';

function livecrafts_table( $name ) {
	global $wpdb;
	return $wpdb->prefix . 'livecrafts_' . $name;
}

function livecrafts_install() {
	global $wpdb;
	if ( get_option( 'livecrafts_db_version' ) === LIVECRAFTS_DB_VERSION ) return;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset = $wpdb->get_charset_collate();

	dbDelta( 'CREATE TABLE ' . livecrafts_table( 'changes' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		status varchar(16) NOT NULL,
		source varchar(16) NOT NULL,
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		object_type varchar(16) NOT NULL,
		object_id bigint(20) unsigned NOT NULL DEFAULT 0,
		kind varchar(32) NOT NULL,
		target varchar(191) NOT NULL DEFAULT '',
		summary varchar(255) NOT NULL DEFAULT '',
		payload longtext NOT NULL,
		release_id bigint(20) unsigned DEFAULT NULL,
		reverts bigint(20) unsigned DEFAULT NULL,
		ref varchar(64) NOT NULL DEFAULT '',
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY status (status),
		KEY object (object_type,object_id),
		KEY release_id (release_id)
	) $charset;" );

	dbDelta( 'CREATE TABLE ' . livecrafts_table( 'releases' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		kind varchar(16) NOT NULL,
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		notes text NOT NULL,
		summary varchar(255) NOT NULL DEFAULT '',
		last_snapshot_id bigint(20) unsigned NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id)
	) $charset;" );

	dbDelta( 'CREATE TABLE ' . livecrafts_table( 'snapshots' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		release_id bigint(20) unsigned DEFAULT NULL,
		change_id bigint(20) unsigned DEFAULT NULL,
		object_type varchar(16) NOT NULL,
		object_id bigint(20) unsigned NOT NULL DEFAULT 0,
		phase varchar(8) NOT NULL,
		data longtext NOT NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY object (object_type,object_id),
		KEY release_id (release_id)
	) $charset;" );

	update_option( 'livecrafts_db_version', LIVECRAFTS_DB_VERSION );
}

/** Current time in the database format (UTC). */
function livecrafts_now() {
	return gmdate( 'Y-m-d H:i:s' );
}
