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
<section class="ai-c relative isolate overflow-hidden bg-surface">
  <div class="absolute inset-x-0 top-0 -z-10 h-72 bg-linear-to-b from-brand-50 to-white" aria-hidden="true"></div>
  <div class="mx-auto max-w-4xl px-4 py-20 text-center sm:px-6 sm:py-28 lg:px-8 lg:py-32">
    <?php if ( $eyebrow ) : ?>
    <p class="mb-6 mt-0 inline-flex items-center rounded-full bg-surface px-3 py-1 text-sm font-medium text-brand-700 shadow-sm ring-1 ring-inset ring-brand-200"><?php echo esc_html( $eyebrow ); ?></p>
    <?php endif; ?>
    <h1 class="m-0 text-4xl font-bold tracking-tight text-balance text-ink sm:text-6xl"><?php echo esc_html( $heading ); ?></h1>
    <?php if ( $text ) : ?>
    <p class="mx-auto mt-6 mb-0 max-w-2xl text-lg/8 text-pretty text-muted"><?php echo esc_html( $text ); ?></p>
    <?php endif; ?>
    <div class="mt-10 flex flex-wrap items-center justify-center gap-3">
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
    <p class="mt-6 mb-0 text-sm text-subtle"><?php echo esc_html( $note ); ?></p>
    <?php endif; ?>
  </div>
</section>
