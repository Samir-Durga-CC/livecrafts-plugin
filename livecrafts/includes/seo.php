<?php
/**
 * SEO description of a page / post: stored where the site's SEO plugin reads it (Yoast, Rank Math, SEOPress,
 * All in One SEO), so it shows in that plugin's own box. Without an SEO plugin Livecrafts keeps it in its own meta
 * and prints <meta name="description"> itself.
 *
 * As a draft change it is post.meta with target "seo_description" (kinds.php maps it to the real meta key).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const LIVECRAFTS_SEO_OWN_KEY = 'lc_meta_description';

/** The active SEO plugin: yoast | rankmath | seopress | aioseo | '' (none). */
function livecrafts_seo_plugin() {
	if ( defined( 'WPSEO_VERSION' ) ) return 'yoast';
	if ( class_exists( 'RankMath' ) || defined( 'RANK_MATH_VERSION' ) ) return 'rankmath';
	if ( defined( 'SEOPRESS_VERSION' ) ) return 'seopress';
	if ( defined( 'AIOSEO_VERSION' ) ) return 'aioseo';
	return '';
}

/** The meta key the active SEO plugin reads the description from. */
function livecrafts_seo_meta_key() {
	switch ( livecrafts_seo_plugin() ) {
		case 'yoast':    return '_yoast_wpseo_metadesc';
		case 'rankmath': return 'rank_math_description';
		case 'seopress': return '_seopress_titles_desc';
		case 'aioseo':   return '_aioseo_description';
	}
	return LIVECRAFTS_SEO_OWN_KEY;
}

/** Plain text, one line, at most 320 characters (search engines show ~155). */
function livecrafts_seo_description_clean( $value ) {
	$t = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $value ) ) );
	return mb_strlen( $t ) > 320 ? rtrim( mb_substr( $t, 0, 319 ) ) . '…' : $t;
}

// No SEO plugin: print the description Livecrafts stored. An SEO plugin prints its own.
add_action( 'wp_head', function () {
	if ( livecrafts_seo_plugin() !== '' || ! is_singular() ) return;
	$d = (string) get_post_meta( get_queried_object_id(), LIVECRAFTS_SEO_OWN_KEY, true );
	if ( $d !== '' ) echo '<meta name="description" content="' . esc_attr( $d ) . '" />' . "\n";
}, 1 );
