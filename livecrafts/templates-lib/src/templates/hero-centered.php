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
<section class="ai-c relative overflow-hidden bg-surface py-sec-sm sm:py-sec">
  <!-- a soft brand light behind the heading: depth with nothing extra to load -->
  <div class="pointer-events-none absolute -top-48 left-1/2 size-[40rem] -translate-x-1/2 rounded-full bg-brand-50 opacity-70 blur-3xl" aria-hidden="true"></div>
  <div class="relative mx-auto max-w-wide px-4 text-center sm:px-6 lg:px-8">
    <div class="mx-auto max-w-3xl">
      <?php ai_component( 'part-head', array_merge( $args, array( 'level' => 'h1' ) ) ); ?>
    </div>
    <?php ai_component( 'part-actions', $args ); ?>
    <?php if ( ! empty( $args['note'] ) ) : ?>
    <p class="m-0 mt-6 text-sm text-subtle"><?php echo esc_html( $args['note'] ); ?></p>
    <?php endif; ?>

    <?php if ( $items ) : ?>
    <dl class="mx-auto m-0 mt-16 grid max-w-3xl gap-10 border-t border-line pt-10 sm:grid-cols-3">
      <?php foreach ( $items as $item ) : ?>
      <div class="m-0">
        <dt class="m-0 text-3xl font-bold tracking-tight text-ink sm:text-4xl"><?php echo esc_html( $item['title'] ?? '' ); ?></dt>
        <dd class="m-0 mt-1 text-sm text-muted"><?php echo esc_html( $item['text'] ?? '' ); ?></dd>
      </div>
      <?php endforeach; ?>
    </dl>
    <?php endif; ?>
  </div>
</section>
