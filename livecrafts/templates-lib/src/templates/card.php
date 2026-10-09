<?php
/**
 * Component: card
 * Params: variant (author|article), title, url, image, author, excerpt, date, read_time, category, link_label
 * Shortcode: [ai_card variant="article" title="" url="" image="" excerpt="" category="" link_label="Read more"]
 * Layout derived from HyperUI blog cards (MIT); content made dynamic, brand colors via --ai-brand.
 */
$variant    = $args['variant'] ?? 'article';
$title      = $args['title'] ?? '';
$url        = $args['url'] ?? '#';
$image      = $args['image'] ?? '';
$author     = $args['author'] ?? '';
$excerpt    = $args['excerpt'] ?? '';
$date       = $args['date'] ?? '';
$read_time  = $args['read_time'] ?? '';
$category   = $args['category'] ?? '';
$link_label = $args['link_label'] ?? 'Read more';
?>
<?php if ( 'author' === $variant ) : ?>
<a href="<?php echo esc_url( $url ); ?>" class="ai-c group block rounded-card border border-line bg-surface p-5 no-underline shadow-sm transition hover:border-brand-200 hover:shadow-md sm:p-6">
  <div class="sm:flex sm:justify-between sm:gap-4 lg:gap-6">
    <?php if ( $image ) : ?>
    <div class="sm:order-last sm:shrink-0">
      <img alt="" src="<?php echo esc_url( $image ); ?>" class="block size-16 max-w-full rounded-full object-cover ring-2 ring-white sm:size-20" />
    </div>
    <?php endif; ?>
    <div class="mt-4 sm:mt-0">
      <h3 class="m-0 text-lg/snug font-semibold text-pretty text-ink group-hover:text-brand-700"><?php echo esc_html( $title ); ?></h3>
      <?php if ( $author ) : ?>
      <p class="mt-1 mb-0 text-sm text-subtle">By <?php echo esc_html( $author ); ?></p>
      <?php endif; ?>
      <?php if ( $excerpt ) : ?>
      <p class="mt-4 mb-0 line-clamp-2 text-sm text-pretty text-muted"><?php echo esc_html( $excerpt ); ?></p>
      <?php endif; ?>
    </div>
  </div>
  <?php if ( $date || $read_time ) : ?>
  <dl class="m-0 mt-6 flex gap-5 border-t border-line pt-4">
    <?php if ( $date ) : ?>
    <div class="m-0 flex items-center gap-2 text-subtle">
      <dt class="m-0 flex"><span class="sr-only">Published on</span><?php ai_e_icon( 'calendar', 5 ); ?></dt>
      <dd class="m-0 text-xs"><?php echo esc_html( $date ); ?></dd>
    </div>
    <?php endif; ?>
    <?php if ( $read_time ) : ?>
    <div class="m-0 flex items-center gap-2 text-subtle">
      <dt class="m-0 flex"><span class="sr-only">Reading time</span><?php ai_e_icon( 'book', 5 ); ?></dt>
      <dd class="m-0 text-xs"><?php echo esc_html( $read_time ); ?></dd>
    </div>
    <?php endif; ?>
  </dl>
  <?php endif; ?>
</a>
<?php else : ?>
<article class="ai-c group flex h-full flex-col overflow-hidden rounded-card border border-line bg-surface shadow-sm transition hover:shadow-md">
  <?php if ( $image ) : ?>
  <a href="<?php echo esc_url( $url ); ?>" tabindex="-1" aria-hidden="true" class="block overflow-hidden">
    <img alt="" src="<?php echo esc_url( $image ); ?>" class="block aspect-16/10 h-auto w-full max-w-full object-cover transition duration-300 group-hover:scale-105" />
  </a>
  <?php endif; ?>
  <div class="flex flex-1 flex-col p-5 sm:p-6">
    <?php if ( $category || $date ) : ?>
    <p class="m-0 flex items-center gap-2 text-xs font-medium text-subtle">
      <?php if ( $category ) : ?><span class="text-brand-700"><?php echo esc_html( $category ); ?></span><?php endif; ?>
      <?php if ( $category && $date ) : ?><span aria-hidden="true">&middot;</span><?php endif; ?>
      <?php if ( $date ) : ?><span><?php echo esc_html( $date ); ?></span><?php endif; ?>
    </p>
    <?php endif; ?>
    <h3 class="mt-2 mb-0 text-lg/snug font-semibold text-ink"><a href="<?php echo esc_url( $url ); ?>" class="text-ink no-underline hover:text-brand-700"><?php echo esc_html( $title ); ?></a></h3>
    <?php if ( $excerpt ) : ?>
    <p class="mt-3 mb-0 line-clamp-3 text-sm/6 text-muted"><?php echo esc_html( $excerpt ); ?></p>
    <?php endif; ?>
    <a href="<?php echo esc_url( $url ); ?>" class="mt-auto inline-flex items-center gap-1 pt-5 text-sm font-semibold text-brand-700 no-underline hover:text-brand-800"><?php echo esc_html( $link_label ); ?><span aria-hidden="true" class="transition group-hover:translate-x-0.5">&rarr;</span></a>
  </div>
</article>
<?php endif; ?>
