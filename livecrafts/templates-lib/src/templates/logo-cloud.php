<?php
/**
 * Component: logo-cloud
 * Params: heading, items [ name, image, url ]  (no image: the name is shown as a wordmark)
 * Shortcode: [ai_logo_cloud heading="Trusted by teams at"][ai_item name="Acme" image=""]...[/ai_logo_cloud]
 */
$heading = $args['heading'] ?? '';
$items   = ai_items( $args );
?>
<section class="ai-c bg-surface py-12 sm:py-16">
  <div class="mx-auto max-w-wide px-4 sm:px-6 lg:px-8">
    <?php if ( $heading ) : ?>
    <p class="m-0 text-center text-sm font-semibold text-subtle"><?php echo esc_html( $heading ); ?></p>
    <?php endif; ?>
    <ul class="m-0 mt-8 grid list-none grid-cols-2 items-center gap-x-8 gap-y-10 p-0 sm:grid-cols-3 lg:grid-cols-5">
      <?php foreach ( $items as $item ) : ?>
      <li class="m-0 flex justify-center p-0">
        <?php if ( ! empty( $item['image'] ) ) : ?>
        <img alt="<?php echo esc_attr( $item['name'] ?? '' ); ?>" src="<?php echo esc_url( $item['image'] ); ?>" class="block h-8 w-auto max-w-full object-contain opacity-60 grayscale transition hover:opacity-100 hover:grayscale-0" />
        <?php else : ?>
        <span class="text-xl font-bold tracking-tight text-gray-400"><?php echo esc_html( $item['name'] ?? '' ); ?></span>
        <?php endif; ?>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
