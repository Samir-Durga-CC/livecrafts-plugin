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
<div class="ai-c bg-brand-700 px-4 py-2.5 text-center text-sm text-white">
  <p class="m-0 text-white"><?php echo esc_html( $text ); ?>
    <?php if ( $link_label ) : ?>
    <a href="<?php echo esc_url( $url ); ?>" class="ml-1 inline-flex items-center gap-1 font-semibold text-white underline underline-offset-2 hover:no-underline"><?php echo esc_html( $link_label ); ?><?php ai_e_icon( 'arrow-right', 4 ); ?></a>
    <?php endif; ?>
  </p>
</div>
