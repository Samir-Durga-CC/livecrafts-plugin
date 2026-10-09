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
  <ol class="ai:m-0 ai:flex ai:list-none ai:flex-wrap ai:items-center ai:gap-2 ai:p-0 ai:text-sm">
    <?php foreach ( $items as $i => $item ) : ?>
    <li class="ai:m-0 ai:flex ai:items-center ai:gap-2 ai:p-0">
      <?php if ( $i < $last && ! empty( $item['url'] ) ) : ?>
      <a href="<?php echo esc_url( $item['url'] ); ?>" class="ai:font-medium ai:text-gray-600 ai:no-underline ai:hover:text-brand-700"><?php echo esc_html( $item['label'] ?? '' ); ?></a>
      <span class="ai:text-gray-400"><?php ai_e_icon( 'chevron-right', 4 ); ?></span>
      <?php else : ?>
      <span aria-current="page" class="ai:font-medium ai:text-gray-900"><?php echo esc_html( $item['label'] ?? '' ); ?></span>
      <?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ol>
</nav>
