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

$stars  = ai_cls( 'ai:flex ai:gap-1 ai:text-amber-400' );
$avatar = ai_cls( 'ai:flex ai:size-11 ai:shrink-0 ai:items-center ai:justify-center ai:rounded-full ai:bg-brand-100 ai:text-sm ai:font-semibold ai:text-brand-700' );
?>
<?php if ( 'cards' === $variant ) : ?>
<section class="ai-c ai:bg-surface ai:py-sec-sm ai:sm:py-sec">
  <div class="ai:mx-auto ai:max-w-wide ai:px-4 ai:sm:px-6 ai:lg:px-8">
    <?php if ( $heading ) : ?>
    <h2 class="ai:mx-auto ai:m-0 ai:max-w-2xl ai:text-center ai:text-3xl ai:font-semibold ai:tracking-tight ai:text-balance ai:text-ink ai:sm:text-4xl"><?php echo esc_html( $heading ); ?></h2>
    <?php endif; ?>
    <div class="ai:mt-12 ai:grid ai:gap-8 ai:lg:grid-cols-3">
      <?php foreach ( $items as $item ) : ?>
      <figure class="ai:m-0 ai:flex ai:flex-col ai:rounded-card ai:border ai:border-line ai:bg-surface ai:p-8 ai:shadow-sm">
        <div class="<?php echo esc_attr( $stars ); ?>" aria-hidden="true">
          <?php for ( $i = 0; $i < 5; $i++ ) { ai_e_icon( 'star', 5 ); } ?>
        </div>
        <blockquote class="ai:m-0 ai:mt-5 ai:flex-1 ai:border-0 ai:p-0 ai:text-base/7 ai:text-muted">
          <p class="ai:m-0 ai:text-muted">&ldquo;<?php echo esc_html( $item['quote'] ?? '' ); ?>&rdquo;</p>
        </blockquote>
        <figcaption class="ai:mt-6 ai:flex ai:items-center ai:gap-3">
          <?php if ( ! empty( $item['image'] ) ) : ?>
          <img alt="" src="<?php echo esc_url( $item['image'] ); ?>" class="ai:block ai:size-11 ai:max-w-full ai:rounded-full ai:object-cover" />
          <?php else : ?>
          <span class="<?php echo esc_attr( $avatar ); ?>"><?php echo esc_html( ai_initials( $item['name'] ?? '' ) ); ?></span>
          <?php endif; ?>
          <div>
            <div class="ai:text-sm ai:font-semibold ai:text-ink"><?php echo esc_html( $item['name'] ?? '' ); ?></div>
            <?php if ( ! empty( $item['role'] ) ) : ?><div class="ai:text-sm ai:text-muted"><?php echo esc_html( $item['role'] ); ?></div><?php endif; ?>
          </div>
        </figcaption>
      </figure>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php elseif ( 'centered' === $variant ) : ?>
<section class="ai-c ai:bg-surface-alt ai:py-sec-sm ai:sm:py-sec">
  <div class="ai:mx-auto ai:max-w-3xl ai:px-4 ai:text-center ai:sm:px-6 ai:lg:px-8">
    <?php if ( $heading ) : ?>
    <h2 class="ai:m-0 ai:text-3xl ai:font-semibold ai:tracking-tight ai:text-balance ai:text-ink ai:sm:text-4xl"><?php echo esc_html( $heading ); ?></h2>
    <?php endif; ?>
    <figure class="ai:m-0">
      <div class="ai:mt-8 ai:flex ai:justify-center ai:gap-1 ai:text-amber-400" aria-hidden="true">
        <?php for ( $i = 0; $i < 5; $i++ ) { ai_e_icon( 'star', 5 ); } ?>
      </div>
      <blockquote class="ai:m-0 ai:mt-6 ai:border-0 ai:p-0">
        <p class="ai:m-0 ai:text-2xl ai:font-medium ai:text-balance ai:text-ink ai:sm:text-3xl">&ldquo;<?php echo esc_html( $one['quote'] ?? '' ); ?>&rdquo;</p>
      </blockquote>
      <figcaption class="ai:mt-8 ai:flex ai:items-center ai:justify-center ai:gap-3">
        <?php if ( ! empty( $one['image'] ) ) : ?>
        <img alt="" src="<?php echo esc_url( $one['image'] ); ?>" class="ai:block ai:size-11 ai:max-w-full ai:rounded-full ai:object-cover" />
        <?php else : ?>
        <span class="<?php echo esc_attr( $avatar ); ?>"><?php echo esc_html( ai_initials( $one['name'] ?? '' ) ); ?></span>
        <?php endif; ?>
        <div class="ai:text-left">
          <div class="ai:text-sm ai:font-semibold ai:text-ink"><?php echo esc_html( $one['name'] ?? '' ); ?></div>
          <?php if ( ! empty( $one['role'] ) ) : ?><div class="ai:text-sm ai:text-muted"><?php echo esc_html( $one['role'] ); ?></div><?php endif; ?>
        </div>
      </figcaption>
    </figure>
  </div>
</section>

<?php else : ?>
<section class="ai-c ai:bg-surface ai:py-sec-sm ai:sm:py-sec">
  <div class="ai:mx-auto ai:grid ai:max-w-wide ai:items-center ai:gap-10 ai:px-4 ai:sm:px-6 ai:lg:grid-cols-2 ai:lg:gap-16 ai:lg:px-8">
    <?php if ( $image ) : ?>
    <img alt="" src="<?php echo esc_url( $image ); ?>" class="ai:block ai:h-full ai:max-h-96 ai:w-full ai:rounded-card ai:object-cover" />
    <?php else : ?>
    <div class="ai:min-h-72 ai:rounded-card ai:bg-brand-100" aria-hidden="true"></div>
    <?php endif; ?>
    <figure class="ai:m-0">
      <?php if ( $heading ) : ?>
      <h2 class="ai:m-0 ai:text-sm ai:font-semibold ai:tracking-wide ai:text-brand-600 ai:uppercase"><?php echo esc_html( $heading ); ?></h2>
      <?php endif; ?>
      <div class="ai:mt-4 ai:flex ai:gap-1 ai:text-amber-400" aria-hidden="true">
        <?php for ( $i = 0; $i < 5; $i++ ) { ai_e_icon( 'star', 5 ); } ?>
      </div>
      <blockquote class="ai:m-0 ai:mt-5 ai:border-0 ai:p-0">
        <p class="ai:m-0 ai:text-xl ai:font-medium ai:text-pretty ai:text-ink ai:sm:text-2xl">&ldquo;<?php echo esc_html( $one['quote'] ?? '' ); ?>&rdquo;</p>
      </blockquote>
      <figcaption class="ai:mt-7 ai:flex ai:items-center ai:gap-3">
        <?php if ( ! empty( $one['image'] ) ) : ?>
        <img alt="" src="<?php echo esc_url( $one['image'] ); ?>" class="ai:block ai:size-11 ai:max-w-full ai:rounded-full ai:object-cover" />
        <?php else : ?>
        <span class="<?php echo esc_attr( $avatar ); ?>"><?php echo esc_html( ai_initials( $one['name'] ?? '' ) ); ?></span>
        <?php endif; ?>
        <div>
          <div class="ai:text-sm ai:font-semibold ai:text-ink"><?php echo esc_html( $one['name'] ?? '' ); ?></div>
          <?php if ( ! empty( $one['role'] ) ) : ?><div class="ai:text-sm ai:text-muted"><?php echo esc_html( $one['role'] ); ?></div><?php endif; ?>
        </div>
      </figcaption>
    </figure>
  </div>
</section>
<?php endif; ?>
