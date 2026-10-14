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

$pill  = ai_cls( 'm-0 mb-6 inline-flex items-center gap-2 rounded-full bg-brand-50 px-3 py-1 text-sm font-medium text-brand-700 ring-1 ring-inset ring-brand-200' );
$lede  = ai_cls( 'mt-6 mb-0 max-w-xl text-lg/8 text-pretty text-muted' );
$note  = ai_cls( 'mt-6 mb-0 text-sm text-subtle' );

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
<section class="ai-c relative overflow-hidden bg-surface-alt py-sec-sm sm:py-sec">
  <!-- a soft brand glow behind the message: depth without a background image to load -->
  <div class="pointer-events-none absolute -top-40 left-1/2 size-[42rem] -translate-x-1/2 rounded-full bg-brand-100 opacity-60 blur-3xl" aria-hidden="true"></div>
  <div class="relative mx-auto max-w-wide px-4 text-center sm:px-6 lg:px-8">
    <?php if ( $eyebrow ) : ?><p class="<?php echo esc_attr( $pill ); ?>"><?php echo esc_html( $eyebrow ); ?></p><?php endif; ?>
    <h1 class="mx-auto m-0 max-w-4xl text-4xl font-bold tracking-tight text-balance text-ink sm:text-5xl lg:text-6xl"><?php echo esc_html( $heading ); ?></h1>
    <?php if ( $text ) : ?>
    <p class="mx-auto mt-6 mb-0 max-w-2xl text-lg/8 text-pretty text-muted"><?php echo esc_html( $text ); ?></p>
    <?php endif; ?>
    <div class="mt-10 flex flex-wrap items-center justify-center gap-3"><?php ai_hero_buttons( $args ); ?></div>
    <?php if ( $proof ) : ?><p class="<?php echo esc_attr( $note ); ?>"><?php echo esc_html( $proof ); ?></p><?php endif; ?>

    <?php if ( $image ) : ?>
    <div class="relative mx-auto mt-16 max-w-4xl">
      <img alt="" src="<?php echo esc_url( $image ); ?>" class="block aspect-16/9 h-auto w-full max-w-full rounded-band object-cover shadow-2xl ring-1 ring-ink/10" />
    </div>
    <?php endif; ?>

    <?php if ( $items ) : ?>
    <dl class="m-0 mt-16 grid gap-10 border-t border-line pt-10 sm:grid-cols-3">
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

<?php else : ?>
<section class="ai-c relative overflow-hidden bg-surface py-sec-sm sm:py-sec">
  <div class="mx-auto grid max-w-wide items-center gap-12 px-4 sm:px-6 lg:grid-cols-2 lg:gap-16 lg:px-8">
    <div>
      <?php if ( $eyebrow ) : ?><p class="<?php echo esc_attr( $pill ); ?>"><?php echo esc_html( $eyebrow ); ?></p><?php endif; ?>
      <h1 class="m-0 text-4xl font-bold tracking-tight text-balance text-ink sm:text-5xl lg:text-6xl"><?php echo esc_html( $heading ); ?></h1>
      <?php if ( $text ) : ?><p class="<?php echo esc_attr( $lede ); ?>"><?php echo esc_html( $text ); ?></p><?php endif; ?>
      <div class="mt-10 flex flex-wrap items-center gap-3"><?php ai_hero_buttons( $args ); ?></div>
      <?php if ( $proof ) : ?><p class="<?php echo esc_attr( $note ); ?>"><?php echo esc_html( $proof ); ?></p><?php endif; ?>

      <?php if ( $items ) : ?>
      <dl class="m-0 mt-12 grid max-w-lg grid-cols-3 gap-6 border-t border-line pt-8">
        <?php foreach ( array_slice( $items, 0, 3 ) as $item ) : ?>
        <div class="m-0">
          <dt class="m-0 text-2xl font-bold tracking-tight text-ink"><?php echo esc_html( $item['title'] ?? '' ); ?></dt>
          <dd class="m-0 mt-1 text-xs/5 text-muted"><?php echo esc_html( $item['text'] ?? '' ); ?></dd>
        </div>
        <?php endforeach; ?>
      </dl>
      <?php endif; ?>
    </div>

    <?php if ( $image ) : ?>
    <div class="relative">
      <!-- the picture sits on a brand panel, offset: it reads as designed rather than dropped in -->
      <div class="absolute -right-6 -bottom-6 size-2/3 rounded-band bg-brand-100" aria-hidden="true"></div>
      <div class="absolute -top-6 -left-6 size-1/3 rounded-band bg-surface-alt" aria-hidden="true"></div>
      <img alt="" src="<?php echo esc_url( $image ); ?>" class="relative block aspect-4/3 h-auto w-full max-w-full rounded-band object-cover shadow-2xl ring-1 ring-ink/10" />
    </div>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>
