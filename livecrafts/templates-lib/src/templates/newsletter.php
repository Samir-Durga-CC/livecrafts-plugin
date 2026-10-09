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
<section class="ai-c bg-surface px-4 py-12 sm:px-6 sm:py-16 lg:px-8">
  <div class="mx-auto grid max-w-wide items-center gap-8 rounded-band bg-brand-50 px-6 py-12 ring-1 ring-inset ring-brand-100 sm:px-12 lg:grid-cols-2 lg:gap-16">
    <div>
      <h2 class="m-0 text-2xl font-semibold tracking-tight text-balance text-ink sm:text-3xl"><?php echo esc_html( $heading ); ?></h2>
      <?php if ( $text ) : ?><p class="mt-3 mb-0 text-base text-pretty text-muted"><?php echo esc_html( $text ); ?></p><?php endif; ?>
    </div>
    <div>
      <form action="<?php echo esc_url( $action ); ?>" method="post" class="m-0 flex flex-col gap-3 sm:flex-row">
        <label for="ai-nl-email" class="sr-only">Email address</label>
        <input id="ai-nl-email" type="email" name="<?php echo esc_attr( $field_name ); ?>" required autocomplete="email" placeholder="<?php echo esc_attr( $placeholder ); ?>" class="block w-full min-w-0 flex-auto rounded-lg border-0 bg-surface px-4 py-3 text-base text-ink shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-brand-600 focus:outline-none" />
        <button type="submit" class="inline-flex shrink-0 cursor-pointer items-center justify-center rounded-lg border-0 bg-brand-600 px-6 py-3 text-base font-semibold text-white shadow-sm transition-colors hover:bg-brand-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600"><?php echo esc_html( $button_label ); ?></button>
      </form>
      <?php if ( $note ) : ?><p class="mt-3 mb-0 text-sm text-subtle"><?php echo esc_html( $note ); ?></p><?php endif; ?>
    </div>
  </div>
</section>
