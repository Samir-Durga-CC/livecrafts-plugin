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
$photo     = ai_cls( 'ai:mx-auto ai:block ai:aspect-square ai:h-auto ai:w-full ai:max-w-48 ai:rounded-card ai:object-cover ai:shadow-sm ai:ring-1 ai:ring-ink/5' );
$fallback  = ai_cls( 'ai:mx-auto ai:flex ai:aspect-square ai:w-full ai:max-w-48 ai:items-center ai:justify-center ai:rounded-card ai:bg-brand-50 ai:text-2xl ai:font-semibold ai:text-brand-700 ai:ring-1 ai:ring-inset ai:ring-brand-100' );
?>
<section class="ai-c ai:bg-surface ai:py-sec-sm ai:sm:py-sec">
  <div class="ai:mx-auto ai:max-w-wide ai:px-4 ai:sm:px-6 ai:lg:px-8">
    <?php ai_component( 'part-head', $args ); ?>
    <ul class="ai:m-0 ai:mt-14 ai:grid ai:list-none ai:gap-x-8 ai:gap-y-12 ai:p-0 ai:sm:grid-cols-2 ai:lg:grid-cols-4">
      <?php foreach ( $items as $item ) : ?>
      <li class="<?php echo esc_attr( $portraits ? ai_cls( 'ai:m-0 ai:p-0 ai:text-center' ) : ai_cls( 'ai:m-0 ai:flex ai:flex-col ai:rounded-card ai:border ai:border-line ai:bg-surface ai:p-6 ai:text-center ai:shadow-sm' ) ); ?>">
        <?php if ( ! empty( $item['image'] ) ) : ?>
        <img alt="<?php echo esc_attr( $item['name'] ?? '' ); ?>" src="<?php echo esc_url( $item['image'] ); ?>" class="<?php echo esc_attr( $photo ); ?>" />
        <?php else : ?>
        <span class="<?php echo esc_attr( $fallback ); ?>" aria-hidden="true"><?php echo esc_html( ai_initials( $item['name'] ?? '' ) ); ?></span>
        <?php endif; ?>
        <h3 class="ai:mt-5 ai:mb-0 ai:text-base ai:font-semibold ai:text-ink">
          <?php if ( ! empty( $item['url'] ) ) : ?>
          <a href="<?php echo esc_url( $item['url'] ); ?>" class="ai:text-ink ai:transition-colors ai:hover:text-brand-600"><?php echo esc_html( $item['name'] ?? '' ); ?></a>
          <?php else : ?>
          <?php echo esc_html( $item['name'] ?? '' ); ?>
          <?php endif; ?>
        </h3>
        <?php if ( ! empty( $item['role'] ) ) : ?>
        <p class="ai:m-0 ai:mt-1 ai:text-sm ai:text-brand-600"><?php echo esc_html( $item['role'] ); ?></p>
        <?php endif; ?>
        <?php if ( ! $portraits && ! empty( $item['bio'] ) ) : ?>
        <p class="ai:m-0 ai:mt-3 ai:text-sm/6 ai:text-muted"><?php echo esc_html( $item['bio'] ); ?></p>
        <?php endif; ?>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
