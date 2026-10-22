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

$thumb = ai_cls( 'ai:block ai:size-full ai:rounded-ctl ai:object-cover' );
$meta  = ai_cls( 'ai:text-xs ai:font-semibold ai:tracking-wide ai:text-brand-600 ai:uppercase' );
?>
<section class="ai-c ai:bg-surface-alt ai:py-sec-sm ai:sm:py-sec">
  <div class="ai:mx-auto ai:max-w-wide ai:px-4 ai:sm:px-6 ai:lg:px-8">
    <?php if ( $heading ) : ?>
    <div class="ai:mx-auto ai:max-w-2xl ai:text-center">
      <h2 class="ai:m-0 ai:text-3xl ai:font-semibold ai:tracking-tight ai:text-balance ai:text-ink ai:sm:text-4xl"><?php echo esc_html( $heading ); ?></h2>
      <?php if ( $text ) : ?><p class="ai:mt-4 ai:mb-0 ai:text-lg ai:text-pretty ai:text-muted"><?php echo esc_html( $text ); ?></p><?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ( 'grid' === $variant ) : ?>
    <div class="ai:mt-12 ai:grid ai:gap-8 ai:sm:grid-cols-2 ai:lg:grid-cols-3">
      <?php
      foreach ( $items as $item ) {
	      ai_component( 'card', array_merge( array( 'variant' => 'article' ), $item ) );
      }
      ?>
    </div>

    <?php elseif ( 'list' === $variant ) : ?>
    <ul class="ai:m-0 ai:mt-12 ai:flex ai:list-none ai:flex-col ai:gap-6 ai:p-0">
      <?php foreach ( $items as $item ) : ?>
      <li class="ai:m-0 ai:flex ai:flex-col ai:gap-5 ai:rounded-card ai:border ai:border-line ai:bg-surface ai:p-5 ai:shadow-sm ai:sm:flex-row ai:sm:items-center">
        <div class="ai:h-40 ai:w-full ai:shrink-0 ai:overflow-hidden ai:rounded-ctl ai:bg-brand-100 ai:sm:h-24 ai:sm:w-40">
          <?php if ( ! empty( $item['image'] ) ) : ?>
          <img alt="" src="<?php echo esc_url( $item['image'] ); ?>" class="<?php echo esc_attr( $thumb ); ?>" />
          <?php endif; ?>
        </div>
        <div class="ai:min-w-0">
          <?php if ( ! empty( $item['category'] ) ) : ?><span class="<?php echo esc_attr( $meta ); ?>"><?php echo esc_html( $item['category'] ); ?></span><?php endif; ?>
          <h3 class="ai:m-0 ai:mt-1 ai:text-lg ai:font-semibold ai:text-pretty ai:text-ink">
            <a href="<?php echo esc_url( $item['url'] ?? '#' ); ?>" class="ai:text-ink ai:transition-colors ai:hover:text-brand-600"><?php echo esc_html( $item['title'] ?? '' ); ?></a>
          </h3>
          <?php if ( ! empty( $item['excerpt'] ) ) : ?><p class="ai:m-0 ai:mt-2 ai:text-sm/6 ai:text-muted"><?php echo esc_html( $item['excerpt'] ); ?></p><?php endif; ?>
          <?php if ( ! empty( $item['date'] ) || ! empty( $item['read_time'] ) ) : ?>
          <p class="ai:m-0 ai:mt-2 ai:text-xs ai:text-subtle"><?php echo esc_html( trim( ( $item['date'] ?? '' ) . ' ' . ( $item['read_time'] ?? '' ) ) ); ?></p>
          <?php endif; ?>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>

    <?php else : ?>
    <div class="ai:mt-12 ai:grid ai:gap-8 ai:lg:grid-cols-2">
      <?php
      $lead = $items ? $items[0] : array();
      $rest = array_slice( $items, 1 );
      ai_component( 'card', array_merge( array( 'variant' => 'article' ), $lead ) );
      ?>
      <ul class="ai:m-0 ai:flex ai:list-none ai:flex-col ai:gap-4 ai:p-0">
        <?php foreach ( $rest as $item ) : ?>
        <li class="ai:m-0 ai:flex ai:items-center ai:gap-4 ai:rounded-card ai:border ai:border-line ai:bg-surface ai:p-4 ai:shadow-sm">
          <div class="ai:size-16 ai:shrink-0 ai:overflow-hidden ai:rounded-ctl ai:bg-brand-100">
            <?php if ( ! empty( $item['image'] ) ) : ?>
            <img alt="" src="<?php echo esc_url( $item['image'] ); ?>" class="<?php echo esc_attr( $thumb ); ?>" />
            <?php endif; ?>
          </div>
          <div class="ai:min-w-0">
            <h3 class="ai:m-0 ai:text-base ai:font-semibold ai:text-pretty ai:text-ink">
              <a href="<?php echo esc_url( $item['url'] ?? '#' ); ?>" class="ai:text-ink ai:transition-colors ai:hover:text-brand-600"><?php echo esc_html( $item['title'] ?? '' ); ?></a>
            </h3>
            <?php if ( ! empty( $item['date'] ) || ! empty( $item['read_time'] ) ) : ?>
            <p class="ai:m-0 ai:mt-1 ai:text-xs ai:text-subtle"><?php echo esc_html( trim( ( $item['date'] ?? '' ) . ' ' . ( $item['read_time'] ?? '' ) ) ); ?></p>
            <?php endif; ?>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>
  </div>
</section>
