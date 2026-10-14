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
$box   = ai_cls( 'ai:divide-y ai:divide-line ai:overflow-hidden ai:rounded-card ai:border ai:border-line ai:bg-surface ai:shadow-sm' );
?>
<section class="ai-c ai:bg-surface ai:py-sec-sm ai:sm:py-sec">
  <div class="<?php echo esc_attr( $split ? ai_cls( 'ai:mx-auto ai:grid ai:max-w-wide ai:gap-10 ai:px-4 ai:sm:px-6 ai:lg:grid-cols-3 ai:lg:gap-16 ai:lg:px-8' ) : ai_cls( 'ai:mx-auto ai:max-w-3xl ai:px-4 ai:sm:px-6 ai:lg:px-8' ) ); ?>">
    <?php ai_component( 'part-head', array_merge( $args, array( 'align' => $split ? 'left' : 'center' ) ) ); ?>
    <div class="<?php echo esc_attr( $split ? ai_cls( 'ai:lg:col-span-2' ) : ai_cls( 'ai:mt-12' ) ); ?>">
      <div class="<?php echo esc_attr( $box ); ?>">
        <?php foreach ( $items as $i => $item ) : ?>
        <details class="ai:group ai:m-0 ai:p-0"<?php echo 0 === $i ? ' open' : ''; ?>>
          <summary class="ai:flex ai:cursor-pointer ai:items-center ai:justify-between ai:gap-4 ai:px-6 ai:py-5 ai:text-left ai:text-base ai:font-semibold ai:text-ink ai:transition-colors ai:hover:text-brand-700 ai:focus-visible:outline-2 ai:focus-visible:-outline-offset-2 ai:focus-visible:outline-brand-600">
            <span><?php echo esc_html( $item['title'] ?? '' ); ?></span>
            <span class="ai:shrink-0 ai:text-subtle ai:transition-transform ai:group-open:rotate-180" aria-hidden="true"><?php ai_e_icon( 'chevron-down', 5 ); ?></span>
          </summary>
          <p class="ai:m-0 ai:px-6 ai:pb-6 ai:text-sm/7 ai:text-muted"><?php echo esc_html( $item['text'] ?? '' ); ?></p>
        </details>
        <?php endforeach; ?>
      </div>
      <?php if ( ! empty( $args['note'] ) ) : ?>
      <p class="ai:m-0 ai:mt-6 ai:text-sm ai:text-subtle"><?php echo esc_html( $args['note'] ); ?></p>
      <?php endif; ?>
    </div>
  </div>
</section>
