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
	'solid'       => ai_cls( 'bg-brand-600 text-white shadow-sm hover:bg-brand-700 focus-visible:outline-brand-600' ),
	'outline'     => ai_cls( 'bg-surface text-ink ring-1 ring-inset ring-gray-300 hover:bg-surface-alt focus-visible:outline-brand-600' ),
	'ghost'       => ai_cls( 'text-brand-700 hover:bg-brand-50 focus-visible:outline-brand-600' ),
	'light'       => ai_cls( 'bg-white text-brand-700 shadow-sm hover:bg-brand-50 focus-visible:outline-white' ),
	'ghost-light' => ai_cls( 'text-white ring-1 ring-inset ring-white/40 hover:bg-white/10 focus-visible:outline-white' ),
);
$sizes = array(
	'md' => ai_cls( 'px-4 py-2.5 text-sm' ),
	'lg' => ai_cls( 'px-6 py-3 text-base' ),
);
$cls = ai_cls( 'ai-c inline-flex items-center justify-center gap-2 rounded-lg font-semibold no-underline transition-colors focus-visible:outline-2 focus-visible:outline-offset-2' )
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
