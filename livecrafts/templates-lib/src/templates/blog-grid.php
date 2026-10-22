<?php
/**
 * Component: blog-grid (section heading + articles)
 * Params: variant (grid|list|featured), heading, text, items [ title, url, image, excerpt, category, date, read_time ]
 * Shortcode: [ai_blog_grid variant="grid" heading="From the blog"][ai_item title="" url="" image="" excerpt="" category="" date=""]...[/ai_blog_grid]
 *   grid     - article cards, 1 / 2 / 3 columns (default)
 *   list     - a row per article: small picture beside the title and excerpt (good for long lists and sidebars)
 *   featured - the first article large, the rest as a compact list beside it
 * ACF: loop your repeater and call ai_component( 'card', ... ) inside a grid, or pass rows as items.
 */
$variant = in_array( $args['variant'] ?? 'grid', array( 'grid', 'list', 'featured' ), true ) ? $args['variant'] : 'grid';
$heading = $args['heading'] ?? '';
$text    = $args['text'] ?? '';
$items   = ai_items( $args );

$thumb = ai_cls( 'block size-full rounded-ctl object-cover' );
$meta  = ai_cls( 'text-xs font-semibold tracking-wide text-brand-600 uppercase' );
?>
<section class="ai-c bg-surface-alt py-sec-sm sm:py-sec">
  <div class="mx-auto max-w-wide px-4 sm:px-6 lg:px-8">
    <?php if ( $heading ) : ?>
    <div class="mx-auto max-w-2xl text-center">
      <h2 class="m-0 text-3xl font-semibold tracking-tight text-balance text-ink sm:text-4xl"><?php echo esc_html( $heading ); ?></h2>
      <?php if ( $text ) : ?><p class="mt-4 mb-0 text-lg text-pretty text-muted"><?php echo esc_html( $text ); ?></p><?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ( 'grid' === $variant ) : ?>
    <div class="mt-12 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
      <?php
      foreach ( $items as $item ) {
	      ai_component( 'card', array_merge( array( 'variant' => 'article' ), $item ) );
      }
      ?>
    </div>

    <?php elseif ( 'list' === $variant ) : ?>
    <ul class="m-0 mt-12 flex list-none flex-col gap-6 p-0">
      <?php foreach ( $items as $item ) : ?>
      <li class="m-0 flex flex-col gap-5 rounded-card border border-line bg-surface p-5 shadow-sm sm:flex-row sm:items-center">
        <div class="h-40 w-full shrink-0 overflow-hidden rounded-ctl bg-brand-100 sm:h-24 sm:w-40">
          <?php if ( ! empty( $item['image'] ) ) : ?>
          <img alt="" src="<?php echo esc_url( $item['image'] ); ?>" class="<?php echo esc_attr( $thumb ); ?>" />
          <?php endif; ?>
        </div>
        <div class="min-w-0">
          <?php if ( ! empty( $item['category'] ) ) : ?><span class="<?php echo esc_attr( $meta ); ?>"><?php echo esc_html( $item['category'] ); ?></span><?php endif; ?>
          <h3 class="m-0 mt-1 text-lg font-semibold text-pretty text-ink">
            <a href="<?php echo esc_url( $item['url'] ?? '#' ); ?>" class="text-ink transition-colors hover:text-brand-600"><?php echo esc_html( $item['title'] ?? '' ); ?></a>
          </h3>
          <?php if ( ! empty( $item['excerpt'] ) ) : ?><p class="m-0 mt-2 text-sm/6 text-muted"><?php echo esc_html( $item['excerpt'] ); ?></p><?php endif; ?>
          <?php if ( ! empty( $item['date'] ) || ! empty( $item['read_time'] ) ) : ?>
          <p class="m-0 mt-2 text-xs text-subtle"><?php echo esc_html( trim( ( $item['date'] ?? '' ) . ' ' . ( $item['read_time'] ?? '' ) ) ); ?></p>
          <?php endif; ?>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>

    <?php else : ?>
    <div class="mt-12 grid gap-8 lg:grid-cols-2">
      <?php
      $lead = $items ? $items[0] : array();
      $rest = array_slice( $items, 1 );
      ai_component( 'card', array_merge( array( 'variant' => 'article' ), $lead ) );
      ?>
      <ul class="m-0 flex list-none flex-col gap-4 p-0">
        <?php foreach ( $rest as $item ) : ?>
        <li class="m-0 flex items-center gap-4 rounded-card border border-line bg-surface p-4 shadow-sm">
          <div class="size-16 shrink-0 overflow-hidden rounded-ctl bg-brand-100">
            <?php if ( ! empty( $item['image'] ) ) : ?>
            <img alt="" src="<?php echo esc_url( $item['image'] ); ?>" class="<?php echo esc_attr( $thumb ); ?>" />
            <?php endif; ?>
          </div>
          <div class="min-w-0">
            <h3 class="m-0 text-base font-semibold text-pretty text-ink">
              <a href="<?php echo esc_url( $item['url'] ?? '#' ); ?>" class="text-ink transition-colors hover:text-brand-600"><?php echo esc_html( $item['title'] ?? '' ); ?></a>
            </h3>
            <?php if ( ! empty( $item['date'] ) || ! empty( $item['read_time'] ) ) : ?>
            <p class="m-0 mt-1 text-xs text-subtle"><?php echo esc_html( trim( ( $item['date'] ?? '' ) . ' ' . ( $item['read_time'] ?? '' ) ) ); ?></p>
            <?php endif; ?>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>
  </div>
</section>
