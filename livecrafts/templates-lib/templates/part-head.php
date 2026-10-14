<?php
/**
 * Partial: the head of a section - eyebrow, heading, lede.
 * Params: eyebrow, heading, text, align (center|left), tone (ink|surface), level (h1|h2|h3)
 *
 * Every section uses it, so the type scale, the spacing under a heading and the colour on a dark background are
 * decided in ONE place. Change it here and the whole library follows.
 */
$heading = trim( (string) ( $args['heading'] ?? '' ) );
$text    = trim( (string) ( $args['text'] ?? '' ) );
$eyebrow = trim( (string) ( $args['eyebrow'] ?? '' ) );
if ( '' === $heading && '' === $text && '' === $eyebrow ) {
	return; // nothing to show: the section keeps its own spacing instead of an empty block
}

$dark  = 'surface' === ( $args['tone'] ?? 'ink' );
$level = in_array( $args['level'] ?? 'h2', array( 'h1', 'h2', 'h3' ), true ) ? $args['level'] : 'h2';
$box   = 'left' === ( $args['align'] ?? 'center' ) ? ai_cls( 'ai:max-w-2xl' ) : ai_cls( 'ai:mx-auto ai:max-w-2xl ai:text-center' );
$pill  = $dark
	? ai_cls( 'ai:m-0 ai:mb-5 ai:inline-flex ai:items-center ai:rounded-full ai:bg-surface/10 ai:px-3 ai:py-1 ai:text-sm ai:font-medium ai:text-surface' )
	: ai_cls( 'ai:m-0 ai:mb-5 ai:inline-flex ai:items-center ai:rounded-full ai:bg-brand-50 ai:px-3 ai:py-1 ai:text-sm ai:font-medium ai:text-brand-700 ai:ring-1 ai:ring-inset ai:ring-brand-200' );
$title = $dark
	? ai_cls( 'ai:m-0 ai:text-3xl ai:font-semibold ai:tracking-tight ai:text-balance ai:text-surface ai:sm:text-4xl' )
	: ai_cls( 'ai:m-0 ai:text-3xl ai:font-semibold ai:tracking-tight ai:text-balance ai:text-ink ai:sm:text-4xl' );
$lede  = $dark
	? ai_cls( 'ai:mt-4 ai:mb-0 ai:text-lg/8 ai:text-pretty ai:text-surface/70' )
	: ai_cls( 'ai:mt-4 ai:mb-0 ai:text-lg/8 ai:text-pretty ai:text-muted' );
?>
<div class="<?php echo esc_attr( $box ); ?>">
	<?php if ( $eyebrow ) : ?>
	<p class="<?php echo esc_attr( $pill ); ?>"><?php echo esc_html( $eyebrow ); ?></p>
	<?php endif; ?>
	<?php if ( $heading ) : ?>
	<<?php echo esc_html( $level ); ?> class="<?php echo esc_attr( $title ); ?>"><?php echo esc_html( $heading ); ?></<?php echo esc_html( $level ); ?>>
	<?php endif; ?>
	<?php if ( $text ) : ?>
	<p class="<?php echo esc_attr( $lede ); ?>"><?php echo esc_html( $text ); ?></p>
	<?php endif; ?>
</div>
