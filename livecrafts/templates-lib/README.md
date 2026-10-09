# AI Components (WordPress plugin)

25 reusable components that look the same in Elementor, Divi, WPBakery and custom/ACF themes.
Tailwind v4, prefixed (`ai:`), no global reset, no JavaScript framework. Requires WordPress 6.1+ / PHP 7.4+.

## Components
Navigation: announcement, header, breadcrumbs, sidebar, pagination, footer
Hero and CTA: hero, hero_centered, cta
Content: feature_grid, steps, stats, logo_cloud, testimonials, team, pricing, faq
Blog: card, blog_grid
Forms (markup only, point `action` at your form handler): newsletter, contact_form
Small parts: button, badge, alert, empty_state

## Use
Shortcode (works in the Elementor Shortcode widget, Divi Code module, WPBakery Text Block):
    [ai_button label="Get started" url="/signup" variant="solid" size="lg"]
    [ai_faq heading="Questions"][ai_item title="Does it work in Divi?"]Yes.[/ai_item][/ai_faq]
List components take [ai_item] children; attributes depend on the component (see the docblock in each template).

PHP / ACF repeater:
    while ( have_rows( 'cards' ) ) : the_row();
        ai_component( 'card', array( 'title' => get_sub_field( 'title' ), 'url' => get_sub_field( 'link' ), 'image' => get_sub_field( 'image' ) ) );
    endwhile;

## Brand color
One variable restyles every component:
    add_filter( 'ai_components_brand', fn() => '#0f766e' );     // or: update_option( 'ai_components_brand', '#0f766e' );
Per section override: wrap in an element with style="--ai-brand:#be123c".
Font: components inherit the theme font. Force one with --ai-font.

## Theme overrides
Copy templates/{name}.php to your-theme/ai-components/{name}.php. Edit the copy (use ai: prefixed classes only if you rebuild the CSS).

## Develop
Edit src/templates/*.php with plain Tailwind classes (never edit templates/ by hand), then:
    npm install
    npm run build       # prefixes classes, compiles CSS, checks every class exists, re-renders preview.html
Class strings built in PHP must go through ai_cls( '...' ) so the prefixer and Tailwind can see them.
Every utility is !important on purpose, so theme CSS (Elementor, Divi, ...) cannot restyle components.

## Licenses
Icons: Heroicons (MIT). Card layouts started from HyperUI (MIT). Check any library you add.
