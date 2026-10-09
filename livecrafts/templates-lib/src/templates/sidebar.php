<?php
/**
 * Component: sidebar (link list with optional search and promo box)
 * Params: heading, items [ label, url, current ], box_title, box_text, box_label, box_url, search_action
 * Shortcode: [ai_sidebar heading="Categories" box_title="Need help?" box_label="Contact us"][ai_item label="Design" url="/design"]...[/ai_sidebar]
 */
$heading       = $args['heading'] ?? '';
$items         = ai_items( $args );
$box_title     = $args['box_title'] ?? '';
$box_text      = $args['box_text'] ?? '';
$box_label     = $args['box_label'] ?? '';
$box_url       = $args['box_url'] ?? '#';
$search_action = $args['search_action'] ?? '';
?>
<aside class="ai-c flex w-full max-w-sm flex-col gap-6">
  <?php if ( $search_action ) : ?>
  <form action="<?php echo esc_url( $search_action ); ?>" method="get" role="search" class="relative m-0">
    <label for="ai-sb-search" class="sr-only">Search</label>
    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400"><?php ai_e_icon( 'search', 5 ); ?></span>
    <input id="ai-sb-search" type="search" name="s" placeholder="Search" class="block w-full rounded-lg border-0 bg-surface py-3 pr-4 pl-10 text-sm text-ink shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-brand-600 focus:outline-none" />
  </form>
  <?php endif; ?>

  <nav class="rounded-card border border-line bg-surface p-5 shadow-sm" aria-label="<?php echo esc_attr( $heading ? $heading : 'Sidebar' ); ?>">
    <?php if ( $heading ) : ?>
    <h2 class="m-0 text-xs font-semibold tracking-wider text-subtle uppercase"><?php echo esc_html( $heading ); ?></h2>
    <?php endif; ?>
    <ul class="m-0 mt-3 flex list-none flex-col gap-1 p-0">
      <?php foreach ( $items as $item ) : ?>
      <li class="m-0 p-0">
        <a href="<?php echo esc_url( $item['url'] ?? '#' ); ?>" class="<?php echo esc_attr( ai_cls( 'flex items-center justify-between rounded-lg px-3 py-2 text-sm font-medium no-underline transition-colors' ) . ' ' . ( ! empty( $item['current'] ) ? ai_cls( 'bg-brand-50 text-brand-700' ) : ai_cls( 'text-muted hover:bg-surface-alt hover:text-ink' ) ) ); ?>">
          <span><?php echo esc_html( $item['label'] ?? '' ); ?></span>
          <span class="text-gray-400"><?php ai_e_icon( 'chevron-right', 4 ); ?></span>
        </a>
      </li>
      <?php endforeach; ?>
    </ul>
  </nav>

  <?php if ( $box_title ) : ?>
  <div class="rounded-card bg-brand-700 p-6">
    <h3 class="m-0 text-base font-semibold text-white"><?php echo esc_html( $box_title ); ?></h3>
    <?php if ( $box_text ) : ?><p class="mt-2 mb-0 text-sm/6 text-brand-100"><?php echo esc_html( $box_text ); ?></p><?php endif; ?>
    <?php if ( $box_label ) : ?>
    <div class="mt-5 flex"><?php ai_component( 'button', array( 'label' => $box_label, 'url' => $box_url, 'variant' => 'light', 'icon' => 'none' ) ); ?></div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</aside>
