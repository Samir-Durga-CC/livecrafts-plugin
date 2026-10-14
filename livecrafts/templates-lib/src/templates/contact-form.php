<?php
/**
 * Component: contact-form (markup only: point action at your form handler / plugin endpoint)
 * Params: variant (split|stacked|inline), heading, text, action, button_label, email, phone, address, hours
 * Shortcode: [ai_contact_form variant="split" heading="" action="https://..." email="" phone="" address=""]
 *   split   - contact details beside the form (default)
 *   stacked - just the form, one narrow column (good inside a page that already explains the context)
 *   inline  - a dark band with name / phone / button on one line (call-back strip; no message field)
 */
$variant      = in_array( $args['variant'] ?? 'split', array( 'split', 'stacked', 'inline' ), true ) ? $args['variant'] : 'split';
$heading      = $args['heading'] ?? '';
$text         = $args['text'] ?? '';
$action       = $args['action'] ?? '';
$button_label = $args['button_label'] ?? 'Send message';
$info         = array(
	'mail'  => $args['email'] ?? '',
	'phone' => $args['phone'] ?? '',
	'pin'   => $args['address'] ?? '',
	'clock' => $args['hours'] ?? '',
);
$input = ai_cls( 'mt-2 block w-full rounded-lg border-0 bg-surface px-4 py-3 text-base text-ink shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-brand-600 focus:outline-none' );
$dark  = ai_cls( 'block w-full min-w-0 rounded-lg border-0 bg-white/10 px-4 py-3 text-base text-white shadow-sm ring-1 ring-inset ring-white/20 placeholder:text-white/60 focus:ring-2 focus:ring-white focus:outline-none' );
$btn   = ai_cls( 'inline-flex cursor-pointer items-center justify-center rounded-lg border-0 bg-brand-600 px-6 py-3 text-base font-semibold text-white shadow-sm transition-colors hover:bg-brand-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600' );
$label = ai_cls( 'block text-sm font-semibold text-ink' );
?>
<?php if ( 'inline' === $variant ) : ?>
<section class="ai-c bg-ink py-sec-sm sm:py-sec">
  <div class="mx-auto max-w-wide px-4 sm:px-6 lg:px-8">
    <div class="grid items-center gap-8 lg:grid-cols-5">
      <div class="lg:col-span-2">
        <?php if ( $heading ) : ?>
        <h2 class="m-0 text-2xl font-semibold tracking-tight text-balance text-surface sm:text-3xl"><?php echo esc_html( $heading ); ?></h2>
        <?php endif; ?>
        <?php if ( $text ) : ?><p class="mt-3 mb-0 text-base text-pretty text-brand-200"><?php echo esc_html( $text ); ?></p><?php endif; ?>
      </div>
      <form action="<?php echo esc_url( $action ); ?>" method="post" class="m-0 grid gap-4 sm:grid-cols-3 lg:col-span-3">
        <div>
          <label for="ai-cf-i-name" class="sr-only">Name</label>
          <input id="ai-cf-i-name" type="text" name="name" required autocomplete="name" placeholder="Name" class="<?php echo esc_attr( $dark ); ?>" />
        </div>
        <div>
          <label for="ai-cf-i-phone" class="sr-only">Phone</label>
          <input id="ai-cf-i-phone" type="tel" name="phone" required autocomplete="tel" placeholder="Phone" class="<?php echo esc_attr( $dark ); ?>" />
        </div>
        <button type="submit" class="<?php echo esc_attr( $btn ); ?>"><?php echo esc_html( $button_label ); ?></button>
      </form>
    </div>
  </div>
</section>

