<?php
/**
 * Component: hero
 * Params: variant (split|spotlight), eyebrow, heading, text, primary_label, primary_url, secondary_label,
 *         secondary_url, image, proof, items [ title, text ]  (items = the figures under a spotlight hero)
 * Shortcode: [ai_hero variant="split" eyebrow="New" heading="..." text="..." primary_label="Start" primary_url="/" image="https://..."]
 *   split     - the message on the left, a picture on the right, layered on a soft brand panel (default)
 *   spotlight - centred message on a tinted panel, with the figures that prove it underneath
 *
 * "proof" is one line of reassurance under the buttons (e.g. "Trusted by 150 manufacturers - no card needed").
 */
$variant   = in_array( $args['variant'] ?? 'split', array( 'split', 'spotlight' ), true ) ? $args['variant'] : 'split';
$eyebrow   = $args['eyebrow'] ?? '';
$heading   = $args['heading'] ?? '';
$text      = $args['text'] ?? '';
$image     = $args['image'] ?? '';
$proof     = $args['proof'] ?? '';
$items     = ai_items( $args );

$pill  = ai_cls( 'ai:m-0 ai:mb-6 ai:inline-flex ai:items-center ai:gap-2 ai:rounded-full ai:bg-brand-50 ai:px-3 ai:py-1 ai:text-sm ai:font-medium ai:text-brand-700 ai:ring-1 ai:ring-inset ai:ring-brand-200' );
$lede  = ai_cls( 'ai:mt-6 ai:mb-0 ai:max-w-xl ai:text-lg/8 ai:text-pretty ai:text-muted' );
$note  = ai_cls( 'ai:mt-6 ai:mb-0 ai:text-sm ai:text-subtle' );

/** The buttons, identical in both layouts. */
function ai_hero_buttons( $args ) {
	if ( ! empty( $args['primary_label'] ) ) {
		ai_component( 'button', array( 'label' => $args['primary_label'], 'url' => $args['primary_url'] ?? '#', 'size' => 'lg' ) );
	}
	if ( ! empty( $args['secondary_label'] ) ) {
		ai_component( 'button', array( 'label' => $args['secondary_label'], 'url' => $args['secondary_url'] ?? '#', 'size' => 'lg', 'variant' => 'outline', 'icon' => 'none' ) );
	}
}
?>
<?php if ( 'spotlight' === $variant ) : ?>
<section class="ai-c ai:relative ai:overflow-hidden ai:bg-surface-alt ai:py-sec-sm ai:sm:py-sec">
  <!-- a soft brand glow behind the message: depth without a background image to load -->
  <div class="ai:pointer-events-none ai:absolute ai:-top-40 ai:left-1/2 ai:size-[42rem] ai:-translate-x-1/2 ai:rounded-full ai:bg-brand-100 ai:opacity-60 ai:blur-3xl" aria-hidden="true"></div>
  <div class="ai:relative ai:mx-auto ai:max-w-wide ai:px-4 ai:text-center ai:sm:px-6 ai:lg:px-8">
    <?php if ( $eyebrow ) : ?><p class="<?php echo esc_attr( $pill ); ?>"><?php echo esc_html( $eyebrow ); ?></p><?php endif; ?>
    <h1 class="ai:mx-auto ai:m-0 ai:max-w-4xl ai:text-4xl ai:font-bold ai:tracking-tight ai:text-balance ai:text-ink ai:sm:text-5xl ai:lg:text-6xl"><?php echo esc_html( $heading ); ?></h1>
    <?php if ( $text ) : ?>
    <p class="ai:mx-auto ai:mt-6 ai:mb-0 ai:max-w-2xl ai:text-lg/8 ai:text-pretty ai:text-muted"><?php echo esc_html( $text ); ?></p>
    <?php endif; ?>
    <div class="ai:mt-10 ai:flex ai:flex-wrap ai:items-center ai:justify-center ai:gap-3"><?php ai_hero_buttons( $args ); ?></div>
    <?php if ( $proof ) : ?><p class="<?php echo esc_attr( $note ); ?>"><?php echo esc_html( $proof ); ?></p><?php endif; ?>

    <?php if ( $image ) : ?>
    <div class="ai:relative ai:mx-auto ai:mt-16 ai:max-w-4xl">
      <img alt="" src="<?php echo esc_url( $image ); ?>" class="ai:block ai:aspect-16/9 ai:h-auto ai:w-full ai:max-w-full ai:rounded-band ai:object-cover ai:shadow-2xl ai:ring-1 ai:ring-ink/10" />
    </div>
    <?php endif; ?>

    <?php if ( $items ) : ?>
    <dl class="ai:m-0 ai:mt-16 ai:grid ai:gap-10 ai:border-t ai:border-line ai:pt-10 ai:sm:grid-cols-3">
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

