<?php
/**
 * Component: cta (call-to-action band)
 * Params: heading, text, primary_label, primary_url, secondary_label, secondary_url, style (brand|light)
 * Shortcode: [ai_cta heading="" text="" primary_label="" primary_url="" secondary_label="" secondary_url="" style="brand"]
 */
$heading = $args['heading'] ?? '';
$text    = $args['text'] ?? '';
$dark    = 'light' !== ( $args['style'] ?? 'brand' );
$wrap    = $dark ? ai_cls( 'bg-brand-700' ) : ai_cls( 'bg-brand-50 ring-1 ring-inset ring-brand-100' );
$h       = $dark ? ai_cls( 'text-white' ) : ai_cls( 'text-gray-900' );
$p       = $dark ? ai_cls( 'text-brand-100' ) : ai_cls( 'text-gray-600' );
?>
<section class="ai-c bg-white px-4 py-12 sm:px-6 sm:py-16 lg:px-8">
  <div class="<?php echo esc_attr( ai_cls( 'mx-auto max-w-7xl overflow-hidden rounded-3xl px-6 py-14 text-center sm:px-16 sm:py-20' ) . ' ' . $wrap ); ?>">
    <h2 class="<?php echo esc_attr( ai_cls( 'mx-auto m-0 max-w-2xl text-3xl font-semibold tracking-tight text-balance sm:text-4xl' ) . ' ' . $h ); ?>"><?php echo esc_html( $heading ); ?></h2>
    <?php if ( $text ) : ?>
    <p class="<?php echo esc_attr( ai_cls( 'mx-auto mt-5 mb-0 max-w-xl text-lg text-pretty' ) . ' ' . $p ); ?>"><?php echo esc_html( $text ); ?></p>
    <?php endif; ?>
    <div class="mt-10 flex flex-wrap items-center justify-center gap-3">
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
