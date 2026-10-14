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
$cols = count( $items ) >= 3 ? ai_cls( 'lg:grid-cols-3' ) : ai_cls( 'lg:mx-auto lg:max-w-4xl lg:grid-cols-2' );
?>
<section class="ai-c bg-surface-alt py-sec-sm sm:py-sec">
  <div class="mx-auto max-w-wide px-4 sm:px-6 lg:px-8">
    <?php ai_component( 'part-head', $args ); ?>
    <div class="<?php echo esc_attr( ai_cls( 'mt-14 grid items-stretch gap-8' ) . ' ' . $cols ); ?>">
      <?php
      foreach ( $items as $item ) :
	      $featured = ! empty( $item['featured'] ) && '0' !== (string) $item['featured'];
	      $card     = $featured ? ai_cls( 'bg-ink ring-ink shadow-xl' ) : ai_cls( 'bg-surface ring-line shadow-sm' );
	      $name_c   = $featured ? ai_cls( 'text-surface' ) : ai_cls( 'text-ink' );
	      $muted_c  = $featured ? ai_cls( 'text-surface/70' ) : ai_cls( 'text-muted' );
	      $check_c  = $featured ? ai_cls( 'text-brand-300' ) : ai_cls( 'text-brand-600' );
	      $features = ai_list( $item['features'] ?? array() );
	      ?>
      <div class="<?php echo esc_attr( ai_cls( 'relative flex flex-col rounded-band p-8 ring-1 ring-inset' ) . ' ' . $card ); ?>">
        <?php if ( $featured ) : ?>
        <p class="absolute -top-3 right-8 m-0 rounded-full bg-brand-600 px-3 py-1 text-xs font-semibold text-white"><?php echo esc_html( $item['badge'] ?? 'Most popular' ); ?></p>
        <?php endif; ?>
        <h3 class="<?php echo esc_attr( ai_cls( 'm-0 text-lg font-semibold' ) . ' ' . $name_c ); ?>"><?php echo esc_html( $item['name'] ?? '' ); ?></h3>
        <?php if ( ! empty( $item['description'] ) ) : ?>
        <p class="<?php echo esc_attr( ai_cls( 'mt-3 mb-0 text-sm/6' ) . ' ' . $muted_c ); ?>"><?php echo esc_html( $item['description'] ); ?></p>
        <?php endif; ?>
        <?php if ( ! empty( $item['price'] ) ) : ?>
        <p class="mt-6 mb-0 flex items-baseline gap-1">
          <span class="<?php echo esc_attr( ai_cls( 'text-5xl font-semibold tracking-tight' ) . ' ' . $name_c ); ?>"><?php echo esc_html( $item['price'] ); ?></span>
          <?php if ( ! empty( $item['period'] ) ) : ?><span class="<?php echo esc_attr( ai_cls( 'text-sm' ) . ' ' . $muted_c ); ?>"><?php echo esc_html( $item['period'] ); ?></span><?php endif; ?>
        </p>
        <?php endif; ?>
        <?php if ( $features ) : ?>
        <ul class="<?php echo esc_attr( ai_cls( 'm-0 mt-8 mb-8 flex list-none flex-col gap-3 p-0 text-sm/6' ) . ' ' . $muted_c ); ?>">
          <?php foreach ( $features as $feature ) : ?>
          <li class="m-0 flex gap-3 p-0">
            <span class="<?php echo esc_attr( ai_cls( 'mt-0.5 shrink-0' ) . ' ' . $check_c ); ?>" aria-hidden="true"><?php ai_e_icon( 'check', 5 ); ?></span>
            <?php echo esc_html( $feature ); ?>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
        <?php if ( ! empty( $item['label'] ) ) : ?>
        <div class="mt-auto flex">
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
    <p class="m-0 mt-8 text-center text-sm text-subtle"><?php echo esc_html( $args['note'] ); ?></p>
    <?php endif; ?>
  </div>
</section>
