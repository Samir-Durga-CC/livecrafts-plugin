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
<section class="ai-c ai:overflow-hidden ai:bg-surface">
  <div class="ai:mx-auto ai:grid ai:max-w-wide ai:items-center ai:gap-12 ai:px-4 ai:sm:px-6 ai:lg:grid-cols-2 ai:lg:gap-16 ai:lg:px-8 ai:py-sec-sm ai:sm:py-sec">
    <div>
      <?php if ( $eyebrow ) : ?>
      <p class="ai:mb-5 ai:mt-0 ai:inline-flex ai:items-center ai:rounded-full ai:bg-brand-50 ai:px-3 ai:py-1 ai:text-sm ai:font-medium ai:text-brand-700 ai:ring-1 ai:ring-inset ai:ring-brand-200"><?php echo esc_html( $eyebrow ); ?></p>
      <?php endif; ?>
      <h1 class="ai:m-0 ai:text-4xl ai:font-bold ai:tracking-tight ai:text-balance ai:text-ink ai:sm:text-5xl ai:lg:text-6xl"><?php echo esc_html( $heading ); ?></h1>
      <?php if ( $text ) : ?>
      <p class="ai:mt-6 ai:mb-0 ai:max-w-xl ai:text-lg/8 ai:text-pretty ai:text-muted"><?php echo esc_html( $text ); ?></p>
      <?php endif; ?>
      <div class="ai:mt-10 ai:flex ai:flex-wrap ai:items-center ai:gap-3">
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
    <div class="ai:relative">
      <div class="ai:absolute ai:-inset-4 ai:rounded-band ai:bg-brand-50" aria-hidden="true"></div>
      <img alt="" src="<?php echo esc_url( $image ); ?>" class="ai:relative ai:block ai:aspect-4/3 ai:h-auto ai:w-full ai:max-w-full ai:rounded-card ai:object-cover ai:shadow-xl ai:ring-1 ai:ring-gray-950/10" />
    </div>
    <?php endif; ?>
  </div>
</section>
