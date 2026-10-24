<?php
/**
 * A page that renders ONE template, so it can be looked at before it goes anywhere near a real page.
 *
 *   /?lc_tpl_preview=testimonials&lc_tpl_variant=centered
 *
 * It is the site's own WordPress, so the preview shows the template in the site's real fonts, colours and width -
 * not on a neutral background somewhere else. Only logged-in editors may open it, nothing is saved, and the component
 * is rendered with sample content that is clearly sample.
 *
 * The Livecrafts app shows a grid of these in frames (the component gallery).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Believable sample content per template, so a preview shows a finished section instead of empty boxes.
 *
 * $variant matters for templates whose layouts are built around DIFFERENT things: a hero "signup" is a picture, an
 * email field and a figure, while "proof" is three reasons and no form. Giving them all one set of content made
 * every layout preview look the same - which is the one thing a gallery must not do.
 */
function livecrafts_template_sample( $id, $variant = '' ) {
	$lorem   = 'Clear, useful copy that explains the offer in one short sentence.';
	$person  = array( 'Anita Shah|Founder, Northwind', 'Raj Patel|Operations lead', 'Meera Kapoor|Head of design' );
	$quote   = array(
		'They understood what we needed before we could explain it, and delivered ahead of the date.',
		'Clean work, straight answers, and the site has not needed a single fix since launch.',
		'Our enquiries doubled in the first two months. Worth every rupee.',
	);
	$links   = array(
		array( 'group' => 'Company', 'label' => 'About us', 'url' => '#' ),
		array( 'group' => 'Company', 'label' => 'Careers', 'url' => '#' ),
		array( 'group' => 'Services', 'label' => 'Design', 'url' => '#' ),
		array( 'group' => 'Services', 'label' => 'Development', 'url' => '#' ),
		array( 'group' => 'Support', 'label' => 'Contact', 'url' => '#' ),
		array( 'group' => 'Support', 'label' => 'Help centre', 'url' => '#' ),
	);
	$rows    = function ( $pairs, $a, $b ) {
		$out = array();
		foreach ( $pairs as $p ) {
			$parts = explode( '|', $p );
			$out[] = array( $a => $parts[0], $b => isset( $parts[1] ) ? $parts[1] : '' );
		}
		return $out;
	};

	$common = array(
		'heading'         => 'Everything you need, nothing you do not',
		'text'            => 'Three things every site we ship is measured against before it goes live.',
		'eyebrow'         => 'Sample content',
		'primary_label'   => 'Start a project',
		'primary_url'     => '#',
		'secondary_label' => 'See our work',
		'secondary_url'   => '#',
	);

	$by_id = array(
		'hero'           => array( 'heading' => 'Built for the way your team actually works', 'proof' => 'Trusted by 150 manufacturers - no card needed', 'items' => array( array( 'title' => '150+', 'text' => 'Projects delivered' ), array( 'title' => '8 yrs', 'text' => 'Average client' ), array( 'title' => '98%', 'text' => 'Would recommend' ) ) ),
		'hero_centered'  => array( 'heading' => 'Small team. Big ideas.', 'note' => 'No commitment. Cancel whenever you like.', 'items' => array( array( 'title' => '150+', 'text' => 'Projects' ), array( 'title' => '12', 'text' => 'Countries' ), array( 'title' => '8 yrs', 'text' => 'Experience' ) ) ),
		'testimonials'   => array( 'heading' => 'What clients say', 'items' => array(
			array( 'quote' => $quote[0], 'name' => 'Anita Shah', 'role' => 'Founder, Northwind' ),
			array( 'quote' => $quote[1], 'name' => 'Raj Patel', 'role' => 'CEO, BluePeak' ),
			array( 'quote' => $quote[2], 'name' => 'Meera Kapoor', 'role' => 'Director, Acme' ),
		) ),
		'feature_grid'   => array( 'heading' => 'Why teams choose us', 'items' => array(
			array( 'icon' => 'bolt', 'title' => 'Fast delivery', 'text' => 'Most sites go live within six weeks of kickoff.' ),
			array( 'icon' => 'shield', 'title' => 'Built to last', 'text' => 'Tested on real traffic before it reaches your customers.' ),
			array( 'icon' => 'users', 'title' => 'One team', 'text' => 'The people who design it are the people who build it.' ),
		) ),
		'stats'          => array( 'heading' => 'The numbers', 'items' => array(
			array( 'value' => '150+', 'label' => 'Projects delivered' ),
			array( 'value' => '98%', 'label' => 'Clients who return' ),
			array( 'value' => '8 yrs', 'label' => 'Average partnership' ),
			array( 'value' => '24h', 'label' => 'Reply time' ),
		) ),
		'steps'          => array( 'heading' => 'How it works', 'items' => array(
			array( 'title' => 'Discover', 'text' => 'We learn the business before we draw anything.' ),
			array( 'title' => 'Design', 'text' => 'You see the pages before a line of code is written.' ),
			array( 'title' => 'Build', 'text' => 'Developed, tested and checked on every screen size.' ),
			array( 'title' => 'Launch', 'text' => 'We stay with you for thirty days after go-live.' ),
		) ),
		'team'           => array( 'heading' => 'The people behind it', 'items' => array_map(
			function ( $p ) { return array( 'name' => $p['name'], 'role' => $p['role'], 'bio' => 'Twelve years of building things people use every day.' ); },
			$rows( $person, 'name', 'role' )
		) ),
		'faq'            => array( 'heading' => 'Questions people ask', 'items' => array(
			array( 'title' => 'How long does a project take?', 'text' => 'Most take eight to twelve weeks from kickoff to launch.' ),
			array( 'title' => 'Do you offer support afterwards?', 'text' => 'Yes - thirty days are included, and a monthly plan after that.' ),
			array( 'title' => 'What do you need from us?', 'text' => 'Your content, your logo, and one person who can approve decisions.' ),
		) ),
		'pricing'        => array( 'heading' => 'Simple pricing', 'note' => 'Prices exclude hosting and domain.', 'items' => array(
			array( 'name' => 'Starter', 'price' => '₹45,000', 'period' => 'one-off', 'description' => 'A small site that does one job well.', 'features' => 'Up to 5 pages|Mobile-first build|Basic SEO', 'label' => 'Choose Starter', 'url' => '#' ),
			array( 'name' => 'Business', 'price' => '₹1,20,000', 'period' => 'one-off', 'description' => 'For a company that sells online.', 'features' => 'Up to 15 pages|Contact and lead forms|Analytics setup|30 days support', 'label' => 'Choose Business', 'url' => '#', 'featured' => '1' ),
			array( 'name' => 'Premium', 'price' => 'From ₹2,50,000', 'period' => '', 'description' => 'Custom work, start to finish.', 'features' => 'Unlimited pages|Custom integrations|Dedicated manager', 'label' => 'Talk to us', 'url' => '#' ),
		) ),
		'blog_grid'      => array( 'heading' => 'From the blog', 'items' => array(
			array( 'title' => 'Why layout matters more than colour', 'category' => 'Design', 'excerpt' => 'A short excerpt that gives the reader a reason to click.', 'date' => '12 Oct 2026', 'read_time' => '5 min read', 'url' => '#' ),
			array( 'title' => 'Ranking without tricks in 2026', 'category' => 'SEO', 'excerpt' => 'What actually moves a site up, and what wastes your budget.', 'date' => '28 Sep 2026', 'read_time' => '7 min read', 'url' => '#' ),
			array( 'title' => 'Speeding up a slow WordPress site', 'category' => 'Technical', 'excerpt' => 'The four changes that account for most of the gain.', 'date' => '14 Sep 2026', 'read_time' => '6 min read', 'url' => '#' ),
		) ),
		'slider'         => array( 'heading' => 'Our work', 'items' => array(
			array( 'title' => 'Launch faster', 'text' => 'Ship a site in weeks, not quarters.', 'link_label' => 'See the case study', 'url' => '#', 'name' => 'Anita Shah', 'role' => 'Founder' ),
			array( 'title' => 'Built to convert', 'text' => 'Layouts tested against real traffic.', 'link_label' => 'See the numbers', 'url' => '#', 'name' => 'Raj Patel', 'role' => 'CEO' ),
			array( 'title' => 'Grow together', 'text' => 'Support that does not end at launch.', 'link_label' => 'How we help', 'url' => '#', 'name' => 'Meera Kapoor', 'role' => 'Director' ),
		) ),
		'contact_form'   => array( 'heading' => 'Tell us about your project', 'text' => 'We reply within one working day.', 'email' => 'hello@example.com', 'phone' => '+91 00000 00000', 'address' => 'Ahmedabad, Gujarat', 'hours' => 'Mon-Sat, 9am-6pm' ),
		'footer'         => array( 'logo_text' => get_bloginfo( 'name' ) ? get_bloginfo( 'name' ) : 'Northwind', 'text' => 'Crafting digital experiences for manufacturers since 2018.', 'copyright' => gmdate( 'Y' ) . ' ' . ( get_bloginfo( 'name' ) ? get_bloginfo( 'name' ) : 'Northwind' ) . '. All rights reserved.', 'legal' => 'Privacy|#, Terms|#', 'cta_heading' => 'Ready to start?', 'cta_text' => 'Tell us what you need and we will come back with a plan.', 'items' => $links ),
		'header'         => array( 'logo_text' => get_bloginfo( 'name' ) ? get_bloginfo( 'name' ) : 'Northwind', 'cta_label' => 'Get a quote', 'cta_url' => '#', 'items' => array(
			array( 'label' => 'Home', 'url' => '#', 'current' => '1' ), array( 'label' => 'Services', 'url' => '#' ), array( 'label' => 'Work', 'url' => '#' ), array( 'label' => 'Contact', 'url' => '#' ),
		) ),
		'cta'            => array( 'heading' => 'Ready to start your project?', 'text' => 'Tell us what you need and we will come back with a plan and a price.', 'note' => 'No obligation. We reply within a day.' ),
		'newsletter'     => array( 'heading' => 'Get one useful email a month', 'text' => 'What we learned building sites, in five minutes.', 'note' => 'No spam. Unsubscribe in one click.' ),
		'logo_cloud'     => array( 'heading' => 'Trusted by teams at', 'items' => array( array( 'name' => 'Northwind' ), array( 'name' => 'BluePeak' ), array( 'name' => 'Acme Foods' ), array( 'name' => 'Vertex' ), array( 'name' => 'Lumen' ) ) ),
		'card'           => array( 'title' => 'Why layout matters more than colour', 'category' => 'Design', 'excerpt' => 'A short excerpt that gives the reader a reason to click through.', 'date' => '12 Oct 2026', 'read_time' => '5 min read', 'url' => '#' ),
		'button'         => array( 'label' => 'Start a project', 'url' => '#' ),
		'badge'          => array( 'label' => 'New' ),
		'alert'          => array( 'type' => 'success', 'title' => 'Saved', 'text' => 'Your changes are live on the site.' ),
		'announcement'   => array( 'text' => 'We are open for projects starting in January.', 'link_label' => 'Get in touch', 'url' => '#' ),
		'breadcrumbs'    => array( 'items' => array( array( 'label' => 'Home', 'url' => '#' ), array( 'label' => 'Services', 'url' => '#' ), array( 'label' => 'Web design' ) ) ),
		'pagination'     => array( 'current' => 2, 'total' => 8 ),
		'empty_state'    => array( 'icon' => 'search', 'title' => 'Nothing matched that search', 'text' => 'Try a different word, or browse everything.', 'label' => 'Browse all', 'url' => '#' ),
		'sidebar'        => array( 'heading' => 'Categories', 'box_title' => 'Need help choosing?', 'box_text' => 'Tell us what you are building and we will point you the right way.', 'box_label' => 'Contact us', 'box_url' => '#', 'items' => array(
			array( 'label' => 'Design', 'url' => '#', 'current' => '1' ), array( 'label' => 'Development', 'url' => '#' ), array( 'label' => 'SEO', 'url' => '#' ),
		) ),
	);

	$sample = isset( $by_id[ $id ] ) ? $by_id[ $id ] : array();

	// What each hero layout is actually built around. Without this the picture-led ones render as bare text panels.
	if ( 'hero' === $id ) {
		$shot = AI_COMPONENTS_URL . 'assets/img/sample.svg';
		$per  = array(
			'split'     => array( 'image' => $shot ),
			'spotlight' => array( 'image' => $shot ),
			'showcase'  => array( 'image' => $shot, 'secondary_label' => 'Watch the tour' ),
			'panel'     => array( 'image' => $shot, 'items' => array(
				array( 'name' => 'Northwind' ), array( 'name' => 'BluePeak' ), array( 'name' => 'Acme Foods' ), array( 'name' => 'Vertex' ),
			) ),
			'proof'     => array( 'image' => $shot, 'items' => array(
				array( 'icon' => 'users', 'title' => 'Over 12,000 teams already use it' ),
				array( 'icon' => 'check-circle', 'title' => 'No setup fee and no yearly contract' ),
				array( 'icon' => 'lock', 'title' => 'Every change can be undone' ),
			) ),
			'signup'    => array(
				'image' => $shot, 'action' => '#', 'button_label' => 'Try 14 days free',
				'placeholder' => 'Enter your email to start', 'proof' => 'Instant access. No card needed.',
				'stat_value' => '395', 'stat_label' => 'Teams started this month',
			),
			'reveal'    => array( 'image' => $shot, 'video_url' => '#', 'video_label' => 'Watch the tour' ),
		);
		$sample = array_merge( $sample, isset( $per[ $variant ] ) ? $per[ $variant ] : $per['split'] );
	}
	// Anything without its own sample still gets a heading, a lede and three rows, so no preview is ever blank.
	if ( ! isset( $sample['items'] ) && ! in_array( $id, array( 'button', 'badge', 'alert', 'announcement', 'card', 'empty_state', 'pagination', 'cta', 'newsletter', 'contact_form', 'hero', 'hero_centered' ), true ) ) {
		$sample['items'] = array(
			array( 'title' => 'Fast delivery', 'text' => $lorem ),
			array( 'title' => 'Built to last', 'text' => $lorem ),
			array( 'title' => 'One team', 'text' => $lorem ),
		);
	}
	return array_merge( $common, $sample );
}

