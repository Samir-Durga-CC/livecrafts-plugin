<?php
/**
 * Component: badge
 * Params: label, variant (brand|gray|green|amber|red)
 * Shortcode: [ai_badge label="New" variant="brand"]
 */
$label   = $args['label'] ?? '';
$variant = $args['variant'] ?? 'brand';
$map     = array(
	'brand' => ai_cls( 'ai:bg-brand-50 ai:text-brand-700 ai:ring-brand-200' ),
	'gray'  => ai_cls( 'ai:bg-gray-100 ai:text-muted ai:ring-gray-200' ),
	'green' => ai_cls( 'ai:bg-green-50 ai:text-green-700 ai:ring-green-200' ),
	'amber' => ai_cls( 'ai:bg-amber-50 ai:text-amber-800 ai:ring-amber-200' ),
	'red'   => ai_cls( 'ai:bg-red-50 ai:text-red-700 ai:ring-red-200' ),
);
$cls = ai_cls( 'ai-c ai:inline-flex ai:items-center ai:rounded-full ai:px-2.5 ai:py-1 ai:text-xs ai:font-medium ai:ring-1 ai:ring-inset' ) . ' ' . ( $map[ $variant ] ?? $map['brand'] );
?>
<span class="<?php echo esc_attr( $cls ); ?>"><?php echo esc_html( $label ); ?></span>
