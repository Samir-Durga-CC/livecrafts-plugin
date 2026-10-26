<?php
/**
 * Editing gallery sections where they are stored (0.24.7).
 *
 * A gallery section on a theme-built page is markup Livecrafts keeps in the page's sections (sections.php). A click on
 * one arrives as "<anchor>|<section index>|<path>" (widget.js lcsOf): the path is element-child indexes from the
 * <section class="lcs"> down to the clicked element, so the same element is found in the stored markup - no text
 * matching, no guessing. Text, links and pictures are then edited in that markup, as a normal tracked draft
 * (kind theme.section, target "edit:<anchor>|<index>|<path>|<field>"), and revert puts the old markup back.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** The stored markup as a DOM (inside one wrapper element), and back. */
function livecrafts_lcs_dom( $html ) {
	$doc = new DOMDocument( '1.0', 'UTF-8' );
	$old = libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?><div id="lcs-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	libxml_clear_errors();
	libxml_use_internal_errors( $old );
	$root = $doc->getElementById( 'lcs-root' );
	if ( ! $root ) foreach ( $doc->getElementsByTagName( 'div' ) as $d ) { if ( $d->getAttribute( 'id' ) === 'lcs-root' ) { $root = $d; break; } }
	return array( $doc, $root );
}
function livecrafts_lcs_html( DOMDocument $doc, DOMElement $root ) {
	$out = '';
	foreach ( iterator_to_array( $root->childNodes ) as $n ) $out .= $doc->saveHTML( $n );
	return $out;
}

function livecrafts_lcs_has_class( DOMElement $el, $class ) {
	return in_array( $class, preg_split( '/\s+/', (string) $el->getAttribute( 'class' ), -1, PREG_SPLIT_NO_EMPTY ), true );
}
function livecrafts_lcs_children( DOMNode $n ) {
	$out = array();
	foreach ( $n->childNodes as $c ) if ( $c instanceof DOMElement ) $out[] = $c;
	return $out;
}

/** The n-th gallery section in the markup and the element at the path inside it, or null. */
function livecrafts_lcs_find( DOMElement $root, $index, $path ) {
	$sections = array();
	foreach ( $root->getElementsByTagName( 'section' ) as $s ) if ( livecrafts_lcs_has_class( $s, 'lcs' ) ) $sections[] = $s;
	if ( ! isset( $sections[ (int) $index ] ) ) return null;
	$sec = $node = $sections[ (int) $index ];
	foreach ( $path === '' ? array() : explode( '.', $path ) as $i ) {
		$kids = livecrafts_lcs_children( $node );
		if ( ! isset( $kids[ (int) $i ] ) ) return null;
		$node = $kids[ (int) $i ];
	}
	return array( $sec, $node );
}

/** What can be edited at a clicked element: the text element, the link, the picture around it (each may be null). */
function livecrafts_lcs_parts( DOMElement $sec, DOMElement $node ) {
	$text = $link = $image = null;
	for ( $n = $node; $n && $n !== $sec->parentNode; $n = $n->parentNode ) {
		if ( ! $n instanceof DOMElement ) break;
		$tag = strtolower( $n->tagName );
		if ( ! $image && ( $tag === 'img' || livecrafts_lcs_has_class( $n, 'lcs-media' ) ) ) $image = $tag === 'img' && $n->parentNode instanceof DOMElement && livecrafts_lcs_has_class( $n->parentNode, 'lcs-media' ) ? $n->parentNode : $n;
		if ( ! $link && $tag === 'a' ) $link = $n;
		if ( ! $text && in_array( $tag, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'span', 'li', 'a', 'small', 'strong', 'b', 'em', 'figcaption', 'blockquote', 'cite', 'dt', 'dd' ), true ) ) {
			$simple = true;
			foreach ( livecrafts_lcs_children( $n ) as $c ) if ( ! in_array( strtolower( $c->tagName ), array( 'br', 'strong', 'b', 'em', 'i' ), true ) ) $simple = false;
			if ( $simple && trim( $n->textContent ) !== '' ) $text = $n;
		}
		if ( $n === $sec ) break;
	}
	return array( 'text' => $text, 'link' => $link, 'image' => $image );
}

