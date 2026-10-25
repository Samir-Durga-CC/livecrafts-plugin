<?php
/**
 * Component: hero
 * Params: variant (split|spotlight|showcase|panel|proof|signup|reveal|wave), eyebrow, heading, accent, text,
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
 *   wave      - the picture held in an organic curve with a painted brand edge, text beside it
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
 *   wave       surface           3xl-5xl, a spaced line      curved cut, picture bleeds right   the picture itself
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
$variant = in_array( $args['variant'] ?? 'split', array( 'split', 'spotlight', 'showcase', 'panel', 'proof', 'signup', 'reveal', 'wave' ), true ) ? $args['variant'] : 'split';
$eyebrow = $args['eyebrow'] ?? '';
$heading = $args['heading'] ?? '';
$accent  = $args['accent'] ?? '';
$text    = $args['text'] ?? '';
$image   = $args['image'] ?? '';
$proof   = $args['proof'] ?? '';
$items   = ai_items( $args );

$pill = ai_cls( 'ai:m-0 ai:mb-6 ai:inline-flex ai:items-center ai:gap-2 ai:rounded-full ai:bg-brand-50 ai:px-3 ai:py-1 ai:text-sm ai:font-medium ai:text-brand-700 ai:ring-1 ai:ring-inset ai:ring-brand-200' );
$lede = ai_cls( 'ai:mt-6 ai:mb-0 ai:max-w-xl ai:text-lg/8 ai:text-pretty ai:text-muted' );
$note = ai_cls( 'ai:mt-6 ai:mb-0 ai:text-sm ai:text-subtle' );

/* The same three roles inverted, for the layouts that sit on a deep panel. */
$d_eyebrow = ai_cls( 'ai:m-0 ai:mb-4 ai:text-sm ai:font-semibold ai:tracking-widest ai:uppercase ai:text-brand-200' );
$d_lede    = ai_cls( 'ai:mt-5 ai:mb-0 ai:text-lg/8 ai:text-pretty ai:text-white/70' );
$d_note    = ai_cls( 'ai:mt-6 ai:mb-0 ai:text-sm ai:text-white/60' );

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
<section class="ai-c ai:relative ai:overflow-hidden ai:bg-surface-alt ai:py-sec-sm ai:sm:py-sec">
  <!-- a soft brand glow behind the message: depth without a background image to load -->
  <div class="ai:pointer-events-none ai:absolute ai:-top-40 ai:left-1/2 ai:size-[42rem] ai:-translate-x-1/2 ai:rounded-full ai:bg-brand-100 ai:opacity-60 ai:blur-3xl" aria-hidden="true"></div>
  <div class="ai:relative ai:mx-auto ai:max-w-wide ai:px-4 ai:text-center ai:sm:px-6 ai:lg:px-8">
    <?php if ( $eyebrow ) : ?><p class="<?php echo esc_attr( $pill ); ?>"><?php echo esc_html( $eyebrow ); ?></p><?php endif; ?>
    <h1 class="ai:mx-auto ai:m-0 ai:max-w-4xl ai:text-4xl ai:font-bold ai:tracking-tight ai:text-balance ai:text-ink ai:sm:text-5xl ai:lg:text-6xl"><?php echo esc_html( $heading ); ?><?php if ( $accent ) : ?> <span class="ai:text-brand-600"><?php echo esc_html( $accent ); ?></span><?php endif; ?></h1>
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

<?php elseif ( 'showcase' === $variant ) : ?>
<?php
/* The picture is TALL and butted against the panel, which is what makes this one recognisable at a glance: no
   other layout is two unequal columns of full height. Without a picture the panel simply takes the whole width. */
$sc_panel = ai_cls( 'ai:relative ai:flex ai:flex-col ai:justify-center ai:overflow-hidden ai:rounded-band ai:p-8 ai:ring-1 ai:ring-inset ai:ring-white/10 ai:sm:p-14' )
	. ' ' . ( $image ? ai_cls( 'ai:lg:col-span-3' ) : ai_cls( 'ai:lg:col-span-5' ) );
