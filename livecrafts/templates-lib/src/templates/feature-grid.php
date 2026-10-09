<?php
/**
 * Component: feature-grid
 * Params: heading, text, items [ icon, title, text ]  (icons: bolt shield chart users globe lock star code clock grid ...)
 * Shortcode: [ai_feature_grid heading=""][ai_item icon="bolt" title="" text=""]...[/ai_feature_grid]
 */
$heading = $args['heading'] ?? '';
$text    = $args['text'] ?? '';
$items   = ai_items( $args );
?>
<section class="ai-c bg-white py-16 sm:py-24">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <?php if ( $heading ) : ?>
    <div class="mx-auto max-w-2xl text-center">
      <h2 class="m-0 text-3xl font-semibold tracking-tight text-balance text-gray-900 sm:text-4xl"><?php echo esc_html( $heading ); ?></h2>
      <?php if ( $text ) : ?><p class="mt-4 mb-0 text-lg text-pretty text-gray-600"><?php echo esc_html( $text ); ?></p><?php endif; ?>
    </div>
    <?php endif; ?>
    <dl class="m-0 mt-14 grid gap-x-8 gap-y-12 sm:grid-cols-2 lg:grid-cols-3">
      <?php foreach ( $items as $item ) : ?>
      <div class="m-0 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm transition hover:border-brand-200 hover:shadow-md">
        <dt class="m-0 flex items-center gap-3 text-base font-semibold text-gray-900">
          <span class="flex size-10 items-center justify-center rounded-lg bg-brand-50 text-brand-600 ring-1 ring-inset ring-brand-100"><?php ai_e_icon( $item['icon'] ?? 'bolt', 5 ); ?></span>
          <?php echo esc_html( $item['title'] ?? '' ); ?>
        </dt>
        <dd class="m-0 mt-4 text-sm/6 text-gray-600"><?php echo esc_html( $item['text'] ?? '' ); ?></dd>
      </div>
      <?php endforeach; ?>
    </dl>
  </div>
</section>
