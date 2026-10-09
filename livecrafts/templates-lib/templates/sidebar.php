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
<aside class="ai-c ai:flex ai:w-full ai:max-w-sm ai:flex-col ai:gap-6">
  <?php if ( $search_action ) : ?>
  <form action="<?php echo esc_url( $search_action ); ?>" method="get" role="search" class="ai:relative ai:m-0">
    <label for="ai-sb-search" class="ai:sr-only">Search</label>
    <span class="ai:pointer-events-none ai:absolute ai:inset-y-0 ai:left-3 ai:flex ai:items-center ai:text-gray-400"><?php ai_e_icon( 'search', 5 ); ?></span>
    <input id="ai-sb-search" type="search" name="s" placeholder="Search" class="ai:block ai:w-full ai:rounded-lg ai:border-0 ai:bg-surface ai:py-3 ai:pr-4 ai:pl-10 ai:text-sm ai:text-ink ai:shadow-sm ai:ring-1 ai:ring-inset ai:ring-gray-300 ai:placeholder:text-gray-400 ai:focus:ring-2 ai:focus:ring-brand-600 ai:focus:outline-none" />
  </form>
  <?php endif; ?>

  <nav class="ai:rounded-card ai:border ai:border-line ai:bg-surface ai:p-5 ai:shadow-sm" aria-label="<?php echo esc_attr( $heading ? $heading : 'Sidebar' ); ?>">
    <?php if ( $heading ) : ?>
    <h2 class="ai:m-0 ai:text-xs ai:font-semibold ai:tracking-wider ai:text-subtle ai:uppercase"><?php echo esc_html( $heading ); ?></h2>
    <?php endif; ?>
    <ul class="ai:m-0 ai:mt-3 ai:flex ai:list-none ai:flex-col ai:gap-1 ai:p-0">
      <?php foreach ( $items as $item ) : ?>
      <li class="ai:m-0 ai:p-0">
        <a href="<?php echo esc_url( $item['url'] ?? '#' ); ?>" class="<?php echo esc_attr( ai_cls( 'ai:flex ai:items-center ai:justify-between ai:rounded-lg ai:px-3 ai:py-2 ai:text-sm ai:font-medium ai:no-underline ai:transition-colors' ) . ' ' . ( ! empty( $item['current'] ) ? ai_cls( 'ai:bg-brand-50 ai:text-brand-700' ) : ai_cls( 'ai:text-muted ai:hover:bg-surface-alt ai:hover:text-ink' ) ) ); ?>">
          <span><?php echo esc_html( $item['label'] ?? '' ); ?></span>
          <span class="ai:text-gray-400"><?php ai_e_icon( 'chevron-right', 4 ); ?></span>
        </a>
      </li>
      <?php endforeach; ?>
    </ul>
  </nav>

  <?php if ( $box_title ) : ?>
  <div class="ai:rounded-card ai:bg-brand-700 ai:p-6">
    <h3 class="ai:m-0 ai:text-base ai:font-semibold ai:text-white"><?php echo esc_html( $box_title ); ?></h3>
    <?php if ( $box_text ) : ?><p class="ai:mt-2 ai:mb-0 ai:text-sm/6 ai:text-brand-100"><?php echo esc_html( $box_text ); ?></p><?php endif; ?>
    <?php if ( $box_label ) : ?>
    <div class="ai:mt-5 ai:flex"><?php ai_component( 'button', array( 'label' => $box_label, 'url' => $box_url, 'variant' => 'light', 'icon' => 'none' ) ); ?></div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</aside>
