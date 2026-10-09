<?php
/**
 * Component: pagination
 * Params: current, total, url_pattern (use %d for the page number, default "?paged=%d")
 * Shortcode: [ai_pagination current="2" total="8" url_pattern="/blog/page/%d/"]
 */
$total   = max( 1, (int) ( $args['total'] ?? 1 ) );
$current = min( $total, max( 1, (int) ( $args['current'] ?? 1 ) ) );
$pattern = $args['url_pattern'] ?? '?paged=%d';
$link    = function ( $n ) use ( $pattern ) {
	return sprintf( $pattern, $n );
};
$pages = array_unique( array_filter( array( 1, $current - 1, $current, $current + 1, $total ), function ( $n ) use ( $total ) {
	return $n >= 1 && $n <= $total;
} ) );
sort( $pages );
$base = ai_cls( 'inline-flex size-10 items-center justify-center rounded-lg text-sm font-medium no-underline transition-colors' );
?>
<nav aria-label="Pagination" class="ai-c">
  <ul class="m-0 flex list-none flex-wrap items-center justify-center gap-1 p-0">
    <?php if ( $current > 1 ) : ?>
    <li class="m-0 p-0"><a href="<?php echo esc_url( $link( $current - 1 ) ); ?>" aria-label="Previous page" class="<?php echo esc_attr( $base . ' ' . ai_cls( 'text-muted hover:bg-gray-100' ) ); ?>"><?php ai_e_icon( 'chevron-left', 5 ); ?></a></li>
    <?php endif; ?>
    <?php
    $prev = 0;
    foreach ( $pages as $n ) :
	    if ( $n - $prev > 1 ) :
		    ?>
    <li class="m-0 p-0"><span class="inline-flex size-10 items-center justify-center text-sm text-subtle">&hellip;</span></li>
	    <?php endif; ?>
    <li class="m-0 p-0">
      <?php if ( $n === $current ) : ?>
      <span aria-current="page" class="<?php echo esc_attr( $base . ' ' . ai_cls( 'bg-brand-600 text-white' ) ); ?>"><?php echo esc_html( $n ); ?></span>
      <?php else : ?>
      <a href="<?php echo esc_url( $link( $n ) ); ?>" class="<?php echo esc_attr( $base . ' ' . ai_cls( 'text-muted hover:bg-gray-100' ) ); ?>"><?php echo esc_html( $n ); ?></a>
      <?php endif; ?>
    </li>
	    <?php
	    $prev = $n;
    endforeach;
    ?>
    <?php if ( $current < $total ) : ?>
    <li class="m-0 p-0"><a href="<?php echo esc_url( $link( $current + 1 ) ); ?>" aria-label="Next page" class="<?php echo esc_attr( $base . ' ' . ai_cls( 'text-muted hover:bg-gray-100' ) ); ?>"><?php ai_e_icon( 'chevron-right', 5 ); ?></a></li>
    <?php endif; ?>
  </ul>
</nav>
