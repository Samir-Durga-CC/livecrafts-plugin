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
<a href="<?php echo esc_url( $url ); ?>" class="ai-c ai:group ai:block ai:rounded-2xl ai:border ai:border-gray-200 ai:bg-white ai:p-5 ai:no-underline ai:shadow-sm ai:transition ai:hover:border-brand-200 ai:hover:shadow-md ai:sm:p-6">
  <div class="ai:sm:flex ai:sm:justify-between ai:sm:gap-4 ai:lg:gap-6">
    <?php if ( $image ) : ?>
    <div class="ai:sm:order-last ai:sm:shrink-0">
      <img alt="" src="<?php echo esc_url( $image ); ?>" class="ai:block ai:size-16 ai:max-w-full ai:rounded-full ai:object-cover ai:ring-2 ai:ring-white ai:sm:size-20" />
    </div>
    <?php endif; ?>
    <div class="ai:mt-4 ai:sm:mt-0">
      <h3 class="ai:m-0 ai:text-lg/snug ai:font-semibold ai:text-pretty ai:text-gray-900 ai:group-hover:text-brand-700"><?php echo esc_html( $title ); ?></h3>
      <?php if ( $author ) : ?>
      <p class="ai:mt-1 ai:mb-0 ai:text-sm ai:text-gray-500">By <?php echo esc_html( $author ); ?></p>
      <?php endif; ?>
      <?php if ( $excerpt ) : ?>
      <p class="ai:mt-4 ai:mb-0 ai:line-clamp-2 ai:text-sm ai:text-pretty ai:text-gray-600"><?php echo esc_html( $excerpt ); ?></p>
      <?php endif; ?>
    </div>
  </div>
  <?php if ( $date || $read_time ) : ?>
  <dl class="ai:m-0 ai:mt-6 ai:flex ai:gap-5 ai:border-t ai:border-gray-100 ai:pt-4">
    <?php if ( $date ) : ?>
    <div class="ai:m-0 ai:flex ai:items-center ai:gap-2 ai:text-gray-500">
      <dt class="ai:m-0 ai:flex"><span class="ai:sr-only">Published on</span><?php ai_e_icon( 'calendar', 5 ); ?></dt>
      <dd class="ai:m-0 ai:text-xs"><?php echo esc_html( $date ); ?></dd>
    </div>
    <?php endif; ?>
    <?php if ( $read_time ) : ?>
    <div class="ai:m-0 ai:flex ai:items-center ai:gap-2 ai:text-gray-500">
      <dt class="ai:m-0 ai:flex"><span class="ai:sr-only">Reading time</span><?php ai_e_icon( 'book', 5 ); ?></dt>
      <dd class="ai:m-0 ai:text-xs"><?php echo esc_html( $read_time ); ?></dd>
    </div>
    <?php endif; ?>
  </dl>
  <?php endif; ?>
</a>
<?php else : ?>
<article class="ai-c ai:group ai:flex ai:h-full ai:flex-col ai:overflow-hidden ai:rounded-2xl ai:border ai:border-gray-200 ai:bg-white ai:shadow-sm ai:transition ai:hover:shadow-md">
  <?php if ( $image ) : ?>
  <a href="<?php echo esc_url( $url ); ?>" tabindex="-1" aria-hidden="true" class="ai:block ai:overflow-hidden">
    <img alt="" src="<?php echo esc_url( $image ); ?>" class="ai:block ai:aspect-16/10 ai:h-auto ai:w-full ai:max-w-full ai:object-cover ai:transition ai:duration-300 ai:group-hover:scale-105" />
  </a>
  <?php endif; ?>
  <div class="ai:flex ai:flex-1 ai:flex-col ai:p-5 ai:sm:p-6">
    <?php if ( $category || $date ) : ?>
    <p class="ai:m-0 ai:flex ai:items-center ai:gap-2 ai:text-xs ai:font-medium ai:text-gray-500">
      <?php if ( $category ) : ?><span class="ai:text-brand-700"><?php echo esc_html( $category ); ?></span><?php endif; ?>
      <?php if ( $category && $date ) : ?><span aria-hidden="true">&middot;</span><?php endif; ?>
      <?php if ( $date ) : ?><span><?php echo esc_html( $date ); ?></span><?php endif; ?>
    </p>
    <?php endif; ?>
    <h3 class="ai:mt-2 ai:mb-0 ai:text-lg/snug ai:font-semibold ai:text-gray-900"><a href="<?php echo esc_url( $url ); ?>" class="ai:text-gray-900 ai:no-underline ai:hover:text-brand-700"><?php echo esc_html( $title ); ?></a></h3>
    <?php if ( $excerpt ) : ?>
    <p class="ai:mt-3 ai:mb-0 ai:line-clamp-3 ai:text-sm/6 ai:text-gray-600"><?php echo esc_html( $excerpt ); ?></p>
    <?php endif; ?>
    <a href="<?php echo esc_url( $url ); ?>" class="ai:mt-auto ai:inline-flex ai:items-center ai:gap-1 ai:pt-5 ai:text-sm ai:font-semibold ai:text-brand-700 ai:no-underline ai:hover:text-brand-800"><?php echo esc_html( $link_label ); ?><span aria-hidden="true" class="ai:transition ai:group-hover:translate-x-0.5">&rarr;</span></a>
  </div>
</article>
<?php endif; ?>
