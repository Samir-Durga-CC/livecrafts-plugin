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
<section class="ai-c ai:bg-white ai:py-16 ai:sm:py-24">
  <div class="ai:mx-auto ai:max-w-7xl ai:px-4 ai:sm:px-6 ai:lg:px-8">
    <?php if ( $heading ) : ?>
    <div class="ai:mx-auto ai:max-w-2xl ai:text-center">
      <h2 class="ai:m-0 ai:text-3xl ai:font-semibold ai:tracking-tight ai:text-balance ai:text-gray-900 ai:sm:text-4xl"><?php echo esc_html( $heading ); ?></h2>
      <?php if ( $text ) : ?><p class="ai:mt-4 ai:mb-0 ai:text-lg ai:text-pretty ai:text-gray-600"><?php echo esc_html( $text ); ?></p><?php endif; ?>
    </div>
    <?php endif; ?>
    <dl class="ai:m-0 ai:mt-14 ai:grid ai:gap-x-8 ai:gap-y-12 ai:sm:grid-cols-2 ai:lg:grid-cols-3">
      <?php foreach ( $items as $item ) : ?>
      <div class="ai:m-0 ai:rounded-2xl ai:border ai:border-gray-200 ai:bg-white ai:p-6 ai:shadow-sm ai:transition ai:hover:border-brand-200 ai:hover:shadow-md">
        <dt class="ai:m-0 ai:flex ai:items-center ai:gap-3 ai:text-base ai:font-semibold ai:text-gray-900">
          <span class="ai:flex ai:size-10 ai:items-center ai:justify-center ai:rounded-lg ai:bg-brand-50 ai:text-brand-600 ai:ring-1 ai:ring-inset ai:ring-brand-100"><?php ai_e_icon( $item['icon'] ?? 'bolt', 5 ); ?></span>
          <?php echo esc_html( $item['title'] ?? '' ); ?>
        </dt>
        <dd class="ai:m-0 ai:mt-4 ai:text-sm/6 ai:text-gray-600"><?php echo esc_html( $item['text'] ?? '' ); ?></dd>
      </div>
      <?php endforeach; ?>
    </dl>
  </div>
</section>
