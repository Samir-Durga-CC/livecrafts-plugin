<?php
/**
 * Component: alert
 * Params: type (info|success|warning|error), title, text
 * Shortcode: [ai_alert type="success" title="Saved" text="Your changes are live."]
 */
$type  = $args['type'] ?? 'info';
$title = $args['title'] ?? '';
$text  = $args['text'] ?? '';
$map   = array(
	'info'    => array( 'icon' => 'info', 'box' => ai_cls( 'bg-blue-50 ring-blue-200' ), 'ic' => ai_cls( 'text-blue-600' ), 'tt' => ai_cls( 'text-blue-900' ), 'tx' => ai_cls( 'text-blue-800' ) ),
	'success' => array( 'icon' => 'check-circle', 'box' => ai_cls( 'bg-green-50 ring-green-200' ), 'ic' => ai_cls( 'text-green-600' ), 'tt' => ai_cls( 'text-green-900' ), 'tx' => ai_cls( 'text-green-800' ) ),
	'warning' => array( 'icon' => 'warning', 'box' => ai_cls( 'bg-amber-50 ring-amber-200' ), 'ic' => ai_cls( 'text-amber-600' ), 'tt' => ai_cls( 'text-amber-900' ), 'tx' => ai_cls( 'text-amber-800' ) ),
	'error'   => array( 'icon' => 'x-circle', 'box' => ai_cls( 'bg-red-50 ring-red-200' ), 'ic' => ai_cls( 'text-red-600' ), 'tt' => ai_cls( 'text-red-900' ), 'tx' => ai_cls( 'text-red-800' ) ),
);
$s = $map[ $type ] ?? $map['info'];
?>
<div role="alert" class="<?php echo esc_attr( ai_cls( 'ai-c flex gap-3 rounded-xl p-4 ring-1 ring-inset' ) . ' ' . $s['box'] ); ?>">
  <span class="<?php echo esc_attr( ai_cls( 'mt-0.5' ) . ' ' . $s['ic'] ); ?>"><?php ai_e_icon( $s['icon'], 5 ); ?></span>
  <div>
    <?php if ( $title ) : ?>
    <p class="<?php echo esc_attr( ai_cls( 'm-0 text-sm font-semibold' ) . ' ' . $s['tt'] ); ?>"><?php echo esc_html( $title ); ?></p>
    <?php endif; ?>
    <?php if ( $text ) : ?>
    <p class="<?php echo esc_attr( ai_cls( 'mb-0 text-sm' ) . ( $title ? ' ' . ai_cls( 'mt-1' ) : ' ' . ai_cls( 'mt-0' ) ) . ' ' . $s['tx'] ); ?>"><?php echo esc_html( $text ); ?></p>
    <?php endif; ?>
  </div>
</div>
