<?php
/**
 * Component: team
 * Params: variant (cards|portraits), eyebrow, heading, text, items [ name, role, image, bio, url ]
 * Shortcode: [ai_team variant="cards" heading=""][ai_item name="" role="" image="" bio=""]...[/ai_team]
 *   cards     - a card per person, with the biography (default)
 *   portraits - picture, name and role only: for a larger team
 *
 * Without a picture the person's initials are shown, so a row never collapses into empty boxes.
 */
$items = ai_items( $args );
if ( ! $items ) {
	return;
}
$portraits = 'portraits' === ( $args['variant'] ?? 'cards' );
$photo     = ai_cls( 'mx-auto block aspect-square h-auto w-full max-w-48 rounded-card object-cover shadow-sm ring-1 ring-ink/5' );
$fallback  = ai_cls( 'mx-auto flex aspect-square w-full max-w-48 items-center justify-center rounded-card bg-brand-50 text-2xl font-semibold text-brand-700 ring-1 ring-inset ring-brand-100' );
?>
<section class="ai-c bg-surface py-sec-sm sm:py-sec">
  <div class="mx-auto max-w-wide px-4 sm:px-6 lg:px-8">
    <?php ai_component( 'part-head', $args ); ?>
    <ul class="m-0 mt-14 grid list-none gap-x-8 gap-y-12 p-0 sm:grid-cols-2 lg:grid-cols-4">
      <?php foreach ( $items as $item ) : ?>
      <li class="<?php echo esc_attr( $portraits ? ai_cls( 'm-0 p-0 text-center' ) : ai_cls( 'm-0 flex flex-col rounded-card border border-line bg-surface p-6 text-center shadow-sm' ) ); ?>">
        <?php if ( ! empty( $item['image'] ) ) : ?>
        <img alt="<?php echo esc_attr( $item['name'] ?? '' ); ?>" src="<?php echo esc_url( $item['image'] ); ?>" class="<?php echo esc_attr( $photo ); ?>" />
        <?php else : ?>
        <span class="<?php echo esc_attr( $fallback ); ?>" aria-hidden="true"><?php echo esc_html( ai_initials( $item['name'] ?? '' ) ); ?></span>
        <?php endif; ?>
        <h3 class="mt-5 mb-0 text-base font-semibold text-ink">
          <?php if ( ! empty( $item['url'] ) ) : ?>
          <a href="<?php echo esc_url( $item['url'] ); ?>" class="text-ink transition-colors hover:text-brand-600"><?php echo esc_html( $item['name'] ?? '' ); ?></a>
          <?php else : ?>
          <?php echo esc_html( $item['name'] ?? '' ); ?>
          <?php endif; ?>
        </h3>
        <?php if ( ! empty( $item['role'] ) ) : ?>
        <p class="m-0 mt-1 text-sm text-brand-600"><?php echo esc_html( $item['role'] ); ?></p>
        <?php endif; ?>
        <?php if ( ! $portraits && ! empty( $item['bio'] ) ) : ?>
        <p class="m-0 mt-3 text-sm/6 text-muted"><?php echo esc_html( $item['bio'] ); ?></p>
        <?php endif; ?>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
