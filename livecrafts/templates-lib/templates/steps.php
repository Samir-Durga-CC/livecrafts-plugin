<?php
/**
 * Component: steps (how it works, a process, a timeline)
 * Params: variant (cards|timeline), eyebrow, heading, text, items [ title, text ]
 * Shortcode: [ai_steps variant="cards" heading="How it works"][ai_item title="" text=""]...[/ai_steps]
 *   cards    - a numbered card per step, joined by a rule on desktop (default)
 *   timeline - one column, each step on a vertical line: for a history or a longer process
 */
$items = ai_items( $args );
if ( ! $items ) {
	return;
}
$timeline = 'timeline' === ( $args['variant'] ?? 'cards' );
$cols     = count( $items ) === 3 ? ai_cls( 'ai:sm:grid-cols-3' ) : ai_cls( 'ai:sm:grid-cols-2 ai:lg:grid-cols-4' );
$num      = ai_cls( 'ai:flex ai:size-10 ai:shrink-0 ai:items-center ai:justify-center ai:rounded-full ai:bg-brand-600 ai:text-sm ai:font-semibold ai:text-white' );
?>
<section class="ai-c ai:bg-surface-alt ai:py-sec-sm ai:sm:py-sec">
  <div class="ai:mx-auto ai:max-w-wide ai:px-4 ai:sm:px-6 ai:lg:px-8">
    <?php ai_component( 'part-head', $args ); ?>

    <?php if ( $timeline ) : ?>
    <ol class="ai:m-0 ai:mt-14 ai:flex ai:list-none ai:flex-col ai:p-0 ai:sm:mx-auto ai:sm:max-w-2xl">
      <?php foreach ( $items as $i => $item ) : ?>
      <li class="ai:relative ai:m-0 ai:flex ai:gap-6 ai:p-0 ai:pb-10 ai:last:pb-0">
        <?php if ( $i + 1 < count( $items ) ) : ?>
        <span class="ai:absolute ai:top-12 ai:bottom-2 ai:left-5 ai:w-px ai:bg-line" aria-hidden="true"></span>
        <?php endif; ?>
        <span class="<?php echo esc_attr( $num ); ?>"><?php echo esc_html( $i + 1 ); ?></span>
        <div class="ai:pt-1.5">
          <h3 class="ai:m-0 ai:text-base ai:font-semibold ai:text-ink"><?php echo esc_html( $item['title'] ?? '' ); ?></h3>
          <?php if ( ! empty( $item['text'] ) ) : ?>
          <p class="ai:mt-2 ai:mb-0 ai:text-sm/6 ai:text-muted"><?php echo esc_html( $item['text'] ); ?></p>
          <?php endif; ?>
        </div>
      </li>
      <?php endforeach; ?>
    </ol>

    <?php else : ?>
    <ol class="<?php echo esc_attr( ai_cls( 'ai:m-0 ai:mt-14 ai:grid ai:list-none ai:gap-8 ai:p-0' ) . ' ' . $cols ); ?>">
      <?php foreach ( $items as $i => $item ) : ?>
      <li class="ai:relative ai:m-0 ai:rounded-card ai:border ai:border-line ai:bg-surface ai:p-6 ai:shadow-sm">
        <?php if ( $i + 1 < count( $items ) ) : ?>
        <!-- the rule that joins one step to the next; it disappears when the cards stack -->
        <span class="ai:absolute ai:top-11 ai:-right-4 ai:hidden ai:h-px ai:w-8 ai:bg-line ai:lg:block" aria-hidden="true"></span>
        <?php endif; ?>
        <span class="<?php echo esc_attr( $num ); ?>"><?php echo esc_html( $i + 1 ); ?></span>
        <h3 class="ai:mt-5 ai:mb-0 ai:text-base ai:font-semibold ai:text-ink"><?php echo esc_html( $item['title'] ?? '' ); ?></h3>
        <?php if ( ! empty( $item['text'] ) ) : ?>
        <p class="ai:mt-2 ai:mb-0 ai:text-sm/6 ai:text-muted"><?php echo esc_html( $item['text'] ); ?></p>
        <?php endif; ?>
      </li>
      <?php endforeach; ?>
    </ol>
    <?php endif; ?>
  </div>
</section>
