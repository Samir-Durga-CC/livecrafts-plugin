<?php
/**
 * Component: blog-grid (section heading + article cards)
 * Params: heading, text, items [ title, url, image, excerpt, category, date ]
 * Shortcode: [ai_blog_grid heading="From the blog"][ai_item title="" url="" image="" excerpt="" category="" date=""]...[/ai_blog_grid]
 * ACF: loop your repeater and call ai_component( 'card', ... ) inside a grid, or pass rows as items.
 */
$heading = $args['heading'] ?? '';
$text    = $args['text'] ?? '';
$items   = ai_items( $args );
?>
<section class="ai-c ai:bg-surface-alt ai:py-sec-sm ai:sm:py-sec">
  <div class="ai:mx-auto ai:max-w-wide ai:px-4 ai:sm:px-6 ai:lg:px-8">
    <?php if ( $heading ) : ?>
    <div class="ai:mx-auto ai:max-w-2xl ai:text-center">
      <h2 class="ai:m-0 ai:text-3xl ai:font-semibold ai:tracking-tight ai:text-balance ai:text-ink ai:sm:text-4xl"><?php echo esc_html( $heading ); ?></h2>
      <?php if ( $text ) : ?><p class="ai:mt-4 ai:mb-0 ai:text-lg ai:text-pretty ai:text-muted"><?php echo esc_html( $text ); ?></p><?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="ai:mt-12 ai:grid ai:gap-8 ai:sm:grid-cols-2 ai:lg:grid-cols-3">
      <?php
      foreach ( $items as $item ) {
	      ai_component( 'card', array_merge( array( 'variant' => 'article' ), $item ) );
      }
      ?>
    </div>
  </div>
</section>
