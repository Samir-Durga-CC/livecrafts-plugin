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
<section class="ai-c bg-surface-alt py-sec-sm sm:py-sec">
  <div class="mx-auto max-w-wide px-4 sm:px-6 lg:px-8">
    <?php if ( $heading ) : ?>
    <div class="mx-auto max-w-2xl text-center">
      <h2 class="m-0 text-3xl font-semibold tracking-tight text-balance text-ink sm:text-4xl"><?php echo esc_html( $heading ); ?></h2>
      <?php if ( $text ) : ?><p class="mt-4 mb-0 text-lg text-pretty text-muted"><?php echo esc_html( $text ); ?></p><?php endif; ?>
    </div>
    <?php endif; ?>
    <ol class="m-0 mt-14 grid list-none gap-8 p-0 sm:grid-cols-2 lg:grid-cols-4">
      <?php foreach ( $items as $i => $item ) : ?>
      <li class="m-0 rounded-card border border-line bg-surface p-6 shadow-sm">
        <span class="flex size-10 items-center justify-center rounded-full bg-brand-600 text-sm font-semibold text-white"><?php echo esc_html( $i + 1 ); ?></span>
        <h3 class="mt-5 mb-0 text-base font-semibold text-ink"><?php echo esc_html( $item['title'] ?? '' ); ?></h3>
        <p class="mt-2 mb-0 text-sm/6 text-muted"><?php echo esc_html( $item['text'] ?? '' ); ?></p>
      </li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>
