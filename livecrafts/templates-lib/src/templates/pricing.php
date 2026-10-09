<?php
/**
 * Component: pricing
 * Params: heading, text, items [ name, price, period, description, features ("a|b|c" or array), label, url, featured (1/0) ]
 * Shortcode: [ai_pricing heading=""][ai_item name="Pro" price="$29" period="/month" description="" features="A|B|C" label="Choose Pro" url="/buy" featured="1"]...[/ai_pricing]
 */
$heading = $args['heading'] ?? '';
$text    = $args['text'] ?? '';
$items   = ai_items( $args );
$cols    = count( $items ) >= 3 ? ai_cls( 'lg:grid-cols-3' ) : ai_cls( 'lg:grid-cols-2 lg:max-w-4xl lg:mx-auto' );
?>
<section class="ai-c bg-gray-50 py-16 sm:py-24">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <?php if ( $heading ) : ?>
    <div class="mx-auto max-w-2xl text-center">
      <h2 class="m-0 text-3xl font-semibold tracking-tight text-balance text-gray-900 sm:text-4xl"><?php echo esc_html( $heading ); ?></h2>
      <?php if ( $text ) : ?><p class="mt-4 mb-0 text-lg text-pretty text-gray-600"><?php echo esc_html( $text ); ?></p><?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="<?php echo esc_attr( ai_cls( 'mt-14 grid items-stretch gap-8' ) . ' ' . $cols ); ?>">
      <?php
      foreach ( $items as $item ) :
	      $featured = ! empty( $item['featured'] ) && '0' !== (string) $item['featured'];
	      $card     = $featured ? ai_cls( 'bg-gray-900 ring-gray-900 shadow-xl' ) : ai_cls( 'bg-white ring-gray-200 shadow-sm' );
	      $name_c   = $featured ? ai_cls( 'text-white' ) : ai_cls( 'text-gray-900' );
	      $price_c  = $featured ? ai_cls( 'text-white' ) : ai_cls( 'text-gray-900' );
	      $muted_c  = $featured ? ai_cls( 'text-gray-300' ) : ai_cls( 'text-gray-600' );
	      $check_c  = $featured ? ai_cls( 'text-brand-300' ) : ai_cls( 'text-brand-600' );
	      ?>
      <div class="<?php echo esc_attr( ai_cls( 'relative flex flex-col rounded-3xl p-8 ring-1 ring-inset' ) . ' ' . $card ); ?>">
        <?php if ( $featured ) : ?>
        <p class="absolute -top-3 right-8 m-0 rounded-full bg-brand-600 px-3 py-1 text-xs font-semibold text-white">Most popular</p>
        <?php endif; ?>
        <h3 class="<?php echo esc_attr( ai_cls( 'm-0 text-lg font-semibold' ) . ' ' . $name_c ); ?>"><?php echo esc_html( $item['name'] ?? '' ); ?></h3>
        <?php if ( ! empty( $item['description'] ) ) : ?>
        <p class="<?php echo esc_attr( ai_cls( 'mt-3 mb-0 text-sm/6' ) . ' ' . $muted_c ); ?>"><?php echo esc_html( $item['description'] ); ?></p>
        <?php endif; ?>
        <p class="mt-6 mb-0 flex items-baseline gap-1">
          <span class="<?php echo esc_attr( ai_cls( 'text-5xl font-semibold tracking-tight' ) . ' ' . $price_c ); ?>"><?php echo esc_html( $item['price'] ?? '' ); ?></span>
          <?php if ( ! empty( $item['period'] ) ) : ?><span class="<?php echo esc_attr( ai_cls( 'text-sm' ) . ' ' . $muted_c ); ?>"><?php echo esc_html( $item['period'] ); ?></span><?php endif; ?>
        </p>
        <ul class="<?php echo esc_attr( ai_cls( 'm-0 mt-8 mb-8 flex list-none flex-col gap-3 p-0 text-sm/6' ) . ' ' . $muted_c ); ?>">
          <?php foreach ( ai_list( $item['features'] ?? array() ) as $feature ) : ?>
          <li class="m-0 flex gap-3 p-0"><span class="<?php echo esc_attr( ai_cls( 'mt-0.5' ) . ' ' . $check_c ); ?>"><?php ai_e_icon( 'check', 5 ); ?></span><?php echo esc_html( $feature ); ?></li>
          <?php endforeach; ?>
        </ul>
        <?php if ( ! empty( $item['label'] ) ) : ?>
        <div class="mt-auto flex">
          <?php ai_component( 'button', array( 'label' => $item['label'], 'url' => $item['url'] ?? '#', 'variant' => $featured ? 'solid' : 'outline', 'icon' => 'none', 'size' => 'lg' ) ); ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
