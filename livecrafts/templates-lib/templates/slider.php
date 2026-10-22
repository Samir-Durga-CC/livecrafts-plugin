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

$rail  = ai_cls( 'ai:m-0 ai:flex ai:snap-x ai:snap-mandatory ai:list-none ai:gap-6 ai:overflow-x-auto ai:scroll-smooth ai:p-0 ai:pb-4' );
$label = $heading ? $heading : 'Slider';
?>
<section class="ai-c ai:bg-surface ai:py-sec-sm ai:sm:py-sec">
  <div class="ai:mx-auto ai:max-w-wide ai:px-4 ai:sm:px-6 ai:lg:px-8">
    <?php if ( $heading ) : ?>
    <div class="ai:mx-auto ai:max-w-2xl ai:text-center">
      <h2 class="ai:m-0 ai:text-3xl ai:font-semibold ai:tracking-tight ai:text-balance ai:text-ink ai:sm:text-4xl"><?php echo esc_html( $heading ); ?></h2>
      <?php if ( $text ) : ?><p class="ai:mt-4 ai:mb-0 ai:text-lg ai:text-pretty ai:text-muted"><?php echo esc_html( $text ); ?></p><?php endif; ?>
    </div>
    <?php endif; ?>

    <ul class="<?php echo esc_attr( $rail ); ?> mt-10" tabindex="0" role="region" aria-label="<?php echo esc_attr( $label ); ?>">
      <?php foreach ( $items as $item ) : ?>

      <?php if ( 'hero' === $variant ) : ?>
      <li class="ai:m-0 ai:w-full ai:shrink-0 ai:snap-center ai:p-0">
        <div class="ai:relative ai:flex ai:min-h-80 ai:flex-col ai:justify-center ai:overflow-hidden ai:rounded-band ai:bg-brand-600 ai:p-8 ai:sm:min-h-96 ai:sm:p-14">
          <?php if ( ! empty( $item['image'] ) ) : ?>
          <img alt="" src="<?php echo esc_url( $item['image'] ); ?>" class="ai:absolute ai:inset-0 ai:block ai:size-full ai:object-cover ai:opacity-30" />
          <?php endif; ?>
          <div class="ai:relative ai:max-w-xl">
            <h3 class="ai:m-0 ai:text-3xl ai:font-bold ai:tracking-tight ai:text-balance ai:text-white ai:sm:text-4xl"><?php echo esc_html( $item['title'] ?? '' ); ?></h3>
            <?php if ( ! empty( $item['text'] ) ) : ?>
            <p class="ai:mt-4 ai:mb-0 ai:text-lg ai:text-pretty ai:text-white"><?php echo esc_html( $item['text'] ); ?></p>
            <?php endif; ?>
            <?php if ( ! empty( $item['link_label'] ) ) : ?>
            <a href="<?php echo esc_url( $item['url'] ?? '#' ); ?>" class="ai:mt-8 ai:inline-flex ai:items-center ai:justify-center ai:rounded-lg ai:bg-white ai:px-6 ai:py-3 ai:text-base ai:font-semibold ai:text-brand-700 ai:shadow-sm ai:transition-colors ai:hover:bg-brand-50 ai:focus-visible:outline-2 ai:focus-visible:outline-offset-2 ai:focus-visible:outline-white"><?php echo esc_html( $item['link_label'] ); ?></a>
            <?php endif; ?>
          </div>
        </div>
      </li>

      <?php elseif ( 'quote' === $variant ) : ?>
      <li class="ai:m-0 ai:w-full ai:shrink-0 ai:snap-center ai:p-0">
        <figure class="ai:m-0 ai:flex ai:min-h-64 ai:flex-col ai:items-center ai:justify-center ai:rounded-band ai:bg-surface-alt ai:p-8 ai:text-center ai:sm:p-14">
          <div class="ai:flex ai:gap-1 ai:text-amber-400" aria-hidden="true">
            <?php for ( $i = 0; $i < 5; $i++ ) { ai_e_icon( 'star', 5 ); } ?>
          </div>
          <blockquote class="ai:m-0 ai:mt-6 ai:border-0 ai:p-0">
            <p class="ai:m-0 ai:max-w-2xl ai:text-xl ai:font-medium ai:text-balance ai:text-ink ai:sm:text-2xl">&ldquo;<?php echo esc_html( $item['text'] ?? ( $item['quote'] ?? '' ) ); ?>&rdquo;</p>
          </blockquote>
          <figcaption class="ai:mt-6 ai:text-sm ai:text-muted">
            <span class="ai:font-semibold ai:text-ink"><?php echo esc_html( $item['name'] ?? ( $item['title'] ?? '' ) ); ?></span>
            <?php if ( ! empty( $item['role'] ) ) : ?><span class="ai:text-muted">, <?php echo esc_html( $item['role'] ); ?></span><?php endif; ?>
          </figcaption>
        </figure>
      </li>

      <?php else : ?>
      <li class="ai:m-0 ai:w-72 ai:shrink-0 ai:snap-start ai:p-0 ai:sm:w-80">
        <article class="ai:flex ai:h-full ai:flex-col ai:overflow-hidden ai:rounded-card ai:border ai:border-line ai:bg-surface ai:shadow-sm">
          <div class="ai:h-40 ai:bg-brand-100">
            <?php if ( ! empty( $item['image'] ) ) : ?>
            <img alt="" src="<?php echo esc_url( $item['image'] ); ?>" class="ai:block ai:size-full ai:object-cover" />
            <?php endif; ?>
          </div>
          <div class="ai:flex ai:flex-1 ai:flex-col ai:p-6">
            <h3 class="ai:m-0 ai:text-lg ai:font-semibold ai:text-pretty ai:text-ink"><?php echo esc_html( $item['title'] ?? '' ); ?></h3>
            <?php if ( ! empty( $item['text'] ) ) : ?>
            <p class="ai:m-0 ai:mt-2 ai:flex-1 ai:text-sm/6 ai:text-muted"><?php echo esc_html( $item['text'] ); ?></p>
            <?php endif; ?>
            <?php if ( ! empty( $item['link_label'] ) ) : ?>
            <a href="<?php echo esc_url( $item['url'] ?? '#' ); ?>" class="ai:mt-4 ai:inline-flex ai:items-center ai:gap-1 ai:text-sm ai:font-semibold ai:text-brand-600 ai:transition-colors ai:hover:text-brand-700"><?php echo esc_html( $item['link_label'] ); ?><?php ai_e_icon( 'arrow-right', 4 ); ?></a>
            <?php endif; ?>
          </div>
        </article>
      </li>
      <?php endif; ?>

      <?php endforeach; ?>
    </ul>
    <p class="ai:m-0 ai:mt-3 ai:text-center ai:text-xs ai:text-subtle">Swipe or scroll sideways to see more.</p>
  </div>
</section>
