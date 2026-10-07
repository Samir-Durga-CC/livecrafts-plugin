<?php
/**
 * Block content (the block editor / Gutenberg): edit, add, remove, move and duplicate blocks inside a page's content,
 * with WordPress's own block parser and serializer - the result is the same block markup the block editor writes.
 *
 * A block is addressed by its PATH: positions among the real blocks (whitespace between blocks does not count),
 * from the top, joined by dots. "2" = the third top-level block, "2.0" = the first block inside it.
 * For logged-in editors, every rendered block of the page content carries data-lc-block="<post id>:<path>", so a click
 * on the page finds its block exactly. Visitors never get this attribute.
 *
 * Changes to block content are checked as a whole against the live content when deploying: if the live content
 * changed after the first draft block change, that is a conflict the person must confirm.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function livecrafts_path_ok( $path ) {
	return is_string( $path ) && preg_match( '/^\d{1,4}(\.\d{1,4}){0,9}$/', $path );
}

/** Real array index of the $n-th real block of a top-level list (null-name blocks are whitespace / freeform HTML). */
function livecrafts_block_real_index( array $list, $n, $top ) {
	if ( ! $top ) return isset( $list[ $n ] ) ? $n : null;
	$seen = -1;
	foreach ( $list as $i => $b ) {
		if ( ! empty( $b['blockName'] ) && ++$seen === $n ) return $i;
	}
	return null;
}

/** The block at a path, or null. */
function livecrafts_block_get( array $blocks, $path ) {
	$list = $blocks;
	$top  = true;
	$node = null;
	foreach ( explode( '.', $path ) as $n ) {
		$i = livecrafts_block_real_index( $list, (int) $n, $top );
		if ( $i === null ) return null;
		$node = $list[ $i ];
		$list = isset( $node['innerBlocks'] ) ? $node['innerBlocks'] : array();
		$top  = false;
	}
	return $node;
}

/** Number of real blocks in a list (the top level, or a block's innerBlocks). */
function livecrafts_block_count( array $blocks, $parent_path ) {
	if ( $parent_path === '' ) return count( array_filter( $blocks, function ( $b ) { return ! empty( $b['blockName'] ); } ) );
	$parent = livecrafts_block_get( $blocks, $parent_path );
	return $parent ? count( $parent['innerBlocks'] ) : 0;
}

/** Replace the block at a path with $fn( $block ), which returns the new block or WP_Error. */
function livecrafts_block_edit( array $blocks, $path, $fn ) {
	$parts = explode( '.', $path );
	$walk  = function ( array $list, array $parts, $top ) use ( &$walk, $fn ) {
		$i = livecrafts_block_real_index( $list, (int) array_shift( $parts ), $top );
		if ( $i === null ) return new WP_Error( 'livecrafts_no_block', 'There is no block at that position (any more).', array( 'status' => 404 ) );
		if ( ! $parts ) {
			$new = $fn( $list[ $i ] );
		} else {
			$new = $list[ $i ];
			$inner = $walk( isset( $new['innerBlocks'] ) ? $new['innerBlocks'] : array(), $parts, false );
			if ( is_wp_error( $inner ) ) return $inner;
			$new['innerBlocks'] = $inner;
		}
		if ( is_wp_error( $new ) ) return $new;
		$list[ $i ] = $new;
		return $list;
	};
	return $walk( $blocks, $parts, true );
}

/** A whitespace-only freeform block, used between top-level blocks. */
function livecrafts_block_gap() {
	return array( 'blockName' => null, 'attrs' => array(), 'innerBlocks' => array(), 'innerHTML' => "\n\n", 'innerContent' => array( "\n\n" ) );
}

/** Remove $remove blocks at $index and insert $insert there, in the top-level list or inside the block at $parent_path. */
function livecrafts_block_splice( array $blocks, $parent_path, $index, $remove, array $insert ) {
	if ( $parent_path === '' ) return livecrafts_block_splice_top( $blocks, $index, $remove, $insert );
	return livecrafts_block_edit( $blocks, $parent_path, function ( $parent ) use ( $index, $remove, $insert ) {
		return livecrafts_block_splice_inner( $parent, $index, $remove, $insert );
	} );
}

