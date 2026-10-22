<?php
/**
 * Component: feature-grid ("why choose us", benefits, services)
 * Params: variant (icons|checklist|band), heading, text, primary_label, primary_url, items [ icon, title, text ]
 * Shortcode: [ai_feature_grid variant="icons" heading=""][ai_item icon="bolt" title="" text=""]...[/ai_feature_grid]
 *   icons     - one card per feature with its icon (default; 1 / 2 / 3 columns)
 *   checklist - heading, text and a button beside a ticked list (item title = the line, text = optional detail)
 *   band      - a dark band of figures: item title = the number, text = its label
 * Icons: bolt shield chart users globe lock star code clock grid calendar book check ...
 */
$variant = in_array( $args['variant'] ?? 'icons', array( 'icons', 'checklist', 'band' ), true ) ? $args['variant'] : 'icons';
$heading = $args['heading'] ?? '';
$text    = $args['text'] ?? '';
$label   = $args['primary_label'] ?? '';
$url     = $args['primary_url'] ?? '#';
$items   = ai_items( $args );
?>
<?php if ( 'icons' === $variant ) : ?>
<section class="ai-c bg-surface py-sec-sm sm:py-sec">
  <div class="mx-auto max-w-wide px-4 sm:px-6 lg:px-8">
    <?php if ( $heading ) : ?>
    <div class="mx-auto max-w-2xl text-center">
      <h2 class="m-0 text-3xl font-semibold tracking-tight text-balance text-ink sm:text-4xl"><?php echo esc_html( $heading ); ?></h2>
      <?php if ( $text ) : ?><p class="mt-4 mb-0 text-lg text-pretty text-muted"><?php echo esc_html( $text ); ?></p><?php endif; ?>
    </div>
    <?php endif; ?>
    <dl class="m-0 mt-14 grid gap-x-8 gap-y-12 sm:grid-cols-2 lg:grid-cols-3">
      <?php foreach ( $items as $item ) : ?>
      <div class="m-0 rounded-card border border-line bg-surface p-6 shadow-sm transition hover:border-brand-200 hover:shadow-md">
        <dt class="m-0 flex items-center gap-3 text-base font-semibold text-ink">
          <span class="flex size-10 items-center justify-center rounded-lg bg-brand-50 text-brand-600 ring-1 ring-inset ring-brand-100"><?php ai_e_icon( $item['icon'] ?? 'bolt', 5 ); ?></span>
          <?php echo esc_html( $item['title'] ?? '' ); ?>
        </dt>
        <dd class="m-0 mt-4 text-sm/6 text-muted"><?php echo esc_html( $item['text'] ?? '' ); ?></dd>
      </div>
      <?php endforeach; ?>
    </dl>
  </div>
</section>

<?php elseif ( 'checklist' === $variant ) : ?>
<section class="ai-c bg-surface py-sec-sm sm:py-sec">
  <div class="mx-auto grid max-w-wide items-center gap-10 px-4 sm:px-6 lg:grid-cols-2 lg:gap-16 lg:px-8">
    <div>
      <?php if ( $heading ) : ?>
      <h2 class="m-0 text-3xl font-semibold tracking-tight text-balance text-ink sm:text-4xl"><?php echo esc_html( $heading ); ?></h2>
      <?php endif; ?>
      <?php if ( $text ) : ?><p class="mt-4 mb-0 text-lg text-pretty text-muted"><?php echo esc_html( $text ); ?></p><?php endif; ?>
      <?php if ( $label ) : ?>
      <a href="<?php echo esc_url( $url ); ?>" class="mt-8 inline-flex items-center justify-center rounded-lg bg-brand-600 px-6 py-3 text-base font-semibold text-white shadow-sm transition-colors hover:bg-brand-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600"><?php echo esc_html( $label ); ?></a>
      <?php endif; ?>
    </div>
    <ul class="m-0 flex list-none flex-col gap-5 p-0">
      <?php foreach ( $items as $item ) : ?>
      <li class="m-0 flex items-start gap-4 p-0">
        <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-600 ring-1 ring-inset ring-brand-100"><?php ai_e_icon( 'check', 4 ); ?></span>
        <span>
          <span class="block text-base font-semibold text-ink"><?php echo esc_html( $item['title'] ?? '' ); ?></span>
          <?php if ( ! empty( $item['text'] ) ) : ?><span class="mt-1 block text-sm/6 text-muted"><?php echo esc_html( $item['text'] ); ?></span><?php endif; ?>
        </span>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>

<?php else : ?>
<section class="ai-c bg-ink py-sec-sm sm:py-sec">
  <div class="mx-auto max-w-wide px-4 sm:px-6 lg:px-8">
    <?php if ( $heading ) : ?>
    <div class="mx-auto max-w-2xl text-center">
      <h2 class="m-0 text-3xl font-semibold tracking-tight text-balance text-surface sm:text-4xl"><?php echo esc_html( $heading ); ?></h2>
      <?php if ( $text ) : ?><p class="mt-4 mb-0 text-lg text-pretty text-brand-200"><?php echo esc_html( $text ); ?></p><?php endif; ?>
    </div>
    <?php endif; ?>
    <dl class="m-0 mt-12 grid gap-10 text-center sm:grid-cols-2 lg:grid-cols-3">
      <?php foreach ( $items as $item ) : ?>
      <div class="m-0">
        <dt class="m-0 text-4xl font-bold tracking-tight text-brand-300 sm:text-5xl"><?php echo esc_html( $item['title'] ?? '' ); ?></dt>
        <dd class="m-0 mt-2 text-base text-surface"><?php echo esc_html( $item['text'] ?? '' ); ?></dd>
      </div>
      <?php endforeach; ?>
    </dl>
  </div>
</section>
<?php endif; ?>
