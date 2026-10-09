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
	'info'    => array( 'icon' => 'info', 'box' => ai_cls( 'ai:bg-blue-50 ai:ring-blue-200' ), 'ic' => ai_cls( 'ai:text-blue-600' ), 'tt' => ai_cls( 'ai:text-blue-900' ), 'tx' => ai_cls( 'ai:text-blue-800' ) ),
	'success' => array( 'icon' => 'check-circle', 'box' => ai_cls( 'ai:bg-green-50 ai:ring-green-200' ), 'ic' => ai_cls( 'ai:text-green-600' ), 'tt' => ai_cls( 'ai:text-green-900' ), 'tx' => ai_cls( 'ai:text-green-800' ) ),
	'warning' => array( 'icon' => 'warning', 'box' => ai_cls( 'ai:bg-amber-50 ai:ring-amber-200' ), 'ic' => ai_cls( 'ai:text-amber-600' ), 'tt' => ai_cls( 'ai:text-amber-900' ), 'tx' => ai_cls( 'ai:text-amber-800' ) ),
	'error'   => array( 'icon' => 'x-circle', 'box' => ai_cls( 'ai:bg-red-50 ai:ring-red-200' ), 'ic' => ai_cls( 'ai:text-red-600' ), 'tt' => ai_cls( 'ai:text-red-900' ), 'tx' => ai_cls( 'ai:text-red-800' ) ),
);
$s = $map[ $type ] ?? $map['info'];
?>
<div role="alert" class="<?php echo esc_attr( ai_cls( 'ai-c ai:flex ai:gap-3 ai:rounded-ctl ai:p-4 ai:ring-1 ai:ring-inset' ) . ' ' . $s['box'] ); ?>">
  <span class="<?php echo esc_attr( ai_cls( 'ai:mt-0.5' ) . ' ' . $s['ic'] ); ?>"><?php ai_e_icon( $s['icon'], 5 ); ?></span>
  <div>
    <?php if ( $title ) : ?>
    <p class="<?php echo esc_attr( ai_cls( 'ai:m-0 ai:text-sm ai:font-semibold' ) . ' ' . $s['tt'] ); ?>"><?php echo esc_html( $title ); ?></p>
    <?php endif; ?>
    <?php if ( $text ) : ?>
    <p class="<?php echo esc_attr( ai_cls( 'ai:mb-0 ai:text-sm' ) . ( $title ? ' ' . ai_cls( 'ai:mt-1' ) : ' ' . ai_cls( 'ai:mt-0' ) ) . ' ' . $s['tx'] ); ?>"><?php echo esc_html( $text ); ?></p>
    <?php endif; ?>
  </div>
</div>
