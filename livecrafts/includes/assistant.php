<?php
/**
 * The AI assistant widget:
 *  - Settings → Livecrafts Assistant: bot name, welcome text, extra instructions (added to the system prompt),
 *    model, approval mode, colours, and where the Livecrafts backend runs.
 *  - GET /livecrafts/v1/assistant: the backend reads these settings (so name + instructions are injected from WordPress).
 *  - A floating chat button on the site for logged-in editors. The chat itself is the backend's widget page in an iframe.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const LIVECRAFTS_ASSISTANT_OPT = 'livecrafts_assistant';

function livecrafts_assistant_defaults() {
	return array(
		'enabled'        => 1,
		'backend_url'    => 'http://127.0.0.1:8790',
		'bot_name'       => 'Livecrafts',
		'welcome'        => 'Hi! Tell me what you would like to change on this page - text, images, colours, new sections or pages.',
		'instructions'   => '',
		'model'          => '',
		'approval_mode'  => 'request',
		'accent'         => '#5b5bd6',
		'position'       => 'right',
	);
}

function livecrafts_assistant() {
	$saved = get_option( LIVECRAFTS_ASSISTANT_OPT, array() );
	return array_merge( livecrafts_assistant_defaults(), is_array( $saved ) ? $saved : array() );
}

function livecrafts_assistant_sanitize( $in ) {
	$d   = livecrafts_assistant_defaults();
	$in  = is_array( $in ) ? $in : array();
	$out = array();
	$out['enabled']        = empty( $in['enabled'] ) ? 0 : 1;
	$url                   = isset( $in['backend_url'] ) ? esc_url_raw( trim( $in['backend_url'] ), array( 'http', 'https' ) ) : '';
	$out['backend_url']    = $url ? untrailingslashit( $url ) : $d['backend_url'];
	$out['bot_name']       = isset( $in['bot_name'] ) && trim( $in['bot_name'] ) !== '' ? mb_substr( sanitize_text_field( $in['bot_name'] ), 0, 60 ) : $d['bot_name'];
	$out['welcome']        = isset( $in['welcome'] ) ? mb_substr( sanitize_textarea_field( $in['welcome'] ), 0, 500 ) : '';
	$out['instructions']   = isset( $in['instructions'] ) ? mb_substr( sanitize_textarea_field( $in['instructions'] ), 0, 4000 ) : '';
	$model                 = isset( $in['model'] ) ? trim( sanitize_text_field( $in['model'] ) ) : '';
	$out['model']          = preg_match( '/^[A-Za-z0-9._:\/@-]{0,120}$/', $model ) ? $model : '';
	$out['approval_mode']  = isset( $in['approval_mode'] ) && in_array( $in['approval_mode'], array( 'every', 'request', 'auto' ), true ) ? $in['approval_mode'] : 'request';
	$out['accent']         = isset( $in['accent'] ) && sanitize_hex_color( $in['accent'] ) ? sanitize_hex_color( $in['accent'] ) : $d['accent'];
	$out['position']       = isset( $in['position'] ) && $in['position'] === 'left' ? 'left' : 'right';
	return $out;
}

add_action( 'admin_init', function () {
	register_setting( 'livecrafts_assistant', LIVECRAFTS_ASSISTANT_OPT, array( 'sanitize_callback' => 'livecrafts_assistant_sanitize' ) );
} );

add_action( 'admin_menu', function () {
	add_options_page( 'Livecrafts Assistant', 'Livecrafts Assistant', 'manage_options', 'livecrafts-assistant', 'livecrafts_assistant_page' );
} );

function livecrafts_assistant_page() {
	if ( ! current_user_can( 'manage_options' ) ) return;
	$a = livecrafts_assistant();
	$n = LIVECRAFTS_ASSISTANT_OPT;
	?>
	<div class="wrap">
		<h1>Livecrafts Assistant</h1>
		<p>An AI chat on your site for logged-in editors. Its changes are drafts that only editors see until someone deploys them, and every change can be reverted.</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'livecrafts_assistant' ); ?>
			<h2 class="title">Assistant</h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row">Show the chat widget</th><td><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[enabled]" value="1" <?php checked( $a['enabled'], 1 ); ?>> Show the chat button on the site (only for logged-in users with the livecrafts_edit capability)</label></td></tr>
				<tr><th scope="row"><label for="lc-bot-name">Bot name</label></th><td><input id="lc-bot-name" class="regular-text" name="<?php echo esc_attr( $n ); ?>[bot_name]" value="<?php echo esc_attr( $a['bot_name'] ); ?>"></td></tr>
				<tr><th scope="row"><label for="lc-welcome">Welcome message</label></th><td><textarea id="lc-welcome" class="large-text" rows="2" name="<?php echo esc_attr( $n ); ?>[welcome]"><?php echo esc_textarea( $a['welcome'] ); ?></textarea></td></tr>
				<tr><th scope="row"><label for="lc-instructions">Instructions (system prompt)</label></th><td><textarea id="lc-instructions" class="large-text code" rows="7" name="<?php echo esc_attr( $n ); ?>[instructions]" placeholder="e.g. Our brand colours are #0B3D91 and #FFB400. Write in British English. Never change the legal pages."><?php echo esc_textarea( $a['instructions'] ); ?></textarea>
					<p class="description">Added to the assistant's instructions for this site: brand rules, tone of voice, pages it must not touch. Safety rules always stay on.</p></td></tr>
				<tr><th scope="row"><label for="lc-model">Model</label></th><td><input id="lc-model" class="regular-text code" name="<?php echo esc_attr( $n ); ?>[model]" value="<?php echo esc_attr( $a['model'] ); ?>" placeholder="leave empty for the backend default">
					<p class="description">Format <code>provider:model</code> - e.g. <code>openai:gpt-5.5</code>, <code>anthropic:claude-sonnet-5-5</code>, <code>openrouter:google/gemini-3-pro</code>, <code>groq:openai/gpt-oss-120b</code>, <code>custom:my-model</code>. API keys are added in the Livecrafts app → Integrations → AI models.</p></td></tr>
				<tr><th scope="row">Approval</th><td><fieldset>
					<label><input type="radio" name="<?php echo esc_attr( $n ); ?>[approval_mode]" value="request" <?php checked( $a['approval_mode'], 'request' ); ?>> <strong>Once per request</strong> - the assistant shows its plan, one click runs all steps (recommended)</label><br>
					<label><input type="radio" name="<?php echo esc_attr( $n ); ?>[approval_mode]" value="every" <?php checked( $a['approval_mode'], 'every' ); ?>> <strong>Every change</strong> - approve each single change</label><br>
					<label><input type="radio" name="<?php echo esc_attr( $n ); ?>[approval_mode]" value="auto" <?php checked( $a['approval_mode'], 'auto' ); ?>> <strong>Auto</strong> - no questions; every change is still checked and can be reverted</label>
				</fieldset></td></tr>
				<tr><th scope="row"><label for="lc-accent">Colour</label></th><td><input id="lc-accent" type="color" name="<?php echo esc_attr( $n ); ?>[accent]" value="<?php echo esc_attr( $a['accent'] ); ?>">
					&nbsp; <label>Button position <select name="<?php echo esc_attr( $n ); ?>[position]"><option value="right" <?php selected( $a['position'], 'right' ); ?>>Bottom right</option><option value="left" <?php selected( $a['position'], 'left' ); ?>>Bottom left</option></select></label></td></tr>
			</table>
			<h2 class="title">Connection</h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="lc-backend">Livecrafts backend address</label></th><td><input id="lc-backend" class="regular-text code" name="<?php echo esc_attr( $n ); ?>[backend_url]" value="<?php echo esc_attr( $a['backend_url'] ); ?>">
					<p class="description">Where the Livecrafts app runs. On your own computer: <code>http://127.0.0.1:8790</code>.</p></td></tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

// The backend reads the assistant settings (bot name, instructions, model ...) with the Application Password.
add_action( 'rest_api_init', function () {
	register_rest_route( 'livecrafts/v1', '/assistant', array(
		'methods' => 'GET', 'callback' => 'livecrafts_rest_assistant', 'permission_callback' => 'livecrafts_rest_permission',
	) );
} );

function livecrafts_rest_assistant() {
	$a = livecrafts_assistant();
	return array(
		'ok' => true, 'botName' => $a['bot_name'], 'welcome' => $a['welcome'], 'instructions' => $a['instructions'],
		'model' => $a['model'], 'approvalMode' => $a['approval_mode'], 'accent' => $a['accent'],
	);
}

// The floating chat button + panel, for editors only, never inside page-builder previews.
add_action( 'wp_enqueue_scripts', function () {
	$a = livecrafts_assistant();
	if ( is_admin() || empty( $a['enabled'] ) || ! livecrafts_user_can_edit() ) return;
	wp_enqueue_media(); // the click panel picks images from the Media Library
	wp_enqueue_style( 'livecrafts-widget', LIVECRAFTS_URL . 'assets/widget.css', array(), LIVECRAFTS_VERSION );
	wp_enqueue_script( 'livecrafts-widget', LIVECRAFTS_URL . 'assets/widget.js', array(), LIVECRAFTS_VERSION, true );
	$scheme = is_ssl() ? 'https' : 'http';
	wp_localize_script( 'livecrafts-widget', 'LIVECRAFTS_WIDGET', array(
		'backend'      => $a['backend_url'],
		'botName'      => $a['bot_name'],
		'welcome'      => $a['welcome'],
		'accent'       => $a['accent'],
		'position'     => $a['position'],
		'approvalMode' => $a['approval_mode'],
		'siteUrl'      => untrailingslashit( home_url() ),
		'pageUrl'      => ( isset( $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'] ) ? esc_url_raw( $scheme . '://' . wp_unslash( $_SERVER['HTTP_HOST'] ) . wp_unslash( $_SERVER['REQUEST_URI'] ) ) : home_url( '/' ) ),
		'user'         => wp_get_current_user()->display_name,
		'pageKey'      => livecrafts_page_key(),
		'version'      => LIVECRAFTS_VERSION,
		'assets'       => LIVECRAFTS_URL . 'assets/',
	) );
}, 40 );