?>
<section class="ai-c ai:bg-brand-900 ai:py-sec-sm ai:sm:py-sec">
  <div class="ai:mx-auto ai:max-w-wide ai:px-4 ai:sm:px-6 ai:lg:px-8">
    <div class="ai:grid ai:gap-5 ai:lg:grid-cols-5">
      <?php if ( $image ) : ?>
      <div class="ai:relative ai:min-h-80 ai:overflow-hidden ai:rounded-band ai:lg:col-span-2">
        <img alt="" src="<?php echo esc_url( $image ); ?>" class="ai:absolute ai:inset-0 ai:block ai:size-full ai:object-cover" />
      </div>
      <?php endif; ?>
      <div class="<?php echo esc_attr( $sc_panel ); ?>">
        <div class="ai:pointer-events-none ai:absolute ai:-top-24 ai:-right-24 ai:size-[28rem] ai:rounded-full ai:bg-brand-500 ai:opacity-25 ai:blur-3xl" aria-hidden="true"></div>
        <div class="ai:relative ai:max-w-xl">
          <?php if ( $eyebrow ) : ?>
          <p class="ai:m-0 ai:mb-5 ai:flex ai:items-center ai:gap-3 ai:text-xs ai:font-semibold ai:tracking-widest ai:uppercase ai:text-brand-200">
            <span class="ai:h-px ai:w-8 ai:bg-brand-200/60"></span><?php echo esc_html( $eyebrow ); ?>
          </p>
          <?php endif; ?>
          <h1 class="ai:m-0 ai:text-4xl ai:font-bold ai:tracking-tight ai:text-balance ai:text-white ai:sm:text-5xl ai:lg:text-6xl"><?php echo esc_html( $heading ); ?><?php if ( $accent ) : ?> <span class="ai:text-brand-300"><?php echo esc_html( $accent ); ?></span><?php endif; ?></h1>
          <?php if ( $text ) : ?><p class="<?php echo esc_attr( $d_lede ); ?>"><?php echo esc_html( $text ); ?></p><?php endif; ?>
          <div class="ai:mt-8 ai:flex ai:flex-wrap ai:items-center ai:gap-3"><?php ai_hero_buttons( $args, 'light' ); ?></div>
          <?php if ( $proof ) : ?><p class="<?php echo esc_attr( $d_note ); ?>"><?php echo esc_html( $proof ); ?></p><?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<?php elseif ( 'panel' === $variant ) : ?>
<?php $names = ai_hero_named( $items ); ?>
<!-- Light, and the only layout that is one bordered card with the picture flush to its edge. -->
<section class="ai-c ai:bg-surface-alt ai:py-sec-sm ai:sm:py-sec">
  <div class="ai:mx-auto ai:max-w-wide ai:px-4 ai:sm:px-6 ai:lg:px-8">
    <div class="ai:overflow-hidden ai:rounded-band ai:bg-surface ai:shadow-sm ai:ring-1 ai:ring-line">
      <div class="ai:grid ai:lg:grid-cols-2">
        <div class="ai:order-2 ai:flex ai:flex-col ai:justify-center ai:p-8 ai:sm:p-12 ai:lg:order-1">
          <?php if ( $eyebrow ) : ?><p class="ai:m-0 ai:mb-4 ai:text-xs ai:font-semibold ai:tracking-widest ai:uppercase ai:text-brand-700"><?php echo esc_html( $eyebrow ); ?></p><?php endif; ?>
          <h1 class="ai:m-0 ai:text-3xl ai:font-bold ai:tracking-tight ai:text-balance ai:text-ink ai:sm:text-4xl ai:lg:text-5xl"><?php echo esc_html( $heading ); ?><?php if ( $accent ) : ?> <span class="ai:text-brand-600"><?php echo esc_html( $accent ); ?></span><?php endif; ?></h1>
          <?php if ( $text ) : ?><p class="ai:mt-5 ai:mb-0 ai:text-base/7 ai:text-pretty ai:text-muted"><?php echo esc_html( $text ); ?></p><?php endif; ?>
          <div class="ai:mt-7 ai:flex ai:flex-wrap ai:items-center ai:gap-3"><?php ai_hero_buttons( $args ); ?></div>
          <?php if ( $proof ) : ?><p class="ai:mt-5 ai:mb-0 ai:text-sm ai:text-subtle"><?php echo esc_html( $proof ); ?></p><?php endif; ?>

          <?php if ( $names ) : ?>
          <!-- the names live INSIDE the card, under a rule: proof that belongs to the offer, not a separate band -->
          <div class="ai:mt-8 ai:border-t ai:border-line ai:pt-6">
            <ul class="ai:m-0 ai:flex ai:flex-wrap ai:items-center ai:gap-x-7 ai:gap-y-3">
              <?php foreach ( $names as $n ) : ?>
              <li class="ai:m-0 ai:text-sm ai:font-semibold ai:tracking-tight ai:text-subtle"><?php echo esc_html( $n ); ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
          <?php endif; ?>
        </div>
        <?php if ( $image ) : ?>
        <div class="ai:relative ai:order-1 ai:min-h-72 ai:lg:order-2 ai:lg:min-h-[28rem]">
          <img alt="" src="<?php echo esc_url( $image ); ?>" class="ai:absolute ai:inset-0 ai:block ai:size-full ai:object-cover" />
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<?php elseif ( 'proof' === $variant ) : ?>
<!-- The loud one: the full brand colour, the largest type on the site, one button, and no picture anywhere.
     Being the only layout with nothing to look at is exactly what makes it read differently. -->
