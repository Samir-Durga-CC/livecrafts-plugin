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
$cols     = count( $items ) === 3 ? ai_cls( 'sm:grid-cols-3' ) : ai_cls( 'sm:grid-cols-2 lg:grid-cols-4' );
$num      = ai_cls( 'flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-600 text-sm font-semibold text-white' );
?>
<section class="ai-c bg-surface-alt py-sec-sm sm:py-sec">
  <div class="mx-auto max-w-wide px-4 sm:px-6 lg:px-8">
    <?php ai_component( 'part-head', $args ); ?>

    <?php if ( $timeline ) : ?>
    <ol class="m-0 mt-14 flex list-none flex-col p-0 sm:mx-auto sm:max-w-2xl">
      <?php foreach ( $items as $i => $item ) : ?>
      <li class="relative m-0 flex gap-6 p-0 pb-10 last:pb-0">
        <?php if ( $i + 1 < count( $items ) ) : ?>
        <span class="absolute top-12 bottom-2 left-5 w-px bg-line" aria-hidden="true"></span>
        <?php endif; ?>
        <span class="<?php echo esc_attr( $num ); ?>"><?php echo esc_html( $i + 1 ); ?></span>
        <div class="pt-1.5">
          <h3 class="m-0 text-base font-semibold text-ink"><?php echo esc_html( $item['title'] ?? '' ); ?></h3>
          <?php if ( ! empty( $item['text'] ) ) : ?>
          <p class="mt-2 mb-0 text-sm/6 text-muted"><?php echo esc_html( $item['text'] ); ?></p>
          <?php endif; ?>
        </div>
      </li>
      <?php endforeach; ?>
    </ol>

    <?php else : ?>
    <ol class="<?php echo esc_attr( ai_cls( 'm-0 mt-14 grid list-none gap-8 p-0' ) . ' ' . $cols ); ?>">
      <?php foreach ( $items as $i => $item ) : ?>
      <li class="relative m-0 rounded-card border border-line bg-surface p-6 shadow-sm">
        <?php if ( $i + 1 < count( $items ) ) : ?>
        <!-- the rule that joins one step to the next; it disappears when the cards stack -->
        <span class="absolute top-11 -right-4 hidden h-px w-8 bg-line lg:block" aria-hidden="true"></span>
        <?php endif; ?>
        <span class="<?php echo esc_attr( $num ); ?>"><?php echo esc_html( $i + 1 ); ?></span>
        <h3 class="mt-5 mb-0 text-base font-semibold text-ink"><?php echo esc_html( $item['title'] ?? '' ); ?></h3>
        <?php if ( ! empty( $item['text'] ) ) : ?>
        <p class="mt-2 mb-0 text-sm/6 text-muted"><?php echo esc_html( $item['text'] ); ?></p>
        <?php endif; ?>
      </li>
      <?php endforeach; ?>
    </ol>
    <?php endif; ?>
  </div>
</section>