/** The template ids the gallery can show, with the layouts each one has (read from its own docblock). */
function livecrafts_template_variants( $params ) {
	if ( preg_match( '/variant\s*\(([a-z0-9|_-]+)\)/i', (string) $params, $m ) ) {
		return array_values( array_filter( array_map( 'trim', explode( '|', $m[1] ) ) ) );
	}
	return array();
}

/**
 * GET /livecrafts/v1/templates/render?id=<id>&variant=<layout>
 *
 * The component's HTML on its own, with the stylesheets that make it look right - no page, no header, no theme
 * chrome. The Livecrafts app shows each one in its own isolated frame, the way a component library's documentation
 * does. This is server-to-server (an Application Password), so nobody has to be logged into the site in a browser.
 */
add_action( 'rest_api_init', function () {
	register_rest_route(
		'livecrafts/v1',
		'/templates/render',
		array(
			'methods'             => 'GET',
			'permission_callback' => 'livecrafts_rest_permission',
			'callback'            => function ( WP_REST_Request $req ) {
				if ( ! function_exists( 'ai_component' ) ) {
					return new WP_Error( 'livecrafts_no_library', 'The template library is not available on this site.', array( 'status' => 404 ) );
				}
				$id      = sanitize_key( (string) $req->get_param( 'id' ) );
				$variant = sanitize_key( (string) $req->get_param( 'variant' ) );
				if ( '' === $id ) {
					return new WP_Error( 'livecrafts_no_id', 'Pass the template id, e.g. id=testimonials.', array( 'status' => 400 ) );
				}

				$args = livecrafts_template_sample( $id, $variant );
				if ( $variant ) {
					$args['variant'] = $variant;
				}
				$html = ai_component( str_replace( '_', '-', $id ), $args, false );
				if ( '' === $html ) {
					return new WP_Error( 'livecrafts_unknown_template', 'There is no template called "' . $id . '".', array( 'status' => 404 ) );
				}

				// The library's own stylesheet, and the theme's, so the preview carries the site's fonts. The
				// components are built to survive a theme's CSS, so loading it changes type and colour, not layout.
				$css = array( AI_COMPONENTS_URL . 'assets/css/ai-components.css?ver=' . AI_COMPONENTS_VERSION );
				$theme = get_stylesheet_uri();
				if ( $theme ) {
					$css[] = $theme;
				}
				return array(
					'ok'      => true,
					'id'      => $id,
					'variant' => $variant,
					'html'    => $html,
					'css'     => $css,
					'custom'  => (string) wp_get_custom_css(),
					'sample'  => true,
				);
			},
		)
	);
} );

