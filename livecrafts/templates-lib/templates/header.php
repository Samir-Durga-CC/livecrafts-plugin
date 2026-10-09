<?php
/**
 * Component: header (navbar with CSS-only mobile menu)
 * Params: logo_text, logo_image, home_url, items [ label, url, current ], cta_label, cta_url
 * Shortcode: [ai_header logo_text="Acme" cta_label="Sign up" cta_url="/signup"][ai_item label="Home" url="/" current="1"]...[/ai_header]
 */
$logo_text  = $args['logo_text'] ?? '';
$logo_image = $args['logo_image'] ?? '';
$home_url   = $args['home_url'] ?? '/';
$items      = ai_items( $args );
$cta_label  = $args['cta_label'] ?? '';
$cta_url    = $args['cta_url'] ?? '#';
$link       = ai_cls( 'ai:text-sm ai:font-medium ai:no-underline ai:transition-colors' );
$on         = ai_cls( 'ai:text-brand-700' );
$off        = ai_cls( 'ai:text-gray-700 ai:hover:text-brand-700' );
?>
<header class="ai-c ai:relative ai:z-30 ai:border-b ai:border-gray-200 ai:bg-white">
  <div class="ai:mx-auto ai:flex ai:max-w-7xl ai:items-center ai:justify-between ai:gap-6 ai:px-4 ai:py-4 ai:sm:px-6 ai:lg:px-8">
    <a href="<?php echo esc_url( $home_url ); ?>" class="ai:flex ai:items-center ai:gap-2 ai:text-lg ai:font-bold ai:text-gray-900 ai:no-underline">
      <?php if ( $logo_image ) : ?>
      <img alt="" src="<?php echo esc_url( $logo_image ); ?>" class="ai:block ai:h-8 ai:w-auto ai:max-w-full" />
      <?php endif; ?>
      <span><?php echo esc_html( $logo_text ); ?></span>
    </a>

    <nav aria-label="Main" class="ai:hidden ai:items-center ai:gap-8 ai:md:flex">
      <?php foreach ( $items as $item ) : ?>
      <a href="<?php echo esc_url( $item['url'] ?? '#' ); ?>" class="<?php echo esc_attr( $link . ' ' . ( ! empty( $item['current'] ) ? $on : $off ) ); ?>"<?php echo ! empty( $item['current'] ) ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $item['label'] ?? '' ); ?></a>
      <?php endforeach; ?>
    </nav>

    <?php if ( $cta_label ) : ?>
    <div class="ai:hidden ai:md:block"><?php ai_component( 'button', array( 'label' => $cta_label, 'url' => $cta_url, 'icon' => 'none' ) ); ?></div>
    <?php endif; ?>

    <details class="ai:relative ai:m-0 ai:md:hidden">
      <summary class="ai:flex ai:size-10 ai:cursor-pointer ai:items-center ai:justify-center ai:rounded-lg ai:text-gray-700 ai:hover:bg-gray-100" aria-label="Open menu"><?php ai_e_icon( 'menu', 6 ); ?></summary>
      <div class="ai:absolute ai:right-0 ai:z-40 ai:mt-3 ai:flex ai:w-64 ai:flex-col ai:gap-1 ai:rounded-xl ai:bg-white ai:p-3 ai:shadow-lg ai:ring-1 ai:ring-gray-950/5">
        <?php foreach ( $items as $item ) : ?>
        <a href="<?php echo esc_url( $item['url'] ?? '#' ); ?>" class="<?php echo esc_attr( ai_cls( 'ai:rounded-lg ai:px-3 ai:py-2 ai:text-sm ai:font-medium ai:no-underline ai:hover:bg-gray-50' ) . ' ' . ( ! empty( $item['current'] ) ? $on : ai_cls( 'ai:text-gray-700' ) ) ); ?>"><?php echo esc_html( $item['label'] ?? '' ); ?></a>
        <?php endforeach; ?>
        <?php if ( $cta_label ) : ?>
        <div class="ai:mt-2 ai:flex"><?php ai_component( 'button', array( 'label' => $cta_label, 'url' => $cta_url, 'icon' => 'none' ) ); ?></div>
        <?php endif; ?>
      </div>
    </details>
  </div>
</header>