function livecrafts_block_splice_top( array $list, $index, $remove, array $insert ) {
	$real = array_keys( array_filter( $list, function ( $b ) { return ! empty( $b['blockName'] ); } ) );
	if ( $index < 0 || $index > count( $real ) || $index + $remove > count( $real ) ) return new WP_Error( 'livecrafts_no_block', 'That position is outside the page content.', array( 'status' => 404 ) );
	// remove, from the last one, each with the gap after it (or before it, for the last block)
	for ( $k = $index + $remove - 1; $k >= $index; $k-- ) {
		$i    = $real[ $k ];
		$from = $i;
		$len  = 1;
		if ( isset( $list[ $i + 1 ] ) && empty( $list[ $i + 1 ]['blockName'] ) && trim( $list[ $i + 1 ]['innerHTML'] ) === '' ) $len = 2;
		elseif ( $i > 0 && empty( $list[ $i - 1 ]['blockName'] ) && trim( $list[ $i - 1 ]['innerHTML'] ) === '' ) { $from = $i - 1; $len = 2; }
		array_splice( $list, $from, $len );
	}
	if ( $insert ) {
		$real  = array_keys( array_filter( $list, function ( $b ) { return ! empty( $b['blockName'] ); } ) );
		$items = array();
		foreach ( $insert as $b ) { $items[] = $b; $items[] = livecrafts_block_gap(); }
		if ( $index < count( $real ) ) {
			array_splice( $list, $real[ $index ], 0, $items );
		} else {
			array_pop( $items );
			if ( $list ) array_unshift( $items, livecrafts_block_gap() );
			$list = array_merge( $list, $items );
		}
	}
	return array_values( $list );
}

function livecrafts_block_splice_inner( array $parent, $index, $remove, array $insert ) {
	$inner   = $parent['innerBlocks'];
	$content = $parent['innerContent'];
	$slots   = array_keys( array_filter( $content, 'is_null' ) ); // position of each inner block in innerContent
	if ( $index < 0 || $index > count( $inner ) || $index + $remove > count( $inner ) ) return new WP_Error( 'livecrafts_no_block', 'That position is outside this block.', array( 'status' => 404 ) );
	if ( ! $inner && $insert ) return new WP_Error( 'livecrafts_empty_parent', 'This block is empty, so where new blocks go is unknown. Replace the whole block instead.', array( 'status' => 400 ) );

	for ( $k = $index + $remove - 1; $k >= $index; $k-- ) {
		$p    = $slots[ $k ];
		$from = $p;
		$len  = 1;
		$gap  = function ( $q ) use ( $content ) { return isset( $content[ $q ] ) && is_string( $content[ $q ] ) && trim( $content[ $q ] ) === ''; };
		if ( $gap( $p - 1 ) && array_key_exists( $p - 2, $content ) && $content[ $p - 2 ] === null ) { $from = $p - 1; $len = 2; }
		elseif ( $gap( $p + 1 ) && array_key_exists( $p + 2, $content ) && $content[ $p + 2 ] === null ) { $len = 2; }
		array_splice( $content, $from, $len );
		array_splice( $inner, $k, 1 );
		$slots = array_keys( array_filter( $content, 'is_null' ) );
	}
	if ( $insert ) {
		if ( $index < count( $inner ) ) {
			$items = array();
			foreach ( $insert as $unused ) { $items[] = null; $items[] = "\n\n"; }
			array_splice( $content, $slots[ $index ], 0, $items );
		} else {
			$items = array();
			foreach ( $insert as $unused ) { $items[] = "\n\n"; $items[] = null; }
			array_splice( $content, end( $slots ) + 1, 0, $items );
		}
		array_splice( $inner, $index, 0, $insert );
	}
	$parent['innerBlocks']  = array_values( $inner );
	$parent['innerContent'] = array_values( $content );
	$parent['innerHTML']    = implode( '', array_filter( $parent['innerContent'], 'is_string' ) );
	return $parent;
}