<section class="ai-c ai:relative ai:overflow-hidden ai:bg-brand-600 ai:py-sec-sm ai:sm:py-sec">
  <div class="ai:pointer-events-none ai:absolute ai:-top-32 ai:left-1/2 ai:size-[44rem] ai:-translate-x-1/2 ai:rounded-full ai:bg-white ai:opacity-10 ai:blur-3xl" aria-hidden="true"></div>
  <div class="ai:relative ai:mx-auto ai:max-w-wide ai:px-4 ai:text-center ai:sm:px-6 ai:lg:px-8">
    <?php if ( $eyebrow ) : ?><p class="ai:m-0 ai:mb-6 ai:text-xs ai:font-semibold ai:tracking-widest ai:uppercase ai:text-white/70"><?php echo esc_html( $eyebrow ); ?></p><?php endif; ?>
    <h1 class="ai:mx-auto ai:m-0 ai:max-w-4xl ai:text-5xl ai:font-bold ai:tracking-tighter ai:text-balance ai:text-white ai:sm:text-6xl ai:lg:text-7xl"><?php echo esc_html( $heading ); ?><?php if ( $accent ) : ?> <span class="ai:text-white/60"><?php echo esc_html( $accent ); ?></span><?php endif; ?></h1>
    <?php if ( $text ) : ?>
    <p class="ai:mx-auto ai:mt-6 ai:mb-0 ai:max-w-xl ai:text-lg/8 ai:text-pretty ai:text-white/80"><?php echo esc_html( $text ); ?></p>
    <?php endif; ?>
    <div class="ai:mt-10 ai:flex ai:flex-wrap ai:items-center ai:justify-center ai:gap-3"><?php ai_hero_buttons( $args, 'light', true ); ?></div>
    <?php if ( $proof ) : ?><p class="ai:mt-6 ai:mb-0 ai:text-sm ai:text-white/70"><?php echo esc_html( $proof ); ?></p><?php endif; ?>

    <?php if ( $items ) : ?>
    <ul class="ai:m-0 ai:mx-auto ai:mt-16 ai:grid ai:max-w-4xl ai:gap-x-10 ai:gap-y-6 ai:border-t ai:border-white/20 ai:pt-10 ai:text-left ai:sm:grid-cols-3">
      <?php foreach ( array_slice( $items, 0, 3 ) as $item ) : ?>
      <li class="ai:m-0 ai:flex ai:items-start ai:gap-3">
        <span class="ai:mt-0.5 ai:text-white"><?php ai_e_icon( $item['icon'] ?? 'check-circle', 5 ); ?></span>
        <span class="ai:text-sm/6 ai:font-medium ai:text-white/90"><?php echo esc_html( $item['title'] ?? $item['text'] ?? '' ); ?></span>
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
$su_col      = ai_cls( 'ai:order-1 ai:flex ai:items-center ai:px-4 ai:py-sec-sm ai:sm:px-8 ai:lg:order-2 ai:lg:px-16' )
	. ' ' . ( $image ? '' : ai_cls( 'ai:justify-center ai:text-center' ) );
