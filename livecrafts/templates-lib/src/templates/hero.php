<?php
/**
 * Component: hero
 * Params: variant (split|spotlight|showcase|panel|proof|signup|reveal), eyebrow, heading, text, primary_label,
 *         primary_url, secondary_label, secondary_url, image, proof, action, field_name, placeholder,
 *         button_label, stat_value, stat_label, video_url, video_label, items [ title, text, icon, name ]
 * Shortcode: [ai_hero variant="split" eyebrow="New" heading="..." text="..." primary_label="Start" primary_url="/" image="https://..."]
 *   split     - the message on the left, a picture on the right, layered on a soft brand panel (default)
 *   spotlight - centred message on a tinted panel, with the figures that prove it underneath
 *   showcase  - a tall picture beside a centred message on a deep brand panel: for one strong product shot
 *   panel     - message and picture inside one deep card, with an optional strip of customer names under it
 *   proof     - centred message on a deep panel, the reasons to trust it in a row, the picture below
 *   signup    - a full-height picture beside the message and ONE email field: the shortest path to a lead
 *   reveal    - centred message on a deep panel with a wide picture that overlaps its lower edge
 *
 * "proof" is one line of reassurance under the buttons (e.g. "Trusted by 150 manufacturers - no card needed").
 * "items" means something different per layout, which is why each one says so:
 *   split / spotlight - the figures  [ title = the number, text = what it counts ]
 *   proof             - the reasons  [ icon, title ]
 *   panel             - the names    [ name ]
 *
 * The deep layouts (showcase, panel, proof, reveal) sit on bg-brand-900, a darkened brand colour, so they stay
 * readable whatever brand the site sets - there is no fixed navy anywhere.
 */
$variant   = in_array( $args['variant'] ?? 'split', array( 'split', 'spotlight', 'showcase', 'panel', 'proof', 'signup', 'reveal' ), true ) ? $args['variant'] : 'split';
$eyebrow   = $args['eyebrow'] ?? '';
$heading   = $args['heading'] ?? '';
$text      = $args['text'] ?? '';
$image     = $args['image'] ?? '';
$proof     = $args['proof'] ?? '';
$items     = ai_items( $args );

$pill  = ai_cls( 'm-0 mb-6 inline-flex items-center gap-2 rounded-full bg-brand-50 px-3 py-1 text-sm font-medium text-brand-700 ring-1 ring-inset ring-brand-200' );
$lede  = ai_cls( 'mt-6 mb-0 max-w-xl text-lg/8 text-pretty text-muted' );
$note  = ai_cls( 'mt-6 mb-0 text-sm text-subtle' );

/* On a deep panel the type is light: these are the same three roles, inverted. */
$d_eyebrow = ai_cls( 'm-0 mb-4 text-sm font-semibold tracking-widest uppercase text-brand-200' );
$d_heading = ai_cls( 'm-0 text-4xl font-bold tracking-tight text-balance text-white sm:text-5xl' );
$d_lede    = ai_cls( 'mt-5 mb-0 text-lg/8 text-pretty text-white/70' );
$d_note    = ai_cls( 'mt-6 mb-0 text-sm text-white/60' );

/**
 * The buttons, in every layout. Guarded: load_template() includes this file again for a second hero on the same
 * page, and an unguarded function would be declared twice and stop the page.
 */
if ( ! function_exists( 'ai_hero_buttons' ) ) {
	/** $tone "ink" on a pale background, "light" on a deep one (the outline button would vanish otherwise). */
	function ai_hero_buttons( $args, $tone = 'ink' ) {
		if ( ! empty( $args['primary_label'] ) ) {
			ai_component( 'button', array( 'label' => $args['primary_label'], 'url' => $args['primary_url'] ?? '#', 'size' => 'lg' ) );
		}
		if ( ! empty( $args['secondary_label'] ) ) {
			ai_component( 'button', array(
				'label'   => $args['secondary_label'],
				'url'     => $args['secondary_url'] ?? '#',
				'size'    => 'lg',
				'variant' => 'light' === $tone ? 'ghost-light' : 'outline',
				'icon'    => 'none',
			) );
		}
	}
}