/** Block markup -> its blocks. Only real, registered blocks (no loose HTML between them). WP_Error otherwise. */
function livecrafts_blocks_from_markup( $markup, $post_id ) {
	$markup = livecrafts_sanitize_post_field( 'post_content', (string) $markup, $post_id );
	$out    = array();
	foreach ( parse_blocks( $markup ) as $b ) {
		if ( empty( $b['blockName'] ) ) {
			if ( trim( $b['innerHTML'] ) !== '' ) return livecrafts_bad_value( 'Use block markup only (<!-- wp:... --> comments around every piece of HTML).' );
			continue;
		}
		$bad = livecrafts_block_unknown( $b );
		if ( $bad ) return livecrafts_bad_value( 'Unknown block type "' . $bad . '". Use core blocks or blocks that are installed on this site.' );
		$out[] = $b;
	}
	if ( ! $out ) return livecrafts_bad_value( 'No block found in that markup.' );
	return $out;
}

function livecrafts_block_unknown( array $b ) {
	if ( ! WP_Block_Type_Registry::get_instance()->is_registered( $b['blockName'] ) && $b['blockName'] !== 'core/freeform' ) return $b['blockName'];
	foreach ( $b['innerBlocks'] as $inner ) { $bad = livecrafts_block_unknown( $inner ); if ( $bad ) return $bad; }
	return '';
}

/** "Paragraph", "Heading", "Columns" ... */
function livecrafts_block_label( array $b ) {
	$type = WP_Block_Type_Registry::get_instance()->get_registered( $b['blockName'] );
	return $type && ! empty( $type->title ) ? $type->title : preg_replace( '#^core/#', '', $b['blockName'] );
}

/** Split "2.1" into parent "2" and index 1 ("3" -> "", 3). */
function livecrafts_path_split( $path ) {
	$parts = explode( '.', $path );
	$index = (int) array_pop( $parts );
	return array( implode( '.', $parts ), $index );
}

function livecrafts_path_join( $parent, $index ) {
	return $parent === '' ? (string) $index : $parent . '.' . $index;
}

/* ------------------------------------------------------------------ text, link, image, class inside one block */

/** Where the editable text of simple text blocks is: block name => regex with groups (open tag)(text)(close tag). */
function livecrafts_block_text_patterns() {
	return array(
		'core/paragraph'  => '#^(\s*<p\b[^>]*>)(.*)(</p>\s*)$#s',
		'core/heading'    => '#^(\s*<h([1-6])\b[^>]*>)(.*)(</h\2>\s*)$#s',
		'core/list-item'  => '#^(\s*<li\b[^>]*>)(.*?)(</li>\s*)$#s',
		'core/button'     => '#^(\s*<div\b[^>]*>\s*<a\b[^>]*>)(.*)(</a>\s*</div>\s*)$#s',
	);
}

/** array( before, text, after ) of a simple text block, or null when its text cannot be edited safely here. */
function livecrafts_block_text_parts( array $b ) {
	$patterns = livecrafts_block_text_patterns();
	if ( empty( $patterns[ $b['blockName'] ] ) || ! empty( $b['innerBlocks'] ) ) return null;
	if ( ! preg_match( $patterns[ $b['blockName'] ], $b['innerHTML'], $m ) ) return null;
	if ( $b['blockName'] === 'core/heading' ) return array( $m[1], $m[3], $m[4] );
	return array( $m[1], $m[2], $m[3] );
}

/** Inline formatting a text block may keep (rich text in the block editor). */
function livecrafts_inline_html( $html ) {
	return trim( wp_kses( (string) $html, array(
		'strong' => array(), 'b' => array(), 'em' => array(), 'i' => array(), 'u' => array(), 's' => array(), 'code' => array(),
		'mark' => array( 'class' => true, 'style' => true ), 'sub' => array(), 'sup' => array(), 'br' => array(),
		'span' => array( 'class' => true ), 'a' => array( 'href' => true, 'target' => true, 'rel' => true ),
	) ) );
}

function livecrafts_block_set_html( array $b, $html ) {
	$b['innerHTML']    = $html;
	$b['innerContent'] = array( $html );
	return $b;
}

/** Add a class to the block: in its attributes and on its outer HTML element (as the block editor does). */
function livecrafts_block_add_class( array $b, $class ) {
	$classes = preg_split( '/\s+/', trim( isset( $b['attrs']['className'] ) ? (string) $b['attrs']['className'] : '' ), -1, PREG_SPLIT_NO_EMPTY );
	if ( in_array( $class, $classes, true ) ) return $b;
	$classes[]               = $class;
	$b['attrs']['className'] = implode( ' ', $classes );
	foreach ( $b['innerContent'] as $k => $chunk ) {
		if ( ! is_string( $chunk ) || trim( $chunk ) === '' ) continue;
		$p = new WP_HTML_Tag_Processor( $chunk );
		if ( $p->next_tag() ) { $p->add_class( $class ); $b['innerContent'][ $k ] = $p->get_updated_html(); }
		break;
	}
	$b['innerHTML'] = implode( '', array_filter( $b['innerContent'], 'is_string' ) );
	return $b;
}

