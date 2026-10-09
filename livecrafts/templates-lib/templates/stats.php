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
<section class="ai-c ai:bg-brand-900 ai:py-sec-sm ai:sm:py-sec">
  <div class="ai:mx-auto ai:max-w-wide ai:px-4 ai:sm:px-6 ai:lg:px-8">
    <?php if ( $heading ) : ?>
    <div class="ai:mx-auto ai:max-w-2xl ai:text-center">
      <h2 class="ai:m-0 ai:text-3xl ai:font-semibold ai:tracking-tight ai:text-balance ai:text-white ai:sm:text-4xl"><?php echo esc_html( $heading ); ?></h2>
      <?php if ( $text ) : ?><p class="ai:mt-4 ai:mb-0 ai:text-lg ai:text-pretty ai:text-brand-100"><?php echo esc_html( $text ); ?></p><?php endif; ?>
    </div>
    <?php endif; ?>
    <dl class="ai:m-0 ai:mt-12 ai:grid ai:grid-cols-2 ai:gap-8 ai:text-center ai:lg:grid-cols-4">
      <?php foreach ( $items as $item ) : ?>
      <div class="ai:m-0 ai:flex ai:flex-col-reverse ai:gap-2">
        <dt class="ai:m-0 ai:text-sm ai:font-medium ai:text-brand-100"><?php echo esc_html( $item['label'] ?? '' ); ?></dt>
        <dd class="ai:m-0 ai:text-4xl ai:font-semibold ai:tracking-tight ai:text-white ai:sm:text-5xl"><?php echo esc_html( $item['value'] ?? '' ); ?></dd>
      </div>
      <?php endforeach; ?>
    </dl>
  </div>
</section>
