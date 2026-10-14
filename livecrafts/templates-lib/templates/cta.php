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
$panel = 'light' === $style ? ai_cls( 'ai:bg-brand-50 ai:ring-1 ai:ring-inset ai:ring-brand-100' ) : ( 'ink' === $style ? ai_cls( 'ai:bg-ink' ) : ai_cls( 'ai:bg-brand-700' ) );
?>
<section class="ai-c ai:bg-surface ai:px-4 ai:py-12 ai:sm:px-6 ai:sm:py-16 ai:lg:px-8">
  <div class="<?php echo esc_attr( ai_cls( 'ai:relative ai:mx-auto ai:max-w-wide ai:overflow-hidden ai:rounded-band ai:px-6 ai:py-14 ai:text-center ai:sm:px-16 ai:sm:py-20' ) . ' ' . $panel ); ?>">
    <?php if ( $dark ) : ?>
    <!-- a soft light behind the message, so the band has depth without an image to load -->
    <div class="ai:pointer-events-none ai:absolute ai:-top-24 ai:left-1/2 ai:size-96 ai:-translate-x-1/2 ai:rounded-full ai:bg-white ai:opacity-10 ai:blur-3xl" aria-hidden="true"></div>
    <?php endif; ?>
    <div class="ai:relative">
      <?php ai_component( 'part-head', array_merge( $args, array( 'tone' => $dark ? 'surface' : 'ink' ) ) ); ?>
      <?php ai_component( 'part-actions', array_merge( $args, array( 'tone' => $dark ? 'surface' : 'ink' ) ) ); ?>
      <?php if ( ! empty( $args['note'] ) ) : ?>
      <p class="<?php echo esc_attr( ai_cls( 'ai:m-0 ai:mt-6 ai:text-sm' ) . ' ' . ( $dark ? ai_cls( 'ai:text-surface/60' ) : ai_cls( 'ai:text-subtle' ) ) ); ?>"><?php echo esc_html( $args['note'] ); ?></p>
      <?php endif; ?>
    </div>
  </div>
</section>
