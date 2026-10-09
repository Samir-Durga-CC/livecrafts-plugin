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
<div class="ai-c ai:rounded-2xl ai:border ai:border-dashed ai:border-gray-300 ai:bg-white ai:px-6 ai:py-14 ai:text-center">
  <span class="ai:mx-auto ai:flex ai:size-12 ai:items-center ai:justify-center ai:rounded-full ai:bg-brand-50 ai:text-brand-600"><?php ai_e_icon( $icon, 6 ); ?></span>
  <h3 class="ai:mt-4 ai:mb-0 ai:text-base ai:font-semibold ai:text-gray-900"><?php echo esc_html( $title ); ?></h3>
  <?php if ( $text ) : ?>
  <p class="ai:mx-auto ai:mt-2 ai:mb-0 ai:max-w-sm ai:text-sm ai:text-pretty ai:text-gray-600"><?php echo esc_html( $text ); ?></p>
  <?php endif; ?>
  <?php if ( $label ) : ?>
  <div class="ai:mt-6"><?php ai_component( 'button', array( 'label' => $label, 'url' => $url, 'icon' => 'none' ) ); ?></div>
  <?php endif; ?>
</div>
