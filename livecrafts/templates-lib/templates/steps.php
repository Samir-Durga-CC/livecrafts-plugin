<?php
/**
 * Component: steps (how it works)
 * Params: heading, text, items [ title, text ]
 * Shortcode: [ai_steps heading="How it works"][ai_item title="" text=""]...[/ai_steps]
 */
$heading = $args['heading'] ?? '';
$text    = $args['text'] ?? '';
$items   = ai_items( $args );
?>
<section class="ai-c ai:bg-gray-50 ai:py-16 ai:sm:py-24">
  <div class="ai:mx-auto ai:max-w-7xl ai:px-4 ai:sm:px-6 ai:lg:px-8">
    <?php if ( $heading ) : ?>
    <div class="ai:mx-auto ai:max-w-2xl ai:text-center">
      <h2 class="ai:m-0 ai:text-3xl ai:font-semibold ai:tracking-tight ai:text-balance ai:text-gray-900 ai:sm:text-4xl"><?php echo esc_html( $heading ); ?></h2>
      <?php if ( $text ) : ?><p class="ai:mt-4 ai:mb-0 ai:text-lg ai:text-pretty ai:text-gray-600"><?php echo esc_html( $text ); ?></p><?php endif; ?>
    </div>
    <?php endif; ?>
    <ol class="ai:m-0 ai:mt-14 ai:grid ai:list-none ai:gap-8 ai:p-0 ai:sm:grid-cols-2 ai:lg:grid-cols-4">
      <?php foreach ( $items as $i => $item ) : ?>
      <li class="ai:m-0 ai:rounded-2xl ai:border ai:border-gray-200 ai:bg-white ai:p-6 ai:shadow-sm">
        <span class="ai:flex ai:size-10 ai:items-center ai:justify-center ai:rounded-full ai:bg-brand-600 ai:text-sm ai:font-semibold ai:text-white"><?php echo esc_html( $i + 1 ); ?></span>
        <h3 class="ai:mt-5 ai:mb-0 ai:text-base ai:font-semibold ai:text-gray-900"><?php echo esc_html( $item['title'] ?? '' ); ?></h3>
        <p class="ai:mt-2 ai:mb-0 ai:text-sm/6 ai:text-gray-600"><?php echo esc_html( $item['text'] ?? '' ); ?></p>
      </li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>
