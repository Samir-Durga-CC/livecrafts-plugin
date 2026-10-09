<?php
/**
 * Component: contact-form (markup only: point action at your form handler / plugin endpoint)
 * Params: heading, text, action, button_label, email, phone, address, hours
 * Shortcode: [ai_contact_form heading="" text="" action="https://..." email="" phone="" address=""]
 */
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
$input = ai_cls( 'mt-2 block w-full rounded-lg border-0 bg-white px-4 py-3 text-base text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-brand-600 focus:outline-none' );
?>
<section class="ai-c bg-white py-16 sm:py-24">
  <div class="mx-auto grid max-w-7xl gap-12 px-4 sm:px-6 lg:grid-cols-5 lg:gap-16 lg:px-8">
    <div class="lg:col-span-2">
      <h2 class="m-0 text-3xl font-semibold tracking-tight text-balance text-gray-900 sm:text-4xl"><?php echo esc_html( $heading ); ?></h2>
      <?php if ( $text ) : ?><p class="mt-4 mb-0 text-lg text-pretty text-gray-600"><?php echo esc_html( $text ); ?></p><?php endif; ?>
      <ul class="m-0 mt-10 flex list-none flex-col gap-5 p-0">
        <?php foreach ( $info as $icon => $value ) : ?>
			<?php if ( $value ) : ?>
        <li class="m-0 flex items-start gap-4 p-0">
          <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600 ring-1 ring-inset ring-brand-100"><?php ai_e_icon( $icon, 5 ); ?></span>
          <span class="pt-2 text-sm text-gray-700"><?php echo esc_html( $value ); ?></span>
        </li>
			<?php endif; ?>
        <?php endforeach; ?>
      </ul>
    </div>
    <form action="<?php echo esc_url( $action ); ?>" method="post" class="m-0 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8 lg:col-span-3">
      <div class="grid gap-6 sm:grid-cols-2">
        <div>
          <label for="ai-cf-name" class="block text-sm font-semibold text-gray-900">Name</label>
          <input id="ai-cf-name" type="text" name="name" required autocomplete="name" class="<?php echo esc_attr( $input ); ?>" />
        </div>
        <div>
          <label for="ai-cf-email" class="block text-sm font-semibold text-gray-900">Email</label>
          <input id="ai-cf-email" type="email" name="email" required autocomplete="email" class="<?php echo esc_attr( $input ); ?>" />
        </div>
        <div class="sm:col-span-2">
          <label for="ai-cf-message" class="block text-sm font-semibold text-gray-900">Message</label>
          <textarea id="ai-cf-message" name="message" rows="5" required class="<?php echo esc_attr( $input ); ?>"></textarea>
        </div>
      </div>
      <div class="mt-6 flex justify-end">
        <button type="submit" class="inline-flex cursor-pointer items-center justify-center rounded-lg border-0 bg-brand-600 px-6 py-3 text-base font-semibold text-white shadow-sm transition-colors hover:bg-brand-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600"><?php echo esc_html( $button_label ); ?></button>
      </div>
    </form>
  </div>
</section>
