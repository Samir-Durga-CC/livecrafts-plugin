<?php
/**
 * Who may do what, and how the Livecrafts backend knows who is asking.
 *
 * Capabilities (added on activation; can be given to any role or user):
 *   livecrafts_edit    use the widget and the assistant on the site (make draft changes, discard drafts)
 *   livecrafts_deploy  put drafts on the live site, reset the site to a release
 *
 * Widget token: a short-lived signed statement "this is WordPress user X, with these capabilities", made by this site
 * with its secret. The secret is shared with the Livecrafts backend once (POST /connect). The browser only ever gets
 * this token, never a backend key. The backend sends it back with each change, so every change is credited to the
 * person in the chat, not to the account the backend connects with.
 *
 * Deploy password: by default the person's own WordPress password. An administrator can set a separate deploy
 * password (Settings → Livecrafts); then that one is asked instead. Five wrong tries lock deploying for that person
 * for 15 minutes.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const LIVECRAFTS_DEPLOY_PASSWORD = 'livecrafts_deploy_password';
const LIVECRAFTS_TOKEN_TTL       = 2 * HOUR_IN_SECONDS;

function livecrafts_add_caps() {
	$grants = array(
		'administrator' => array( 'livecrafts_edit', 'livecrafts_deploy' ),
		'editor'        => array( 'livecrafts_edit' ),
	);
	foreach ( $grants as $role_name => $caps ) {
		$role = get_role( $role_name );
		if ( ! $role ) continue;
		foreach ( $caps as $cap ) {
			if ( ! $role->has_cap( $cap ) ) $role->add_cap( $cap );
		}
	}
}

function livecrafts_can_edit( $user_id = 0 ) {
	return $user_id ? user_can( $user_id, 'livecrafts_edit' ) : current_user_can( 'livecrafts_edit' );
}

function livecrafts_can_deploy( $user_id = 0 ) {
	return $user_id ? user_can( $user_id, 'livecrafts_deploy' ) : current_user_can( 'livecrafts_deploy' );
}

/** The site secret (created on first use). $rotate = make a new one; every widget token signed with the old one stops working. */
function livecrafts_secret( $rotate = false ) {
	$secret = get_option( 'livecrafts_secret' );
	if ( $rotate || ! is_string( $secret ) || strlen( $secret ) < 64 ) {
		$secret = bin2hex( random_bytes( 32 ) );
		update_option( 'livecrafts_secret', $secret, false );
	}
	return $secret;
}

function livecrafts_b64url( $bytes ) {
	return rtrim( strtr( base64_encode( $bytes ), '+/', '-_' ), '=' );
}

function livecrafts_b64url_decode( $text ) {
	return base64_decode( strtr( $text, '-_', '+/' ) );
}

/** A signed token for one user: "<payload>.<signature>", both base64url. */
function livecrafts_widget_token( $user_id ) {
	$user = get_userdata( $user_id );
	if ( ! $user ) return '';
	$payload = livecrafts_b64url( wp_json_encode( array(
		'v'      => 1,
		'site'   => untrailingslashit( home_url() ),
		'uid'    => (int) $user->ID,
		'login'  => $user->user_login,
		'name'   => $user->display_name,
		'edit'   => livecrafts_can_edit( $user->ID ),
		'deploy' => livecrafts_can_deploy( $user->ID ),
		'exp'    => time() + LIVECRAFTS_TOKEN_TTL,
	) ) );
	return $payload . '.' . livecrafts_b64url( hash_hmac( 'sha256', $payload, livecrafts_secret(), true ) );
}

/** The payload of a valid, unexpired token made by this site, or null. */
function livecrafts_verify_token( $token ) {
	$parts = explode( '.', (string) $token );
	if ( count( $parts ) !== 2 || $parts[0] === '' ) return null;
	$expected = livecrafts_b64url( hash_hmac( 'sha256', $parts[0], livecrafts_secret(), true ) );
	if ( ! hash_equals( $expected, $parts[1] ) ) return null;
	$payload = json_decode( (string) livecrafts_b64url_decode( $parts[0] ), true );
	if ( ! is_array( $payload ) || empty( $payload['uid'] ) || empty( $payload['exp'] ) || (int) $payload['exp'] < time() ) return null;
	return $payload;
}

/**
 * The person a change is credited to. A backend request (Application Password) may carry the chat person's widget
 * token in the X-Livecrafts-Actor header; it counts only when it is valid and that person may still edit.
 * Otherwise it is the logged-in / authenticated user.
 */
function livecrafts_actor( $request = null ) {
	if ( $request instanceof WP_REST_Request ) {
		$token = $request->get_header( 'x-livecrafts-actor' );
		if ( $token ) {
			$payload = livecrafts_verify_token( $token );
			if ( $payload && livecrafts_can_edit( (int) $payload['uid'] ) ) return (int) $payload['uid'];
		}
	}
	return get_current_user_id();
}

/** Where a change came from, as the caller says (only the two values a caller may claim). */
function livecrafts_request_source( WP_REST_Request $request ) {
	$source = (string) $request->get_param( 'source' );
	return in_array( $source, array( 'assistant', 'widget' ), true ) ? $source : 'assistant';
}

function livecrafts_has_deploy_password() {
	return (string) get_option( LIVECRAFTS_DEPLOY_PASSWORD, '' ) !== '';
}

/** Set ('' = remove) the separate deploy password. Returns true or WP_Error. */
function livecrafts_set_deploy_password( $password ) {
	$password = (string) $password;
	if ( $password === '' ) {
		delete_option( LIVECRAFTS_DEPLOY_PASSWORD );
		return true;
	}
	if ( strlen( $password ) < 8 ) return new WP_Error( 'livecrafts_weak_password', 'The deploy password needs at least 8 characters.' );
	update_option( LIVECRAFTS_DEPLOY_PASSWORD, wp_hash_password( $password ), false );
	return true;
}

/** Check the password a person typed to deploy or reset. Returns true or WP_Error (wrong, or locked after 5 tries). */
function livecrafts_check_deploy_password( $user_id, $password ) {
	$lock_key = 'livecrafts_deploy_fails_' . (int) $user_id;
	$fails    = (int) get_transient( $lock_key );
	if ( $fails >= 5 ) {
		return new WP_Error( 'livecrafts_locked', 'Too many wrong passwords. Deploying is locked for 15 minutes.', array( 'status' => 429 ) );
	}
	$password = (string) $password;
	$hash     = (string) get_option( LIVECRAFTS_DEPLOY_PASSWORD, '' );
	if ( $hash !== '' ) {
		$ok = $password !== '' && wp_check_password( $password, $hash );
	} else {
		$user = get_userdata( $user_id );
		$ok   = $user && $password !== '' && wp_check_password( $password, $user->user_pass, $user->ID );
	}
	if ( ! $ok ) {
		set_transient( $lock_key, $fails + 1, 15 * MINUTE_IN_SECONDS );
		$message = $hash !== '' ? 'Wrong deploy password.' : 'Wrong password. Type your WordPress password.';
		return new WP_Error( 'livecrafts_bad_password', $message, array( 'status' => 403 ) );
	}
	delete_transient( $lock_key );
	return true;
}
