<?php
/**
 * Component: pricing
 * Params: heading, text, eyebrow, note, items [ name, price, period, description, features ("a|b|c" or array),
 *         label, url, featured (1/0), badge ]
 * Shortcode: [ai_pricing heading=""][ai_item name="Pro" price="$29" period="/month" description="" features="A|B|C" label="Choose Pro" url="/buy" featured="1"]...[/ai_pricing]
 *
 * The featured plan uses the site's INK colour, not a fixed grey, so it follows the site like everything else.
 */
$items = ai_items( $args );
if ( ! $items ) {
	return; // a pricing section with no plans is an empty band: show nothing instead
}
$cols = count( $items ) >= 3 ? ai_cls( 'ai:lg:grid-cols-3' ) : ai_cls( 'ai:lg:mx-auto ai:lg:max-w-4xl ai:lg:grid-cols-2' );
?>
<section class="ai-c ai:bg-surface-alt ai:py-sec-sm ai:sm:py-sec">
  <div class="ai:mx-auto ai:max-w-wide ai:px-4 ai:sm:px-6 ai:lg:px-8">
    <?php ai_component( 'part-head', $args ); ?>
    <div class="<?php echo esc_attr( ai_cls( 'ai:mt-14 ai:grid ai:items-stretch ai:gap-8' ) . ' ' . $cols ); ?>">
      <?php
      foreach ( $items as $item ) :
	      $featured = ! empty( $item['featured'] ) && '0' !== (string) $item['featured'];
	      $card     = $featured ? ai_cls( 'ai:bg-ink ai:ring-ink ai:shadow-xl' ) : ai_cls( 'ai:bg-surface ai:ring-line ai:shadow-sm' );
	      $name_c   = $featured ? ai_cls( 'ai:text-surface' ) : ai_cls( 'ai:text-ink' );
	      $muted_c  = $featured ? ai_cls( 'ai:text-surface/70' ) : ai_cls( 'ai:text-muted' );
	      $check_c  = $featured ? ai_cls( 'ai:text-brand-300' ) : ai_cls( 'ai:text-brand-600' );
	      $features = ai_list( $item['features'] ?? array() );
	      ?>
      <div class="<?php echo esc_attr( ai_cls( 'ai:relative ai:flex ai:flex-col ai:rounded-band ai:p-8 ai:ring-1 ai:ring-inset' ) . ' ' . $card ); ?>">
        <?php if ( $featured ) : ?>
        <p class="ai:absolute ai:-top-3 ai:right-8 ai:m-0 ai:rounded-full ai:bg-brand-600 ai:px-3 ai:py-1 ai:text-xs ai:font-semibold ai:text-white"><?php echo esc_html( $item['badge'] ?? 'Most popular' ); ?></p>
        <?php endif; ?>
        <h3 class="<?php echo esc_attr( ai_cls( 'ai:m-0 ai:text-lg ai:font-semibold' ) . ' ' . $name_c ); ?>"><?php echo esc_html( $item['name'] ?? '' ); ?></h3>
        <?php if ( ! empty( $item['description'] ) ) : ?>
        <p class="<?php echo esc_attr( ai_cls( 'ai:mt-3 ai:mb-0 ai:text-sm/6' ) . ' ' . $muted_c ); ?>"><?php echo esc_html( $item['description'] ); ?></p>
        <?php endif; ?>
        <?php if ( ! empty( $item['price'] ) ) : ?>
        <p class="ai:mt-6 ai:mb-0 ai:flex ai:items-baseline ai:gap-1">
          <span class="<?php echo esc_attr( ai_cls( 'ai:text-5xl ai:font-semibold ai:tracking-tight' ) . ' ' . $name_c ); ?>"><?php echo esc_html( $item['price'] ); ?></span>
          <?php if ( ! empty( $item['period'] ) ) : ?><span class="<?php echo esc_attr( ai_cls( 'ai:text-sm' ) . ' ' . $muted_c ); ?>"><?php echo esc_html( $item['period'] ); ?></span><?php endif; ?>
        </p>
        <?php endif; ?>
        <?php if ( $features ) : ?>
        <ul class="<?php echo esc_attr( ai_cls( 'ai:m-0 ai:mt-8 ai:mb-8 ai:flex ai:list-none ai:flex-col ai:gap-3 ai:p-0 ai:text-sm/6' ) . ' ' . $muted_c ); ?>">
          <?php foreach ( $features as $feature ) : ?>
          <li class="ai:m-0 ai:flex ai:gap-3 ai:p-0">
            <span class="<?php echo esc_attr( ai_cls( 'ai:mt-0.5 ai:shrink-0' ) . ' ' . $check_c ); ?>" aria-hidden="true"><?php ai_e_icon( 'check', 5 ); ?></span>
            <?php echo esc_html( $feature ); ?>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
        <?php if ( ! empty( $item['label'] ) ) : ?>
        <div class="ai:mt-auto ai:flex">
          <?php
          ai_component( 'button', array(
	          'label'   => $item['label'],
	          'url'     => $item['url'] ?? '#',
	          'variant' => $featured ? 'light' : 'outline',
	          'icon'    => 'none',
	          'size'    => 'lg',
          ) );
          ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php if ( ! empty( $args['note'] ) ) : ?>
    <p class="ai:m-0 ai:mt-8 ai:text-center ai:text-sm ai:text-subtle"><?php echo esc_html( $args['note'] ); ?></p>
    <?php endif; ?>
  </div>
</section>
