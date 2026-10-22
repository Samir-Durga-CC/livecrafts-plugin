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

$link   = ai_cls( 'ai:text-sm ai:text-surface/70 ai:no-underline ai:underline-offset-4 ai:transition-colors ai:hover:text-surface ai:hover:underline' );
$head   = ai_cls( 'ai:m-0 ai:text-xs ai:font-semibold ai:tracking-wider ai:text-surface/50 ai:uppercase' );
$bar    = ai_cls( 'ai:mt-14 ai:flex ai:flex-col ai:gap-4 ai:border-t ai:border-surface/10 ai:pt-8 ai:sm:flex-row ai:sm:items-center ai:sm:justify-between' );
$shell  = ai_cls( 'ai:mx-auto ai:max-w-wide ai:px-4 ai:sm:px-6 ai:lg:px-8' );
?>
<footer class="ai-c ai:bg-ink">
  <?php if ( 'cta' === $variant && ( ! empty( $args['cta_heading'] ) || ! empty( $args['primary_label'] ) ) ) : ?>
  <div class="<?php echo esc_attr( $shell ); ?> pt-16">
    <div class="ai:flex ai:flex-col ai:items-start ai:gap-6 ai:rounded-band ai:bg-brand-600 ai:p-8 ai:sm:flex-row ai:sm:items-center ai:sm:justify-between ai:sm:p-12">
      <div class="ai:max-w-xl">
        <h2 class="ai:m-0 ai:text-2xl ai:font-semibold ai:tracking-tight ai:text-balance ai:text-white ai:sm:text-3xl"><?php echo esc_html( $args['cta_heading'] ?? '' ); ?></h2>
        <?php if ( ! empty( $args['cta_text'] ) ) : ?>
        <p class="ai:mt-3 ai:mb-0 ai:text-base ai:text-pretty ai:text-white/80"><?php echo esc_html( $args['cta_text'] ); ?></p>
        <?php endif; ?>
      </div>
      <?php if ( ! empty( $args['primary_label'] ) ) : ?>
      <a href="<?php echo esc_url( $args['primary_url'] ?? '#' ); ?>" class="ai:inline-flex ai:shrink-0 ai:items-center ai:justify-center ai:rounded-ctl ai:bg-white ai:px-6 ai:py-3 ai:text-base ai:font-semibold ai:text-brand-700 ai:shadow-sm ai:transition-transform ai:hover:-translate-y-0.5 ai:focus-visible:outline-2 ai:focus-visible:outline-offset-2 ai:focus-visible:outline-white"><?php echo esc_html( $args['primary_label'] ); ?></a>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if ( 'simple' === $variant ) : ?>
  <div class="<?php echo esc_attr( $shell ); ?> py-14">
    <div class="ai:flex ai:flex-col ai:items-center ai:gap-6 ai:text-center">
      <?php if ( $logo_text ) : ?><p class="ai:m-0 ai:text-lg ai:font-semibold ai:tracking-tight ai:text-surface"><?php echo esc_html( $logo_text ); ?></p><?php endif; ?>
      <?php if ( $text ) : ?><p class="ai:m-0 ai:max-w-md ai:text-sm/6 ai:text-surface/60"><?php echo esc_html( $text ); ?></p><?php endif; ?>
      <?php if ( $groups ) : ?>
      <ul class="ai:m-0 ai:flex ai:list-none ai:flex-wrap ai:items-center ai:justify-center ai:gap-x-8 ai:gap-y-3 ai:p-0">
        <?php foreach ( $groups as $links ) : ?>
			<?php foreach ( $links as $l ) : ?>
        <li class="ai:m-0 ai:p-0"><a href="<?php echo esc_url( $l['url'] ?? '#' ); ?>" class="<?php echo esc_attr( $link ); ?>"><?php echo esc_html( $l['label'] ?? '' ); ?></a></li>
			<?php endforeach; ?>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
      <?php if ( $copyright ) : ?>
      <p class="ai:m-0 ai:border-t ai:border-surface/10 ai:pt-6 ai:text-xs ai:text-surface/50">&copy; <?php echo esc_html( $copyright ); ?></p>
      <?php endif; ?>
    </div>
  </div>

  <?php else : ?>
  <div class="<?php echo esc_attr( $shell ); ?> py-16">
    <div class="ai:grid ai:gap-12 ai:lg:grid-cols-12 ai:lg:gap-8">
      <div class="ai:lg:col-span-4">
        <?php if ( $logo_text ) : ?>
        <p class="ai:m-0 ai:text-xl ai:font-semibold ai:tracking-tight ai:text-surface"><?php echo esc_html( $logo_text ); ?></p>
        <?php endif; ?>
        <?php if ( $text ) : ?>
        <p class="ai:mt-4 ai:mb-0 ai:max-w-sm ai:text-sm/6 ai:text-surface/60"><?php echo esc_html( $text ); ?></p>
        <?php endif; ?>
      </div>
      <div class="ai:grid ai:grid-cols-2 ai:gap-8 ai:sm:grid-cols-3 ai:lg:col-span-8">
        <?php foreach ( $groups as $group => $links ) : ?>
        <div>
          <?php if ( $group ) : ?><h3 class="<?php echo esc_attr( $head ); ?>"><?php echo esc_html( $group ); ?></h3><?php endif; ?>
          <ul class="ai:m-0 ai:mt-5 ai:flex ai:list-none ai:flex-col ai:gap-3 ai:p-0">
            <?php foreach ( $links as $l ) : ?>
            <li class="ai:m-0 ai:p-0"><a href="<?php echo esc_url( $l['url'] ?? '#' ); ?>" class="<?php echo esc_attr( $link ); ?>"><?php echo esc_html( $l['label'] ?? '' ); ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if ( $copyright || $legal ) : ?>
    <div class="<?php echo esc_attr( $bar ); ?>">
      <?php if ( $copyright ) : ?>
      <p class="ai:m-0 ai:text-xs ai:text-surface/50">&copy; <?php echo esc_html( $copyright ); ?></p>
      <?php endif; ?>
      <?php if ( $legal ) : ?>
      <ul class="ai:m-0 ai:flex ai:list-none ai:flex-wrap ai:items-center ai:gap-x-6 ai:gap-y-2 ai:p-0">
        <?php foreach ( $legal as $l ) : ?>
        <li class="ai:m-0 ai:p-0"><a href="<?php echo esc_url( $l['url'] ); ?>" class="ai:text-xs ai:text-surface/50 ai:no-underline ai:underline-offset-4 ai:transition-colors ai:hover:text-surface ai:hover:underline"><?php echo esc_html( $l['label'] ); ?></a></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</footer>
