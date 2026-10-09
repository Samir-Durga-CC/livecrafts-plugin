<?php
/**
 * Renders the REAL templates/*.php (with minimal WordPress function stubs) into a self-contained preview.html.
 * Usage: php tools/preview.php
 */
define( 'ABSPATH', __DIR__ );
define( 'AI_COMPONENTS_DIR', dirname( __DIR__ ) . '/' );
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_url( $s ) { $s = (string) $s; return preg_match( '#^(https?:|/|\#|\?|mailto:|tel:)#i', $s ) || '' === $s ? esc_html( $s ) : ''; }
function wp_kses( $s, $a ) { return $s; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function locate_template( $p ) { return ''; }
function wp_enqueue_style( $h ) {}
function load_template( $path, $once, $args ) { include $path; }
require AI_COMPONENTS_DIR . 'includes/helpers.php';

function c( $name, $args ) { return ai_component( $name, $args, false ); }
function av( $n ) { return 'https://i.pravatar.cc/160?img=' . $n; }
function ph( $seed, $w = 1200, $h = 800 ) { return "https://picsum.photos/seed/$seed/$w/$h"; }

$lorem = 'A short, clear summary of what the reader will get from this article, written to fit neatly in two or three lines of the card.';

$blog = array(
	array( 'title' => 'How we cut page load time by 62% without changing hosts', 'url' => '#', 'image' => ph( 'speed' ), 'excerpt' => $lorem, 'category' => 'Performance', 'date' => 'Oct 2, 2026' ),
	array( 'title' => 'A practical guide to design tokens for WordPress teams', 'url' => '#', 'image' => ph( 'tokens' ), 'excerpt' => $lorem, 'category' => 'Design systems', 'date' => 'Sep 24, 2026' ),
	array( 'title' => 'Shipping accessible navigation that works with a keyboard', 'url' => '#', 'image' => ph( 'access' ), 'excerpt' => $lorem, 'category' => 'Accessibility', 'date' => 'Sep 11, 2026' ),
);

// id, group, title, shortcode example, rendered html
$groups = array(
	'Navigation' => array(
		array( 'announcement', 'Announcement bar', '[ai_announcement text="Version 2.0 is live." link_label="See what\'s new" url="/changelog"]', c( 'announcement', array( 'text' => 'Version 2.0 is live with faster builds and a new design system.', 'link_label' => "See what's new", 'url' => '#' ) ) ),
		array( 'header', 'Header', '[ai_header logo_text="Meridian" cta_label="Get started" cta_url="/signup"][ai_item label="Product" url="/product" current="1"]...[/ai_header]', c( 'header', array( 'logo_text' => 'Meridian', 'home_url' => '#', 'cta_label' => 'Get started', 'cta_url' => '#', 'items' => array( array( 'label' => 'Product', 'url' => '#', 'current' => '1' ), array( 'label' => 'Solutions', 'url' => '#' ), array( 'label' => 'Pricing', 'url' => '#' ), array( 'label' => 'Blog', 'url' => '#' ), array( 'label' => 'Contact', 'url' => '#' ) ) ) ) ),
		array( 'breadcrumbs', 'Breadcrumbs', '[ai_breadcrumbs][ai_item label="Home" url="/"][ai_item label="Blog" url="/blog"][ai_item label="Article"][/ai_breadcrumbs]', '<div style="padding:20px 24px">' . c( 'breadcrumbs', array( 'items' => array( array( 'label' => 'Home', 'url' => '#' ), array( 'label' => 'Blog', 'url' => '#' ), array( 'label' => 'Design systems', 'url' => '#' ), array( 'label' => 'Design tokens for WordPress teams' ) ) ) ) . '</div>' ),
		array( 'sidebar', 'Sidebar', '[ai_sidebar heading="Categories" box_title="Need help?" box_label="Contact us"][ai_item label="Design" url="/design"]...[/ai_sidebar]', '<div style="padding:24px;background:#f9fafb">' . c( 'sidebar', array( 'heading' => 'Categories', 'search_action' => '#', 'items' => array( array( 'label' => 'Performance', 'url' => '#', 'current' => '1' ), array( 'label' => 'Design systems', 'url' => '#' ), array( 'label' => 'Accessibility', 'url' => '#' ), array( 'label' => 'Case studies', 'url' => '#' ) ), 'box_title' => 'Need a hand?', 'box_text' => 'Our team replies within one working day.', 'box_label' => 'Contact us', 'box_url' => '#' ) ) . '</div>' ),
		array( 'pagination', 'Pagination', '[ai_pagination current="4" total="12" url_pattern="/blog/page/%d/"]', '<div style="padding:24px">' . c( 'pagination', array( 'current' => 4, 'total' => 12, 'url_pattern' => '#page-%d' ) ) . '</div>' ),
		array( 'footer', 'Footer', '[ai_footer logo_text="Meridian" text="..." copyright="2026 Meridian"][ai_item group="Product" label="Pricing" url="/pricing"]...[/ai_footer]', c( 'footer', array( 'logo_text' => 'Meridian', 'text' => 'Tools that help small teams ship fast, accessible websites.', 'copyright' => '2026 Meridian Inc. All rights reserved.', 'items' => array( array( 'group' => 'Product', 'label' => 'Features', 'url' => '#' ), array( 'group' => 'Product', 'label' => 'Pricing', 'url' => '#' ), array( 'group' => 'Product', 'label' => 'Changelog', 'url' => '#' ), array( 'group' => 'Company', 'label' => 'About', 'url' => '#' ), array( 'group' => 'Company', 'label' => 'Careers', 'url' => '#' ), array( 'group' => 'Company', 'label' => 'Contact', 'url' => '#' ), array( 'group' => 'Legal', 'label' => 'Privacy', 'url' => '#' ), array( 'group' => 'Legal', 'label' => 'Terms', 'url' => '#' ) ) ) ) ),
	),
	'Hero and calls to action' => array(
		array( 'hero', 'Hero (split)', '[ai_hero eyebrow="New" heading="..." text="..." primary_label="Start free" primary_url="/signup" secondary_label="Book a demo" secondary_url="/demo" image="https://..."]', c( 'hero', array( 'eyebrow' => 'Now with design tokens', 'heading' => 'Launch polished pages in minutes, not weeks', 'text' => 'Build on a tested component library that adapts to your brand, works in any WordPress builder and stays fast.', 'primary_label' => 'Start free', 'primary_url' => '#', 'secondary_label' => 'Book a demo', 'secondary_url' => '#', 'image' => ph( 'hero', 1400, 1050 ) ) ) ),
		array( 'hero-centered', 'Hero (centered)', '[ai_hero_centered eyebrow="" heading="" text="" primary_label="" primary_url="" note=""]', c( 'hero-centered', array( 'eyebrow' => 'Trusted by 4,000+ teams', 'heading' => 'The calm way to build websites that convert', 'text' => 'One library of accessible, responsive sections you can drop into Elementor, Divi or your own theme.', 'primary_label' => 'Get started', 'primary_url' => '#', 'secondary_label' => 'See pricing', 'secondary_url' => '#', 'note' => 'No credit card required. Cancel any time.' ) ) ),
		array( 'cta', 'Call to action', '[ai_cta heading="" text="" primary_label="" primary_url="" style="brand"]', c( 'cta', array( 'heading' => 'Ready to launch your next page?', 'text' => 'Pick a component, add your content and publish. No custom layout code needed.', 'primary_label' => 'Get started', 'primary_url' => '#', 'secondary_label' => 'Talk to sales', 'secondary_url' => '#' ) ) . c( 'cta', array( 'style' => 'light', 'heading' => 'Prefer a lighter band?', 'text' => 'The light style works well between two white sections.', 'primary_label' => 'Try it free', 'primary_url' => '#', 'secondary_label' => 'Learn more', 'secondary_url' => '#' ) ) ),
	),
	'Content sections' => array(
		array( 'feature-grid', 'Feature grid', '[ai_feature_grid heading=""][ai_item icon="bolt" title="" text=""]...[/ai_feature_grid]', c( 'feature-grid', array( 'heading' => 'Everything you need to ship faster', 'text' => 'Sensible defaults, strong accessibility and a single brand color that restyles every section.', 'items' => array( array( 'icon' => 'bolt', 'title' => 'Fast by default', 'text' => 'Only the CSS you use is shipped, with no JavaScript framework to load.' ), array( 'icon' => 'shield', 'title' => 'Accessible', 'text' => 'Keyboard friendly, proper landmarks and visible focus states throughout.' ), array( 'icon' => 'grid', 'title' => 'Works everywhere', 'text' => 'Use the same component in Elementor, Divi, WPBakery or a custom theme.' ), array( 'icon' => 'chart', 'title' => 'Built for clarity', 'text' => 'Clean typography and spacing that keep the focus on your content.' ), array( 'icon' => 'lock', 'title' => 'Escaped output', 'text' => 'Every value is escaped on output, following WordPress coding standards.' ), array( 'icon' => 'code', 'title' => 'Developer friendly', 'text' => 'Plain PHP template parts that any WordPress developer can read.' ) ) ) ) ),
		array( 'steps', 'Steps', '[ai_steps heading="How it works"][ai_item title="" text=""]...[/ai_steps]', c( 'steps', array( 'heading' => 'How it works', 'text' => 'From design to published page in four steps.', 'items' => array( array( 'title' => 'Pick a component', 'text' => 'Choose from the library or let the agent suggest one.' ), array( 'title' => 'Add your content', 'text' => 'Fill in text, images and links. Nothing else to configure.' ), array( 'title' => 'Match your brand', 'text' => 'Set one brand color and every section follows.' ), array( 'title' => 'Publish', 'text' => 'Checks run first, then the page goes live.' ) ) ) ) ),
		array( 'stats', 'Stats', '[ai_stats heading=""][ai_item value="99.9%" label="Uptime"]...[/ai_stats]', c( 'stats', array( 'heading' => 'Results that speak for themselves', 'text' => 'Averages across sites built with the library.', 'items' => array( array( 'value' => '62%', 'label' => 'Faster page loads' ), array( 'value' => '98', 'label' => 'Mobile PageSpeed score' ), array( 'value' => '4,000+', 'label' => 'Sites launched' ), array( 'value' => '24h', 'label' => 'Average time to publish' ) ) ) ) ),
		array( 'logo-cloud', 'Logo cloud', '[ai_logo_cloud heading="Trusted by"][ai_item name="Acme"]...[/ai_logo_cloud]', c( 'logo-cloud', array( 'heading' => 'Trusted by teams at', 'items' => array( array( 'name' => 'Northwind' ), array( 'name' => 'Globex' ), array( 'name' => 'Initech' ), array( 'name' => 'Umbrella' ), array( 'name' => 'Hooli' ) ) ) ) ),
		array( 'testimonials', 'Testimonials', '[ai_testimonials heading=""][ai_item name="" role="" image=""]Quote text[/ai_item][/ai_testimonials]', c( 'testimonials', array( 'heading' => 'Loved by the people who build with it', 'items' => array( array( 'quote' => 'We replaced three plugins with this library and our pages got faster and more consistent overnight.', 'name' => 'Priya Nair', 'role' => 'Head of Web, Northwind', 'image' => av( 47 ) ), array( 'quote' => 'The components look right in every builder we use. Our designers finally trust the output.', 'name' => 'Daniel Okafor', 'role' => 'Creative Director, Globex', 'image' => av( 12 ) ), array( 'quote' => 'One brand color and the whole site updated. It saved us a full week on the last project.', 'name' => 'Sofia Martins', 'role' => 'Founder, Initech' ) ) ) ) ),
		array( 'team', 'Team', '[ai_team heading=""][ai_item name="" role="" image="" bio=""]...[/ai_team]', c( 'team', array( 'heading' => 'Meet the team', 'text' => 'A small group focused on making the web simpler.', 'items' => array( array( 'name' => 'Anika Rao', 'role' => 'Founder and CEO', 'image' => av( 5 ), 'bio' => 'Ten years building products for small teams.' ), array( 'name' => 'Marcus Lee', 'role' => 'Head of Design', 'image' => av( 15 ), 'bio' => 'Design systems and accessibility specialist.' ), array( 'name' => 'Elena Petrova', 'role' => 'Lead Engineer', 'image' => av( 32 ), 'bio' => 'Performance obsessed WordPress developer.' ), array( 'name' => 'Tomas Silva', 'role' => 'Customer Success' ) ) ) ) ),
		array( 'pricing', 'Pricing', '[ai_pricing heading=""][ai_item name="Pro" price="$29" period="/month" features="A|B|C" label="Choose Pro" url="/buy" featured="1"]...[/ai_pricing]', c( 'pricing', array( 'heading' => 'Simple, transparent pricing', 'text' => 'Start free and upgrade when you are ready.', 'items' => array( array( 'name' => 'Starter', 'price' => '$0', 'period' => '/month', 'description' => 'For personal projects and trying things out.', 'features' => 'Up to 3 sites|Core components|Community support', 'label' => 'Start free', 'url' => '#' ), array( 'name' => 'Pro', 'price' => '$29', 'period' => '/month', 'description' => 'For freelancers and growing teams.', 'features' => 'Unlimited sites|All 25 components|Brand color control|Priority support', 'label' => 'Choose Pro', 'url' => '#', 'featured' => '1' ), array( 'name' => 'Agency', 'price' => '$99', 'period' => '/month', 'description' => 'For agencies managing many clients.', 'features' => 'Everything in Pro|White label|Staging workflow|Dedicated manager', 'label' => 'Contact sales', 'url' => '#' ) ) ) ) ),
		array( 'faq', 'FAQ', '[ai_faq heading="Questions"][ai_item title="Question?"]Answer text[/ai_item][/ai_faq]', c( 'faq', array( 'heading' => 'Frequently asked questions', 'text' => 'Can\'t find what you need? Get in touch.', 'items' => array( array( 'title' => 'Does it work with Elementor and Divi?', 'text' => 'Yes. Add the shortcode in the Shortcode widget (Elementor) or a Code module (Divi). The component looks the same in both.' ), array( 'title' => 'Will it change my existing theme?', 'text' => 'No. Styles are prefixed and scoped, and the global reset is not included, so your theme keeps its own look.' ), array( 'title' => 'How do I change the colors?', 'text' => 'Set the single brand color once for the site and every component follows it.' ), array( 'title' => 'Is JavaScript required?', 'text' => 'No. The accordion and mobile menu use native HTML elements.' ) ) ) ) ),
	),
	'Blog' => array(
		array( 'card', 'Card', '[ai_card variant="article" title="" url="" image="" excerpt="" category="" date=""]', '<div style="display:grid;gap:24px;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));padding:24px;background:#f9fafb">' . c( 'card', array_merge( array( 'variant' => 'article' ), $blog[0] ) ) . c( 'card', array( 'variant' => 'author', 'title' => 'How I built my first website with Nuxt, Tailwind CSS and Vercel', 'url' => '#', 'image' => av( 11 ), 'author' => 'John Doe', 'excerpt' => $lorem, 'date' => '31 Jun 2025', 'read_time' => '12 minutes' ) ) . '</div>' ),
		array( 'blog-grid', 'Blog grid', '[ai_blog_grid heading="From the blog"][ai_item title="" url="" image="" excerpt="" category="" date=""]...[/ai_blog_grid]', c( 'blog-grid', array( 'heading' => 'From the blog', 'text' => 'Practical articles on performance, design and accessibility.', 'items' => $blog ) ) ),
	),
	'Forms' => array(
		array( 'newsletter', 'Newsletter', '[ai_newsletter heading="" text="" action="https://..." button_label="Subscribe"]', c( 'newsletter', array( 'heading' => 'Get the monthly newsletter', 'text' => 'Short, useful notes on building better websites. No spam.', 'action' => '#', 'button_label' => 'Subscribe', 'note' => 'We care about your data. Read our privacy policy.' ) ) ),
		array( 'contact-form', 'Contact form', '[ai_contact_form heading="" text="" action="https://..." email="" phone="" address=""]', c( 'contact-form', array( 'heading' => 'Let\'s talk', 'text' => 'Tell us about your project and we will get back to you within one working day.', 'action' => '#', 'email' => 'hello@meridian.example', 'phone' => '+1 (555) 010-2030', 'address' => '221 Market Street, San Francisco, CA', 'hours' => 'Mon to Fri, 9:00 to 17:00' ) ) ),
	),
	'Buttons and small parts' => array(
		array( 'button', 'Button', '[ai_button label="Get started" url="/signup" variant="solid" size="lg" icon="arrow-right"]', '<div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;padding:32px;background:#fff">' . c( 'button', array( 'label' => 'Solid', 'size' => 'lg' ) ) . c( 'button', array( 'label' => 'Outline', 'variant' => 'outline', 'size' => 'lg', 'icon' => 'none' ) ) . c( 'button', array( 'label' => 'Ghost', 'variant' => 'ghost', 'size' => 'lg' ) ) . c( 'button', array( 'label' => 'Small', 'icon' => 'none' ) ) . '<span style="background:#312e81;padding:12px;border-radius:12px;display:inline-flex;gap:8px">' . c( 'button', array( 'label' => 'Light', 'variant' => 'light', 'icon' => 'none' ) ) . c( 'button', array( 'label' => 'Ghost light', 'variant' => 'ghost-light', 'icon' => 'none' ) ) . '</span></div>' ),
		array( 'badge', 'Badge', '[ai_badge label="New" variant="brand"]', '<div style="display:flex;flex-wrap:wrap;gap:10px;padding:32px;background:#fff">' . c( 'badge', array( 'label' => 'Brand' ) ) . c( 'badge', array( 'label' => 'Neutral', 'variant' => 'gray' ) ) . c( 'badge', array( 'label' => 'Published', 'variant' => 'green' ) ) . c( 'badge', array( 'label' => 'Pending', 'variant' => 'amber' ) ) . c( 'badge', array( 'label' => 'Failed', 'variant' => 'red' ) ) . '</div>' ),
		array( 'alert', 'Alert', '[ai_alert type="success" title="Saved" text="Your changes are live."]', '<div style="display:grid;gap:12px;padding:32px;background:#fff">' . c( 'alert', array( 'type' => 'info', 'title' => 'Heads up', 'text' => 'A new version of the component library is available.' ) ) . c( 'alert', array( 'type' => 'success', 'title' => 'Changes saved', 'text' => 'Your page is live and passed all checks.' ) ) . c( 'alert', array( 'type' => 'warning', 'title' => 'Check this page', 'text' => 'One image is missing alternative text.' ) ) . c( 'alert', array( 'type' => 'error', 'title' => 'Could not publish', 'text' => 'The staging copy failed the syntax check.' ) ) . '</div>' ),
		array( 'empty-state', 'Empty state', '[ai_empty_state icon="search" title="No results" text="" label="Clear filters" url="/shop"]', '<div style="padding:32px;background:#f9fafb">' . c( 'empty-state', array( 'icon' => 'search', 'title' => 'No results found', 'text' => 'We could not find anything that matches your search. Try different keywords or clear your filters.', 'label' => 'Clear filters', 'url' => '#' ) ) . '</div>' ),
	),
);

$css = file_get_contents( AI_COMPONENTS_DIR . 'assets/css/ai-components.css' );

// A deliberately aggressive "theme" to prove the components are isolated from it. Can be switched off in the preview.
$hostile = 'body{font-family:Georgia,serif;color:#444;line-height:1.9}'
	. 'h1,h2,h3,h4{font-family:Georgia,serif;color:#b00020;text-transform:uppercase;letter-spacing:.12em;margin:0 0 1.2em;font-weight:400;border-bottom:1px solid #b00020;padding-bottom:.3em}'
	. 'p{margin:0 0 1.6em;color:#555;text-indent:1em}a{color:#d35400;text-decoration:underline;border-bottom:1px dotted #d35400}'
	. 'ul,ol{margin:0 0 1.5em;padding-left:2.2em;list-style:disc}li{margin:.5em 0}img{border:3px solid #d35400;padding:4px;box-shadow:0 0 0 2px #fff}'
	. 'svg{fill:#d35400}input,textarea,button{font-family:Georgia,serif;border:2px solid #d35400;border-radius:0;text-transform:uppercase}'
	. 'blockquote{border-left:4px solid #d35400;margin:0 0 1.5em;padding-left:1em;font-style:italic}dl,dt,dd{margin:0 0 .8em}summary{color:#b00020;text-transform:uppercase}';

$inner = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style id="host" media="not all">' . $hostile . '</style>'
	. '<style>html{scroll-behavior:smooth}img{background:#e0e7ff}body{margin:0;background:#eef0f4;font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:#374151}.sec{padding:36px 24px 8px;max-width:1360px;margin:0 auto}.sec-h{font:600 11px/1 system-ui,sans-serif!important;letter-spacing:.1em!important;text-transform:uppercase!important;color:#6b7280!important;margin:0 0 4px!important;border:0!important;padding:0!important}'
	. '.item{margin:0 0 28px}.meta{display:flex;flex-wrap:wrap;gap:8px 16px;align-items:baseline;margin:0 0 10px}.meta b{font:600 15px system-ui,sans-serif;color:#111}.meta code{font:12px ui-monospace,monospace;color:#4b5563;background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:3px 8px;word-break:break-all}'
	. '.stage{background:#fff;border:1px solid #d9dce3;border-radius:14px;overflow:hidden;box-shadow:0 1px 2px rgba(0,0,0,.04)}</style><style>' . $css . '</style></head><body>';
foreach ( $groups as $group => $items ) {
	$inner .= '<div class="sec"><p class="sec-h">' . esc_html( $group ) . '</p>';
	foreach ( $items as $it ) {
		$inner .= '<div class="item" id="c-' . esc_attr( $it[0] ) . '"><div class="meta"><b>' . esc_html( $it[1] ) . '</b><code>' . esc_html( $it[2] ) . '</code></div><div class="stage">' . $it[3] . '</div></div>';
	}
	$inner .= '</div>';
}
$inner .= '<script>addEventListener("message",function(e){var d=e.data||{};if(d.go){var el=document.getElementById(d.go);if(el)el.scrollIntoView({behavior:"smooth",block:"start"})}'
	. 'if(d.brand){document.documentElement.style.setProperty("--ai-brand",d.brand)}'
	. 'if(typeof d.host==="boolean"){document.getElementById("host").media=d.host?"all":"not all"}});</script></body></html>';

$nav = '';
foreach ( $groups as $group => $items ) {
	$nav .= '<p class="g">' . esc_html( $group ) . '</p>';
	foreach ( $items as $it ) {
		$nav .= '<a href="#" data-go="c-' . esc_attr( $it[0] ) . '">' . esc_html( $it[1] ) . '</a>';
	}
}
$total = 0;
foreach ( $groups as $items ) { $total += count( $items ); }

$swatches = array( '#4f46e5' => 'Indigo', '#0f766e' => 'Teal', '#be123c' => 'Rose', '#b45309' => 'Amber', '#1e293b' => 'Slate' );
$sw = '';
foreach ( $swatches as $hex => $name ) {
	$sw .= '<button class="sw" data-c="' . $hex . '" title="' . $name . '" style="background:' . $hex . '" aria-label="' . $name . '"></button>';
}

$page = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>AI Components preview</title><style>'
	. '*{box-sizing:border-box}body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",sans-serif;background:#e5e7eb;color:#111;height:100vh;display:flex;flex-direction:column}'
	. 'header{display:flex;flex-wrap:wrap;gap:12px 24px;align-items:center;padding:10px 20px;background:#111827;color:#fff}header h1{font-size:15px;margin:0;font-weight:600}header span.n{font-size:12px;color:#9ca3af;margin-left:8px;font-weight:400}'
	. '.grp{display:flex;align-items:center;gap:8px;font-size:12px;color:#9ca3af}.seg{display:flex;background:#1f2937;border-radius:8px;padding:2px}.seg button{font:500 12px system-ui;border:0;background:none;color:#d1d5db;padding:6px 12px;border-radius:6px;cursor:pointer}.seg button[aria-pressed=true]{background:#fff;color:#111}'
	. '.sw{width:22px;height:22px;border-radius:50%;border:2px solid #111827;outline:2px solid transparent;cursor:pointer;padding:0}.sw[aria-pressed=true]{outline-color:#fff}input[type=color]{width:30px;height:24px;border:0;padding:0;background:none;cursor:pointer}'
	. 'label.t{display:flex;align-items:center;gap:6px;font-size:12px;color:#d1d5db;cursor:pointer}'
	. '.wrap{flex:1;display:flex;min-height:0}nav{width:230px;flex:none;background:#fff;border-right:1px solid #d1d5db;overflow:auto;padding:12px 10px 40px}nav .g{font-size:11px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#6b7280;margin:16px 8px 6px}nav a{display:block;padding:6px 8px;border-radius:6px;font-size:13px;color:#374151;text-decoration:none}nav a:hover{background:#f3f4f6}'
	. 'main{flex:1;display:flex;justify-content:center;padding:14px;min-width:0}iframe{width:100%;max-width:100%;height:100%;border:1px solid #cbd0d8;border-radius:10px;background:#eef0f4;transition:width .2s}'
	. '@media(max-width:800px){nav{display:none}}</style></head><body>'
	. '<header><h1>AI Components<span class="n">' . $total . ' components rendered from the real PHP templates</span></h1>'
	. '<div class="grp">Width<div class="seg" id="w"><button data-w="390" aria-pressed="false">Phone</button><button data-w="768" aria-pressed="false">Tablet</button><button data-w="100%" aria-pressed="true">Desktop</button></div></div>'
	. '<div class="grp">Brand color<span style="display:flex;gap:8px" id="sws">' . $sw . '</span><input type="color" id="cp" value="#4f46e5" aria-label="Custom brand color"></div>'
	. '<label class="t"><input type="checkbox" id="host"> Simulate an aggressive theme</label></header>'
	. '<div class="wrap"><nav>' . $nav . '</nav><main><iframe id="f" title="Component preview" srcdoc="' . htmlspecialchars( $inner, ENT_QUOTES, 'UTF-8' ) . '"></iframe></main></div>'
	. '<script>var f=document.getElementById("f");function post(m){f.contentWindow.postMessage(m,"*")}'
	. 'document.querySelectorAll("#w button").forEach(function(b){b.onclick=function(){f.style.width=b.dataset.w;document.querySelectorAll("#w button").forEach(function(x){x.setAttribute("aria-pressed",x===b)})}});'
	. 'function brand(c){post({brand:c});document.getElementById("cp").value=c;document.querySelectorAll(".sw").forEach(function(s){s.setAttribute("aria-pressed",s.dataset.c===c)})}'
	. 'document.querySelectorAll(".sw").forEach(function(s){s.onclick=function(){brand(s.dataset.c)}});document.getElementById("cp").oninput=function(e){brand(e.target.value)};brand("#4f46e5");'
	. 'document.getElementById("host").onchange=function(e){post({host:e.target.checked})};'
	. 'document.querySelectorAll("nav a").forEach(function(a){a.onclick=function(e){e.preventDefault();post({go:a.dataset.go})}});f.onload=function(){brand(document.getElementById("cp").value);post({host:document.getElementById("host").checked})};</script></body></html>';

file_put_contents( AI_COMPONENTS_DIR . 'preview.html', $page );
if ( getenv( 'AI_INNER' ) ) {
	file_put_contents( getenv( 'AI_INNER' ), $inner ); // debugging aid: the gallery document without the outer chrome.
}
echo 'preview.html written (' . round( strlen( $page ) / 1024 ) . " KB, $total components)\n";
