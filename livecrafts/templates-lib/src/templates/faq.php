<?php
/**
 * Component: faq
 * Params: variant (list|split), eyebrow, heading, text, note, items [ title (question), text (answer) ]
 * Shortcode: [ai_faq heading="Questions"][ai_item title="Question?"]Answer[/ai_item][/ai_faq]
 *   list  - one column of questions, the first one open (default)
 *   split - the heading on the left, the questions on the right: for a page that already has a lot above it
 *
 * Built on <details>, so it opens and closes with no JavaScript, works with a keyboard, and is read correctly by a
 * screen reader. The browser's own marker is hidden in the stylesheet, not here.
 */
$items = ai_items( $args );
if ( ! $items ) {
	return;
}
$split = 'split' === ( $args['variant'] ?? 'list' );
$box   = ai_cls( 'divide-y divide-line overflow-hidden rounded-card border border-line bg-surface shadow-sm' );
?>
<section class="ai-c bg-surface py-sec-sm sm:py-sec">
  <div class="<?php echo esc_attr( $split ? ai_cls( 'mx-auto grid max-w-wide gap-10 px-4 sm:px-6 lg:grid-cols-3 lg:gap-16 lg:px-8' ) : ai_cls( 'mx-auto max-w-3xl px-4 sm:px-6 lg:px-8' ) ); ?>">
    <?php ai_component( 'part-head', array_merge( $args, array( 'align' => $split ? 'left' : 'center' ) ) ); ?>
    <div class="<?php echo esc_attr( $split ? ai_cls( 'lg:col-span-2' ) : ai_cls( 'mt-12' ) ); ?>">
      <div class="<?php echo esc_attr( $box ); ?>">
        <?php foreach ( $items as $i => $item ) : ?>
        <details class="group m-0 p-0"<?php echo 0 === $i ? ' open' : ''; ?>>
          <summary class="flex cursor-pointer items-center justify-between gap-4 px-6 py-5 text-left text-base font-semibold text-ink transition-colors hover:text-brand-700 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-brand-600">
            <span><?php echo esc_html( $item['title'] ?? '' ); ?></span>
            <span class="shrink-0 text-subtle transition-transform group-open:rotate-180" aria-hidden="true"><?php ai_e_icon( 'chevron-down', 5 ); ?></span>
          </summary>
          <p class="m-0 px-6 pb-6 text-sm/7 text-muted"><?php echo esc_html( $item['text'] ?? '' ); ?></p>
        </details>
        <?php endforeach; ?>
      </div>
      <?php if ( ! empty( $args['note'] ) ) : ?>
      <p class="m-0 mt-6 text-sm text-subtle"><?php echo esc_html( $args['note'] ); ?></p>
      <?php endif; ?>
    </div>
  </div>
</section>
