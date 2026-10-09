<?php
/**
 * Component: faq (accordion, no JavaScript - native details/summary)
 * Params: heading, text, items [ title (question), text (answer) ]
 * Shortcode: [ai_faq heading="Questions"][ai_item title="Question?"]Answer[/ai_item][/ai_faq]
 */
$heading = $args['heading'] ?? '';
$text    = $args['text'] ?? '';
$items   = ai_items( $args );
?>
<section class="ai-c ai:bg-surface ai:py-sec-sm ai:sm:py-sec">
  <div class="ai:mx-auto ai:max-w-3xl ai:px-4 ai:sm:px-6 ai:lg:px-8">
    <?php if ( $heading ) : ?>
    <div class="ai:text-center">
      <h2 class="ai:m-0 ai:text-3xl ai:font-semibold ai:tracking-tight ai:text-balance ai:text-ink ai:sm:text-4xl"><?php echo esc_html( $heading ); ?></h2>
      <?php if ( $text ) : ?><p class="ai:mt-4 ai:mb-0 ai:text-lg ai:text-pretty ai:text-muted"><?php echo esc_html( $text ); ?></p><?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="ai:mt-12 ai:divide-y ai:divide-gray-200 ai:rounded-card ai:border ai:border-line ai:bg-surface ai:shadow-sm">
      <?php foreach ( $items as $i => $item ) : ?>
      <details class="ai:group ai:m-0 ai:p-0"<?php echo 0 === $i ? ' open' : ''; ?>>
        <summary class="ai:flex ai:cursor-pointer ai:items-center ai:justify-between ai:gap-4 ai:px-6 ai:py-5 ai:text-left ai:text-base ai:font-semibold ai:text-ink ai:hover:text-brand-700">
          <span><?php echo esc_html( $item['title'] ?? '' ); ?></span>
          <span class="ai:text-gray-400 ai:transition-transform ai:group-open:rotate-180"><?php ai_e_icon( 'chevron-down', 5 ); ?></span>
        </summary>
        <p class="ai:m-0 ai:px-6 ai:pb-6 ai:text-sm/7 ai:text-muted"><?php echo esc_html( $item['text'] ?? '' ); ?></p>
      </details>
      <?php endforeach; ?>
    </div>
  </div>
</section>
