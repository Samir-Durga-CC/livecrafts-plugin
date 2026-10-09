<?php
/**
 * Component: breadcrumbs
 * Params: items [ label, url ] (last item is the current page)
 * Shortcode: [ai_breadcrumbs][ai_item label="Home" url="/"][ai_item label="Blog" url="/blog"][ai_item label="Post"][/ai_breadcrumbs]
 */
$items = ai_items( $args );
$last  = count( $items ) - 1;
?>
<nav aria-label="Breadcrumb" class="ai-c">
  <ol class="m-0 flex list-none flex-wrap items-center gap-2 p-0 text-sm">
    <?php foreach ( $items as $i => $item ) : ?>
    <li class="m-0 flex items-center gap-2 p-0">
      <?php if ( $i < $last && ! empty( $item['url'] ) ) : ?>
      <a href="<?php echo esc_url( $item['url'] ); ?>" class="font-medium text-gray-600 no-underline hover:text-brand-700"><?php echo esc_html( $item['label'] ?? '' ); ?></a>
      <span class="text-gray-400"><?php ai_e_icon( 'chevron-right', 4 ); ?></span>
      <?php else : ?>
      <span aria-current="page" class="font-medium text-gray-900"><?php echo esc_html( $item['label'] ?? '' ); ?></span>
      <?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ol>
</nav>
