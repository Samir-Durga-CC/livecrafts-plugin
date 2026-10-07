<?php
/**
 * Notes: what the assistant (or a developer) learned about the site and its pages, kept with the site so the next
 * request starts informed - e.g. "Hero title = Elementor heading 3f2a1c. Brand colours #0B3D91 / #FFB400.
 * The footer comes from the theme (footer.php), not from a page."
 *
 *   site notes  option livecrafts_notes                       array( text, user_id, updated_at )
 *   page notes  post meta _livecrafts_notes on that page      same shape
 * Notes are plain text/Markdown, never executed or printed on the site.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const LIVECRAFTS_NOTES_MAX = 20000;

function livecrafts_notes_get( $post_id = 0 ) {
	$raw = $post_id ? get_post_meta( $post_id, '_livecrafts_notes', true ) : get_option( 'livecrafts_notes', array() );
	if ( ! is_array( $raw ) || ! isset( $raw['text'] ) ) return array( 'text' => '', 'user' => null, 'updated' => null );
	$user = ! empty( $raw['user_id'] ) ? get_userdata( (int) $raw['user_id'] ) : null;
	return array(
		'text'    => (string) $raw['text'],
		'user'    => $user ? $user->display_name : null,
		'updated' => ! empty( $raw['updated_at'] ) ? gmdate( 'c', (int) $raw['updated_at'] ) : null,
	);
}

/** Replace the notes of the site ($post_id 0) or of one page. Returns the saved notes or WP_Error. */
function livecrafts_notes_set( $post_id, $text, $user_id ) {
	$text = trim( str_replace( "\r\n", "\n", wp_strip_all_tags( (string) $text ) ) );
	if ( mb_strlen( $text ) > LIVECRAFTS_NOTES_MAX ) return livecrafts_bad_value( 'Notes are limited to ' . LIVECRAFTS_NOTES_MAX . ' characters. Keep them short and current.' );
	$value = array( 'text' => $text, 'user_id' => (int) $user_id, 'updated_at' => time() );
	if ( $post_id ) {
		if ( ! get_post( $post_id ) ) return new WP_Error( 'livecrafts_no_page', 'That page does not exist.', array( 'status' => 404 ) );
		if ( $text === '' ) delete_post_meta( $post_id, '_livecrafts_notes' );
		else update_post_meta( $post_id, '_livecrafts_notes', wp_slash( $value ) );
	} else {
		update_option( 'livecrafts_notes', $value, false );
	}
	return livecrafts_notes_get( $post_id );
}
