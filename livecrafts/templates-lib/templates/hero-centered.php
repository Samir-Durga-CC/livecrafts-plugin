<?php
/**
 * Component: hero-centered
 * Params: eyebrow, heading, text, primary_label, primary_url, secondary_label, secondary_url, note
 * Shortcode: [ai_hero_centered eyebrow="" heading="" text="" primary_label="" primary_url="" secondary_label="" secondary_url="" note=""]
 */
$eyebrow = $args['eyebrow'] ?? '';
$heading = $args['heading'] ?? '';
$text    = $args['text'] ?? '';
$note    = $args['note'] ?? '';
?>
<section class="ai-c ai:relative ai:isolate ai:overflow-hidden ai:bg-white">
  <div class="ai:absolute ai:inset-x-0 ai:top-0 ai:-z-10 ai:h-72 ai:bg-linear-to-b ai:from-brand-50 ai:to-white" aria-hidden="true"></div>
  <div class="ai:mx-auto ai:max-w-4xl ai:px-4 ai:py-20 ai:text-center ai:sm:px-6 ai:sm:py-28 ai:lg:px-8 ai:lg:py-32">
    <?php if ( $eyebrow ) : ?>
    <p class="ai:mb-6 ai:mt-0 ai:inline-flex ai:items-center ai:rounded-full ai:bg-white ai:px-3 ai:py-1 ai:text-sm ai:font-medium ai:text-brand-700 ai:shadow-sm ai:ring-1 ai:ring-inset ai:ring-brand-200"><?php echo esc_html( $eyebrow ); ?></p>
    <?php endif; ?>
    <h1 class="ai:m-0 ai:text-4xl ai:font-bold ai:tracking-tight ai:text-balance ai:text-gray-900 ai:sm:text-6xl"><?php echo esc_html( $heading ); ?></h1>
    <?php if ( $text ) : ?>
    <p class="ai:mx-auto ai:mt-6 ai:mb-0 ai:max-w-2xl ai:text-lg/8 ai:text-pretty ai:text-gray-600"><?php echo esc_html( $text ); ?></p>
    <?php endif; ?>
    <div class="ai:mt-10 ai:flex ai:flex-wrap ai:items-center ai:justify-center ai:gap-3">
      <?php
      if ( ! empty( $args['primary_label'] ) ) {
	      ai_component( 'button', array( 'label' => $args['primary_label'], 'url' => $args['primary_url'] ?? '#', 'size' => 'lg' ) );
      }
      if ( ! empty( $args['secondary_label'] ) ) {
	      ai_component( 'button', array( 'label' => $args['secondary_label'], 'url' => $args['secondary_url'] ?? '#', 'size' => 'lg', 'variant' => 'outline', 'icon' => 'none' ) );
      }
      ?>
    </div>
    <?php if ( $note ) : ?>
    <p class="ai:mt-6 ai:mb-0 ai:text-sm ai:text-gray-500"><?php echo esc_html( $note ); ?></p>
    <?php endif; ?>
  </div>
</section>