<?php elseif ( 'stacked' === $variant ) : ?>
<section class="ai-c bg-surface py-sec-sm sm:py-sec">
  <div class="mx-auto max-w-xl px-4 sm:px-6 lg:px-8">
    <?php if ( $heading ) : ?>
    <h2 class="m-0 text-3xl font-semibold tracking-tight text-balance text-ink sm:text-4xl"><?php echo esc_html( $heading ); ?></h2>
    <?php endif; ?>
    <?php if ( $text ) : ?><p class="mt-4 mb-0 text-lg text-pretty text-muted"><?php echo esc_html( $text ); ?></p><?php endif; ?>
    <form action="<?php echo esc_url( $action ); ?>" method="post" class="m-0 mt-8">
      <div class="grid gap-6">
        <div>
          <label for="ai-cf-s-name" class="<?php echo esc_attr( $label ); ?>">Name</label>
          <input id="ai-cf-s-name" type="text" name="name" required autocomplete="name" class="<?php echo esc_attr( $input ); ?>" />
        </div>
        <div>
          <label for="ai-cf-s-email" class="<?php echo esc_attr( $label ); ?>">Email</label>
          <input id="ai-cf-s-email" type="email" name="email" required autocomplete="email" class="<?php echo esc_attr( $input ); ?>" />
        </div>
        <div>
          <label for="ai-cf-s-message" class="<?php echo esc_attr( $label ); ?>">Message</label>
          <textarea id="ai-cf-s-message" name="message" rows="5" required class="<?php echo esc_attr( $input ); ?>"></textarea>
        </div>
      </div>
      <div class="mt-6">
        <button type="submit" class="<?php echo esc_attr( $btn ); ?> w-full sm:w-auto"><?php echo esc_html( $button_label ); ?></button>
      </div>
    </form>
  </div>
</section>

<?php else : ?>
<section class="ai-c bg-surface py-sec-sm sm:py-sec">
  <div class="mx-auto grid max-w-wide gap-12 px-4 sm:px-6 lg:grid-cols-5 lg:gap-16 lg:px-8">
    <div class="lg:col-span-2">
      <h2 class="m-0 text-3xl font-semibold tracking-tight text-balance text-ink sm:text-4xl"><?php echo esc_html( $heading ); ?></h2>
      <?php if ( $text ) : ?><p class="mt-4 mb-0 text-lg text-pretty text-muted"><?php echo esc_html( $text ); ?></p><?php endif; ?>
      <ul class="m-0 mt-10 flex list-none flex-col gap-5 p-0">
        <?php foreach ( $info as $icon => $value ) : ?>
			<?php if ( $value ) : ?>
        <li class="m-0 flex items-start gap-4 p-0">
          <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600 ring-1 ring-inset ring-brand-100"><?php ai_e_icon( $icon, 5 ); ?></span>
          <span class="pt-2 text-sm text-muted"><?php echo esc_html( $value ); ?></span>
        </li>
			<?php endif; ?>
        <?php endforeach; ?>
      </ul>
    </div>
    <form action="<?php echo esc_url( $action ); ?>" method="post" class="m-0 rounded-card border border-line bg-surface p-6 shadow-sm sm:p-8 lg:col-span-3">
      <div class="grid gap-6 sm:grid-cols-2">
        <div>
          <label for="ai-cf-name" class="<?php echo esc_attr( $label ); ?>">Name</label>
          <input id="ai-cf-name" type="text" name="name" required autocomplete="name" class="<?php echo esc_attr( $input ); ?>" />
        </div>
        <div>
          <label for="ai-cf-email" class="<?php echo esc_attr( $label ); ?>">Email</label>
          <input id="ai-cf-email" type="email" name="email" required autocomplete="email" class="<?php echo esc_attr( $input ); ?>" />
        </div>
        <div class="sm:col-span-2">
          <label for="ai-cf-message" class="<?php echo esc_attr( $label ); ?>">Message</label>
          <textarea id="ai-cf-message" name="message" rows="5" required class="<?php echo esc_attr( $input ); ?>"></textarea>
        </div>
      </div>
      <div class="mt-6 flex justify-end">
        <button type="submit" class="<?php echo esc_attr( $btn ); ?>"><?php echo esc_html( $button_label ); ?></button>
      </div>
    </form>
  </div>
</section>
<?php endif; ?>