<?php else : ?>
<section class="ai-c ai:relative ai:overflow-hidden ai:bg-surface ai:py-sec-sm ai:sm:py-sec">
  <div class="ai:mx-auto ai:grid ai:max-w-wide ai:items-center ai:gap-12 ai:px-4 ai:sm:px-6 ai:lg:grid-cols-2 ai:lg:gap-16 ai:lg:px-8">
    <div>
      <?php if ( $eyebrow ) : ?><p class="<?php echo esc_attr( $pill ); ?>"><?php echo esc_html( $eyebrow ); ?></p><?php endif; ?>
      <h1 class="ai:m-0 ai:text-4xl ai:font-bold ai:tracking-tight ai:text-balance ai:text-ink ai:sm:text-5xl ai:lg:text-6xl"><?php echo esc_html( $heading ); ?></h1>
      <?php if ( $text ) : ?><p class="<?php echo esc_attr( $lede ); ?>"><?php echo esc_html( $text ); ?></p><?php endif; ?>
      <div class="ai:mt-10 ai:flex ai:flex-wrap ai:items-center ai:gap-3"><?php ai_hero_buttons( $args ); ?></div>
      <?php if ( $proof ) : ?><p class="<?php echo esc_attr( $note ); ?>"><?php echo esc_html( $proof ); ?></p><?php endif; ?>

      <?php if ( $items ) : ?>
      <dl class="ai:m-0 ai:mt-12 ai:grid ai:max-w-lg ai:grid-cols-3 ai:gap-6 ai:border-t ai:border-line ai:pt-8">
        <?php foreach ( array_slice( $items, 0, 3 ) as $item ) : ?>
        <div class="ai:m-0">
          <dt class="ai:m-0 ai:text-2xl ai:font-bold ai:tracking-tight ai:text-ink"><?php echo esc_html( $item['title'] ?? '' ); ?></dt>
          <dd class="ai:m-0 ai:mt-1 ai:text-xs/5 ai:text-muted"><?php echo esc_html( $item['text'] ?? '' ); ?></dd>
        </div>
        <?php endforeach; ?>
      </dl>
      <?php endif; ?>
    </div>

    <?php if ( $image ) : ?>
    <div class="ai:relative">
      <!-- the picture sits on a brand panel, offset: it reads as designed rather than dropped in -->
      <div class="ai:absolute ai:-right-6 ai:-bottom-6 ai:size-2/3 ai:rounded-band ai:bg-brand-100" aria-hidden="true"></div>
      <div class="ai:absolute ai:-top-6 ai:-left-6 ai:size-1/3 ai:rounded-band ai:bg-surface-alt" aria-hidden="true"></div>
      <img alt="" src="<?php echo esc_url( $image ); ?>" class="ai:relative ai:block ai:aspect-4/3 ai:h-auto ai:w-full ai:max-w-full ai:rounded-band ai:object-cover ai:shadow-2xl ai:ring-1 ai:ring-ink/10" />
    </div>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>
