<?php
/**
 * Component: stats (the figures that prove a claim)
 * Params: variant (band|plain), eyebrow, heading, text, items [ value, label ]
 * Shortcode: [ai_stats variant="band" heading=""][ai_item value="99.9%" label="Uptime"]...[/ai_stats]
 *   band  - a deep brand band, for breaking up a long page (default)
 *   plain - the figures on the page's own background, separated by rules: quieter, good above a footer or after a hero
 */
$items = ai_items( $args );
if ( ! $items ) {
	return;
}
$band = 'plain' !== ( $args['variant'] ?? 'band' );
$cols = count( $items ) === 3 ? ai_cls( 'sm:grid-cols-3' ) : ai_cls( 'sm:grid-cols-2 lg:grid-cols-4' );
?>
<section class="<?php echo esc_attr( ai_cls( 'ai-c py-sec-sm sm:py-sec' ) . ' ' . ( $band ? ai_cls( 'bg-brand-900' ) : ai_cls( 'bg-surface' ) ) ); ?>">
  <div class="mx-auto max-w-wide px-4 sm:px-6 lg:px-8">
    <?php ai_component( 'part-head', array_merge( $args, array( 'tone' => $band ? 'surface' : 'ink' ) ) ); ?>
    <dl class="<?php echo esc_attr( ai_cls( 'm-0 mt-12 grid grid-cols-2 gap-x-8 gap-y-12 text-center' ) . ' ' . $cols ); ?>">
      <?php foreach ( $items as $item ) : ?>
      <div class="<?php echo esc_attr( ai_cls( 'm-0 flex flex-col-reverse gap-2' ) . ' ' . ( $band ? '' : ai_cls( 'border-t border-line pt-8' ) ) ); ?>">
        <dt class="<?php echo esc_attr( ai_cls( 'm-0 text-sm font-medium' ) . ' ' . ( $band ? ai_cls( 'text-brand-100' ) : ai_cls( 'text-muted' ) ) ); ?>"><?php echo esc_html( $item['label'] ?? '' ); ?></dt>
        <dd class="<?php echo esc_attr( ai_cls( 'm-0 text-4xl font-semibold tracking-tight sm:text-5xl' ) . ' ' . ( $band ? ai_cls( 'text-white' ) : ai_cls( 'text-ink' ) ) ); ?>"><?php echo esc_html( $item['value'] ?? '' ); ?></dd>
      </div>
      <?php endforeach; ?>
    </dl>
  </div>
</section>