/** Apply one edit to the stored markup. Returns array( new markup, summary ) or WP_Error. */
function livecrafts_lcs_apply( $html, $index, $path, $field, $value ) {
	list( $doc, $root ) = livecrafts_lcs_dom( $html );
	$found = $root ? livecrafts_lcs_find( $root, $index, $path ) : null;
	if ( ! $found ) return new WP_Error( 'livecrafts_stale', 'That part of the section has changed since it was clicked. Click it again.', array( 'status' => 409 ) );
	list( $sec, $node ) = $found;
	$parts = livecrafts_lcs_parts( $sec, $node );
	switch ( $field ) {
		case 'text':
			$el  = $parts['text'];
			$new = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $value ) ) );
			if ( ! $el ) return new WP_Error( 'livecrafts_bad_value', 'There is no text to edit there.', array( 'status' => 400 ) );
			if ( $new === '' ) return new WP_Error( 'livecrafts_bad_value', 'Text cannot be empty. To take it out, remove the section or ask the assistant.', array( 'status' => 400 ) );
			while ( $el->firstChild ) $el->removeChild( $el->firstChild );
			$el->appendChild( $doc->createTextNode( $new ) );
			$summary = 'Section text → “' . mb_substr( $new, 0, 60 ) . '”';
			break;
		case 'link':
			$el  = $parts['link'];
			$url = esc_url_raw( trim( (string) $value ), array( 'http', 'https', 'mailto', 'tel' ) );
			if ( ! $el ) return new WP_Error( 'livecrafts_bad_value', 'There is no link there.', array( 'status' => 400 ) );
			if ( $url === '' && ! preg_match( '#^(/|\#)#', trim( (string) $value ) ) ) return new WP_Error( 'livecrafts_bad_value', 'That is not a link: use a web address, a page path (/contact), mailto: or tel:.', array( 'status' => 400 ) );
			$el->setAttribute( 'href', $url !== '' ? $url : trim( (string) $value ) );
			$summary = 'Section link → ' . $el->getAttribute( 'href' );
			break;
		case 'image':
			$el = $parts['image'];
			$id = absint( is_array( $value ) ? ( isset( $value['id'] ) ? $value['id'] : 0 ) : $value );
			if ( ! $el ) return new WP_Error( 'livecrafts_bad_value', 'There is no picture there.', array( 'status' => 400 ) );
			if ( ! $id || ! wp_attachment_is_image( $id ) ) return new WP_Error( 'livecrafts_bad_value', 'That is not an image from the Media Library.', array( 'status' => 400 ) );
			foreach ( iterator_to_array( $el->getElementsByTagName( 'img' ) ) as $old ) $old->parentNode->removeChild( $old );
			$img = $doc->createElement( 'img' );
			$img->setAttribute( 'src', (string) wp_get_attachment_image_url( $id, 'large' ) );
			$img->setAttribute( 'alt', (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );
			$img->setAttribute( 'loading', 'lazy' );
			$el->appendChild( $img );
			$summary = 'Section picture → ' . basename( (string) wp_get_attachment_url( $id ) );
			break;
		case 'remove':
			$host = $sec->parentNode;
			$host->removeChild( $sec );
			if ( $host instanceof DOMElement && livecrafts_lcs_has_class( $host, 'lcs-host' ) && ! livecrafts_lcs_children( $host ) ) $host->parentNode->removeChild( $host );
			$summary = 'Section removed';
			break;
		default:
			return new WP_Error( 'livecrafts_bad_target', 'Unknown section edit.', array( 'status' => 400 ) );
	}
	return array( trim( livecrafts_lcs_html( $doc, $root ) ), $summary );
}

