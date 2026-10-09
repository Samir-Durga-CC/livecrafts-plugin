<?php
/**
 * Component: logo-cloud
 * Params: heading, items [ name, image, url ]  (no image: the name is shown as a wordmark)
 * Shortcode: [ai_logo_cloud heading="Trusted by teams at"][ai_item name="Acme" image=""]...[/ai_logo_cloud]
 */
$heading = $args['heading'] ?? '';
$items   = ai_items( $args );
?>
<section class="ai-c ai:bg-surface ai:py-12 ai:sm:py-16">
  <div class="ai:mx-auto ai:max-w-wide ai:px-4 ai:sm:px-6 ai:lg:px-8">
    <?php if ( $heading ) : ?>
    <p class="ai:m-0 ai:text-center ai:text-sm ai:font-semibold ai:text-subtle"><?php echo esc_html( $heading ); ?></p>
    <?php endif; ?>
    <ul class="ai:m-0 ai:mt-8 ai:grid ai:list-none ai:grid-cols-2 ai:items-center ai:gap-x-8 ai:gap-y-10 ai:p-0 ai:sm:grid-cols-3 ai:lg:grid-cols-5">
      <?php foreach ( $items as $item ) : ?>
      <li class="ai:m-0 ai:flex ai:justify-center ai:p-0">
        <?php if ( ! empty( $item['image'] ) ) : ?>
        <img alt="<?php echo esc_attr( $item['name'] ?? '' ); ?>" src="<?php echo esc_url( $item['image'] ); ?>" class="ai:block ai:h-8 ai:w-auto ai:max-w-full ai:object-contain ai:opacity-60 ai:grayscale ai:transition ai:hover:opacity-100 ai:hover:grayscale-0" />
        <?php else : ?>
        <span class="ai:text-xl ai:font-bold ai:tracking-tight ai:text-gray-400"><?php echo esc_html( $item['name'] ?? '' ); ?></span>
        <?php endif; ?>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
