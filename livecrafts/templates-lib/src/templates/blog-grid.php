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
<section class="ai-c bg-surface-alt py-sec-sm sm:py-sec">
  <div class="mx-auto max-w-wide px-4 sm:px-6 lg:px-8">
    <?php if ( $heading ) : ?>
    <div class="mx-auto max-w-2xl text-center">
      <h2 class="m-0 text-3xl font-semibold tracking-tight text-balance text-ink sm:text-4xl"><?php echo esc_html( $heading ); ?></h2>
      <?php if ( $text ) : ?><p class="mt-4 mb-0 text-lg text-pretty text-muted"><?php echo esc_html( $text ); ?></p><?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="mt-12 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
      <?php
      foreach ( $items as $item ) {
	      ai_component( 'card', array_merge( array( 'variant' => 'article' ), $item ) );
      }
      ?>
    </div>
  </div>
</section>
