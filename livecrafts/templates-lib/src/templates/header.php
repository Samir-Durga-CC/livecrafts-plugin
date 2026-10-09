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
$link       = ai_cls( 'text-sm font-medium no-underline transition-colors' );
$on         = ai_cls( 'text-brand-700' );
$off        = ai_cls( 'text-gray-700 hover:text-brand-700' );
?>
<header class="ai-c relative z-30 border-b border-gray-200 bg-white">
  <div class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-4 py-4 sm:px-6 lg:px-8">
    <a href="<?php echo esc_url( $home_url ); ?>" class="flex items-center gap-2 text-lg font-bold text-gray-900 no-underline">
      <?php if ( $logo_image ) : ?>
      <img alt="" src="<?php echo esc_url( $logo_image ); ?>" class="block h-8 w-auto max-w-full" />
      <?php endif; ?>
      <span><?php echo esc_html( $logo_text ); ?></span>
    </a>

    <nav aria-label="Main" class="hidden items-center gap-8 md:flex">
      <?php foreach ( $items as $item ) : ?>
      <a href="<?php echo esc_url( $item['url'] ?? '#' ); ?>" class="<?php echo esc_attr( $link . ' ' . ( ! empty( $item['current'] ) ? $on : $off ) ); ?>"<?php echo ! empty( $item['current'] ) ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $item['label'] ?? '' ); ?></a>
      <?php endforeach; ?>
    </nav>

    <?php if ( $cta_label ) : ?>
    <div class="hidden md:block"><?php ai_component( 'button', array( 'label' => $cta_label, 'url' => $cta_url, 'icon' => 'none' ) ); ?></div>
    <?php endif; ?>

    <details class="relative m-0 md:hidden">
      <summary class="flex size-10 cursor-pointer items-center justify-center rounded-lg text-gray-700 hover:bg-gray-100" aria-label="Open menu"><?php ai_e_icon( 'menu', 6 ); ?></summary>
      <div class="absolute right-0 z-40 mt-3 flex w-64 flex-col gap-1 rounded-xl bg-white p-3 shadow-lg ring-1 ring-gray-950/5">
        <?php foreach ( $items as $item ) : ?>
        <a href="<?php echo esc_url( $item['url'] ?? '#' ); ?>" class="<?php echo esc_attr( ai_cls( 'rounded-lg px-3 py-2 text-sm font-medium no-underline hover:bg-gray-50' ) . ' ' . ( ! empty( $item['current'] ) ? $on : ai_cls( 'text-gray-700' ) ) ); ?>"><?php echo esc_html( $item['label'] ?? '' ); ?></a>
        <?php endforeach; ?>
        <?php if ( $cta_label ) : ?>
        <div class="mt-2 flex"><?php ai_component( 'button', array( 'label' => $cta_label, 'url' => $cta_url, 'icon' => 'none' ) ); ?></div>
        <?php endif; ?>
      </div>
    </details>
  </div>
</header>
