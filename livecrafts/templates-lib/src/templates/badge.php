<?php
/**
 * Component: badge
 * Params: label, variant (brand|gray|green|amber|red)
 * Shortcode: [ai_badge label="New" variant="brand"]
 */
$label   = $args['label'] ?? '';
$variant = $args['variant'] ?? 'brand';
$map     = array(
	'brand' => ai_cls( 'bg-brand-50 text-brand-700 ring-brand-200' ),
	'gray'  => ai_cls( 'bg-gray-100 text-muted ring-gray-200' ),
	'green' => ai_cls( 'bg-green-50 text-green-700 ring-green-200' ),
	'amber' => ai_cls( 'bg-amber-50 text-amber-800 ring-amber-200' ),
	'red'   => ai_cls( 'bg-red-50 text-red-700 ring-red-200' ),
);
$cls = ai_cls( 'ai-c inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset' ) . ' ' . ( $map[ $variant ] ?? $map['brand'] );
?>
<span class="<?php echo esc_attr( $cls ); ?>"><?php echo esc_html( $label ); ?></span>