/** Set the link of a button / navigation link / linked image. */
function livecrafts_block_set_link( array $b, $url ) {
	if ( in_array( $b['blockName'], array( 'core/navigation-link', 'core/navigation-submenu', 'core/home-link' ), true ) ) {
		$b['attrs']['url'] = $url;
		return $b;
	}
	$p = new WP_HTML_Tag_Processor( $b['innerHTML'] );
	if ( ! $p->next_tag( 'a' ) ) return new WP_Error( 'livecrafts_no_link', 'This block has no link to change.', array( 'status' => 400 ) );
	$p->set_attribute( 'href', $url );
	return livecrafts_block_set_html( $b, $p->get_updated_html() );
}

function livecrafts_block_link( array $b ) {
	if ( isset( $b['attrs']['url'] ) && in_array( $b['blockName'], array( 'core/navigation-link', 'core/navigation-submenu', 'core/home-link' ), true ) ) return (string) $b['attrs']['url'];
	$p = new WP_HTML_Tag_Processor( isset( $b['innerHTML'] ) ? $b['innerHTML'] : '' );
	return $p->next_tag( 'a' ) ? (string) $p->get_attribute( 'href' ) : null;
}

/** Point an image or cover block at another Media Library image. */
function livecrafts_block_set_image( array $b, $id ) {
	$url = (string) wp_get_attachment_url( $id );
	$alt = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
	if ( $b['blockName'] === 'core/image' ) {
		$b['attrs']['id'] = $id;
		unset( $b['attrs']['width'], $b['attrs']['height'] );
	} elseif ( $b['blockName'] === 'core/cover' ) {
		$b['attrs']['id']  = $id;
		$b['attrs']['url'] = $url;
	} elseif ( $b['blockName'] === 'core/media-text' ) {
		$b['attrs']['mediaId']   = $id;
		$b['attrs']['mediaLink'] = get_attachment_link( $id );
	} else {
		return new WP_Error( 'livecrafts_no_image', 'This block has no image to change.', array( 'status' => 400 ) );
	}
	foreach ( $b['innerContent'] as $k => $chunk ) {
		if ( ! is_string( $chunk ) || stripos( $chunk, '<img' ) === false ) continue;
		$p = new WP_HTML_Tag_Processor( $chunk );
		while ( $p->next_tag( 'img' ) ) {
			$p->set_attribute( 'src', $url );
			if ( $alt !== '' || $p->get_attribute( 'alt' ) === null ) $p->set_attribute( 'alt', $alt );
			$p->remove_attribute( 'srcset' );
			$p->remove_attribute( 'width' );
			$p->remove_attribute( 'height' );
			foreach ( preg_split( '/\s+/', (string) $p->get_attribute( 'class' ), -1, PREG_SPLIT_NO_EMPTY ) as $c ) {
				if ( preg_match( '/^wp-image-\d+$/', $c ) ) $p->remove_class( $c );
			}
			$p->add_class( 'wp-image-' . $id );
			break;
		}
		$b['innerContent'][ $k ] = $p->get_updated_html();
		break;
	}
	$b['innerHTML'] = implode( '', array_filter( $b['innerContent'], 'is_string' ) );
	return $b;
}

/* ------------------------------------------------------------------ editor markers (data-lc-block) */

