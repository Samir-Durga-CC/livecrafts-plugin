<?php
/**
 * Component: slider (carousel)
 * Params: variant (hero|quote|cards), heading, text, items [ title, text, image, url, link_label, name, role ]
 * Shortcode: [ai_slider variant="hero" heading=""][ai_item title="" text="" image="" url="" link_label=""]...[/ai_slider]
 *   hero  - full-width slides with a heading, text and a button (default)
 *   quote - one quotation per slide (quote/text + name + role)
 *   cards - several cards side by side, scrolled horizontally
 *
 * It slides with CSS scroll-snap: no JavaScript, so it cannot clash with the theme's own slider library, it works with
 * a swipe, a trackpad, the scrollbar and the keyboard, and it degrades to a plain scrolling row in old browsers.
 */
$variant = in_array( $args['variant'] ?? 'hero', array( 'hero', 'quote', 'cards' ), true ) ? $args['variant'] : 'hero';
$heading = $args['heading'] ?? '';
$text    = $args['text'] ?? '';
$items   = ai_items( $args );

$rail  = ai_cls( 'm-0 flex snap-x snap-mandatory list-none gap-6 overflow-x-auto scroll-smooth p-0 pb-4' );
$label = $heading ? $heading : 'Slider';
?>
<section class="ai-c bg-surface py-sec-sm sm:py-sec">
  <div class="mx-auto max-w-wide px-4 sm:px-6 lg:px-8">
    <?php if ( $heading ) : ?>
    <div class="mx-auto max-w-2xl text-center">
      <h2 class="m-0 text-3xl font-semibold tracking-tight text-balance text-ink sm:text-4xl"><?php echo esc_html( $heading ); ?></h2>
      <?php if ( $text ) : ?><p class="mt-4 mb-0 text-lg text-pretty text-muted"><?php echo esc_html( $text ); ?></p><?php endif; ?>
    </div>
    <?php endif; ?>

    <ul class="<?php echo esc_attr( $rail ); ?> mt-10" tabindex="0" role="region" aria-label="<?php echo esc_attr( $label ); ?>">
      <?php foreach ( $items as $item ) : ?>

      <?php if ( 'hero' === $variant ) : ?>
      <li class="m-0 w-full shrink-0 snap-center p-0">
        <div class="relative flex min-h-80 flex-col justify-center overflow-hidden rounded-band bg-brand-600 p-8 sm:min-h-96 sm:p-14">
          <?php if ( ! empty( $item['image'] ) ) : ?>
          <img alt="" src="<?php echo esc_url( $item['image'] ); ?>" class="absolute inset-0 block size-full object-cover opacity-30" />
          <?php endif; ?>
          <div class="relative max-w-xl">
            <h3 class="m-0 text-3xl font-bold tracking-tight text-balance text-white sm:text-4xl"><?php echo esc_html( $item['title'] ?? '' ); ?></h3>
            <?php if ( ! empty( $item['text'] ) ) : ?>
            <p class="mt-4 mb-0 text-lg text-pretty text-white"><?php echo esc_html( $item['text'] ); ?></p>
            <?php endif; ?>
            <?php if ( ! empty( $item['link_label'] ) ) : ?>
            <a href="<?php echo esc_url( $item['url'] ?? '#' ); ?>" class="mt-8 inline-flex items-center justify-center rounded-lg bg-white px-6 py-3 text-base font-semibold text-brand-700 shadow-sm transition-colors hover:bg-brand-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"><?php echo esc_html( $item['link_label'] ); ?></a>
            <?php endif; ?>
          </div>
        </div>
      </li>

      <?php elseif ( 'quote' === $variant ) : ?>
      <li class="m-0 w-full shrink-0 snap-center p-0">
        <figure class="m-0 flex min-h-64 flex-col items-center justify-center rounded-band bg-surface-alt p-8 text-center sm:p-14">
          <div class="flex gap-1 text-amber-400" aria-hidden="true">
            <?php for ( $i = 0; $i < 5; $i++ ) { ai_e_icon( 'star', 5 ); } ?>
          </div>
          <blockquote class="m-0 mt-6 border-0 p-0">
            <p class="m-0 max-w-2xl text-xl font-medium text-balance text-ink sm:text-2xl">&ldquo;<?php echo esc_html( $item['text'] ?? ( $item['quote'] ?? '' ) ); ?>&rdquo;</p>
          </blockquote>
          <figcaption class="mt-6 text-sm text-muted">
            <span class="font-semibold text-ink"><?php echo esc_html( $item['name'] ?? ( $item['title'] ?? '' ) ); ?></span>
            <?php if ( ! empty( $item['role'] ) ) : ?><span class="text-muted">, <?php echo esc_html( $item['role'] ); ?></span><?php endif; ?>
          </figcaption>
        </figure>
      </li>

      <?php else : ?>
      <li class="m-0 w-72 shrink-0 snap-start p-0 sm:w-80">
        <article class="flex h-full flex-col overflow-hidden rounded-card border border-line bg-surface shadow-sm">
          <div class="h-40 bg-brand-100">
            <?php if ( ! empty( $item['image'] ) ) : ?>
            <img alt="" src="<?php echo esc_url( $item['image'] ); ?>" class="block size-full object-cover" />
            <?php endif; ?>
          </div>
          <div class="flex flex-1 flex-col p-6">
            <h3 class="m-0 text-lg font-semibold text-pretty text-ink"><?php echo esc_html( $item['title'] ?? '' ); ?></h3>
            <?php if ( ! empty( $item['text'] ) ) : ?>
            <p class="m-0 mt-2 flex-1 text-sm/6 text-muted"><?php echo esc_html( $item['text'] ); ?></p>
            <?php endif; ?>
            <?php if ( ! empty( $item['link_label'] ) ) : ?>
            <a href="<?php echo esc_url( $item['url'] ?? '#' ); ?>" class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-brand-600 transition-colors hover:text-brand-700"><?php echo esc_html( $item['link_label'] ); ?><?php ai_e_icon( 'arrow-right', 4 ); ?></a>
            <?php endif; ?>
          </div>
        </article>
      </li>
      <?php endif; ?>

      <?php endforeach; ?>
    </ul>
    <p class="m-0 mt-3 text-center text-xs text-subtle">Swipe or scroll sideways to see more.</p>
  </div>
</section>
