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
<section class="ai-c ai:bg-surface ai:py-sec-sm ai:sm:py-sec">
  <div class="ai:mx-auto ai:max-w-wide ai:px-4 ai:sm:px-6 ai:lg:px-8">
    <?php if ( $heading ) : ?>
    <div class="ai:mx-auto ai:max-w-2xl ai:text-center">
      <h2 class="ai:m-0 ai:text-3xl ai:font-semibold ai:tracking-tight ai:text-balance ai:text-ink ai:sm:text-4xl"><?php echo esc_html( $heading ); ?></h2>
      <?php if ( $text ) : ?><p class="ai:mt-4 ai:mb-0 ai:text-lg ai:text-pretty ai:text-muted"><?php echo esc_html( $text ); ?></p><?php endif; ?>
    </div>
    <?php endif; ?>
    <dl class="ai:m-0 ai:mt-14 ai:grid ai:gap-x-8 ai:gap-y-12 ai:sm:grid-cols-2 ai:lg:grid-cols-3">
      <?php foreach ( $items as $item ) : ?>
      <div class="ai:m-0 ai:rounded-card ai:border ai:border-line ai:bg-surface ai:p-6 ai:shadow-sm ai:transition ai:hover:border-brand-200 ai:hover:shadow-md">
        <dt class="ai:m-0 ai:flex ai:items-center ai:gap-3 ai:text-base ai:font-semibold ai:text-ink">
          <span class="ai:flex ai:size-10 ai:items-center ai:justify-center ai:rounded-lg ai:bg-brand-50 ai:text-brand-600 ai:ring-1 ai:ring-inset ai:ring-brand-100"><?php ai_e_icon( $item['icon'] ?? 'bolt', 5 ); ?></span>
          <?php echo esc_html( $item['title'] ?? '' ); ?>
        </dt>
        <dd class="ai:m-0 ai:mt-4 ai:text-sm/6 ai:text-muted"><?php echo esc_html( $item['text'] ?? '' ); ?></dd>
      </div>
      <?php endforeach; ?>
    </dl>
  </div>
</section>

<?php elseif ( 'checklist' === $variant ) : ?>
<section class="ai-c ai:bg-surface ai:py-sec-sm ai:sm:py-sec">
  <div class="ai:mx-auto ai:grid ai:max-w-wide ai:items-center ai:gap-10 ai:px-4 ai:sm:px-6 ai:lg:grid-cols-2 ai:lg:gap-16 ai:lg:px-8">
    <div>
      <?php if ( $heading ) : ?>
      <h2 class="ai:m-0 ai:text-3xl ai:font-semibold ai:tracking-tight ai:text-balance ai:text-ink ai:sm:text-4xl"><?php echo esc_html( $heading ); ?></h2>
      <?php endif; ?>
      <?php if ( $text ) : ?><p class="ai:mt-4 ai:mb-0 ai:text-lg ai:text-pretty ai:text-muted"><?php echo esc_html( $text ); ?></p><?php endif; ?>
      <?php if ( $label ) : ?>
      <a href="<?php echo esc_url( $url ); ?>" class="ai:mt-8 ai:inline-flex ai:items-center ai:justify-center ai:rounded-lg ai:bg-brand-600 ai:px-6 ai:py-3 ai:text-base ai:font-semibold ai:text-white ai:shadow-sm ai:transition-colors ai:hover:bg-brand-700 ai:focus-visible:outline-2 ai:focus-visible:outline-offset-2 ai:focus-visible:outline-brand-600"><?php echo esc_html( $label ); ?></a>
      <?php endif; ?>
    </div>
    <ul class="ai:m-0 ai:flex ai:list-none ai:flex-col ai:gap-5 ai:p-0">
      <?php foreach ( $items as $item ) : ?>
      <li class="ai:m-0 ai:flex ai:items-start ai:gap-4 ai:p-0">
        <span class="ai:flex ai:size-7 ai:shrink-0 ai:items-center ai:justify-center ai:rounded-full ai:bg-brand-50 ai:text-brand-600 ai:ring-1 ai:ring-inset ai:ring-brand-100"><?php ai_e_icon( 'check', 4 ); ?></span>
        <span>
          <span class="ai:block ai:text-base ai:font-semibold ai:text-ink"><?php echo esc_html( $item['title'] ?? '' ); ?></span>
          <?php if ( ! empty( $item['text'] ) ) : ?><span class="ai:mt-1 ai:block ai:text-sm/6 ai:text-muted"><?php echo esc_html( $item['text'] ); ?></span><?php endif; ?>
        </span>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>

<?php else : ?>
<section class="ai-c ai:bg-ink ai:py-sec-sm ai:sm:py-sec">
  <div class="ai:mx-auto ai:max-w-wide ai:px-4 ai:sm:px-6 ai:lg:px-8">
    <?php if ( $heading ) : ?>
    <div class="ai:mx-auto ai:max-w-2xl ai:text-center">
      <h2 class="ai:m-0 ai:text-3xl ai:font-semibold ai:tracking-tight ai:text-balance ai:text-surface ai:sm:text-4xl"><?php echo esc_html( $heading ); ?></h2>
      <?php if ( $text ) : ?><p class="ai:mt-4 ai:mb-0 ai:text-lg ai:text-pretty ai:text-brand-200"><?php echo esc_html( $text ); ?></p><?php endif; ?>
    </div>
    <?php endif; ?>
    <dl class="ai:m-0 ai:mt-12 ai:grid ai:gap-10 ai:text-center ai:sm:grid-cols-2 ai:lg:grid-cols-3">
      <?php foreach ( $items as $item ) : ?>
      <div class="ai:m-0">
        <dt class="ai:m-0 ai:text-4xl ai:font-bold ai:tracking-tight ai:text-brand-300 ai:sm:text-5xl"><?php echo esc_html( $item['title'] ?? '' ); ?></dt>
        <dd class="ai:m-0 ai:mt-2 ai:text-base ai:text-surface"><?php echo esc_html( $item['text'] ?? '' ); ?></dd>
      </div>
      <?php endforeach; ?>
    </dl>
  </div>
</section>
<?php endif; ?>
