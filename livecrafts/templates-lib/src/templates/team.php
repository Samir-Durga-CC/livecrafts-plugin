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
<section class="ai-c bg-surface py-sec-sm sm:py-sec">
  <div class="mx-auto max-w-wide px-4 sm:px-6 lg:px-8">
    <?php if ( $heading ) : ?>
    <div class="mx-auto max-w-2xl text-center">
      <h2 class="m-0 text-3xl font-semibold tracking-tight text-balance text-ink sm:text-4xl"><?php echo esc_html( $heading ); ?></h2>
      <?php if ( $text ) : ?><p class="mt-4 mb-0 text-lg text-pretty text-muted"><?php echo esc_html( $text ); ?></p><?php endif; ?>
    </div>
    <?php endif; ?>
    <ul class="m-0 mt-14 grid list-none gap-x-8 gap-y-12 p-0 sm:grid-cols-2 lg:grid-cols-4">
      <?php foreach ( $items as $item ) : ?>
      <li class="m-0 p-0 text-center">
        <?php if ( ! empty( $item['image'] ) ) : ?>
        <img alt="" src="<?php echo esc_url( $item['image'] ); ?>" class="mx-auto block aspect-square h-auto w-full max-w-48 rounded-card object-cover shadow-sm ring-1 ring-gray-950/5" />
        <?php else : ?>
        <span class="mx-auto flex aspect-square w-full max-w-48 items-center justify-center rounded-card bg-brand-50 text-4xl font-semibold text-brand-600"><?php echo esc_html( ai_initials( $item['name'] ?? '' ) ); ?></span>
        <?php endif; ?>
        <h3 class="mt-5 mb-0 text-base font-semibold text-ink"><?php echo esc_html( $item['name'] ?? '' ); ?></h3>
        <p class="m-0 mt-1 text-sm font-medium text-brand-700"><?php echo esc_html( $item['role'] ?? '' ); ?></p>
        <?php if ( ! empty( $item['bio'] ) ) : ?><p class="mt-3 mb-0 text-sm/6 text-muted"><?php echo esc_html( $item['bio'] ); ?></p><?php endif; ?>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