// For logged-in editors: tag the blocks of the page's own content with their path, before WordPress renders them.
add_filter( 'the_content', function ( $content ) {
	if ( ! livecrafts_user_can_edit() || ! has_blocks( $content ) || ! in_the_loop() || ! is_main_query() ) return $content;
	$post_id = (int) get_the_ID();
	if ( ! $post_id || $post_id !== (int) get_queried_object_id() ) return $content;
	$mark = function ( array $blocks, $prefix ) use ( &$mark, $post_id ) {
		$n = 0;
		foreach ( $blocks as $i => $b ) {
			if ( empty( $b['blockName'] ) ) continue;
			$path = $prefix === '' ? (string) $n : $prefix . '.' . $n;
			$blocks[ $i ]['attrs']['lcPath'] = $post_id . ':' . $path;
			$blocks[ $i ]['innerBlocks']     = $mark( $b['innerBlocks'], $path );
			$n++;
		}
		return $blocks;
	};
	return serialize_blocks( $mark( parse_blocks( $content ), '' ) );
}, 8 );

add_filter( 'render_block', function ( $html, $block ) {
	if ( empty( $block['attrs']['lcPath'] ) || ! is_string( $html ) || $html === '' ) return $html;
	$p = new WP_HTML_Tag_Processor( $html );
	if ( ! $p->next_tag() ) return $html;
	$p->set_attribute( 'data-lc-block', $block['attrs']['lcPath'] );
	return $p->get_updated_html();
}, 10, 2 );

/* ------------------------------------------------------------------ change kinds block.* (see kinds.php) */

/** The kind definition for one block operation. */
function livecrafts_block_kind( $op ) {
	return array(
		'object'  => 'post',
		'group'   => 'content', // conflicts: the page content as a whole
		'prepare' => function ( $post_id, $target, $value, $draft ) use ( $op ) { return livecrafts_block_prepare( $op, $post_id, $target, $value, $draft ); },
		'read'    => function ( $data, $target, $payload ) {
			if ( empty( $payload['op'] ) || $payload['op'] === 'insert' ) return '';
			$b = livecrafts_block_get( parse_blocks( $data['fields']['content'] ), $target );
			return $b ? serialize_block( $b ) : '';
		},
		'base'    => function ( $data ) { return $data['fields']['content']; },
		'apply'   => function ( &$data, $target, $after, $payload ) {
			$blocks = livecrafts_block_apply( parse_blocks( $data['fields']['content'] ), $target, $after, $payload );
			if ( is_wp_error( $blocks ) ) return $blocks;
			$data['fields']['content'] = serialize_blocks( $blocks );
			return true;
		},
		'landed'  => function ( $copy, $expected ) { return $copy['fields']['content'] === $expected['fields']['content']; },
		'revert'  => 'livecrafts_block_revert',
	);
}

