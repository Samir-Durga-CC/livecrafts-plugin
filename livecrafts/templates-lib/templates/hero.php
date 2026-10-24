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

$pill  = ai_cls( 'ai:m-0 ai:mb-6 ai:inline-flex ai:items-center ai:gap-2 ai:rounded-full ai:bg-brand-50 ai:px-3 ai:py-1 ai:text-sm ai:font-medium ai:text-brand-700 ai:ring-1 ai:ring-inset ai:ring-brand-200' );
$lede  = ai_cls( 'ai:mt-6 ai:mb-0 ai:max-w-xl ai:text-lg/8 ai:text-pretty ai:text-muted' );
$note  = ai_cls( 'ai:mt-6 ai:mb-0 ai:text-sm ai:text-subtle' );

/* On a deep panel the type is light: these are the same three roles, inverted. */
$d_eyebrow = ai_cls( 'ai:m-0 ai:mb-4 ai:text-sm ai:font-semibold ai:tracking-widest ai:uppercase ai:text-brand-200' );
$d_heading = ai_cls( 'ai:m-0 ai:text-4xl ai:font-bold ai:tracking-tight ai:text-balance ai:text-white ai:sm:text-5xl' );
$d_lede    = ai_cls( 'ai:mt-5 ai:mb-0 ai:text-lg/8 ai:text-pretty ai:text-white/70' );
$d_note    = ai_cls( 'ai:mt-6 ai:mb-0 ai:text-sm ai:text-white/60' );

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
		echo '<div class="ai:pointer-events-none ai:absolute ai:-top-24 ai:left-1/2 ai:size-[36rem] ai:-translate-x-1/2 ai:rounded-full ai:bg-brand-500 ai:opacity-20 ai:blur-3xl" aria-hidden="true"></div>';
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

<?php elseif ( 'showcase' === $variant ) : ?>
<?php
/* A class attribute is either wholly static or wholly PHP: the build prefixes the literal, so the two may not mix. */
$sc_card = ai_cls( 'ai:relative ai:flex ai:flex-col ai:items-center ai:justify-center ai:overflow-hidden ai:rounded-band ai:p-8 ai:text-center ai:ring-1 ai:ring-inset ai:ring-white/10 ai:sm:p-14' )
	. ' ' . ( $image ? ai_cls( 'ai:lg:col-span-2' ) : ai_cls( 'ai:lg:col-span-3' ) );
?>
<!-- A tall product shot holds one third; the message sits centred in the panel beside it. -->
<section class="ai-c ai:bg-brand-900 ai:py-sec-sm ai:sm:py-sec">
  <div class="ai:mx-auto ai:max-w-wide ai:px-4 ai:sm:px-6 ai:lg:px-8">
    <div class="ai:grid ai:gap-6 ai:lg:grid-cols-3">
      <?php if ( $image ) : ?>
      <img alt="" src="<?php echo esc_url( $image ); ?>" class="ai:block ai:h-80 ai:w-full ai:max-w-full ai:rounded-band ai:object-cover ai:lg:h-auto ai:lg:self-stretch" />
      <?php endif; ?>
      <div class="<?php echo esc_attr( $sc_card ); ?>">
        <?php ai_hero_glow(); ?>
        <div class="ai:relative ai:max-w-2xl">
          <?php if ( $eyebrow ) : ?><p class="<?php echo esc_attr( $d_eyebrow ); ?>"><?php echo esc_html( $eyebrow ); ?></p><?php endif; ?>
          <h1 class="<?php echo esc_attr( $d_heading ); ?>"><?php echo esc_html( $heading ); ?></h1>
          <?php if ( $text ) : ?><p class="<?php echo esc_attr( $d_lede ); ?>"><?php echo esc_html( $text ); ?></p><?php endif; ?>
          <div class="ai:mt-8 ai:flex ai:flex-wrap ai:items-center ai:justify-center ai:gap-3"><?php ai_hero_buttons( $args, 'light' ); ?></div>
          <?php if ( $proof ) : ?><p class="<?php echo esc_attr( $d_note ); ?>"><?php echo esc_html( $proof ); ?></p><?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<?php elseif ( 'panel' === $variant ) : ?>
