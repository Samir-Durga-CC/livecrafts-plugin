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
$input = ai_cls( 'ai:mt-2 ai:block ai:w-full ai:rounded-lg ai:border-0 ai:bg-surface ai:px-4 ai:py-3 ai:text-base ai:text-ink ai:shadow-sm ai:ring-1 ai:ring-inset ai:ring-gray-300 ai:placeholder:text-gray-400 ai:focus:ring-2 ai:focus:ring-brand-600 ai:focus:outline-none' );
$dark  = ai_cls( 'ai:block ai:w-full ai:min-w-0 ai:rounded-lg ai:border-0 ai:bg-white/10 ai:px-4 ai:py-3 ai:text-base ai:text-white ai:shadow-sm ai:ring-1 ai:ring-inset ai:ring-white/20 ai:placeholder:text-white/60 ai:focus:ring-2 ai:focus:ring-white ai:focus:outline-none' );
$btn   = ai_cls( 'ai:inline-flex ai:cursor-pointer ai:items-center ai:justify-center ai:rounded-lg ai:border-0 ai:bg-brand-600 ai:px-6 ai:py-3 ai:text-base ai:font-semibold ai:text-white ai:shadow-sm ai:transition-colors ai:hover:bg-brand-700 ai:focus-visible:outline-2 ai:focus-visible:outline-offset-2 ai:focus-visible:outline-brand-600' );
$label = ai_cls( 'ai:block ai:text-sm ai:font-semibold ai:text-ink' );
?>
<?php if ( 'inline' === $variant ) : ?>
<section class="ai-c ai:bg-ink ai:py-sec-sm ai:sm:py-sec">
  <div class="ai:mx-auto ai:max-w-wide ai:px-4 ai:sm:px-6 ai:lg:px-8">
    <div class="ai:grid ai:items-center ai:gap-8 ai:lg:grid-cols-5">
      <div class="ai:lg:col-span-2">
        <?php if ( $heading ) : ?>
        <h2 class="ai:m-0 ai:text-2xl ai:font-semibold ai:tracking-tight ai:text-balance ai:text-surface ai:sm:text-3xl"><?php echo esc_html( $heading ); ?></h2>
        <?php endif; ?>
        <?php if ( $text ) : ?><p class="ai:mt-3 ai:mb-0 ai:text-base ai:text-pretty ai:text-brand-200"><?php echo esc_html( $text ); ?></p><?php endif; ?>
      </div>
      <form action="<?php echo esc_url( $action ); ?>" method="post" class="ai:m-0 ai:grid ai:gap-4 ai:sm:grid-cols-3 ai:lg:col-span-3">
        <div>
          <label for="ai-cf-i-name" class="ai:sr-only">Name</label>
          <input id="ai-cf-i-name" type="text" name="name" required autocomplete="name" placeholder="Name" class="<?php echo esc_attr( $dark ); ?>" />
        </div>
        <div>
          <label for="ai-cf-i-phone" class="ai:sr-only">Phone</label>
          <input id="ai-cf-i-phone" type="tel" name="phone" required autocomplete="tel" placeholder="Phone" class="<?php echo esc_attr( $dark ); ?>" />
        </div>
        <button type="submit" class="<?php echo esc_attr( $btn ); ?>"><?php echo esc_html( $button_label ); ?></button>
      </form>
    </div>
  </div>
</section>

<?php elseif ( 'stacked' === $variant ) : ?>
<section class="ai-c ai:bg-surface ai:py-sec-sm ai:sm:py-sec">
  <div class="ai:mx-auto ai:max-w-xl ai:px-4 ai:sm:px-6 ai:lg:px-8">
    <?php if ( $heading ) : ?>
    <h2 class="ai:m-0 ai:text-3xl ai:font-semibold ai:tracking-tight ai:text-balance ai:text-ink ai:sm:text-4xl"><?php echo esc_html( $heading ); ?></h2>
    <?php endif; ?>
    <?php if ( $text ) : ?><p class="ai:mt-4 ai:mb-0 ai:text-lg ai:text-pretty ai:text-muted"><?php echo esc_html( $text ); ?></p><?php endif; ?>
    <form action="<?php echo esc_url( $action ); ?>" method="post" class="ai:m-0 ai:mt-8">
      <div class="ai:grid ai:gap-6">
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
      <div class="ai:mt-6">
        <button type="submit" class="<?php echo esc_attr( $btn ); ?> w-full sm:w-auto"><?php echo esc_html( $button_label ); ?></button>
      </div>
    </form>
  </div>
</section>

<?php else : ?>
<section class="ai-c ai:bg-surface ai:py-sec-sm ai:sm:py-sec">
  <div class="ai:mx-auto ai:grid ai:max-w-wide ai:gap-12 ai:px-4 ai:sm:px-6 ai:lg:grid-cols-5 ai:lg:gap-16 ai:lg:px-8">
    <div class="ai:lg:col-span-2">
      <h2 class="ai:m-0 ai:text-3xl ai:font-semibold ai:tracking-tight ai:text-balance ai:text-ink ai:sm:text-4xl"><?php echo esc_html( $heading ); ?></h2>
      <?php if ( $text ) : ?><p class="ai:mt-4 ai:mb-0 ai:text-lg ai:text-pretty ai:text-muted"><?php echo esc_html( $text ); ?></p><?php endif; ?>
      <ul class="ai:m-0 ai:mt-10 ai:flex ai:list-none ai:flex-col ai:gap-5 ai:p-0">
        <?php foreach ( $info as $icon => $value ) : ?>
			<?php if ( $value ) : ?>
        <li class="ai:m-0 ai:flex ai:items-start ai:gap-4 ai:p-0">
          <span class="ai:flex ai:size-10 ai:shrink-0 ai:items-center ai:justify-center ai:rounded-lg ai:bg-brand-50 ai:text-brand-600 ai:ring-1 ai:ring-inset ai:ring-brand-100"><?php ai_e_icon( $icon, 5 ); ?></span>
          <span class="ai:pt-2 ai:text-sm ai:text-muted"><?php echo esc_html( $value ); ?></span>
        </li>
			<?php endif; ?>
        <?php endforeach; ?>
      </ul>
    </div>
    <form action="<?php echo esc_url( $action ); ?>" method="post" class="ai:m-0 ai:rounded-card ai:border ai:border-line ai:bg-surface ai:p-6 ai:shadow-sm ai:sm:p-8 ai:lg:col-span-3">
      <div class="ai:grid ai:gap-6 ai:sm:grid-cols-2">
        <div>
          <label for="ai-cf-name" class="<?php echo esc_attr( $label ); ?>">Name</label>
          <input id="ai-cf-name" type="text" name="name" required autocomplete="name" class="<?php echo esc_attr( $input ); ?>" />
        </div>
        <div>
          <label for="ai-cf-email" class="<?php echo esc_attr( $label ); ?>">Email</label>
          <input id="ai-cf-email" type="email" name="email" required autocomplete="email" class="<?php echo esc_attr( $input ); ?>" />
        </div>
        <div class="ai:sm:col-span-2">
          <label for="ai-cf-message" class="<?php echo esc_attr( $label ); ?>">Message</label>
          <textarea id="ai-cf-message" name="message" rows="5" required class="<?php echo esc_attr( $input ); ?>"></textarea>
        </div>
      </div>
      <div class="ai:mt-6 ai:flex ai:justify-end">
        <button type="submit" class="<?php echo esc_attr( $btn ); ?>"><?php echo esc_html( $button_label ); ?></button>
      </div>
    </form>
  </div>
</section>
<?php endif; ?>