/** The stored markup of one place on a page (as editors see it: drafts included). */
function livecrafts_lcs_stored( $post_id, $anchor ) {
	$data = livecrafts_draft_data( 'post', (int) $post_id );
	if ( is_wp_error( $data ) ) return null;
	foreach ( (array) ( isset( $data['meta'][ LIVECRAFTS_SECTIONS_META ] ) ? $data['meta'][ LIVECRAFTS_SECTIONS_META ] : array() ) as $row ) {
		if ( is_array( $row ) && isset( $row['anchor'] ) && $row['anchor'] === $anchor ) return (string) $row['content'];
	}
	return null;
}

/** resolve.php: a click on a gallery section on a theme-built page. */
function livecrafts_resolve_lcs( $page, $lcs, $device, $selector, $tag ) {
	if ( ! preg_match( '#^([a-z0-9:/_-]{1,90})\|(\d{1,3})\|(\d{1,3}(?:\.\d{1,3}){0,20})?$#', (string) $lcs, $m ) ) return null;
	$anchor = livecrafts_sections_clean_anchor( $m[1] );
	$path   = isset( $m[3] ) ? $m[3] : '';
	$html   = $anchor ? livecrafts_lcs_stored( $page, $anchor ) : null;
	if ( $html === null ) return null;
	list( , $root ) = livecrafts_lcs_dom( $html );
	$found = $root ? livecrafts_lcs_find( $root, $m[2], $path ) : null;
	if ( ! $found ) return null;
	list( $sec, $node ) = $found;
	$parts  = livecrafts_lcs_parts( $sec, $node );
	$name   = (string) $sec->getAttribute( 'data-lcs' );
	$change = function ( $field ) use ( $page, $anchor, $m, $path ) { return array( 'kind' => 'theme.section', 'post' => (int) $page, 'target' => 'edit:' . $anchor . '|' . (int) $m[2] . '|' . $path . '|' . $field ); };
	$actions = array();
	if ( $parts['text'] ) $actions[] = livecrafts_action( 'lcs:text', 'content', 'Edit text', 'text', trim( preg_replace( '/\s+/u', ' ', $parts['text']->textContent ) ), $change( 'text' ) );
	if ( $parts['link'] ) $actions[] = livecrafts_action( 'lcs:link', 'content', 'Change link', 'url', (string) $parts['link']->getAttribute( 'href' ), $change( 'link' ) );
	if ( $parts['image'] ) {
		$img = $parts['image']->getElementsByTagName( 'img' )->item( 0 );
		$actions[] = livecrafts_action( 'lcs:image', 'content', $img ? 'Replace picture' : 'Add a picture', 'image', array( 'id' => 0, 'url' => $img ? (string) $img->getAttribute( 'src' ) : '' ), $change( 'image' ) );
	}
	$actions[] = livecrafts_action( 'ask', 'content', 'Change it another way (assistant)', 'ask', null, array() );
	$scopes = null;
	$sel    = livecrafts_css_selector_clean( $selector );
	if ( $sel !== '' && current_user_can( 'edit_css' ) ) {
		$own    = ( get_post_type( $page ) === 'page' ? 'body.page-id-' : 'body.postid-' ) . $page . ' ' . $sel;
		$scopes = array( 'page' => $own, 'site' => $sel );
		$actions = array_merge( $actions, livecrafts_css_actions( $own, $device, $tag ) );
	}
	$actions[] = livecrafts_action( 'lcs:remove', 'layout', 'Remove this section', 'confirm', null, $change( 'remove' ) );
	return array(
		'source'     => array( 'kind' => 'section', 'label' => $name !== '' ? 'Section: ' . $name : 'Section', 'post' => (int) $page, 'anchor' => $anchor, 'where' => livecrafts_object_label( 'post', (int) $page ) ),
		'actions'    => $actions,
		'css_scopes' => $scopes,
		'notes'      => array(),
	);
}
