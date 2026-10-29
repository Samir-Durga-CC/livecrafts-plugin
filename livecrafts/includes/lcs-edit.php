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

/* ------------------------------------------------------------------ the same component, wherever it is stored */

/**
 * A ready-made component is the same markup whichever builder holds it, but until now only one store said so on the
 * page: a theme-section row (data-lc-anchor). Everywhere else the identical component could not be picked at all.
 *
 * A component address names the store it sits in:
 *
 *   theme:<anchor>          a livecrafts_sections row            (sections.php)
 *   el:<post>:<widget id>   an Elementor widget's markup setting (elementor.php)
 *   block:<post>:<path>     a block of the page content          (blocks.php)
 *   divi:<post>:<path>      a Divi module's own content          (divi.php)
 *   content:<post>          the page content itself
 *
 * Every one of those is already addressable on the page: Elementor prints data-id, blocks.php prints data-lc-block,
 * divi.php prints the lcd-<path> class, sections.php prints data-lc-anchor. The widget reads whichever is nearest
 * (widget.js srcOf), so nothing new has to be stamped except raw page content, which has no marker of its own.
 *
 * The DOM surgery below it is unchanged: livecrafts_lcs_apply() takes markup in and gives markup back. Only reading
 * and writing that markup differs per store, which is what these functions do.
 */

/** A component address as array( store, post id, key ), or null. */
function livecrafts_src_parse( $src, $page ) {
	$src = (string) $src;
	if ( strpos( $src, 'theme:' ) === 0 ) {
		$anchor = livecrafts_sections_clean_anchor( substr( $src, 6 ) );
		return $anchor ? array( 'theme', (int) $page, $anchor ) : null;
	}
	if ( preg_match( '/^content:(\d{1,10})$/', $src, $m ) ) return array( 'content', (int) $m[1], '' );
	if ( preg_match( '/^el:(\d{1,10}):([A-Za-z0-9]{3,16})$/', $src, $m ) ) return array( 'el', (int) $m[1], $m[2] );
	if ( preg_match( '/^(block|divi):(\d{1,10}):(\d{1,4}(?:\.\d{1,4}){0,9})$/', $src, $m ) ) return array( $m[1], (int) $m[2], $m[3] );
	return null;
}

/**
 * The setting that holds a widget's own markup. Elementor's Text Editor keeps it in "editor" and its HTML widget in
 * "html"; the Section Gallery places a Text Editor (place.ts), so that is the default.
 */
function livecrafts_src_el_setting( array $node ) {
	$type = isset( $node['widgetType'] ) ? (string) $node['widgetType'] : '';
	return $type === 'html' ? 'html' : 'editor';
}

/** The stored markup of the store a component sits in, read from an object data array. '' when there is none. */
function livecrafts_src_markup( array $at, array $data ) {
	list( $store, , $key ) = $at;
	if ( $store === 'theme' ) {
		foreach ( (array) ( isset( $data['meta'][ LIVECRAFTS_SECTIONS_META ] ) ? $data['meta'][ LIVECRAFTS_SECTIONS_META ] : array() ) as $row ) {
			if ( is_array( $row ) && isset( $row['anchor'] ) && $row['anchor'] === $key ) return (string) $row['content'];
		}
		return '';
	}
	$content = isset( $data['fields']['content'] ) ? (string) $data['fields']['content'] : '';
	if ( $store === 'content' ) return $content;
	if ( $store === 'block' ) {
		$b = livecrafts_block_get( parse_blocks( $content ), $key );
		return $b && isset( $b['innerHTML'] ) ? (string) $b['innerHTML'] : '';
	}
	if ( $store === 'divi' ) {
		$node = livecrafts_divi_get( livecrafts_divi_tree( $content ), $key );
		return $node ? (string) livecrafts_divi_field_value( $content, $node, 'text' ) : '';
	}
	if ( ! livecrafts_el_active() ) return '';
	$tree = livecrafts_el_tree( $data );
	$node = $tree ? livecrafts_el_node( $tree, $key ) : null;
	if ( ! $node ) return '';
	$r = livecrafts_el_read( $tree, $key, livecrafts_src_el_setting( $node ) );
	return is_string( $r['value'] ) ? $r['value'] : '';
}

