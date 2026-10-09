<?php
/**
 * Component: footer
 * Params: logo_text, text, copyright, items [ label, url, group ]  (links are grouped into columns by "group")
 * Shortcode: [ai_footer logo_text="Acme" text="" copyright="2026 Acme"][ai_item group="Product" label="Pricing" url="/pricing"]...[/ai_footer]
 */
$logo_text = $args['logo_text'] ?? '';
$text      = $args['text'] ?? '';
$copyright = $args['copyright'] ?? '';
$groups    = array();
foreach ( ai_items( $args ) as $item ) {
	$groups[ $item['group'] ?? '' ][] = $item;
}
?>
<footer class="ai-c ai:bg-gray-900">
  <div class="ai:mx-auto ai:max-w-7xl ai:px-4 ai:py-14 ai:sm:px-6 ai:lg:px-8">
    <div class="ai:grid ai:gap-12 ai:lg:grid-cols-3">
      <div>
        <p class="ai:m-0 ai:text-lg ai:font-bold ai:text-white"><?php echo esc_html( $logo_text ); ?></p>
        <?php if ( $text ) : ?><p class="ai:mt-4 ai:mb-0 ai:max-w-xs ai:text-sm/6 ai:text-gray-400"><?php echo esc_html( $text ); ?></p><?php endif; ?>
      </div>
      <div class="ai:grid ai:grid-cols-2 ai:gap-8 ai:sm:grid-cols-3 ai:lg:col-span-2">
        <?php foreach ( $groups as $group => $links ) : ?>
        <div>
          <h3 class="ai:m-0 ai:text-sm ai:font-semibold ai:text-white"><?php echo esc_html( $group ); ?></h3>
          <ul class="ai:m-0 ai:mt-4 ai:flex ai:list-none ai:flex-col ai:gap-3 ai:p-0">
            <?php foreach ( $links as $l ) : ?>
            <li class="ai:m-0 ai:p-0"><a href="<?php echo esc_url( $l['url'] ?? '#' ); ?>" class="ai:text-sm ai:text-gray-400 ai:no-underline ai:transition-colors ai:hover:text-white"><?php echo esc_html( $l['label'] ?? '' ); ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php if ( $copyright ) : ?>
    <p class="ai:m-0 ai:mt-12 ai:border-t ai:border-gray-800 ai:pt-8 ai:text-sm ai:text-gray-500">&copy; <?php echo esc_html( $copyright ); ?></p>
    <?php endif; ?>
  </div>
</footer>