/** Check one block operation against the draft content. */
function livecrafts_block_prepare( $op, $post_id, $target, $value, array $draft ) {
	$content = (string) $draft['fields']['content'];
	$blocks  = parse_blocks( $content );
	if ( $op === 'insert' ) {
		if ( ! preg_match( '/^((?:\d{1,4})(?:\.\d{1,4}){0,9})?:(\d{1,4}|end)$/', (string) $target, $m ) ) {
			return livecrafts_bad_value( 'Where to add: "<parent path>:<index>", e.g. ":0" (top of the page), ":end", or "2:1" (inside block 2, second place).' );
		}
		$parent = isset( $m[1] ) ? $m[1] : '';
		if ( $parent !== '' && ! livecrafts_block_get( $blocks, $parent ) ) return new WP_Error( 'livecrafts_no_block', 'There is no block at ' . $parent . '.', array( 'status' => 404 ) );
		if ( $content !== '' && ! has_blocks( $content ) ) return livecrafts_bad_value( 'This page content is not made of blocks (classic editor or a page builder), so blocks cannot be added here.' );
		$count = livecrafts_block_count( $blocks, $parent );
		$index = $m[2] === 'end' ? $count : (int) $m[2];
		if ( $index > $count ) return livecrafts_bad_value( 'That place is past the end (there are ' . $count . ' blocks there).' );
		$new = livecrafts_blocks_from_markup( $value, $post_id );
		if ( is_wp_error( $new ) ) return $new;
		return array(
			'target'  => $parent . ':' . $index,
			'after'   => serialize_blocks( $new ),
			'payload' => array( 'op' => 'insert', 'parent' => $parent, 'index' => $index, 'count' => count( $new ) ),
			'summary' => 'Add ' . implode( ', ', array_map( 'livecrafts_block_label', $new ) ) . ' block' . ( count( $new ) === 1 ? '' : 's' ),
		);
	}

	if ( ! livecrafts_path_ok( $target ) ) return livecrafts_bad_value( 'A block is addressed by its path, e.g. 2 or 2.0 (data-lc-block on the page).' );
	$b = livecrafts_block_get( $blocks, $target );
	if ( ! $b ) return new WP_Error( 'livecrafts_no_block', 'There is no block at ' . $target . ' (any more).', array( 'status' => 404 ) );
	list( $parent, $index ) = livecrafts_path_split( $target );
	$label   = livecrafts_block_label( $b );
	$payload = array( 'op' => $op, 'parent' => $parent, 'index' => $index, 'before_block' => serialize_block( $b ), 'block' => $b['blockName'] );
	$same    = array( 'target' => $target, 'after' => null, 'payload' => $payload, 'summary' => '', 'unchanged' => true );

	switch ( $op ) {
		case 'text':
			$parts = livecrafts_block_text_parts( $b );
			if ( ! $parts ) return livecrafts_bad_value( 'The text of a ' . $label . ' block cannot be edited directly. Replace the block instead.' );
			$html = livecrafts_inline_html( $value );
			if ( $html === '' ) return livecrafts_bad_value( 'The new text is empty. To take the block away, remove it.' );
			if ( $html === trim( $parts[1] ) ) return $same;
			return array( 'target' => $target, 'after' => $html, 'payload' => $payload, 'summary' => $label . ' text: ' . livecrafts_quote( $parts[1] ) . ' → ' . livecrafts_quote( $html ) );
		case 'link':
			$old = livecrafts_block_link( $b );
			if ( $old === null ) return livecrafts_bad_value( 'This ' . $label . ' block has no link.' );
			$url = esc_url_raw( (string) $value );
			if ( $url === '' ) return livecrafts_bad_value( 'Not a valid address.' );
			if ( $url === $old ) return $same;
			return array( 'target' => $target, 'after' => $url, 'payload' => $payload, 'summary' => $label . ' link: ' . livecrafts_quote( $old ) . ' → ' . livecrafts_quote( $url ) );
		case 'image':
			$id = absint( $value );
			if ( ! $id || ! wp_attachment_is_image( $id ) ) return livecrafts_bad_value( 'That is not an image from the Media Library.' );
			if ( ! in_array( $b['blockName'], array( 'core/image', 'core/cover', 'core/media-text' ), true ) ) return livecrafts_bad_value( 'This ' . $label . ' block has no image.' );
			return array( 'target' => $target, 'after' => $id, 'payload' => $payload, 'summary' => $label . ' image → ' . livecrafts_quote( wp_get_attachment_url( $id ) ) );
		case 'class':
			$class = sanitize_html_class( (string) $value );
			if ( $class === '' || $class !== (string) $value ) return livecrafts_bad_value( 'Not a valid class name.' );
			if ( in_array( $class, preg_split( '/\s+/', isset( $b['attrs']['className'] ) ? (string) $b['attrs']['className'] : '' ), true ) ) return $same;
			return array( 'target' => $target, 'after' => $class, 'payload' => $payload, 'summary' => $label . ': add class ' . $class );
		case 'replace':
			$new = livecrafts_blocks_from_markup( $value, $post_id );
			if ( is_wp_error( $new ) ) return $new;
			$markup = serialize_blocks( $new );
			if ( $markup === $payload['before_block'] ) return $same;
			$payload['count'] = count( $new );
			return array( 'target' => $target, 'after' => $markup, 'payload' => $payload, 'summary' => 'Replace ' . $label . ' block' );
		case 'remove':
			$count = max( 1, (int) ( $value === null || $value === '' ? 1 : $value ) );
			if ( $index + $count > livecrafts_block_count( $blocks, $parent ) ) return livecrafts_bad_value( 'There are not that many blocks to remove there.' );
			$all = array();
			for ( $k = 0; $k < $count; $k++ ) $all[] = serialize_block( livecrafts_block_get( $blocks, livecrafts_path_join( $parent, $index + $k ) ) );
			$payload['before_block'] = implode( "\n\n", $all );
			return array( 'target' => $target, 'after' => $count, 'payload' => $payload, 'summary' => 'Remove ' . $label . ' block' . ( $count > 1 ? ' and ' . ( $count - 1 ) . ' more' : '' ) );
		case 'duplicate':
			return array( 'target' => $target, 'after' => $index + 1, 'payload' => $payload, 'summary' => 'Duplicate ' . $label . ' block' );
		case 'move':
			$count = livecrafts_block_count( $blocks, $parent );
			$to    = $value === 'up' ? $index - 1 : ( $value === 'down' ? $index + 1 : ( is_numeric( $value ) ? (int) $value : -1 ) );
			if ( $to < 0 || $to >= $count ) return livecrafts_bad_value( 'It cannot move there (positions 0 to ' . ( $count - 1 ) . ').' );
			if ( $to === $index ) return $same;
			return array( 'target' => $target, 'after' => $to, 'payload' => $payload, 'summary' => 'Move ' . $label . ' block ' . ( $to < $index ? 'up' : 'down' ) );
	}
	return livecrafts_bad_value( 'Unknown block operation.' );
}