/** Put edited markup back where it came from. true, or WP_Error. */
function livecrafts_src_write( array $at, array &$data, $markup ) {
	list( $store, , $key ) = $at;
	$markup = (string) $markup;
	if ( $store === 'theme' ) {
		$rows = array();
		foreach ( (array) ( isset( $data['meta'][ LIVECRAFTS_SECTIONS_META ] ) ? $data['meta'][ LIVECRAFTS_SECTIONS_META ] : array() ) as $row ) {
			if ( is_array( $row ) && ! empty( $row['anchor'] ) && isset( $row['content'] ) ) $rows[] = array( 'anchor' => (string) $row['anchor'], 'content' => (string) $row['content'] );
		}
		$next = livecrafts_sections_set( $rows, $key, $markup );
		if ( $next ) $data['meta'][ LIVECRAFTS_SECTIONS_META ] = $next; else unset( $data['meta'][ LIVECRAFTS_SECTIONS_META ] );
		return true;
	}
	if ( $store === 'content' ) {
		$data['fields']['content'] = $markup;
		return true;
	}
	$content = isset( $data['fields']['content'] ) ? (string) $data['fields']['content'] : '';
	if ( $store === 'block' ) {
		$blocks = livecrafts_block_edit( parse_blocks( $content ), $key, function ( $b ) use ( $markup ) {
			return livecrafts_block_set_html( $b, $markup );
		} );
		if ( is_wp_error( $blocks ) ) return $blocks;
		$data['fields']['content'] = serialize_blocks( $blocks );
		return true;
	}
	if ( $store === 'divi' ) {
		$new = livecrafts_divi_write( $content, $key, 'text', $markup );
		if ( is_wp_error( $new ) ) return $new;
		$data['fields']['content'] = $new;
		return true;
	}
	if ( ! livecrafts_el_active() ) return new WP_Error( 'livecrafts_no_elementor', 'Elementor is not active on this site.', array( 'status' => 400 ) );
	$tree = livecrafts_el_tree( $data );
	$node = $tree ? livecrafts_el_node( $tree, $key ) : null;
	if ( ! $node ) return new WP_Error( 'livecrafts_no_element', 'That Elementor element is not on this page any more.', array( 'status' => 404 ) );
	$r = livecrafts_el_write_tree( $tree, $key, livecrafts_src_el_setting( $node ), $markup );
	if ( is_wp_error( $r ) ) return $r;
	livecrafts_el_set_tree( $data, $tree );
	return true;
}

/**
 * The conflict group of an edit, matching the group the store's own kind uses: two people editing the same page
 * content, or the same widget setting, or the same section row, still conflict with each other.
 */
function livecrafts_src_group( $target ) {
	$src = explode( '|', (string) $target, 2 );
	$at  = livecrafts_src_parse( $src[0], 0 );
	if ( ! $at ) return 'target:' . $target;
	if ( $at[0] === 'theme' ) return 'target:' . $at[2];
	if ( $at[0] === 'el' ) return 'target:' . $at[2] . ':editor';
	return 'content';
}

/** Clean edited markup the way the store it goes back into would. */
function livecrafts_src_clean( array $at, $markup, $post_id ) {
	if ( $at[0] === 'divi' ) {
		$clean = livecrafts_sanitize_post_field( 'post_content', $markup, $post_id );
		if ( preg_match( '/\[\/?et_pb_/', $clean ) ) return livecrafts_bad_value( 'A Divi module\'s content cannot hold Divi shortcodes.' );
		return $clean;
	}
	if ( $at[0] === 'content' ) return livecrafts_sanitize_post_field( 'post_content', $markup, $post_id );
	return trim( wp_kses_post( $markup ) );
}

/**
 * kind lcs.edit - one text, link or picture inside a ready-made component, wherever the component is stored.
 * Target: "<src>|<section index>|<path>|<field>" (widget.js srcOf gives the first three).
 */
