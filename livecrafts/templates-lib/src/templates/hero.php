<?php
/**
 * Component: hero
 * Params: variant (split|spotlight|showcase|panel|proof|signup|reveal), eyebrow, heading, accent, text,
 *         primary_label, primary_url, secondary_label, secondary_url, image, proof, action, field_name,
 *         placeholder, button_label, stat_value, stat_label, video_url, video_label, items [ title, text, icon, name ]
 * Shortcode: [ai_hero variant="split" eyebrow="New" heading="..." text="..." primary_label="Start" primary_url="/" image="https://..."]
 *   split     - message left, picture right, on the page's own background (default)
 *   spotlight - centred on a tinted panel, a soft brand glow behind it, the figures underneath
 *   showcase  - a TALL picture butted against a deep panel, message left-aligned beside it
 *   panel     - a light bordered card: message left, picture flush to the card's edge, customer names inside
 *   proof     - the loud one: the full brand colour, the largest type, three reasons, no picture at all
 *   signup    - full-bleed photograph with a figure on it, message and ONE email field beside it
 *   reveal    - editorial: a serif line in the heading, then a wide picture straddling a deep band
 *
 * WHY THEY LOOK DIFFERENT, AND WHY THAT TOOK A SECOND PASS
 * The first version of these varied one thing each - where the picture sat - and kept the same panel, the same
 * eyebrow pill, the same two buttons and the same type size throughout. Rendered without a picture they were
 * indistinguishable, and even with one they read as the same section seven times. A layout is only a real choice
 * when it differs on SEVERAL axes at once, so each one here picks its own:
 *
 *   variant    background        type                        composition              what carries the proof
 *   split      surface           4xl-6xl sans                two columns              figures under the text
 *   spotlight  surface-alt+glow  4xl-6xl sans, centred       one column               figures under a rule
 *   showcase   brand-900         4xl-6xl sans, left          2/5 tall image + panel   one line of reassurance
 *   panel      surface-alt       3xl-5xl sans                bordered card, flush img customer names in the card
 *   proof      brand-600         5xl-7xl sans, centred       one column, no image     three reasons with icons
 *   signup     surface           4xl-6xl sans, left          full-bleed half photo    a figure on the photograph
 *   reveal     surface + band    5xl-7xl with a serif line   centred, image straddles the picture itself
 *
 * "accent" is a short phrase the heading ends with, set apart: in the brand colour on most layouts, and in italic
 * serif on "reveal". It is what lets one line of copy carry a bit of character without a second font file.
 * (It is written inline in each layout: a class attribute built in PHP would be rewritten by the prefixer.)
 *
 * "proof" is one line of reassurance under the buttons (e.g. "Trusted by 150 manufacturers - no card needed").
 * "items" means something different per layout, which is why each one says so:
 *   split / spotlight - the figures  [ title = the number, text = what it counts ]
 *   proof             - the reasons  [ icon, title ]
 *   panel             - the names    [ name ]  (an item with no name is skipped, so figures never leak in here)
 *
 * Nothing carries a fixed colour: the deep layouts use brand-900 and brand-600, both mixed from the site's own
 * brand, so none of this is navy on a site that is not.
 */
$variant = in_array( $args['variant'] ?? 'split', array( 'split', 'spotlight', 'showcase', 'panel', 'proof', 'signup', 'reveal' ), true ) ? $args['variant'] : 'split';
$eyebrow = $args['eyebrow'] ?? '';
$heading = $args['heading'] ?? '';
$accent  = $args['accent'] ?? '';
$text    = $args['text'] ?? '';
$image   = $args['image'] ?? '';
$proof   = $args['proof'] ?? '';
$items   = ai_items( $args );

$pill = ai_cls( 'm-0 mb-6 inline-flex items-center gap-2 rounded-full bg-brand-50 px-3 py-1 text-sm font-medium text-brand-700 ring-1 ring-inset ring-brand-200' );
$lede = ai_cls( 'mt-6 mb-0 max-w-xl text-lg/8 text-pretty text-muted' );
$note = ai_cls( 'mt-6 mb-0 text-sm text-subtle' );

