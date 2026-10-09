<?php
/**
 * Component: announcement (top bar)
 * Params: text, link_label, url
 * Shortcode: [ai_announcement text="Version 2 is live." link_label="See what's new" url="/changelog"]
 */
$text       = $args['text'] ?? '';
$link_label = $args['link_label'] ?? '';
$url        = $args['url'] ?? '#';
?>
<div class="ai-c ai:bg-brand-700 ai:px-4 ai:py-2.5 ai:text-center ai:text-sm ai:text-white">
  <p class="ai:m-0 ai:text-white"><?php echo esc_html( $text ); ?>
    <?php if ( $link_label ) : ?>
    <a href="<?php echo esc_url( $url ); ?>" class="ai:ml-1 ai:inline-flex ai:items-center ai:gap-1 ai:font-semibold ai:text-white ai:underline ai:underline-offset-2 ai:hover:no-underline"><?php echo esc_html( $link_label ); ?><?php ai_e_icon( 'arrow-right', 4 ); ?></a>
    <?php endif; ?>
  </p>
</div>
