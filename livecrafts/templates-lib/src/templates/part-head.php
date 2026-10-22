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
$box   = 'left' === ( $args['align'] ?? 'center' ) ? ai_cls( 'max-w-2xl' ) : ai_cls( 'mx-auto max-w-2xl text-center' );
$pill  = $dark
	? ai_cls( 'm-0 mb-5 inline-flex items-center rounded-full bg-surface/10 px-3 py-1 text-sm font-medium text-surface' )
	: ai_cls( 'm-0 mb-5 inline-flex items-center rounded-full bg-brand-50 px-3 py-1 text-sm font-medium text-brand-700 ring-1 ring-inset ring-brand-200' );
$title = $dark
	? ai_cls( 'm-0 text-3xl font-semibold tracking-tight text-balance text-surface sm:text-4xl' )
	: ai_cls( 'm-0 text-3xl font-semibold tracking-tight text-balance text-ink sm:text-4xl' );
$lede  = $dark
	? ai_cls( 'mt-4 mb-0 text-lg/8 text-pretty text-surface/70' )
	: ai_cls( 'mt-4 mb-0 text-lg/8 text-pretty text-muted' );
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
