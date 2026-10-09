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
<section class="ai-c bg-surface py-sec-sm sm:py-sec">
  <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
    <?php if ( $heading ) : ?>
    <div class="text-center">
      <h2 class="m-0 text-3xl font-semibold tracking-tight text-balance text-ink sm:text-4xl"><?php echo esc_html( $heading ); ?></h2>
      <?php if ( $text ) : ?><p class="mt-4 mb-0 text-lg text-pretty text-muted"><?php echo esc_html( $text ); ?></p><?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="mt-12 divide-y divide-gray-200 rounded-card border border-line bg-surface shadow-sm">
      <?php foreach ( $items as $i => $item ) : ?>
      <details class="group m-0 p-0"<?php echo 0 === $i ? ' open' : ''; ?>>
        <summary class="flex cursor-pointer items-center justify-between gap-4 px-6 py-5 text-left text-base font-semibold text-ink hover:text-brand-700">
          <span><?php echo esc_html( $item['title'] ?? '' ); ?></span>
          <span class="text-gray-400 transition-transform group-open:rotate-180"><?php ai_e_icon( 'chevron-down', 5 ); ?></span>
        </summary>
        <p class="m-0 px-6 pb-6 text-sm/7 text-muted"><?php echo esc_html( $item['text'] ?? '' ); ?></p>
      </details>
      <?php endforeach; ?>
    </div>
  </div>
</section>
