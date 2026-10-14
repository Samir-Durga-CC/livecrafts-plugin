<?php
/**
 * Partial: the one or two buttons under a message.
 * Params: primary_label, primary_url, secondary_label, secondary_url, align (center|left), tone (ink|surface), size (md|lg)
 *
 * One place decides which button is solid, which is outline, and how they behave on a dark background - so no section
 * invents its own pair.
 */
$primary   = trim( (string) ( $args['primary_label'] ?? '' ) );
$secondary = trim( (string) ( $args['secondary_label'] ?? '' ) );
if ( '' === $primary && '' === $secondary ) {
	return;
}

$dark = 'surface' === ( $args['tone'] ?? 'ink' );
$size = 'md' === ( $args['size'] ?? 'lg' ) ? 'md' : 'lg';
$row  = 'left' === ( $args['align'] ?? 'center' )
	? ai_cls( 'mt-10 flex flex-wrap items-center gap-3' )
	: ai_cls( 'mt-10 flex flex-wrap items-center justify-center gap-3' );
?>
<div class="<?php echo esc_attr( $row ); ?>">
	<?php
	if ( $primary ) {
		ai_component( 'button', array(
			'label'   => $primary,
			'url'     => $args['primary_url'] ?? '#',
			'size'    => $size,
			'variant' => $dark ? 'light' : 'solid',
		) );
	}
	if ( $secondary ) {
		ai_component( 'button', array(
			'label'   => $secondary,
			'url'     => $args['secondary_url'] ?? '#',
			'size'    => $size,
			'variant' => $dark ? 'ghost-light' : 'outline',
			'icon'    => 'none',
		) );
	}
	?>
</div>
