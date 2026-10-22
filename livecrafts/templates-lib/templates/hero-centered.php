<?php
/**
 * Component: hero-centered (the opening of a page, message only)
 * Params: eyebrow, heading, text, note, primary_label, primary_url, secondary_label, secondary_url, items [ title, text ]
 * Shortcode: [ai_hero_centered eyebrow="" heading="" text="" primary_label="" primary_url="" note=""]
 *
 * For a page whose opening is a statement rather than a picture: an about page, a help centre, a policy page.
 * With items it also shows the figures that back the statement. For a picture beside the message use [ai_hero].
 */
$items = ai_items( $args );
?>
<section class="ai-c ai:relative ai:overflow-hidden ai:bg-surface ai:py-sec-sm ai:sm:py-sec">
  <!-- a soft brand light behind the heading: depth with nothing extra to load -->
  <div class="ai:pointer-events-none ai:absolute ai:-top-48 ai:left-1/2 ai:size-[40rem] ai:-translate-x-1/2 ai:rounded-full ai:bg-brand-50 ai:opacity-70 ai:blur-3xl" aria-hidden="true"></div>
  <div class="ai:relative ai:mx-auto ai:max-w-wide ai:px-4 ai:text-center ai:sm:px-6 ai:lg:px-8">
    <div class="ai:mx-auto ai:max-w-3xl">
      <?php ai_component( 'part-head', array_merge( $args, array( 'level' => 'h1' ) ) ); ?>
    </div>
    <?php ai_component( 'part-actions', $args ); ?>
    <?php if ( ! empty( $args['note'] ) ) : ?>
    <p class="ai:m-0 ai:mt-6 ai:text-sm ai:text-subtle"><?php echo esc_html( $args['note'] ); ?></p>
    <?php endif; ?>

    <?php if ( $items ) : ?>
    <dl class="ai:mx-auto ai:m-0 ai:mt-16 ai:grid ai:max-w-3xl ai:gap-10 ai:border-t ai:border-line ai:pt-10 ai:sm:grid-cols-3">
      <?php foreach ( $items as $item ) : ?>
      <div class="ai:m-0">
        <dt class="ai:m-0 ai:text-3xl ai:font-bold ai:tracking-tight ai:text-ink ai:sm:text-4xl"><?php echo esc_html( $item['title'] ?? '' ); ?></dt>
        <dd class="ai:m-0 ai:mt-1 ai:text-sm ai:text-muted"><?php echo esc_html( $item['text'] ?? '' ); ?></dd>
      </div>
      <?php endforeach; ?>
    </dl>
    <?php endif; ?>
  </div>
</section>
