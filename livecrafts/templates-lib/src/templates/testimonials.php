<?php
/**
 * Component: testimonials
 * Params: heading, items [ quote, name, role, image ]
 * Shortcode: [ai_testimonials heading=""][ai_item quote="" name="" role="" image=""]...[/ai_testimonials]
 */
$heading = $args['heading'] ?? '';
$items   = ai_items( $args );
?>
<section class="ai-c bg-white py-16 sm:py-24">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <?php if ( $heading ) : ?>
    <h2 class="mx-auto m-0 max-w-2xl text-center text-3xl font-semibold tracking-tight text-balance text-gray-900 sm:text-4xl"><?php echo esc_html( $heading ); ?></h2>
    <?php endif; ?>
    <div class="mt-12 grid gap-8 lg:grid-cols-3">
      <?php foreach ( $items as $item ) : ?>
      <figure class="m-0 flex flex-col rounded-2xl border border-gray-200 bg-white p-8 shadow-sm">
        <div class="flex gap-1 text-amber-400" aria-hidden="true">
          <?php for ( $i = 0; $i < 5; $i++ ) { ai_e_icon( 'star', 5 ); } ?>
        </div>
        <blockquote class="m-0 mt-5 flex-1 border-0 p-0 text-base/7 text-gray-700">
          <p class="m-0 text-gray-700">&ldquo;<?php echo esc_html( $item['quote'] ?? '' ); ?>&rdquo;</p>
        </blockquote>
        <figcaption class="mt-6 flex items-center gap-3">
          <?php if ( ! empty( $item['image'] ) ) : ?>
          <img alt="" src="<?php echo esc_url( $item['image'] ); ?>" class="block size-11 max-w-full rounded-full object-cover" />
          <?php else : ?>
          <span class="flex size-11 items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-brand-700"><?php echo esc_html( ai_initials( $item['name'] ?? '' ) ); ?></span>
          <?php endif; ?>
          <div>
            <p class="m-0 text-sm font-semibold text-gray-900"><?php echo esc_html( $item['name'] ?? '' ); ?></p>
            <?php if ( ! empty( $item['role'] ) ) : ?><p class="m-0 text-sm text-gray-500"><?php echo esc_html( $item['role'] ); ?></p><?php endif; ?>
          </div>
        </figcaption>
      </figure>
      <?php endforeach; ?>
    </div>
  </div>
</section>