/** Do one block operation on a block list. */
function livecrafts_block_apply( array $blocks, $target, $after, array $payload ) {
	$op = $payload['op'];
	if ( $op === 'insert' ) {
		$new = array_values( array_filter( parse_blocks( (string) $after ), function ( $b ) { return ! empty( $b['blockName'] ); } ) );
		return livecrafts_block_splice( $blocks, $payload['parent'], (int) $payload['index'], 0, $new );
	}
	$parent = $payload['parent'];
	$index  = (int) $payload['index'];
	switch ( $op ) {
		case 'text':
			return livecrafts_block_edit( $blocks, $target, function ( $b ) use ( $after ) {
				$parts = livecrafts_block_text_parts( $b );
				return $parts ? livecrafts_block_set_html( $b, $parts[0] . $after . $parts[2] ) : livecrafts_bad_value( 'The block changed: its text cannot be edited directly any more.' );
			} );
		case 'link':
			return livecrafts_block_edit( $blocks, $target, function ( $b ) use ( $after ) { return livecrafts_block_set_link( $b, (string) $after ); } );
		case 'image':
			return livecrafts_block_edit( $blocks, $target, function ( $b ) use ( $after ) { return livecrafts_block_set_image( $b, (int) $after ); } );
		case 'class':
			return livecrafts_block_edit( $blocks, $target, function ( $b ) use ( $after ) { return livecrafts_block_add_class( $b, (string) $after ); } );
		case 'replace':
			$new = array_values( array_filter( parse_blocks( (string) $after ), function ( $b ) { return ! empty( $b['blockName'] ); } ) );
			return livecrafts_block_splice( $blocks, $parent, $index, 1, $new );
		case 'remove':
			return livecrafts_block_splice( $blocks, $parent, $index, max( 1, (int) $after ), array() );
		case 'duplicate':
			$b = livecrafts_block_get( $blocks, $target );
			return $b ? livecrafts_block_splice( $blocks, $parent, $index + 1, 0, array( $b ) ) : new WP_Error( 'livecrafts_no_block', 'The block is gone.', array( 'status' => 404 ) );
		case 'move':
			$b = livecrafts_block_get( $blocks, $target );
			if ( ! $b ) return new WP_Error( 'livecrafts_no_block', 'The block is gone.', array( 'status' => 404 ) );
			$blocks = livecrafts_block_splice( $blocks, $parent, $index, 1, array() );
			return is_wp_error( $blocks ) ? $blocks : livecrafts_block_splice( $blocks, $parent, (int) $after, 0, array( $b ) );
	}
	return livecrafts_bad_value( 'Unknown block operation.' );
}

/** The opposite of a block change that went live: array( kind, target, value, args ). */
function livecrafts_block_revert( array $c ) {
	$p = $c['payload'];
	switch ( $p['op'] ) {
		case 'insert':    return array( 'block.remove', livecrafts_path_join( $p['parent'], $p['index'] ), (int) $p['count'], array() );
		case 'remove':    return array( 'block.insert', $p['parent'] . ':' . $p['index'], $p['before_block'], array() );
		case 'duplicate': return array( 'block.remove', livecrafts_path_join( $p['parent'], $p['index'] + 1 ), 1, array() );
		case 'move':      return array( 'block.move', livecrafts_path_join( $p['parent'], (int) $p['after'] ), $p['index'], array() );
		default:          return array( 'block.replace', $c['target'], $p['before_block'], array() );
	}
}
