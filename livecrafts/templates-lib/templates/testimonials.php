<?php
/**
 * Component: testimonials
 * Params: heading, items [ quote, name, role, image ]
 * Shortcode: [ai_testimonials heading=""][ai_item quote="" name="" role="" image=""]...[/ai_testimonials]
 */
$heading = $args['heading'] ?? '';
$items   = ai_items( $args );
?>
<section class="ai-c ai:bg-surface ai:py-sec-sm ai:sm:py-sec">
  <div class="ai:mx-auto ai:max-w-wide ai:px-4 ai:sm:px-6 ai:lg:px-8">
    <?php if ( $heading ) : ?>
    <h2 class="ai:mx-auto ai:m-0 ai:max-w-2xl ai:text-center ai:text-3xl ai:font-semibold ai:tracking-tight ai:text-balance ai:text-ink ai:sm:text-4xl"><?php echo esc_html( $heading ); ?></h2>
    <?php endif; ?>
    <div class="ai:mt-12 ai:grid ai:gap-8 ai:lg:grid-cols-3">
      <?php foreach ( $items as $item ) : ?>
      <figure class="ai:m-0 ai:flex ai:flex-col ai:rounded-card ai:border ai:border-line ai:bg-surface ai:p-8 ai:shadow-sm">
        <div class="ai:flex ai:gap-1 ai:text-amber-400" aria-hidden="true">
          <?php for ( $i = 0; $i < 5; $i++ ) { ai_e_icon( 'star', 5 ); } ?>
        </div>
        <blockquote class="ai:m-0 ai:mt-5 ai:flex-1 ai:border-0 ai:p-0 ai:text-base/7 ai:text-muted">
          <p class="ai:m-0 ai:text-muted">&ldquo;<?php echo esc_html( $item['quote'] ?? '' ); ?>&rdquo;</p>
        </blockquote>
        <figcaption class="ai:mt-6 ai:flex ai:items-center ai:gap-3">
          <?php if ( ! empty( $item['image'] ) ) : ?>
          <img alt="" src="<?php echo esc_url( $item['image'] ); ?>" class="ai:block ai:size-11 ai:max-w-full ai:rounded-full ai:object-cover" />
          <?php else : ?>
          <span class="ai:flex ai:size-11 ai:items-center ai:justify-center ai:rounded-full ai:bg-brand-100 ai:text-sm ai:font-semibold ai:text-brand-700"><?php echo esc_html( ai_initials( $item['name'] ?? '' ) ); ?></span>
          <?php endif; ?>
          <div>
            <p class="ai:m-0 ai:text-sm ai:font-semibold ai:text-ink"><?php echo esc_html( $item['name'] ?? '' ); ?></p>
            <?php if ( ! empty( $item['role'] ) ) : ?><p class="ai:m-0 ai:text-sm ai:text-subtle"><?php echo esc_html( $item['role'] ); ?></p><?php endif; ?>
          </div>
        </figcaption>
      </figure>
      <?php endforeach; ?>
    </div>
  </div>
</section>
