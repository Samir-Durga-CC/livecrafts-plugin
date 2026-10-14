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
$cols = count( $items ) === 3 ? ai_cls( 'ai:sm:grid-cols-3' ) : ai_cls( 'ai:sm:grid-cols-2 ai:lg:grid-cols-4' );
?>
<section class="<?php echo esc_attr( ai_cls( 'ai-c ai:py-sec-sm ai:sm:py-sec' ) . ' ' . ( $band ? ai_cls( 'ai:bg-brand-900' ) : ai_cls( 'ai:bg-surface' ) ) ); ?>">
  <div class="ai:mx-auto ai:max-w-wide ai:px-4 ai:sm:px-6 ai:lg:px-8">
    <?php ai_component( 'part-head', array_merge( $args, array( 'tone' => $band ? 'surface' : 'ink' ) ) ); ?>
    <dl class="<?php echo esc_attr( ai_cls( 'ai:m-0 ai:mt-12 ai:grid ai:grid-cols-2 ai:gap-x-8 ai:gap-y-12 ai:text-center' ) . ' ' . $cols ); ?>">
      <?php foreach ( $items as $item ) : ?>
      <div class="<?php echo esc_attr( ai_cls( 'ai:m-0 ai:flex ai:flex-col-reverse ai:gap-2' ) . ' ' . ( $band ? '' : ai_cls( 'ai:border-t ai:border-line ai:pt-8' ) ) ); ?>">
        <dt class="<?php echo esc_attr( ai_cls( 'ai:m-0 ai:text-sm ai:font-medium' ) . ' ' . ( $band ? ai_cls( 'ai:text-brand-100' ) : ai_cls( 'ai:text-muted' ) ) ); ?>"><?php echo esc_html( $item['label'] ?? '' ); ?></dt>
        <dd class="<?php echo esc_attr( ai_cls( 'ai:m-0 ai:text-4xl ai:font-semibold ai:tracking-tight ai:sm:text-5xl' ) . ' ' . ( $band ? ai_cls( 'ai:text-white' ) : ai_cls( 'ai:text-ink' ) ) ); ?>"><?php echo esc_html( $item['value'] ?? '' ); ?></dd>
      </div>
      <?php endforeach; ?>
    </dl>
  </div>
</section>
