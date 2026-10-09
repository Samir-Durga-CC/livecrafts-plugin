<?php
/**
 * Component: hero (split, text left / image right)
 * Params: eyebrow, heading, text, primary_label, primary_url, secondary_label, secondary_url, image
 * Shortcode: [ai_hero eyebrow="New" heading="..." text="..." primary_label="Start" primary_url="/" secondary_label="Learn more" secondary_url="/about" image="https://..."]
 */
$eyebrow = $args['eyebrow'] ?? '';
$heading = $args['heading'] ?? '';
$text    = $args['text'] ?? '';
$image   = $args['image'] ?? '';
?>
<section class="ai-c overflow-hidden bg-surface">
  <div class="mx-auto grid max-w-wide items-center gap-12 px-4 sm:px-6 lg:grid-cols-2 lg:gap-16 lg:px-8 py-sec-sm sm:py-sec">
    <div>
      <?php if ( $eyebrow ) : ?>
      <p class="mb-5 mt-0 inline-flex items-center rounded-full bg-brand-50 px-3 py-1 text-sm font-medium text-brand-700 ring-1 ring-inset ring-brand-200"><?php echo esc_html( $eyebrow ); ?></p>
      <?php endif; ?>
      <h1 class="m-0 text-4xl font-bold tracking-tight text-balance text-ink sm:text-5xl lg:text-6xl"><?php echo esc_html( $heading ); ?></h1>
      <?php if ( $text ) : ?>
      <p class="mt-6 mb-0 max-w-xl text-lg/8 text-pretty text-muted"><?php echo esc_html( $text ); ?></p>
      <?php endif; ?>
      <div class="mt-10 flex flex-wrap items-center gap-3">
        <?php
        if ( ! empty( $args['primary_label'] ) ) {
	        ai_component( 'button', array( 'label' => $args['primary_label'], 'url' => $args['primary_url'] ?? '#', 'size' => 'lg' ) );
        }
        if ( ! empty( $args['secondary_label'] ) ) {
	        ai_component( 'button', array( 'label' => $args['secondary_label'], 'url' => $args['secondary_url'] ?? '#', 'size' => 'lg', 'variant' => 'outline', 'icon' => 'none' ) );
        }
        ?>
      </div>
    </div>
    <?php if ( $image ) : ?>
    <div class="relative">
      <div class="absolute -inset-4 rounded-band bg-brand-50" aria-hidden="true"></div>
      <img alt="" src="<?php echo esc_url( $image ); ?>" class="relative block aspect-4/3 h-auto w-full max-w-full rounded-card object-cover shadow-xl ring-1 ring-gray-950/10" />
    </div>
    <?php endif; ?>
  </div>
</section>
