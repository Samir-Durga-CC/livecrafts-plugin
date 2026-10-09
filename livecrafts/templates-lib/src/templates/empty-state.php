<?php
/**
 * Component: empty-state
 * Params: icon, title, text, label, url
 * Shortcode: [ai_empty_state icon="search" title="No results" text="Try a different search." label="Clear filters" url="/shop"]
 */
$icon  = $args['icon'] ?? 'search';
$title = $args['title'] ?? '';
$text  = $args['text'] ?? '';
$label = $args['label'] ?? '';
$url   = $args['url'] ?? '#';
?>
<div class="ai-c rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-14 text-center">
  <span class="mx-auto flex size-12 items-center justify-center rounded-full bg-brand-50 text-brand-600"><?php ai_e_icon( $icon, 6 ); ?></span>
  <h3 class="mt-4 mb-0 text-base font-semibold text-gray-900"><?php echo esc_html( $title ); ?></h3>
  <?php if ( $text ) : ?>
  <p class="mx-auto mt-2 mb-0 max-w-sm text-sm text-pretty text-gray-600"><?php echo esc_html( $text ); ?></p>
  <?php endif; ?>
  <?php if ( $label ) : ?>
  <div class="mt-6"><?php ai_component( 'button', array( 'label' => $label, 'url' => $url, 'icon' => 'none' ) ); ?></div>
  <?php endif; ?>
</div>
