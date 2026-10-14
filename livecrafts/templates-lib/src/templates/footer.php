<?php
/**
 * Component: footer
 * Params: variant (columns|simple|cta), logo_text, text, copyright, legal, cta_heading, cta_text,
 *         primary_label, primary_url, items [ label, url, group ]  (links are grouped into columns by "group")
 * Shortcode: [ai_footer variant="columns" logo_text="Acme" text="" copyright="2026 Acme"][ai_item group="Product" label="Pricing" url="/pricing"]...[/ai_footer]
 *   columns - the brand and up to four groups of links, with a bottom bar (default)
 *   simple  - one centred row: name, links, copyright
 *   cta     - a call-to-action band above the columns, for the last thing a visitor sees
 *
 * "legal" is a comma-separated list of "Label|/url" pairs for the bottom bar (privacy, terms).
 */
$variant   = in_array( $args['variant'] ?? 'columns', array( 'columns', 'simple', 'cta' ), true ) ? $args['variant'] : 'columns';
$logo_text = $args['logo_text'] ?? '';
$text      = $args['text'] ?? '';
$copyright = $args['copyright'] ?? '';
$groups    = array();
foreach ( ai_items( $args ) as $item ) {
	$groups[ $item['group'] ?? '' ][] = $item;
}

$legal = array();
foreach ( array_filter( array_map( 'trim', explode( ',', (string) ( $args['legal'] ?? '' ) ) ) ) as $pair ) {
	$parts   = array_map( 'trim', explode( '|', $pair, 2 ) );
	$legal[] = array( 'label' => $parts[0], 'url' => isset( $parts[1] ) ? $parts[1] : '#' );
}

$link   = ai_cls( 'text-sm text-surface/70 no-underline underline-offset-4 transition-colors hover:text-surface hover:underline' );
$head   = ai_cls( 'm-0 text-xs font-semibold tracking-wider text-surface/50 uppercase' );
$bar    = ai_cls( 'mt-14 flex flex-col gap-4 border-t border-surface/10 pt-8 sm:flex-row sm:items-center sm:justify-between' );
$shell  = ai_cls( 'mx-auto max-w-wide px-4 sm:px-6 lg:px-8' );
?>
<footer class="ai-c bg-ink">
  <?php if ( 'cta' === $variant && ( ! empty( $args['cta_heading'] ) || ! empty( $args['primary_label'] ) ) ) : ?>
  <div class="<?php echo esc_attr( $shell ); ?> pt-16">
    <div class="flex flex-col items-start gap-6 rounded-band bg-brand-600 p-8 sm:flex-row sm:items-center sm:justify-between sm:p-12">
      <div class="max-w-xl">
        <h2 class="m-0 text-2xl font-semibold tracking-tight text-balance text-white sm:text-3xl"><?php echo esc_html( $args['cta_heading'] ?? '' ); ?></h2>
        <?php if ( ! empty( $args['cta_text'] ) ) : ?>
        <p class="mt-3 mb-0 text-base text-pretty text-white/80"><?php echo esc_html( $args['cta_text'] ); ?></p>
        <?php endif; ?>
      </div>
      <?php if ( ! empty( $args['primary_label'] ) ) : ?>
      <a href="<?php echo esc_url( $args['primary_url'] ?? '#' ); ?>" class="inline-flex shrink-0 items-center justify-center rounded-ctl bg-white px-6 py-3 text-base font-semibold text-brand-700 shadow-sm transition-transform hover:-translate-y-0.5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"><?php echo esc_html( $args['primary_label'] ); ?></a>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if ( 'simple' === $variant ) : ?>
  <div class="<?php echo esc_attr( $shell ); ?> py-14">
    <div class="flex flex-col items-center gap-6 text-center">
      <?php if ( $logo_text ) : ?><p class="m-0 text-lg font-semibold tracking-tight text-surface"><?php echo esc_html( $logo_text ); ?></p><?php endif; ?>
      <?php if ( $text ) : ?><p class="m-0 max-w-md text-sm/6 text-surface/60"><?php echo esc_html( $text ); ?></p><?php endif; ?>
      <?php if ( $groups ) : ?>
      <ul class="m-0 flex list-none flex-wrap items-center justify-center gap-x-8 gap-y-3 p-0">
        <?php foreach ( $groups as $links ) : ?>
			<?php foreach ( $links as $l ) : ?>
        <li class="m-0 p-0"><a href="<?php echo esc_url( $l['url'] ?? '#' ); ?>" class="<?php echo esc_attr( $link ); ?>"><?php echo esc_html( $l['label'] ?? '' ); ?></a></li>
			<?php endforeach; ?>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
      <?php if ( $copyright ) : ?>
      <p class="m-0 border-t border-surface/10 pt-6 text-xs text-surface/50">&copy; <?php echo esc_html( $copyright ); ?></p>
      <?php endif; ?>
    </div>
  </div>

  <?php else : ?>
  <div class="<?php echo esc_attr( $shell ); ?> py-16">
    <div class="grid gap-12 lg:grid-cols-12 lg:gap-8">
      <div class="lg:col-span-4">
        <?php if ( $logo_text ) : ?>
        <p class="m-0 text-xl font-semibold tracking-tight text-surface"><?php echo esc_html( $logo_text ); ?></p>
        <?php endif; ?>
        <?php if ( $text ) : ?>
        <p class="mt-4 mb-0 max-w-sm text-sm/6 text-surface/60"><?php echo esc_html( $text ); ?></p>
        <?php endif; ?>
      </div>
      <div class="grid grid-cols-2 gap-8 sm:grid-cols-3 lg:col-span-8">
        <?php foreach ( $groups as $group => $links ) : ?>
        <div>
          <?php if ( $group ) : ?><h3 class="<?php echo esc_attr( $head ); ?>"><?php echo esc_html( $group ); ?></h3><?php endif; ?>
          <ul class="m-0 mt-5 flex list-none flex-col gap-3 p-0">
            <?php foreach ( $links as $l ) : ?>
            <li class="m-0 p-0"><a href="<?php echo esc_url( $l['url'] ?? '#' ); ?>" class="<?php echo esc_attr( $link ); ?>"><?php echo esc_html( $l['label'] ?? '' ); ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if ( $copyright || $legal ) : ?>
    <div class="<?php echo esc_attr( $bar ); ?>">
      <?php if ( $copyright ) : ?>
      <p class="m-0 text-xs text-surface/50">&copy; <?php echo esc_html( $copyright ); ?></p>
      <?php endif; ?>
      <?php if ( $legal ) : ?>
      <ul class="m-0 flex list-none flex-wrap items-center gap-x-6 gap-y-2 p-0">
        <?php foreach ( $legal as $l ) : ?>
        <li class="m-0 p-0"><a href="<?php echo esc_url( $l['url'] ); ?>" class="text-xs text-surface/50 no-underline underline-offset-4 transition-colors hover:text-surface hover:underline"><?php echo esc_html( $l['label'] ); ?></a></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</footer>
