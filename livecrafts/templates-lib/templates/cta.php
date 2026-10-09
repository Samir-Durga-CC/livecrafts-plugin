<?php
/**
 * Component: cta (call-to-action band)
 * Params: heading, text, primary_label, primary_url, secondary_label, secondary_url, style (brand|light)
 * Shortcode: [ai_cta heading="" text="" primary_label="" primary_url="" secondary_label="" secondary_url="" style="brand"]
 */
$heading = $args['heading'] ?? '';
$text    = $args['text'] ?? '';
$dark    = 'light' !== ( $args['style'] ?? 'brand' );
$wrap    = $dark ? ai_cls( 'ai:bg-brand-700' ) : ai_cls( 'ai:bg-brand-50 ai:ring-1 ai:ring-inset ai:ring-brand-100' );
$h       = $dark ? ai_cls( 'ai:text-white' ) : ai_cls( 'ai:text-ink' );
$p       = $dark ? ai_cls( 'ai:text-brand-100' ) : ai_cls( 'ai:text-muted' );
?>
<section class="ai-c ai:bg-surface ai:px-4 ai:py-12 ai:sm:px-6 ai:sm:py-16 ai:lg:px-8">
  <div class="<?php echo esc_attr( ai_cls( 'ai:mx-auto ai:max-w-wide ai:overflow-hidden ai:rounded-3xl ai:px-6 ai:py-14 ai:text-center ai:sm:px-16 ai:sm:py-20' ) . ' ' . $wrap ); ?>">
    <h2 class="<?php echo esc_attr( ai_cls( 'ai:mx-auto ai:m-0 ai:max-w-2xl ai:text-3xl ai:font-semibold ai:tracking-tight ai:text-balance ai:sm:text-4xl' ) . ' ' . $h ); ?>"><?php echo esc_html( $heading ); ?></h2>
    <?php if ( $text ) : ?>
    <p class="<?php echo esc_attr( ai_cls( 'ai:mx-auto ai:mt-5 ai:mb-0 ai:max-w-xl ai:text-lg ai:text-pretty' ) . ' ' . $p ); ?>"><?php echo esc_html( $text ); ?></p>
    <?php endif; ?>
    <div class="ai:mt-10 ai:flex ai:flex-wrap ai:items-center ai:justify-center ai:gap-3">
      <?php
      if ( ! empty( $args['primary_label'] ) ) {
	      ai_component( 'button', array( 'label' => $args['primary_label'], 'url' => $args['primary_url'] ?? '#', 'size' => 'lg', 'variant' => $dark ? 'light' : 'solid' ) );
      }
      if ( ! empty( $args['secondary_label'] ) ) {
	      ai_component( 'button', array( 'label' => $args['secondary_label'], 'url' => $args['secondary_url'] ?? '#', 'size' => 'lg', 'variant' => $dark ? 'ghost-light' : 'outline', 'icon' => 'none' ) );
      }
      ?>
    </div>
  </div>
</section>