<!-- Message and picture share one card, so the hero reads as a single object on the page. -->
<section class="ai-c ai:bg-brand-900 ai:py-sec-sm ai:sm:py-sec">
  <div class="ai:mx-auto ai:max-w-wide ai:px-4 ai:sm:px-6 ai:lg:px-8">
    <div class="ai:relative ai:overflow-hidden ai:rounded-band ai:p-8 ai:ring-1 ai:ring-inset ai:ring-white/10 ai:sm:p-12">
      <?php ai_hero_glow(); ?>
      <div class="ai:relative ai:grid ai:items-center ai:gap-10 ai:lg:grid-cols-2 ai:lg:gap-16">
        <div>
          <?php if ( $eyebrow ) : ?><p class="<?php echo esc_attr( $d_eyebrow ); ?>"><?php echo esc_html( $eyebrow ); ?></p><?php endif; ?>
          <h1 class="<?php echo esc_attr( $d_heading ); ?>"><?php echo esc_html( $heading ); ?></h1>
          <?php if ( $text ) : ?><p class="<?php echo esc_attr( $d_lede ); ?>"><?php echo esc_html( $text ); ?></p><?php endif; ?>
          <div class="ai:mt-8 ai:flex ai:flex-wrap ai:items-center ai:gap-3"><?php ai_hero_buttons( $args, 'light' ); ?></div>
          <?php if ( $proof ) : ?><p class="<?php echo esc_attr( $d_note ); ?>"><?php echo esc_html( $proof ); ?></p><?php endif; ?>
        </div>
        <?php if ( $image ) : ?>
        <div class="ai:overflow-hidden ai:rounded-card ai:bg-white">
          <img alt="" src="<?php echo esc_url( $image ); ?>" class="ai:block ai:h-auto ai:w-full ai:max-w-full ai:object-cover" />
        </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ( $items ) : ?>
    <!-- The names under the card: who already trusts this. Text, so no logo files have to be found. -->
    <ul class="ai:m-0 ai:mt-6 ai:flex ai:flex-wrap ai:items-center ai:justify-center ai:gap-x-12 ai:gap-y-4 ai:rounded-band ai:px-8 ai:py-8 ai:ring-1 ai:ring-inset ai:ring-white/10">
      <?php foreach ( $items as $item ) : ?>
      <li class="ai:m-0 ai:text-lg ai:font-semibold ai:tracking-tight ai:text-white/50"><?php echo esc_html( $item['name'] ?? $item['title'] ?? '' ); ?></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </div>
</section>

<?php elseif ( 'proof' === $variant ) : ?>
<!-- Centred message, then the three reasons to believe it, then the picture. -->
<section class="ai-c ai:relative ai:overflow-hidden ai:bg-brand-900 ai:py-sec-sm ai:sm:py-sec">
  <?php ai_hero_glow(); ?>
  <div class="ai:relative ai:mx-auto ai:max-w-wide ai:px-4 ai:text-center ai:sm:px-6 ai:lg:px-8">
    <?php if ( $eyebrow ) : ?><p class="<?php echo esc_attr( $d_eyebrow ); ?>"><?php echo esc_html( $eyebrow ); ?></p><?php endif; ?>
    <h1 class="ai:mx-auto ai:m-0 ai:max-w-3xl ai:text-4xl ai:font-bold ai:tracking-tight ai:text-balance ai:text-white ai:sm:text-6xl"><?php echo esc_html( $heading ); ?></h1>
    <?php if ( $text ) : ?>
    <p class="ai:mx-auto ai:mt-5 ai:mb-0 ai:max-w-2xl ai:text-lg/8 ai:text-pretty ai:text-white/70"><?php echo esc_html( $text ); ?></p>
    <?php endif; ?>
    <div class="ai:mt-10 ai:flex ai:flex-wrap ai:items-center ai:justify-center ai:gap-3"><?php ai_hero_buttons( $args, 'light' ); ?></div>
    <?php if ( $proof ) : ?><p class="<?php echo esc_attr( $d_note ); ?>"><?php echo esc_html( $proof ); ?></p><?php endif; ?>

    <?php if ( $items ) : ?>
    <ul class="ai:m-0 ai:mx-auto ai:mt-14 ai:grid ai:max-w-4xl ai:gap-8 ai:text-left ai:sm:grid-cols-3">
      <?php foreach ( array_slice( $items, 0, 3 ) as $item ) : ?>
      <li class="ai:m-0 ai:flex ai:items-start ai:gap-3">
        <span class="ai:mt-0.5 ai:text-brand-200"><?php ai_e_icon( $item['icon'] ?? 'check-circle', 5 ); ?></span>
        <span class="ai:text-sm/6 ai:text-white/80"><?php echo esc_html( $item['title'] ?? $item['text'] ?? '' ); ?></span>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <?php if ( $image ) : ?>
    <div class="ai:relative ai:mx-auto ai:mt-16 ai:max-w-5xl">
      <img alt="" src="<?php echo esc_url( $image ); ?>" class="ai:block ai:h-auto ai:w-full ai:max-w-full ai:rounded-band ai:object-cover ai:shadow-2xl ai:ring-1 ai:ring-white/10" />
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
$su_col      = ai_cls( 'ai:order-1 ai:flex ai:w-full ai:items-center ai:justify-center ai:px-4 ai:py-sec-sm ai:sm:px-6 ai:lg:order-2 ai:lg:px-12' )
	. ' ' . ( $image ? ai_cls( 'ai:lg:w-7/12' ) : ai_cls( 'ai:lg:w-full' ) );