/* The same three roles inverted, for the layouts that sit on a deep panel. */
$d_eyebrow = ai_cls( 'm-0 mb-4 text-sm font-semibold tracking-widest uppercase text-brand-200' );
$d_lede    = ai_cls( 'mt-5 mb-0 text-lg/8 text-pretty text-white/70' );
$d_note    = ai_cls( 'mt-6 mb-0 text-sm text-white/60' );

if ( ! function_exists( 'ai_hero_buttons' ) ) {
	/**
	 * The buttons. Guarded: load_template() includes this file again for a second hero on the same page, and an
	 * unguarded function would be declared twice and stop the page.
	 *
	 * $tone "ink" on a pale background, "light" on a deep one (an outline button would vanish there). $only keeps
	 * the primary: the loud layouts say one thing, and a second button next to it halves the first one's weight.
	 */
	function ai_hero_buttons( $args, $tone = 'ink', $only = false ) {
		if ( ! empty( $args['primary_label'] ) ) {
			ai_component( 'button', array(
				'label'   => $args['primary_label'],
				'url'     => $args['primary_url'] ?? '#',
				'size'    => 'lg',
				'variant' => 'light' === $tone ? 'light' : 'solid',
			) );
		}
		if ( ! $only && ! empty( $args['secondary_label'] ) ) {
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

if ( ! function_exists( 'ai_hero_named' ) ) {
	/** Only the items that actually name somebody, so a set of figures never renders as a row of logos. */
	function ai_hero_named( $items ) {
		$out = array();
		foreach ( (array) $items as $i ) {
			if ( ! empty( $i['name'] ) ) {
				$out[] = $i['name'];
			}
		}
		return $out;
	}
}
?>
<?php if ( 'spotlight' === $variant ) : ?>
<section class="ai-c relative overflow-hidden bg-surface-alt py-sec-sm sm:py-sec">
  <!-- a soft brand glow behind the message: depth without a background image to load -->
  <div class="pointer-events-none absolute -top-40 left-1/2 size-[42rem] -translate-x-1/2 rounded-full bg-brand-100 opacity-60 blur-3xl" aria-hidden="true"></div>
  <div class="relative mx-auto max-w-wide px-4 text-center sm:px-6 lg:px-8">
    <?php if ( $eyebrow ) : ?><p class="<?php echo esc_attr( $pill ); ?>"><?php echo esc_html( $eyebrow ); ?></p><?php endif; ?>
    <h1 class="mx-auto m-0 max-w-4xl text-4xl font-bold tracking-tight text-balance text-ink sm:text-5xl lg:text-6xl"><?php echo esc_html( $heading ); ?><?php if ( $accent ) : ?> <span class="text-brand-600"><?php echo esc_html( $accent ); ?></span><?php endif; ?></h1>
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
/* The picture is TALL and butted against the panel, which is what makes this one recognisable at a glance: no
   other layout is two unequal columns of full height. Without a picture the panel simply takes the whole width. */
$sc_panel = ai_cls( 'relative flex flex-col justify-center overflow-hidden rounded-band p-8 ring-1 ring-inset ring-white/10 sm:p-14' )
	. ' ' . ( $image ? ai_cls( 'lg:col-span-3' ) : ai_cls( 'lg:col-span-5' ) );
?>
<section class="ai-c bg-brand-900 py-sec-sm sm:py-sec">
  <div class="mx-auto max-w-wide px-4 sm:px-6 lg:px-8">
    <div class="grid gap-5 lg:grid-cols-5">
      <?php if ( $image ) : ?>
      <div class="relative min-h-80 overflow-hidden rounded-band lg:col-span-2">
        <img alt="" src="<?php echo esc_url( $image ); ?>" class="absolute inset-0 block size-full object-cover" />
      </div>
      <?php endif; ?>
      <div class="<?php echo esc_attr( $sc_panel ); ?>">
        <div class="pointer-events-none absolute -top-24 -right-24 size-[28rem] rounded-full bg-brand-500 opacity-25 blur-3xl" aria-hidden="true"></div>
        <div class="relative max-w-xl">
          <?php if ( $eyebrow ) : ?>
          <p class="m-0 mb-5 flex items-center gap-3 text-xs font-semibold tracking-widest uppercase text-brand-200">
            <span class="h-px w-8 bg-brand-200/60"></span><?php echo esc_html( $eyebrow ); ?>
          </p>
          <?php endif; ?>
          <h1 class="m-0 text-4xl font-bold tracking-tight text-balance text-white sm:text-5xl lg:text-6xl"><?php echo esc_html( $heading ); ?><?php if ( $accent ) : ?> <span class="text-brand-300"><?php echo esc_html( $accent ); ?></span><?php endif; ?></h1>
          <?php if ( $text ) : ?><p class="<?php echo esc_attr( $d_lede ); ?>"><?php echo esc_html( $text ); ?></p><?php endif; ?>
          <div class="mt-8 flex flex-wrap items-center gap-3"><?php ai_hero_buttons( $args, 'light' ); ?></div>
          <?php if ( $proof ) : ?><p class="<?php echo esc_attr( $d_note ); ?>"><?php echo esc_html( $proof ); ?></p><?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<?php elseif ( 'panel' === $variant ) : ?>
<?php $names = ai_hero_named( $items ); ?>
<!-- Light, and the only layout that is one bordered card with the picture flush to its edge. -->
<section class="ai-c bg-surface-alt py-sec-sm sm:py-sec">
  <div class="mx-auto max-w-wide px-4 sm:px-6 lg:px-8">
    <div class="overflow-hidden rounded-band bg-surface shadow-sm ring-1 ring-line">
      <div class="grid lg:grid-cols-2">
        <div class="order-2 flex flex-col justify-center p-8 sm:p-12 lg:order-1">
          <?php if ( $eyebrow ) : ?><p class="m-0 mb-4 text-xs font-semibold tracking-widest uppercase text-brand-700"><?php echo esc_html( $eyebrow ); ?></p><?php endif; ?>
          <h1 class="m-0 text-3xl font-bold tracking-tight text-balance text-ink sm:text-4xl lg:text-5xl"><?php echo esc_html( $heading ); ?><?php if ( $accent ) : ?> <span class="text-brand-600"><?php echo esc_html( $accent ); ?></span><?php endif; ?></h1>
          <?php if ( $text ) : ?><p class="mt-5 mb-0 text-base/7 text-pretty text-muted"><?php echo esc_html( $text ); ?></p><?php endif; ?>
          <div class="mt-7 flex flex-wrap items-center gap-3"><?php ai_hero_buttons( $args ); ?></div>
          <?php if ( $proof ) : ?><p class="mt-5 mb-0 text-sm text-subtle"><?php echo esc_html( $proof ); ?></p><?php endif; ?>

          <?php if ( $names ) : ?>
          <!-- the names live INSIDE the card, under a rule: proof that belongs to the offer, not a separate band -->
          <div class="mt-8 border-t border-line pt-6">
            <ul class="m-0 flex flex-wrap items-center gap-x-7 gap-y-3">
              <?php foreach ( $names as $n ) : ?>
              <li class="m-0 text-sm font-semibold tracking-tight text-subtle"><?php echo esc_html( $n ); ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
          <?php endif; ?>
        </div>
        <?php if ( $image ) : ?>
        <div class="relative order-1 min-h-72 lg:order-2 lg:min-h-[28rem]">
          <img alt="" src="<?php echo esc_url( $image ); ?>" class="absolute inset-0 block size-full object-cover" />
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<?php elseif ( 'proof' === $variant ) : ?>
<!-- The loud one: the full brand colour, the largest type on the site, one button, and no picture anywhere.
     Being the only layout with nothing to look at is exactly what makes it read differently. -->
<section class="ai-c relative overflow-hidden bg-brand-600 py-sec-sm sm:py-sec">
  <div class="pointer-events-none absolute -top-32 left-1/2 size-[44rem] -translate-x-1/2 rounded-full bg-white opacity-10 blur-3xl" aria-hidden="true"></div>
  <div class="relative mx-auto max-w-wide px-4 text-center sm:px-6 lg:px-8">
    <?php if ( $eyebrow ) : ?><p class="m-0 mb-6 text-xs font-semibold tracking-widest uppercase text-white/70"><?php echo esc_html( $eyebrow ); ?></p><?php endif; ?>
    <h1 class="mx-auto m-0 max-w-4xl text-5xl font-bold tracking-tighter text-balance text-white sm:text-6xl lg:text-7xl"><?php echo esc_html( $heading ); ?><?php if ( $accent ) : ?> <span class="text-white/60"><?php echo esc_html( $accent ); ?></span><?php endif; ?></h1>
    <?php if ( $text ) : ?>
    <p class="mx-auto mt-6 mb-0 max-w-xl text-lg/8 text-pretty text-white/80"><?php echo esc_html( $text ); ?></p>
    <?php endif; ?>
    <div class="mt-10 flex flex-wrap items-center justify-center gap-3"><?php ai_hero_buttons( $args, 'light', true ); ?></div>
    <?php if ( $proof ) : ?><p class="mt-6 mb-0 text-sm text-white/70"><?php echo esc_html( $proof ); ?></p><?php endif; ?>

    <?php if ( $items ) : ?>
    <ul class="m-0 mx-auto mt-16 grid max-w-4xl gap-x-10 gap-y-6 border-t border-white/20 pt-10 text-left sm:grid-cols-3">
      <?php foreach ( array_slice( $items, 0, 3 ) as $item ) : ?>
      <li class="m-0 flex items-start gap-3">
        <span class="mt-0.5 text-white"><?php ai_e_icon( $item['icon'] ?? 'check-circle', 5 ); ?></span>
        <span class="text-sm/6 font-medium text-white/90"><?php echo esc_html( $item['title'] ?? $item['text'] ?? '' ); ?></span>
      </li>
      <?php endforeach; ?>
    </ul>
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
/* No max-width wrapper on purpose: the photograph runs to the edge of the screen, which no other layout does. */
$su_col      = ai_cls( 'order-1 flex items-center px-4 py-sec-sm sm:px-8 lg:order-2 lg:px-16' )
	. ' ' . ( $image ? '' : ai_cls( 'justify-center text-center' ) );
?>
<section class="ai-c bg-surface">
  <div class="grid lg:min-h-[34rem] lg:grid-cols-2">
    <?php if ( $image ) : ?>
    <div class="relative order-2 h-72 overflow-hidden sm:h-96 lg:order-1 lg:h-auto">
      <img alt="" src="<?php echo esc_url( $image ); ?>" class="absolute inset-0 block size-full object-cover" />
      <?php if ( $stat_value ) : ?>
      <!-- the figure sits ON the photograph, over a gradient so it stays readable whatever the picture is -->
      <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent p-6 sm:p-10">
        <p class="m-0 text-5xl font-bold tracking-tight text-white sm:text-6xl"><?php echo esc_html( $stat_value ); ?></p>
        <?php if ( $stat_label ) : ?><p class="m-0 mt-2 max-w-xs text-sm/6 text-white/85"><?php echo esc_html( $stat_label ); ?></p><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="<?php echo esc_attr( $su_col ); ?>">
      <div class="w-full max-w-xl">
        <?php if ( $eyebrow ) : ?><p class="<?php echo esc_attr( $pill ); ?>"><?php echo esc_html( $eyebrow ); ?></p><?php endif; ?>
        <h1 class="m-0 text-4xl font-bold tracking-tight text-balance text-ink sm:text-5xl lg:text-6xl"><?php echo esc_html( $heading ); ?><?php if ( $accent ) : ?> <span class="text-brand-600"><?php echo esc_html( $accent ); ?></span><?php endif; ?></h1>
        <?php if ( $text ) : ?><p class="mt-5 mb-0 text-lg/8 text-pretty text-muted"><?php echo esc_html( $text ); ?></p><?php endif; ?>

        <?php if ( $action ) : ?>
        <form action="<?php echo esc_url( $action ); ?>" method="post" class="mt-8">
          <div class="flex flex-col gap-3 sm:flex-row">
            <label for="ai-hero-signup" class="sr-only"><?php echo esc_html( $placeholder ); ?></label>
            <input id="ai-hero-signup" type="email" required name="<?php echo esc_attr( $field_name ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>"
              class="w-full min-w-0 flex-1 rounded-ctl bg-surface px-4 py-3.5 text-base text-ink ring-1 ring-inset ring-line focus:outline-2 focus:outline-offset-2 focus:outline-brand-600" />
            <button type="submit" class="inline-flex items-center justify-center rounded-ctl bg-brand-600 px-6 py-3.5 text-base font-semibold whitespace-nowrap text-white transition-colors hover:bg-brand-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600">
              <?php echo esc_html( $btn_label ); ?>
            </button>
          </div>
        </form>
        <?php else : ?>
        <div class="mt-8 flex flex-wrap items-center gap-3"><?php ai_hero_buttons( $args ); ?></div>
        <?php endif; ?>

        <?php if ( $proof ) : ?><p class="mt-5 mb-0 text-sm text-subtle"><?php echo esc_html( $proof ); ?></p><?php endif; ?>
      </div>
    </div>
  </div>
</section>

<?php elseif ( 'reveal' === $variant ) : ?>
<?php
$video_url   = $args['video_url'] ?? '';
$video_label = $args['video_label'] ?? 'Play trailer';
?>
<!-- Editorial: the heading's accent is set in italic serif, which nothing else here does, and the picture
     straddles the join between the light half and the deep band. The band is drawn INSIDE this section, so the
     sections around it keep their own spacing and no theme's overflow rule can clip it. -->
<section class="ai-c bg-surface pt-sec-sm sm:pt-sec">
  <div class="mx-auto max-w-wide px-4 text-center sm:px-6 lg:px-8">
    <?php if ( $eyebrow ) : ?><p class="m-0 mb-5 text-xs font-semibold tracking-widest uppercase text-muted"><?php echo esc_html( $eyebrow ); ?></p><?php endif; ?>
    <h1 class="mx-auto m-0 max-w-3xl text-5xl leading-tight font-bold tracking-tighter text-balance text-ink sm:text-6xl lg:text-7xl"><?php echo esc_html( $heading ); ?><?php if ( $accent ) : ?> <span class="font-serif font-normal italic"><?php echo esc_html( $accent ); ?></span><?php endif; ?></h1>
    <?php if ( $text ) : ?>
    <p class="mx-auto mt-6 mb-0 max-w-xl text-base/7 text-pretty text-muted"><?php echo esc_html( $text ); ?></p>
    <?php endif; ?>
    <div class="mt-9 flex flex-wrap items-center justify-center gap-3"><?php ai_hero_buttons( $args, 'ink', true ); ?></div>
    <?php if ( $proof ) : ?><p class="<?php echo esc_attr( $note ); ?>"><?php echo esc_html( $proof ); ?></p><?php endif; ?>
  </div>

  <?php if ( $image ) : ?>
  <div class="relative mt-14">
    <!-- the deep band takes the lower half, so the picture sits across the join -->
    <div class="absolute inset-x-0 bottom-0 h-1/2 bg-brand-900" aria-hidden="true"></div>
    <div class="relative mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
      <div class="relative overflow-hidden rounded-band shadow-2xl ring-1 ring-ink/10">
        <img alt="" src="<?php echo esc_url( $image ); ?>" class="block aspect-16/9 h-auto w-full max-w-full object-cover" />
        <?php if ( $video_url ) : ?>
        <!-- a link, not a player: no JavaScript ships to the site -->
        <a href="<?php echo esc_url( $video_url ); ?>" class="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-black/25 no-underline transition-colors hover:bg-black/40">
          <span class="flex size-16 items-center justify-center rounded-full ring-2 ring-inset ring-white/80">
            <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6 text-white"><path d="M8.04 4.92A.9.9 0 0 0 6.7 5.7v12.6a.9.9 0 0 0 1.34.78l10.37-6.3a.9.9 0 0 0 0-1.56L8.04 4.92Z" /></svg>
          </span>
          <span class="text-sm font-semibold tracking-wide text-white uppercase"><?php echo esc_html( $video_label ); ?></span>
        </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php else : ?>
  <div class="pb-sec-sm sm:pb-sec"></div>
  <?php endif; ?>
</section>

<?php else : ?>
<section class="ai-c relative overflow-hidden bg-surface py-sec-sm sm:py-sec">
  <div class="mx-auto grid max-w-wide items-center gap-12 px-4 sm:px-6 lg:grid-cols-2 lg:gap-16 lg:px-8">
    <div>
      <?php if ( $eyebrow ) : ?><p class="<?php echo esc_attr( $pill ); ?>"><?php echo esc_html( $eyebrow ); ?></p><?php endif; ?>
      <h1 class="m-0 text-4xl font-bold tracking-tight text-balance text-ink sm:text-5xl lg:text-6xl"><?php echo esc_html( $heading ); ?><?php if ( $accent ) : ?> <span class="text-brand-600"><?php echo esc_html( $accent ); ?></span><?php endif; ?></h1>
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
