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
<footer class="ai-c bg-gray-900">
  <div class="mx-auto max-w-wide px-4 py-14 sm:px-6 lg:px-8">
    <div class="grid gap-12 lg:grid-cols-3">
      <div>
        <p class="m-0 text-lg font-bold text-white"><?php echo esc_html( $logo_text ); ?></p>
        <?php if ( $text ) : ?><p class="mt-4 mb-0 max-w-xs text-sm/6 text-gray-400"><?php echo esc_html( $text ); ?></p><?php endif; ?>
      </div>
      <div class="grid grid-cols-2 gap-8 sm:grid-cols-3 lg:col-span-2">
        <?php foreach ( $groups as $group => $links ) : ?>
        <div>
          <h3 class="m-0 text-sm font-semibold text-white"><?php echo esc_html( $group ); ?></h3>
          <ul class="m-0 mt-4 flex list-none flex-col gap-3 p-0">
            <?php foreach ( $links as $l ) : ?>
            <li class="m-0 p-0"><a href="<?php echo esc_url( $l['url'] ?? '#' ); ?>" class="text-sm text-gray-400 no-underline transition-colors hover:text-white"><?php echo esc_html( $l['label'] ?? '' ); ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php if ( $copyright ) : ?>
    <p class="m-0 mt-12 border-t border-gray-800 pt-8 text-sm text-subtle">&copy; <?php echo esc_html( $copyright ); ?></p>
    <?php endif; ?>
  </div>
</footer>