?>
<section class="ai-c ai:bg-surface">
  <div class="ai:grid ai:lg:min-h-[34rem] ai:lg:grid-cols-2">
    <?php if ( $image ) : ?>
    <div class="ai:relative ai:order-2 ai:h-72 ai:overflow-hidden ai:sm:h-96 ai:lg:order-1 ai:lg:h-auto">
      <img alt="" src="<?php echo esc_url( $image ); ?>" class="ai:absolute ai:inset-0 ai:block ai:size-full ai:object-cover" />
      <?php if ( $stat_value ) : ?>
      <!-- the figure sits ON the photograph, over a gradient so it stays readable whatever the picture is -->
      <div class="ai:absolute ai:inset-x-0 ai:bottom-0 ai:bg-gradient-to-t ai:from-black/80 ai:via-black/40 ai:to-transparent ai:p-6 ai:sm:p-10">
        <p class="ai:m-0 ai:text-5xl ai:font-bold ai:tracking-tight ai:text-white ai:sm:text-6xl"><?php echo esc_html( $stat_value ); ?></p>
        <?php if ( $stat_label ) : ?><p class="ai:m-0 ai:mt-2 ai:max-w-xs ai:text-sm/6 ai:text-white/85"><?php echo esc_html( $stat_label ); ?></p><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="<?php echo esc_attr( $su_col ); ?>">
      <div class="ai:w-full ai:max-w-xl">
        <?php if ( $eyebrow ) : ?><p class="<?php echo esc_attr( $pill ); ?>"><?php echo esc_html( $eyebrow ); ?></p><?php endif; ?>
        <h1 class="ai:m-0 ai:text-4xl ai:font-bold ai:tracking-tight ai:text-balance ai:text-ink ai:sm:text-5xl ai:lg:text-6xl"><?php echo esc_html( $heading ); ?><?php if ( $accent ) : ?> <span class="ai:text-brand-600"><?php echo esc_html( $accent ); ?></span><?php endif; ?></h1>
        <?php if ( $text ) : ?><p class="ai:mt-5 ai:mb-0 ai:text-lg/8 ai:text-pretty ai:text-muted"><?php echo esc_html( $text ); ?></p><?php endif; ?>

        <?php if ( $action ) : ?>
        <form action="<?php echo esc_url( $action ); ?>" method="post" class="ai:mt-8">
          <div class="ai:flex ai:flex-col ai:gap-3 ai:sm:flex-row">
            <label for="ai-hero-signup" class="ai:sr-only"><?php echo esc_html( $placeholder ); ?></label>
            <input id="ai-hero-signup" type="email" required name="<?php echo esc_attr( $field_name ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>"
              class="ai:w-full ai:min-w-0 ai:flex-1 ai:rounded-ctl ai:bg-surface ai:px-4 ai:py-3.5 ai:text-base ai:text-ink ai:ring-1 ai:ring-inset ai:ring-line ai:focus:outline-2 ai:focus:outline-offset-2 ai:focus:outline-brand-600" />
            <button type="submit" class="ai:inline-flex ai:items-center ai:justify-center ai:rounded-ctl ai:bg-brand-600 ai:px-6 ai:py-3.5 ai:text-base ai:font-semibold ai:whitespace-nowrap ai:text-white ai:transition-colors ai:hover:bg-brand-700 ai:focus-visible:outline-2 ai:focus-visible:outline-offset-2 ai:focus-visible:outline-brand-600">
              <?php echo esc_html( $btn_label ); ?>
            </button>
          </div>
        </form>
        <?php else : ?>
        <div class="ai:mt-8 ai:flex ai:flex-wrap ai:items-center ai:gap-3"><?php ai_hero_buttons( $args ); ?></div>
        <?php endif; ?>

        <?php if ( $proof ) : ?><p class="ai:mt-5 ai:mb-0 ai:text-sm ai:text-subtle"><?php echo esc_html( $proof ); ?></p><?php endif; ?>
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
<section class="ai-c ai:bg-surface ai:pt-sec-sm ai:sm:pt-sec">
  <div class="ai:mx-auto ai:max-w-wide ai:px-4 ai:text-center ai:sm:px-6 ai:lg:px-8">
    <?php if ( $eyebrow ) : ?><p class="ai:m-0 ai:mb-5 ai:text-xs ai:font-semibold ai:tracking-widest ai:uppercase ai:text-muted"><?php echo esc_html( $eyebrow ); ?></p><?php endif; ?>
    <h1 class="ai:mx-auto ai:m-0 ai:max-w-3xl ai:text-5xl ai:leading-tight ai:font-bold ai:tracking-tighter ai:text-balance ai:text-ink ai:sm:text-6xl ai:lg:text-7xl"><?php echo esc_html( $heading ); ?><?php if ( $accent ) : ?> <span class="ai:font-serif ai:font-normal ai:italic"><?php echo esc_html( $accent ); ?></span><?php endif; ?></h1>
    <?php if ( $text ) : ?>
    <p class="ai:mx-auto ai:mt-6 ai:mb-0 ai:max-w-xl ai:text-base/7 ai:text-pretty ai:text-muted"><?php echo esc_html( $text ); ?></p>
    <?php endif; ?>
    <div class="ai:mt-9 ai:flex ai:flex-wrap ai:items-center ai:justify-center ai:gap-3"><?php ai_hero_buttons( $args, 'ink', true ); ?></div>
    <?php if ( $proof ) : ?><p class="<?php echo esc_attr( $note ); ?>"><?php echo esc_html( $proof ); ?></p><?php endif; ?>
  </div>

  <?php if ( $image ) : ?>
  <div class="ai:relative ai:mt-14">
    <!-- the deep band takes the lower half, so the picture sits across the join -->
    <div class="ai:absolute ai:inset-x-0 ai:bottom-0 ai:h-1/2 ai:bg-brand-900" aria-hidden="true"></div>
    <div class="ai:relative ai:mx-auto ai:max-w-5xl ai:px-4 ai:sm:px-6 ai:lg:px-8">
      <div class="ai:relative ai:overflow-hidden ai:rounded-band ai:shadow-2xl ai:ring-1 ai:ring-ink/10">
        <img alt="" src="<?php echo esc_url( $image ); ?>" class="ai:block ai:aspect-16/9 ai:h-auto ai:w-full ai:max-w-full ai:object-cover" />
        <?php if ( $video_url ) : ?>
        <!-- a link, not a player: no JavaScript ships to the site -->
        <a href="<?php echo esc_url( $video_url ); ?>" class="ai:absolute ai:inset-0 ai:flex ai:flex-col ai:items-center ai:justify-center ai:gap-3 ai:bg-black/25 ai:no-underline ai:transition-colors ai:hover:bg-black/40">
          <span class="ai:flex ai:size-16 ai:items-center ai:justify-center ai:rounded-full ai:ring-2 ai:ring-inset ai:ring-white/80">
            <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="ai:size-6 ai:text-white"><path d="M8.04 4.92A.9.9 0 0 0 6.7 5.7v12.6a.9.9 0 0 0 1.34.78l10.37-6.3a.9.9 0 0 0 0-1.56L8.04 4.92Z" /></svg>
          </span>
          <span class="ai:text-sm ai:font-semibold ai:tracking-wide ai:text-white ai:uppercase"><?php echo esc_html( $video_label ); ?></span>
        </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php else : ?>
  <div class="ai:pb-sec-sm ai:sm:pb-sec"></div>
  <?php endif; ?>
