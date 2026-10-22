<?php
/**
 * Component: testimonials
 * Params: variant (cards|centered|split), heading, image, items [ quote, name, role, image ]
 * Shortcode: [ai_testimonials variant="cards" heading=""][ai_item quote="" name="" role="" image=""]...[/ai_testimonials]
 *   cards    - social-proof cards in a responsive grid (default; 1 / 2 / 3 columns)
 *   centered - ONE large quote, centered (uses the first item)
 *   split    - a picture beside ONE quote (image = the picture; uses the first item)
 */
$variant = in_array( $args['variant'] ?? 'cards', array( 'cards', 'centered', 'split' ), true ) ? $args['variant'] : 'cards';
$heading = $args['heading'] ?? '';
$image   = $args['image'] ?? '';
$items   = ai_items( $args );
$one     = $items ? $items[0] : array();

$stars  = ai_cls( 'flex gap-1 text-amber-400' );
$avatar = ai_cls( 'flex size-11 shrink-0 items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-brand-700' );
?>
<?php if ( 'cards' === $variant ) : ?>
<section class="ai-c bg-surface py-sec-sm sm:py-sec">
  <div class="mx-auto max-w-wide px-4 sm:px-6 lg:px-8">
    <?php if ( $heading ) : ?>
    <h2 class="mx-auto m-0 max-w-2xl text-center text-3xl font-semibold tracking-tight text-balance text-ink sm:text-4xl"><?php echo esc_html( $heading ); ?></h2>
    <?php endif; ?>
    <div class="mt-12 grid gap-8 lg:grid-cols-3">
      <?php foreach ( $items as $item ) : ?>
      <figure class="m-0 flex flex-col rounded-card border border-line bg-surface p-8 shadow-sm">
        <div class="<?php echo esc_attr( $stars ); ?>" aria-hidden="true">
          <?php for ( $i = 0; $i < 5; $i++ ) { ai_e_icon( 'star', 5 ); } ?>
        </div>
        <blockquote class="m-0 mt-5 flex-1 border-0 p-0 text-base/7 text-muted">
          <p class="m-0 text-muted">&ldquo;<?php echo esc_html( $item['quote'] ?? '' ); ?>&rdquo;</p>
        </blockquote>
        <figcaption class="mt-6 flex items-center gap-3">
          <?php if ( ! empty( $item['image'] ) ) : ?>
          <img alt="" src="<?php echo esc_url( $item['image'] ); ?>" class="block size-11 max-w-full rounded-full object-cover" />
          <?php else : ?>
          <span class="<?php echo esc_attr( $avatar ); ?>"><?php echo esc_html( ai_initials( $item['name'] ?? '' ) ); ?></span>
          <?php endif; ?>
          <div>
            <div class="text-sm font-semibold text-ink"><?php echo esc_html( $item['name'] ?? '' ); ?></div>
            <?php if ( ! empty( $item['role'] ) ) : ?><div class="text-sm text-muted"><?php echo esc_html( $item['role'] ); ?></div><?php endif; ?>
          </div>
        </figcaption>
      </figure>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php elseif ( 'centered' === $variant ) : ?>
<section class="ai-c bg-surface-alt py-sec-sm sm:py-sec">
  <div class="mx-auto max-w-3xl px-4 text-center sm:px-6 lg:px-8">
    <?php if ( $heading ) : ?>
    <h2 class="m-0 text-3xl font-semibold tracking-tight text-balance text-ink sm:text-4xl"><?php echo esc_html( $heading ); ?></h2>
    <?php endif; ?>
    <figure class="m-0">
      <div class="mt-8 flex justify-center gap-1 text-amber-400" aria-hidden="true">
        <?php for ( $i = 0; $i < 5; $i++ ) { ai_e_icon( 'star', 5 ); } ?>
      </div>
      <blockquote class="m-0 mt-6 border-0 p-0">
        <p class="m-0 text-2xl font-medium text-balance text-ink sm:text-3xl">&ldquo;<?php echo esc_html( $one['quote'] ?? '' ); ?>&rdquo;</p>
      </blockquote>
      <figcaption class="mt-8 flex items-center justify-center gap-3">
        <?php if ( ! empty( $one['image'] ) ) : ?>
        <img alt="" src="<?php echo esc_url( $one['image'] ); ?>" class="block size-11 max-w-full rounded-full object-cover" />
        <?php else : ?>
        <span class="<?php echo esc_attr( $avatar ); ?>"><?php echo esc_html( ai_initials( $one['name'] ?? '' ) ); ?></span>
        <?php endif; ?>
        <div class="text-left">
          <div class="text-sm font-semibold text-ink"><?php echo esc_html( $one['name'] ?? '' ); ?></div>
          <?php if ( ! empty( $one['role'] ) ) : ?><div class="text-sm text-muted"><?php echo esc_html( $one['role'] ); ?></div><?php endif; ?>
        </div>
      </figcaption>
    </figure>
  </div>
</section>

<?php else : ?>
<section class="ai-c bg-surface py-sec-sm sm:py-sec">
  <div class="mx-auto grid max-w-wide items-center gap-10 px-4 sm:px-6 lg:grid-cols-2 lg:gap-16 lg:px-8">
    <?php if ( $image ) : ?>
    <img alt="" src="<?php echo esc_url( $image ); ?>" class="block h-full max-h-96 w-full rounded-card object-cover" />
    <?php else : ?>
    <div class="min-h-72 rounded-card bg-brand-100" aria-hidden="true"></div>
    <?php endif; ?>
    <figure class="m-0">
      <?php if ( $heading ) : ?>
      <h2 class="m-0 text-sm font-semibold tracking-wide text-brand-600 uppercase"><?php echo esc_html( $heading ); ?></h2>
      <?php endif; ?>
      <div class="mt-4 flex gap-1 text-amber-400" aria-hidden="true">
        <?php for ( $i = 0; $i < 5; $i++ ) { ai_e_icon( 'star', 5 ); } ?>
      </div>
      <blockquote class="m-0 mt-5 border-0 p-0">
        <p class="m-0 text-xl font-medium text-pretty text-ink sm:text-2xl">&ldquo;<?php echo esc_html( $one['quote'] ?? '' ); ?>&rdquo;</p>
      </blockquote>
      <figcaption class="mt-7 flex items-center gap-3">
        <?php if ( ! empty( $one['image'] ) ) : ?>
        <img alt="" src="<?php echo esc_url( $one['image'] ); ?>" class="block size-11 max-w-full rounded-full object-cover" />
        <?php else : ?>
        <span class="<?php echo esc_attr( $avatar ); ?>"><?php echo esc_html( ai_initials( $one['name'] ?? '' ) ); ?></span>
        <?php endif; ?>
        <div>
          <div class="text-sm font-semibold text-ink"><?php echo esc_html( $one['name'] ?? '' ); ?></div>
          <?php if ( ! empty( $one['role'] ) ) : ?><div class="text-sm text-muted"><?php echo esc_html( $one['role'] ); ?></div><?php endif; ?>
        </div>
      </figcaption>
    </figure>
  </div>
</section>
<?php endif; ?>
