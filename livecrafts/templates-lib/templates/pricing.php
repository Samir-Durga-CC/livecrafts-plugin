<?php
/**
 * Component: pricing
 * Params: heading, text, items [ name, price, period, description, features ("a|b|c" or array), label, url, featured (1/0) ]
 * Shortcode: [ai_pricing heading=""][ai_item name="Pro" price="$29" period="/month" description="" features="A|B|C" label="Choose Pro" url="/buy" featured="1"]...[/ai_pricing]
 */
$heading = $args['heading'] ?? '';
$text    = $args['text'] ?? '';
$items   = ai_items( $args );
$cols    = count( $items ) >= 3 ? ai_cls( 'ai:lg:grid-cols-3' ) : ai_cls( 'ai:lg:grid-cols-2 ai:lg:max-w-4xl ai:lg:mx-auto' );
?>
<section class="ai-c ai:bg-gray-50 ai:py-16 ai:sm:py-24">
  <div class="ai:mx-auto ai:max-w-7xl ai:px-4 ai:sm:px-6 ai:lg:px-8">
    <?php if ( $heading ) : ?>
    <div class="ai:mx-auto ai:max-w-2xl ai:text-center">
      <h2 class="ai:m-0 ai:text-3xl ai:font-semibold ai:tracking-tight ai:text-balance ai:text-gray-900 ai:sm:text-4xl"><?php echo esc_html( $heading ); ?></h2>
      <?php if ( $text ) : ?><p class="ai:mt-4 ai:mb-0 ai:text-lg ai:text-pretty ai:text-gray-600"><?php echo esc_html( $text ); ?></p><?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="<?php echo esc_attr( ai_cls( 'ai:mt-14 ai:grid ai:items-stretch ai:gap-8' ) . ' ' . $cols ); ?>">
      <?php
      foreach ( $items as $item ) :
	      $featured = ! empty( $item['featured'] ) && '0' !== (string) $item['featured'];
	      $card     = $featured ? ai_cls( 'ai:bg-gray-900 ai:ring-gray-900 ai:shadow-xl' ) : ai_cls( 'ai:bg-white ai:ring-gray-200 ai:shadow-sm' );
	      $name_c   = $featured ? ai_cls( 'ai:text-white' ) : ai_cls( 'ai:text-gray-900' );
	      $price_c  = $featured ? ai_cls( 'ai:text-white' ) : ai_cls( 'ai:text-gray-900' );
	      $muted_c  = $featured ? ai_cls( 'ai:text-gray-300' ) : ai_cls( 'ai:text-gray-600' );
	      $check_c  = $featured ? ai_cls( 'ai:text-brand-300' ) : ai_cls( 'ai:text-brand-600' );
	      ?>
      <div class="<?php echo esc_attr( ai_cls( 'ai:relative ai:flex ai:flex-col ai:rounded-3xl ai:p-8 ai:ring-1 ai:ring-inset' ) . ' ' . $card ); ?>">
        <?php if ( $featured ) : ?>
        <p class="ai:absolute ai:-top-3 ai:right-8 ai:m-0 ai:rounded-full ai:bg-brand-600 ai:px-3 ai:py-1 ai:text-xs ai:font-semibold ai:text-white">Most popular</p>
        <?php endif; ?>
        <h3 class="<?php echo esc_attr( ai_cls( 'ai:m-0 ai:text-lg ai:font-semibold' ) . ' ' . $name_c ); ?>"><?php echo esc_html( $item['name'] ?? '' ); ?></h3>
        <?php if ( ! empty( $item['description'] ) ) : ?>
        <p class="<?php echo esc_attr( ai_cls( 'ai:mt-3 ai:mb-0 ai:text-sm/6' ) . ' ' . $muted_c ); ?>"><?php echo esc_html( $item['description'] ); ?></p>
        <?php endif; ?>
        <p class="ai:mt-6 ai:mb-0 ai:flex ai:items-baseline ai:gap-1">
          <span class="<?php echo esc_attr( ai_cls( 'ai:text-5xl ai:font-semibold ai:tracking-tight' ) . ' ' . $price_c ); ?>"><?php echo esc_html( $item['price'] ?? '' ); ?></span>
          <?php if ( ! empty( $item['period'] ) ) : ?><span class="<?php echo esc_attr( ai_cls( 'ai:text-sm' ) . ' ' . $muted_c ); ?>"><?php echo esc_html( $item['period'] ); ?></span><?php endif; ?>
        </p>
        <ul class="<?php echo esc_attr( ai_cls( 'ai:m-0 ai:mt-8 ai:mb-8 ai:flex ai:list-none ai:flex-col ai:gap-3 ai:p-0 ai:text-sm/6' ) . ' ' . $muted_c ); ?>">
          <?php foreach ( ai_list( $item['features'] ?? array() ) as $feature ) : ?>
          <li class="ai:m-0 ai:flex ai:gap-3 ai:p-0"><span class="<?php echo esc_attr( ai_cls( 'ai:mt-0.5' ) . ' ' . $check_c ); ?>"><?php ai_e_icon( 'check', 5 ); ?></span><?php echo esc_html( $feature ); ?></li>
          <?php endforeach; ?>
        </ul>
        <?php if ( ! empty( $item['label'] ) ) : ?>
        <div class="ai:mt-auto ai:flex">
          <?php ai_component( 'button', array( 'label' => $item['label'], 'url' => $item['url'] ?? '#', 'variant' => $featured ? 'solid' : 'outline', 'icon' => 'none', 'size' => 'lg' ) ); ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