</section>

<?php elseif ( 'wave' === $variant ) : ?>
<?php
/**
 * The picture is cut by an organic curve with a painted brand edge down its left side - the shape every
 * "landing page" illustration pack uses, which is why it reads as designed rather than as a photo dropped in
 * a box. Two copies of the same path: one filled with the edge, one used as the clip, offset.
 *
 * The ids must be unique or a second hero on the page would clip itself with the first one's shape.
 */
$uid = 'lcw' . substr( md5( uniqid( '', true ) ), 0, 8 );
$blob_edge = 'M176,0 C132,56 212,112 178,176 C144,242 44,268 70,338 C96,408 186,426 196,486 C202,516 228,514 252,520 L560,520 L560,0 Z';
$blob_clip = 'M210,0 C166,56 246,112 212,176 C178,242 78,268 104,338 C130,408 220,426 230,486 C236,516 262,514 286,520 L560,520 L560,0 Z';
?>
<section class="ai-c ai:relative ai:overflow-hidden ai:bg-surface">
  <div class="ai:relative ai:mx-auto ai:grid ai:max-w-wide ai:items-center ai:gap-10 ai:px-4 ai:py-sec-sm ai:sm:px-6 ai:sm:py-sec ai:lg:grid-cols-2 ai:lg:px-8">
    <div class="ai:relative ai:z-10">
      <span class="ai:block ai:h-1.5 ai:w-20 ai:rounded-full ai:bg-gradient-to-r ai:from-brand-600 ai:to-brand-300"></span>
      <?php if ( $eyebrow ) : ?><p class="ai:m-0 ai:mt-7 ai:text-xs ai:font-semibold ai:tracking-widest ai:uppercase ai:text-brand-700"><?php echo esc_html( $eyebrow ); ?></p><?php endif; ?>
      <h1 class="ai:m-0 ai:mt-6 ai:text-3xl ai:font-light ai:tracking-tight ai:text-balance ai:text-ink ai:sm:text-4xl ai:lg:text-5xl"><?php echo esc_html( $heading ); ?></h1>
      <?php if ( $accent ) : ?>
      <!-- the accent is a line of its own here, widely spaced: the quiet second line of a title block -->
      <p class="ai:m-0 ai:mt-3 ai:text-lg ai:font-light ai:tracking-[.3em] ai:uppercase ai:text-muted ai:sm:text-xl"><?php echo esc_html( $accent ); ?></p>
      <?php endif; ?>
      <?php if ( $text ) : ?><p class="ai:mt-7 ai:mb-0 ai:max-w-sm ai:text-sm/8 ai:text-pretty ai:text-muted"><?php echo esc_html( $text ); ?></p><?php endif; ?>
      <div class="ai:mt-9 ai:flex ai:flex-wrap ai:items-center ai:gap-3"><?php ai_hero_buttons( $args ); ?></div>
      <?php if ( $proof ) : ?><p class="<?php echo esc_attr( $note ); ?>"><?php echo esc_html( $proof ); ?></p><?php endif; ?>
    </div>

    <!-- In the grid on a phone, pinned to the right edge from lg up so the curve bleeds off the section. -->
    <div class="ai:relative ai:order-first ai:h-64 ai:w-full ai:sm:h-80 ai:lg:absolute ai:lg:inset-y-0 ai:lg:right-0 ai:lg:order-none ai:lg:h-auto ai:lg:w-1/2">
      <svg viewBox="0 0 560 520" preserveAspectRatio="xMidYMid slice" class="ai:absolute ai:inset-0 ai:size-full ai:text-brand-600" aria-hidden="true">
        <defs>
          <!-- currentColor, so the edge is the site's brand; the stops only vary its strength -->
          <linearGradient id="<?php echo esc_attr( $uid ); ?>g" x1="0" y1="1" x2="1" y2="0">
            <stop offset="0" stop-color="currentColor" stop-opacity=".72"/>
            <stop offset=".55" stop-color="currentColor" stop-opacity=".42"/>
            <stop offset="1" stop-color="currentColor" stop-opacity=".16"/>
          </linearGradient>
          <clipPath id="<?php echo esc_attr( $uid ); ?>c"><path d="<?php echo esc_attr( $blob_clip ); ?>"/></clipPath>
        </defs>
        <path fill="url(#<?php echo esc_attr( $uid ); ?>g)" d="<?php echo esc_attr( $blob_edge ); ?>"/>
        <g clip-path="url(#<?php echo esc_attr( $uid ); ?>c)">
          <!-- a tint under the picture, so the shape is still a shape when no picture was given -->
          <rect x="40" y="0" width="520" height="520" class="ai:fill-brand-50"/>
          <?php if ( $image ) : ?>
          <image href="<?php echo esc_url( $image ); ?>" x="40" y="0" width="520" height="520" preserveAspectRatio="xMidYMid slice"/>
          <?php endif; ?>
        </g>
      </svg>
    </div>
  </div>
</section>

<?php else : ?>
<section class="ai-c ai:relative ai:overflow-hidden ai:bg-surface ai:py-sec-sm ai:sm:py-sec">
  <div class="ai:mx-auto ai:grid ai:max-w-wide ai:items-center ai:gap-12 ai:px-4 ai:sm:px-6 ai:lg:grid-cols-2 ai:lg:gap-16 ai:lg:px-8">
    <div>
      <?php if ( $eyebrow ) : ?><p class="<?php echo esc_attr( $pill ); ?>"><?php echo esc_html( $eyebrow ); ?></p><?php endif; ?>
      <h1 class="ai:m-0 ai:text-4xl ai:font-bold ai:tracking-tight ai:text-balance ai:text-ink ai:sm:text-5xl ai:lg:text-6xl"><?php echo esc_html( $heading ); ?><?php if ( $accent ) : ?> <span class="ai:text-brand-600"><?php echo esc_html( $accent ); ?></span><?php endif; ?></h1>
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