function livecrafts_kind_lcs_edit_prepare( $post_id, $target, $value, array $draft ) {
	$bits = explode( '|', (string) $target );
	if ( count( $bits ) !== 4 || ! preg_match( '/^\d{1,3}$/', $bits[1] ) || ! preg_match( '/^(\d{1,3}(\.\d{1,3}){0,20})?$/', $bits[2] ) ) {
		return new WP_Error( 'livecrafts_bad_target', 'A component edit is "<source>|<section index>|<path>|<field>".', array( 'status' => 400 ) );
	}
	$at = livecrafts_src_parse( $bits[0], $post_id );
	if ( ! $at ) return new WP_Error( 'livecrafts_bad_target', 'That is not a component address: it starts with theme:, el:, block:, divi: or content:.', array( 'status' => 400 ) );
	if ( $at[1] !== (int) $post_id ) return new WP_Error( 'livecrafts_bad_target', 'That component is stored on another page.', array( 'status' => 400 ) );

	$now = livecrafts_src_markup( $at, $draft );
	if ( trim( $now ) === '' ) return new WP_Error( 'livecrafts_stale', 'That component is no longer there. Click it again.', array( 'status' => 409 ) );

	$r = livecrafts_lcs_apply( $now, $bits[1], $bits[2], $bits[3], $value );
	if ( is_wp_error( $r ) ) return $r;

	$clean = livecrafts_src_clean( $at, $r[0], $post_id );
	if ( is_wp_error( $clean ) ) return $clean;
	if ( trim( $clean ) === '' ) {
		return livecrafts_bad_value( 'That is the only component here, so taking it out would leave an empty block. Remove the block itself in the builder.' );
	}
	return array( 'target' => $target, 'after' => $clean, 'payload' => array( 'src' => $bits[0], 'edit' => $bits[3] ), 'summary' => $r[1] );
}

/** resolve.php: a click on a ready-made component, in whichever store it sits. */
function livecrafts_resolve_src( $page, $src, $device, $selector, $tag ) {
	if ( ! preg_match( '#^([a-z]+:[a-z0-9:/_.-]{1,120})\|(\d{1,3})\|(\d{1,3}(?:\.\d{1,3}){0,20})?$#', (string) $src, $m ) ) return null;
	$at = livecrafts_src_parse( $m[1], $page );
	if ( ! $at || ! current_user_can( 'edit_post', $at[1] ) ) return null;

	$data = livecrafts_draft_data( 'post', $at[1] );
	if ( is_wp_error( $data ) ) return null;
	$html = livecrafts_src_markup( $at, $data );
	if ( trim( $html ) === '' ) return null;

	$path = isset( $m[3] ) ? $m[3] : '';
	list( , $root ) = livecrafts_lcs_dom( $html );
	$found = $root ? livecrafts_lcs_find( $root, $m[2], $path ) : null;
	if ( ! $found ) return null;
	list( $sec, $node ) = $found;
	$parts  = livecrafts_lcs_parts( $sec, $node );
	$name   = (string) $sec->getAttribute( 'data-lcs' );
	$post   = $at[1];
	$change = function ( $field ) use ( $post, $m, $path ) {
		return array( 'kind' => 'lcs.edit', 'post' => (int) $post, 'target' => $m[1] . '|' . (int) $m[2] . '|' . $path . '|' . $field );
	};

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
		$own     = ( get_post_type( $page ) === 'page' ? 'body.page-id-' : 'body.postid-' ) . (int) $page . ' ' . $sel;
		$scopes  = array( 'page' => $own, 'site' => $sel );
		$actions = array_merge( $actions, livecrafts_css_actions( $own, $device, $tag ) );
	}
	$actions[] = livecrafts_action( 'lcs:remove', 'layout', 'Remove this section', 'confirm', null, $change( 'remove' ) );

	return array(
		'source'     => array( 'kind' => 'section', 'label' => $name !== '' ? 'Section: ' . $name : 'Section', 'post' => (int) $post, 'src' => $m[1], 'where' => livecrafts_object_label( 'post', (int) $post ) ),
		'actions'    => $actions,
		'css_scopes' => $scopes,
		'notes'      => array(),
	);
}