if ( ! function_exists( 'ai_hero_glow' ) ) {
	/**
	 * A soft brand glow behind the message: depth with no image to download.
	 * One constant string, never concatenated - the build prefixes the class attribute where it finds it, and a
	 * joined one would hand it the surrounding PHP as class names.
	 */
	function ai_hero_glow() {
		echo '<div class="pointer-events-none absolute -top-24 left-1/2 size-[36rem] -translate-x-1/2 rounded-full bg-brand-500 opacity-20 blur-3xl" aria-hidden="true"></div>';
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

<?php elseif ( 'showcase' === $variant ) : ?>
<?php
/* A class attribute is either wholly static or wholly PHP: the build prefixes the literal, so the two may not mix. */
$sc_card = ai_cls( 'relative flex flex-col items-center justify-center overflow-hidden rounded-band p-8 text-center ring-1 ring-inset ring-white/10 sm:p-14' )
	. ' ' . ( $image ? ai_cls( 'lg:col-span-2' ) : ai_cls( 'lg:col-span-3' ) );
?>
<!-- A tall product shot holds one third; the message sits centred in the panel beside it. -->
<section class="ai-c bg-brand-900 py-sec-sm sm:py-sec">
  <div class="mx-auto max-w-wide px-4 sm:px-6 lg:px-8">
    <div class="grid gap-6 lg:grid-cols-3">
      <?php if ( $image ) : ?>
      <img alt="" src="<?php echo esc_url( $image ); ?>" class="block h-80 w-full max-w-full rounded-band object-cover lg:h-auto lg:self-stretch" />
      <?php endif; ?>
      <div class="<?php echo esc_attr( $sc_card ); ?>">
        <?php ai_hero_glow(); ?>
        <div class="relative max-w-2xl">
          <?php if ( $eyebrow ) : ?><p class="<?php echo esc_attr( $d_eyebrow ); ?>"><?php echo esc_html( $eyebrow ); ?></p><?php endif; ?>
          <h1 class="<?php echo esc_attr( $d_heading ); ?>"><?php echo esc_html( $heading ); ?></h1>
          <?php if ( $text ) : ?><p class="<?php echo esc_attr( $d_lede ); ?>"><?php echo esc_html( $text ); ?></p><?php endif; ?>
          <div class="mt-8 flex flex-wrap items-center justify-center gap-3"><?php ai_hero_buttons( $args, 'light' ); ?></div>
          <?php if ( $proof ) : ?><p class="<?php echo esc_attr( $d_note ); ?>"><?php echo esc_html( $proof ); ?></p><?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<?php elseif ( 'panel' === $variant ) : ?>
<!-- Message and picture share one card, so the hero reads as a single object on the page. -->
<section class="ai-c bg-brand-900 py-sec-sm sm:py-sec">
  <div class="mx-auto max-w-wide px-4 sm:px-6 lg:px-8">
    <div class="relative overflow-hidden rounded-band p-8 ring-1 ring-inset ring-white/10 sm:p-12">
      <?php ai_hero_glow(); ?>
      <div class="relative grid items-center gap-10 lg:grid-cols-2 lg:gap-16">
        <div>
          <?php if ( $eyebrow ) : ?><p class="<?php echo esc_attr( $d_eyebrow ); ?>"><?php echo esc_html( $eyebrow ); ?></p><?php endif; ?>
          <h1 class="<?php echo esc_attr( $d_heading ); ?>"><?php echo esc_html( $heading ); ?></h1>
          <?php if ( $text ) : ?><p class="<?php echo esc_attr( $d_lede ); ?>"><?php echo esc_html( $text ); ?></p><?php endif; ?>
          <div class="mt-8 flex flex-wrap items-center gap-3"><?php ai_hero_buttons( $args, 'light' ); ?></div>
          <?php if ( $proof ) : ?><p class="<?php echo esc_attr( $d_note ); ?>"><?php echo esc_html( $proof ); ?></p><?php endif; ?>
        </div>
        <?php if ( $image ) : ?>
        <div class="overflow-hidden rounded-card bg-white">
          <img alt="" src="<?php echo esc_url( $image ); ?>" class="block h-auto w-full max-w-full object-cover" />
        </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ( $items ) : ?>
    <!-- The names under the card: who already trusts this. Text, so no logo files have to be found. -->
    <ul class="m-0 mt-6 flex flex-wrap items-center justify-center gap-x-12 gap-y-4 rounded-band px-8 py-8 ring-1 ring-inset ring-white/10">
      <?php foreach ( $items as $item ) : ?>
      <li class="m-0 text-lg font-semibold tracking-tight text-white/50"><?php echo esc_html( $item['name'] ?? $item['title'] ?? '' ); ?></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </div>
</section>

<?php elseif ( 'proof' === $variant ) : ?>
<!-- Centred message, then the three reasons to believe it, then the picture. -->
<section class="ai-c relative overflow-hidden bg-brand-900 py-sec-sm sm:py-sec">
  <?php ai_hero_glow(); ?>
  <div class="relative mx-auto max-w-wide px-4 text-center sm:px-6 lg:px-8">
    <?php if ( $eyebrow ) : ?><p class="<?php echo esc_attr( $d_eyebrow ); ?>"><?php echo esc_html( $eyebrow ); ?></p><?php endif; ?>
    <h1 class="mx-auto m-0 max-w-3xl text-4xl font-bold tracking-tight text-balance text-white sm:text-6xl"><?php echo esc_html( $heading ); ?></h1>
    <?php if ( $text ) : ?>
    <p class="mx-auto mt-5 mb-0 max-w-2xl text-lg/8 text-pretty text-white/70"><?php echo esc_html( $text ); ?></p>
    <?php endif; ?>
    <div class="mt-10 flex flex-wrap items-center justify-center gap-3"><?php ai_hero_buttons( $args, 'light' ); ?></div>
    <?php if ( $proof ) : ?><p class="<?php echo esc_attr( $d_note ); ?>"><?php echo esc_html( $proof ); ?></p><?php endif; ?>

    <?php if ( $items ) : ?>
    <ul class="m-0 mx-auto mt-14 grid max-w-4xl gap-8 text-left sm:grid-cols-3">
      <?php foreach ( array_slice( $items, 0, 3 ) as $item ) : ?>
      <li class="m-0 flex items-start gap-3">
        <span class="mt-0.5 text-brand-200"><?php ai_e_icon( $item['icon'] ?? 'check-circle', 5 ); ?></span>
        <span class="text-sm/6 text-white/80"><?php echo esc_html( $item['title'] ?? $item['text'] ?? '' ); ?></span>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <?php if ( $image ) : ?>
    <div class="relative mx-auto mt-16 max-w-5xl">
      <img alt="" src="<?php echo esc_url( $image ); ?>" class="block h-auto w-full max-w-full rounded-band object-cover shadow-2xl ring-1 ring-white/10" />
    </div>
    <?php endif; ?>
  </div>
</section>

<?php elseif ( 'signup' === $variant ) : ?>
<?php
$action      = $args['action'] ?? '';
$field_name  = $args['field_name'] ?? 'email';
$placeholder = $args['placeholder'] ?? 'Enter your email';
$btn_label   = $args['button_label'] ?? ( $args['primary_label'] ?? 'Get started' );
$stat_value  = $args['stat_value'] ?? '';
$stat_label  = $args['stat_label'] ?? '';
$su_col      = ai_cls( 'order-1 flex w-full items-center justify-center px-4 py-sec-sm sm:px-6 lg:order-2 lg:px-12' )
	. ' ' . ( $image ? ai_cls( 'lg:w-7/12' ) : ai_cls( 'lg:w-full' ) );
?>
<!-- Picture and message side by side, full height, with one field: the shortest path from reading to signing up. -->
<section class="ai-c bg-surface-alt">
  <div class="flex flex-col lg:min-h-[36rem] lg:flex-row lg:items-stretch">
    <?php if ( $image ) : ?>
    <div class="relative order-2 h-80 w-full overflow-hidden lg:order-1 lg:h-auto lg:w-5/12">
      <img alt="" src="<?php echo esc_url( $image ); ?>" class="absolute inset-0 block size-full object-cover" />
      <?php if ( $stat_value ) : ?>
      <!-- the figure sits on the picture, over a gradient so it stays readable on any photograph -->
      <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 to-transparent p-6 sm:p-8">
        <p class="m-0 text-5xl font-bold tracking-tight text-white"><?php echo esc_html( $stat_value ); ?></p>
        <?php if ( $stat_label ) : ?><p class="m-0 mt-1 max-w-xs text-sm text-white/80"><?php echo esc_html( $stat_label ); ?></p><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="<?php echo esc_attr( $su_col ); ?>">
      <div class="w-full max-w-xl">
        <?php if ( $eyebrow ) : ?><p class="<?php echo esc_attr( $pill ); ?>"><?php echo esc_html( $eyebrow ); ?></p><?php endif; ?>
        <h1 class="m-0 text-4xl font-bold tracking-tight text-balance text-ink sm:text-5xl lg:text-6xl"><?php echo esc_html( $heading ); ?></h1>
        <?php if ( $text ) : ?><p class="<?php echo esc_attr( $lede ); ?>"><?php echo esc_html( $text ); ?></p><?php endif; ?>

        <?php if ( $action ) : ?>
        <form action="<?php echo esc_url( $action ); ?>" method="post" class="mt-8">
          <div class="flex flex-col gap-3 sm:flex-row">
            <label for="ai-hero-signup" class="sr-only"><?php echo esc_html( $placeholder ); ?></label>
            <input id="ai-hero-signup" type="email" required name="<?php echo esc_attr( $field_name ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>"
              class="w-full min-w-0 flex-1 rounded-lg bg-surface px-4 py-3 text-base text-ink ring-1 ring-inset ring-line focus:outline-2 focus:outline-offset-2 focus:outline-brand-600" />
            <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-brand-600 px-6 py-3 text-base font-semibold text-white transition-colors hover:bg-brand-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600">
              <?php echo esc_html( $btn_label ); ?>
            </button>
          </div>
        </form>
        <?php else : ?>
        <div class="mt-8 flex flex-wrap items-center gap-3"><?php ai_hero_buttons( $args ); ?></div>
        <?php endif; ?>

        <?php if ( $proof ) : ?><p class="<?php echo esc_attr( $note ); ?>"><?php echo esc_html( $proof ); ?></p><?php endif; ?>
      </div>
    </div>
  </div>
</section>

<?php elseif ( 'reveal' === $variant ) : ?>
<?php
$video_url   = $args['video_url'] ?? '';
$video_label = $args['video_label'] ?? 'Play trailer';
/* With a picture the panel keeps room at the bottom for it to sit over; without one it closes up normally. */
$rv_panel    = ai_cls( 'relative overflow-hidden rounded-band bg-brand-900 px-6 pt-sec-sm text-center sm:px-12 sm:pt-sec' )
	. ' ' . ( $image ? ai_cls( 'pb-40 sm:pb-56' ) : ai_cls( 'pb-sec-sm sm:pb-sec' ) );
?>
<!-- The picture overlaps the panel's lower edge. The overlap is INSIDE this section, so the sections around it
     keep their own spacing and nothing is clipped by a theme that sets overflow on the content wrapper. -->
<section class="ai-c bg-surface pb-sec-sm sm:pb-sec">
  <div class="mx-auto max-w-wide px-4 sm:px-6 lg:px-8">
    <div class="<?php echo esc_attr( $rv_panel ); ?>">
      <?php ai_hero_glow(); ?>
      <div class="relative mx-auto max-w-2xl">
        <?php if ( $eyebrow ) : ?><p class="<?php echo esc_attr( $d_eyebrow ); ?>"><?php echo esc_html( $eyebrow ); ?></p><?php endif; ?>
        <h1 class="m-0 text-5xl font-bold tracking-tight text-balance text-white sm:text-7xl"><?php echo esc_html( $heading ); ?></h1>
        <?php if ( $text ) : ?><p class="<?php echo esc_attr( $d_lede ); ?>"><?php echo esc_html( $text ); ?></p><?php endif; ?>
        <div class="mt-8 flex flex-wrap items-center justify-center gap-3"><?php ai_hero_buttons( $args, 'light' ); ?></div>
        <?php if ( $proof ) : ?><p class="<?php echo esc_attr( $d_note ); ?>"><?php echo esc_html( $proof ); ?></p><?php endif; ?>
      </div>
    </div>

    <?php if ( $image ) : ?>
    <!-- pulled up over the panel it just left: one object straddling two bands -->
    <div class="relative z-10 mx-auto -mt-32 max-w-4xl sm:-mt-44">
      <div class="relative overflow-hidden rounded-band shadow-2xl ring-1 ring-ink/10">
        <img alt="" src="<?php echo esc_url( $image ); ?>" class="block aspect-16/9 h-auto w-full max-w-full object-cover" />
        <?php if ( $video_url ) : ?>
        <!-- a link, not a player: no JavaScript ships to the site -->
        <a href="<?php echo esc_url( $video_url ); ?>" class="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-black/30 no-underline transition-colors hover:bg-black/40">
          <span class="flex size-16 items-center justify-center rounded-full ring-2 ring-inset ring-white/70">
            <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6 text-white"><path d="M8.04 4.92A.9.9 0 0 0 6.7 5.7v12.6a.9.9 0 0 0 1.34.78l10.37-6.3a.9.9 0 0 0 0-1.56L8.04 4.92Z" /></svg>
          </span>
          <span class="text-base font-semibold text-white"><?php echo esc_html( $video_label ); ?></span>
        </a>
        <?php endif; ?>
      </div>
    </div>
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