?>
<!-- Picture and message side by side, full height, with one field: the shortest path from reading to signing up. -->
<section class="ai-c ai:bg-surface-alt">
  <div class="ai:flex ai:flex-col ai:lg:min-h-[36rem] ai:lg:flex-row ai:lg:items-stretch">
    <?php if ( $image ) : ?>
    <div class="ai:relative ai:order-2 ai:h-80 ai:w-full ai:overflow-hidden ai:lg:order-1 ai:lg:h-auto ai:lg:w-5/12">
      <img alt="" src="<?php echo esc_url( $image ); ?>" class="ai:absolute ai:inset-0 ai:block ai:size-full ai:object-cover" />
      <?php if ( $stat_value ) : ?>
      <!-- the figure sits on the picture, over a gradient so it stays readable on any photograph -->
      <div class="ai:absolute ai:inset-x-0 ai:bottom-0 ai:bg-gradient-to-t ai:from-black/80 ai:to-transparent ai:p-6 ai:sm:p-8">
        <p class="ai:m-0 ai:text-5xl ai:font-bold ai:tracking-tight ai:text-white"><?php echo esc_html( $stat_value ); ?></p>
        <?php if ( $stat_label ) : ?><p class="ai:m-0 ai:mt-1 ai:max-w-xs ai:text-sm ai:text-white/80"><?php echo esc_html( $stat_label ); ?></p><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="<?php echo esc_attr( $su_col ); ?>">
      <div class="ai:w-full ai:max-w-xl">
        <?php if ( $eyebrow ) : ?><p class="<?php echo esc_attr( $pill ); ?>"><?php echo esc_html( $eyebrow ); ?></p><?php endif; ?>
        <h1 class="ai:m-0 ai:text-4xl ai:font-bold ai:tracking-tight ai:text-balance ai:text-ink ai:sm:text-5xl ai:lg:text-6xl"><?php echo esc_html( $heading ); ?></h1>
        <?php if ( $text ) : ?><p class="<?php echo esc_attr( $lede ); ?>"><?php echo esc_html( $text ); ?></p><?php endif; ?>

        <?php if ( $action ) : ?>
        <form action="<?php echo esc_url( $action ); ?>" method="post" class="ai:mt-8">
          <div class="ai:flex ai:flex-col ai:gap-3 ai:sm:flex-row">
            <label for="ai-hero-signup" class="ai:sr-only"><?php echo esc_html( $placeholder ); ?></label>
            <input id="ai-hero-signup" type="email" required name="<?php echo esc_attr( $field_name ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>"
              class="ai:w-full ai:min-w-0 ai:flex-1 ai:rounded-lg ai:bg-surface ai:px-4 ai:py-3 ai:text-base ai:text-ink ai:ring-1 ai:ring-inset ai:ring-line ai:focus:outline-2 ai:focus:outline-offset-2 ai:focus:outline-brand-600" />
            <button type="submit" class="ai:inline-flex ai:items-center ai:justify-center ai:rounded-lg ai:bg-brand-600 ai:px-6 ai:py-3 ai:text-base ai:font-semibold ai:text-white ai:transition-colors ai:hover:bg-brand-700 ai:focus-visible:outline-2 ai:focus-visible:outline-offset-2 ai:focus-visible:outline-brand-600">
              <?php echo esc_html( $btn_label ); ?>
            </button>
          </div>
        </form>
        <?php else : ?>
        <div class="ai:mt-8 ai:flex ai:flex-wrap ai:items-center ai:gap-3"><?php ai_hero_buttons( $args ); ?></div>
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
$rv_panel    = ai_cls( 'ai:relative ai:overflow-hidden ai:rounded-band ai:bg-brand-900 ai:px-6 ai:pt-sec-sm ai:text-center ai:sm:px-12 ai:sm:pt-sec' )
	. ' ' . ( $image ? ai_cls( 'ai:pb-40 ai:sm:pb-56' ) : ai_cls( 'ai:pb-sec-sm ai:sm:pb-sec' ) );
