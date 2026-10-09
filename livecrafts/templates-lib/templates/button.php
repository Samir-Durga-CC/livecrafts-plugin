<?php
/**
 * Component: button
 * Params: label, url, variant (solid|outline|ghost|light|ghost-light), size (md|lg), icon (arrow-right|none|any icon name)
 * Shortcode: [ai_button label="Get started" url="/signup" variant="solid" size="lg" icon="arrow-right"]
 */
$label   = $args['label'] ?? '';
$url     = $args['url'] ?? '#';
$variant = $args['variant'] ?? 'solid';
$size    = $args['size'] ?? 'md';
$icon    = $args['icon'] ?? 'arrow-right';

$variants = array(
	'solid'       => ai_cls( 'ai:bg-brand-600 ai:text-white ai:shadow-sm ai:hover:bg-brand-700 ai:focus-visible:outline-brand-600' ),
	'outline'     => ai_cls( 'ai:bg-white ai:text-gray-900 ai:ring-1 ai:ring-inset ai:ring-gray-300 ai:hover:bg-gray-50 ai:focus-visible:outline-brand-600' ),
	'ghost'       => ai_cls( 'ai:text-brand-700 ai:hover:bg-brand-50 ai:focus-visible:outline-brand-600' ),
	'light'       => ai_cls( 'ai:bg-white ai:text-brand-700 ai:shadow-sm ai:hover:bg-brand-50 ai:focus-visible:outline-white' ),
	'ghost-light' => ai_cls( 'ai:text-white ai:ring-1 ai:ring-inset ai:ring-white/40 ai:hover:bg-white/10 ai:focus-visible:outline-white' ),
);
$sizes = array(
	'md' => ai_cls( 'ai:px-4 ai:py-2.5 ai:text-sm' ),
	'lg' => ai_cls( 'ai:px-6 ai:py-3 ai:text-base' ),
);
$cls = ai_cls( 'ai-c ai:inline-flex ai:items-center ai:justify-center ai:gap-2 ai:rounded-lg ai:font-semibold ai:no-underline ai:transition-colors ai:focus-visible:outline-2 ai:focus-visible:outline-offset-2' )
	. ' ' . ( $variants[ $variant ] ?? $variants['solid'] )
	. ' ' . ( $sizes[ $size ] ?? $sizes['md'] );
?>
<a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $cls ); ?>">
  <span><?php echo esc_html( $label ); ?></span>
  <?php
  if ( 'none' !== $icon && '' !== $icon ) {
	  ai_e_icon( $icon, 'lg' === $size ? 5 : 4 );
  }
  ?>
</a>
