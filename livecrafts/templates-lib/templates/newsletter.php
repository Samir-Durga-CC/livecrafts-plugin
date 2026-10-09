<?php
/**
 * Component: newsletter (markup only: point action at your mail service / form plugin endpoint)
 * Params: heading, text, action, method, field_name, button_label, placeholder, note
 * Shortcode: [ai_newsletter heading="" text="" action="https://..." button_label="Subscribe"]
 */
$heading      = $args['heading'] ?? '';
$text         = $args['text'] ?? '';
$action       = $args['action'] ?? '';
$field_name   = $args['field_name'] ?? 'email';
$button_label = $args['button_label'] ?? 'Subscribe';
$placeholder  = $args['placeholder'] ?? 'Enter your email';
$note         = $args['note'] ?? '';
?>
<section class="ai-c ai:bg-surface ai:px-4 ai:py-12 ai:sm:px-6 ai:sm:py-16 ai:lg:px-8">
  <div class="ai:mx-auto ai:grid ai:max-w-wide ai:items-center ai:gap-8 ai:rounded-band ai:bg-brand-50 ai:px-6 ai:py-12 ai:ring-1 ai:ring-inset ai:ring-brand-100 ai:sm:px-12 ai:lg:grid-cols-2 ai:lg:gap-16">
    <div>
      <h2 class="ai:m-0 ai:text-2xl ai:font-semibold ai:tracking-tight ai:text-balance ai:text-ink ai:sm:text-3xl"><?php echo esc_html( $heading ); ?></h2>
      <?php if ( $text ) : ?><p class="ai:mt-3 ai:mb-0 ai:text-base ai:text-pretty ai:text-muted"><?php echo esc_html( $text ); ?></p><?php endif; ?>
    </div>
    <div>
      <form action="<?php echo esc_url( $action ); ?>" method="post" class="ai:m-0 ai:flex ai:flex-col ai:gap-3 ai:sm:flex-row">
        <label for="ai-nl-email" class="ai:sr-only">Email address</label>
        <input id="ai-nl-email" type="email" name="<?php echo esc_attr( $field_name ); ?>" required autocomplete="email" placeholder="<?php echo esc_attr( $placeholder ); ?>" class="ai:block ai:w-full ai:min-w-0 ai:flex-auto ai:rounded-lg ai:border-0 ai:bg-surface ai:px-4 ai:py-3 ai:text-base ai:text-ink ai:shadow-sm ai:ring-1 ai:ring-inset ai:ring-gray-300 ai:placeholder:text-gray-400 ai:focus:ring-2 ai:focus:ring-brand-600 ai:focus:outline-none" />
        <button type="submit" class="ai:inline-flex ai:shrink-0 ai:cursor-pointer ai:items-center ai:justify-center ai:rounded-lg ai:border-0 ai:bg-brand-600 ai:px-6 ai:py-3 ai:text-base ai:font-semibold ai:text-white ai:shadow-sm ai:transition-colors ai:hover:bg-brand-700 ai:focus-visible:outline-2 ai:focus-visible:outline-offset-2 ai:focus-visible:outline-brand-600"><?php echo esc_html( $button_label ); ?></button>
      </form>
      <?php if ( $note ) : ?><p class="ai:mt-3 ai:mb-0 ai:text-sm ai:text-subtle"><?php echo esc_html( $note ); ?></p><?php endif; ?>
    </div>
  </div>
</section>