?>
<!-- The picture overlaps the panel's lower edge. The overlap is INSIDE this section, so the sections around it
     keep their own spacing and nothing is clipped by a theme that sets overflow on the content wrapper. -->
<section class="ai-c ai:bg-surface ai:pb-sec-sm ai:sm:pb-sec">
  <div class="ai:mx-auto ai:max-w-wide ai:px-4 ai:sm:px-6 ai:lg:px-8">
    <div class="<?php echo esc_attr( $rv_panel ); ?>">
      <?php ai_hero_glow(); ?>
      <div class="ai:relative ai:mx-auto ai:max-w-2xl">
        <?php if ( $eyebrow ) : ?><p class="<?php echo esc_attr( $d_eyebrow ); ?>"><?php echo esc_html( $eyebrow ); ?></p><?php endif; ?>
        <h1 class="ai:m-0 ai:text-5xl ai:font-bold ai:tracking-tight ai:text-balance ai:text-white ai:sm:text-7xl"><?php echo esc_html( $heading ); ?></h1>
        <?php if ( $text ) : ?><p class="<?php echo esc_attr( $d_lede ); ?>"><?php echo esc_html( $text ); ?></p><?php endif; ?>
        <div class="ai:mt-8 ai:flex ai:flex-wrap ai:items-center ai:justify-center ai:gap-3"><?php ai_hero_buttons( $args, 'light' ); ?></div>
        <?php if ( $proof ) : ?><p class="<?php echo esc_attr( $d_note ); ?>"><?php echo esc_html( $proof ); ?></p><?php endif; ?>
      </div>
    </div>

    <?php if ( $image ) : ?>
    <!-- pulled up over the panel it just left: one object straddling two bands -->
    <div class="ai:relative ai:z-10 ai:mx-auto ai:-mt-32 ai:max-w-4xl ai:sm:-mt-44">
      <div class="ai:relative ai:overflow-hidden ai:rounded-band ai:shadow-2xl ai:ring-1 ai:ring-ink/10">
        <img alt="" src="<?php echo esc_url( $image ); ?>" class="ai:block ai:aspect-16/9 ai:h-auto ai:w-full ai:max-w-full ai:object-cover" />
        <?php if ( $video_url ) : ?>
        <!-- a link, not a player: no JavaScript ships to the site -->
        <a href="<?php echo esc_url( $video_url ); ?>" class="ai:absolute ai:inset-0 ai:flex ai:flex-col ai:items-center ai:justify-center ai:gap-3 ai:bg-black/30 ai:no-underline ai:transition-colors ai:hover:bg-black/40">
          <span class="ai:flex ai:size-16 ai:items-center ai:justify-center ai:rounded-full ai:ring-2 ai:ring-inset ai:ring-white/70">
            <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="ai:size-6 ai:text-white"><path d="M8.04 4.92A.9.9 0 0 0 6.7 5.7v12.6a.9.9 0 0 0 1.34.78l10.37-6.3a.9.9 0 0 0 0-1.56L8.04 4.92Z" /></svg>
          </span>
          <span class="ai:text-base ai:font-semibold ai:text-white"><?php echo esc_html( $video_label ); ?></span>
        </a>
        <?php endif; ?>
      </div>
    </div>
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
