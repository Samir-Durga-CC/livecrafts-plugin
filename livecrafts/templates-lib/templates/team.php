<?php
/**
 * Component: team
 * Params: heading, text, items [ name, role, image, bio ]
 * Shortcode: [ai_team heading=""][ai_item name="" role="" image="" bio=""]...[/ai_team]
 */
$heading = $args['heading'] ?? '';
$text    = $args['text'] ?? '';
$items   = ai_items( $args );
?>
<section class="ai-c ai:bg-surface ai:py-sec-sm ai:sm:py-sec">
  <div class="ai:mx-auto ai:max-w-wide ai:px-4 ai:sm:px-6 ai:lg:px-8">
    <?php if ( $heading ) : ?>
    <div class="ai:mx-auto ai:max-w-2xl ai:text-center">
      <h2 class="ai:m-0 ai:text-3xl ai:font-semibold ai:tracking-tight ai:text-balance ai:text-ink ai:sm:text-4xl"><?php echo esc_html( $heading ); ?></h2>
      <?php if ( $text ) : ?><p class="ai:mt-4 ai:mb-0 ai:text-lg ai:text-pretty ai:text-muted"><?php echo esc_html( $text ); ?></p><?php endif; ?>
    </div>
    <?php endif; ?>
    <ul class="ai:m-0 ai:mt-14 ai:grid ai:list-none ai:gap-x-8 ai:gap-y-12 ai:p-0 ai:sm:grid-cols-2 ai:lg:grid-cols-4">
      <?php foreach ( $items as $item ) : ?>
      <li class="ai:m-0 ai:p-0 ai:text-center">
        <?php if ( ! empty( $item['image'] ) ) : ?>
        <img alt="" src="<?php echo esc_url( $item['image'] ); ?>" class="ai:mx-auto ai:block ai:aspect-square ai:h-auto ai:w-full ai:max-w-48 ai:rounded-card ai:object-cover ai:shadow-sm ai:ring-1 ai:ring-gray-950/5" />
        <?php else : ?>
        <span class="ai:mx-auto ai:flex ai:aspect-square ai:w-full ai:max-w-48 ai:items-center ai:justify-center ai:rounded-card ai:bg-brand-50 ai:text-4xl ai:font-semibold ai:text-brand-600"><?php echo esc_html( ai_initials( $item['name'] ?? '' ) ); ?></span>
        <?php endif; ?>
        <h3 class="ai:mt-5 ai:mb-0 ai:text-base ai:font-semibold ai:text-ink"><?php echo esc_html( $item['name'] ?? '' ); ?></h3>
        <p class="ai:m-0 ai:mt-1 ai:text-sm ai:font-medium ai:text-brand-700"><?php echo esc_html( $item['role'] ?? '' ); ?></p>
        <?php if ( ! empty( $item['bio'] ) ) : ?><p class="ai:mt-3 ai:mb-0 ai:text-sm/6 ai:text-muted"><?php echo esc_html( $item['bio'] ); ?></p><?php endif; ?>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
