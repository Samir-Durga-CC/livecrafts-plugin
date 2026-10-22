<?php
/**
 * Component: cta (the band that asks for the next step)
 * Params: style (brand|light|ink), eyebrow, heading, text, note, primary_label, primary_url, secondary_label, secondary_url
 * Shortcode: [ai_cta heading="" text="" primary_label="" primary_url="" style="brand"]
 *   brand - the site's colour, full strength (default)
 *   light - a tinted panel, for a page that already has a strong band
 *   ink   - the site's dark colour, for the band directly above a light footer
 */
$heading = trim( (string) ( $args['heading'] ?? '' ) );
if ( '' === $heading && empty( $args['primary_label'] ) ) {
	return; // nothing to ask for
}
$style = in_array( $args['style'] ?? 'brand', array( 'brand', 'light', 'ink' ), true ) ? $args['style'] : 'brand';
$dark  = 'light' !== $style;
$panel = 'light' === $style ? ai_cls( 'bg-brand-50 ring-1 ring-inset ring-brand-100' ) : ( 'ink' === $style ? ai_cls( 'bg-ink' ) : ai_cls( 'bg-brand-700' ) );
?>
<section class="ai-c bg-surface px-4 py-12 sm:px-6 sm:py-16 lg:px-8">
  <div class="<?php echo esc_attr( ai_cls( 'relative mx-auto max-w-wide overflow-hidden rounded-band px-6 py-14 text-center sm:px-16 sm:py-20' ) . ' ' . $panel ); ?>">
    <?php if ( $dark ) : ?>
    <!-- a soft light behind the message, so the band has depth without an image to load -->
    <div class="pointer-events-none absolute -top-24 left-1/2 size-96 -translate-x-1/2 rounded-full bg-white opacity-10 blur-3xl" aria-hidden="true"></div>
    <?php endif; ?>
    <div class="relative">
      <?php ai_component( 'part-head', array_merge( $args, array( 'tone' => $dark ? 'surface' : 'ink' ) ) ); ?>
      <?php ai_component( 'part-actions', array_merge( $args, array( 'tone' => $dark ? 'surface' : 'ink' ) ) ); ?>
      <?php if ( ! empty( $args['note'] ) ) : ?>
      <p class="<?php echo esc_attr( ai_cls( 'm-0 mt-6 text-sm' ) . ' ' . ( $dark ? ai_cls( 'text-surface/60' ) : ai_cls( 'text-subtle' ) ) ); ?>"><?php echo esc_html( $args['note'] ); ?></p>
      <?php endif; ?>
    </div>
  </div>
</section>