/**
 * /?lc_tpl_preview=<id>&lc_tpl_variant=<layout> - the same thing as a page an editor can open in a tab.
 * The theme's own head is loaded, so it shows the site's real fonts and colours.
 */
add_action( 'template_redirect', function () {
	$id = isset( $_GET['lc_tpl_preview'] ) ? sanitize_key( wp_unslash( $_GET['lc_tpl_preview'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only preview, capability checked below
	if ( '' === $id ) {
		return;
	}
	if ( ! livecrafts_user_can_edit() ) {
		wp_die( esc_html__( 'You need to be logged in as an editor of this site to preview templates.', 'livecrafts' ), '', array( 'response' => 403 ) );
	}
	if ( ! function_exists( 'ai_component' ) ) {
		wp_die( esc_html__( 'The template library is not available on this site.', 'livecrafts' ), '', array( 'response' => 404 ) );
	}

	$variant = isset( $_GET['lc_tpl_variant'] ) ? sanitize_key( wp_unslash( $_GET['lc_tpl_variant'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$args    = livecrafts_template_sample( $id, $variant );
	if ( $variant ) {
		$args['variant'] = $variant;
	}

	show_admin_bar( false );
	add_filter( 'show_admin_bar', '__return_false' );

	$html = ai_component( str_replace( '_', '-', $id ), $args, false );
	if ( '' === $html ) {
		wp_die( esc_html__( 'There is no template with that name.', 'livecrafts' ), '', array( 'response' => 404 ) );
	}

	// A bare document: the theme's head (fonts, variables, its own stylesheet) and the component. No page, no loop.
	?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( $id . ( $variant ? ' - ' . $variant : '' ) ); ?></title>
	<?php wp_head(); ?>
	<style>
		html, body { margin: 0 !important; padding: 0 !important; background: #fff; }
		body > *:not(.ai-t):not(script):not(style):not(noscript) { display: none !important; }
		#wpadminbar { display: none !important; }
	</style>
</head>
<body class="livecrafts-template-preview">
	<?php
	echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the component escapes its own output.
	wp_footer();
	?>
</body>
</html>
	<?php
	exit;
} );
