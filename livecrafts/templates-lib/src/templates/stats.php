<?php
/**
 * Component: stats
 * Params: heading, text, items [ value, label ]
 * Shortcode: [ai_stats heading=""][ai_item value="99.9%" label="Uptime"]...[/ai_stats]
 */
$heading = $args['heading'] ?? '';
$text    = $args['text'] ?? '';
$items   = ai_items( $args );
?>
<section class="ai-c bg-brand-900 py-16 sm:py-20">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <?php if ( $heading ) : ?>
    <div class="mx-auto max-w-2xl text-center">
      <h2 class="m-0 text-3xl font-semibold tracking-tight text-balance text-white sm:text-4xl"><?php echo esc_html( $heading ); ?></h2>
      <?php if ( $text ) : ?><p class="mt-4 mb-0 text-lg text-pretty text-brand-100"><?php echo esc_html( $text ); ?></p><?php endif; ?>
    </div>
    <?php endif; ?>
    <dl class="m-0 mt-12 grid grid-cols-2 gap-8 text-center lg:grid-cols-4">
      <?php foreach ( $items as $item ) : ?>
      <div class="m-0 flex flex-col-reverse gap-2">
        <dt class="m-0 text-sm font-medium text-brand-100"><?php echo esc_html( $item['label'] ?? '' ); ?></dt>
        <dd class="m-0 text-4xl font-semibold tracking-tight text-white sm:text-5xl"><?php echo esc_html( $item['value'] ?? '' ); ?></dd>
      </div>
      <?php endforeach; ?>
    </dl>
  </div>
</section>
