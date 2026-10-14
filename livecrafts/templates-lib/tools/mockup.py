#!/usr/bin/env python3
"""
A ROUGH preview of templates/*.php without PHP, for looking at layout while developing.

It is not a renderer: it strips the PHP, fills sample text, repeats each foreach three times and shows every branch of
a variant separately. Use it to see that a layout holds together and is responsive; use a real WordPress page for
anything that depends on data. `php tools/preview.php` stays the accurate preview where PHP is available.

    python3 tools/mockup.py testimonials feature-grid blog-grid contact-form slider > /tmp/mockup.html
"""
import re, sys, pathlib

root = pathlib.Path(__file__).resolve().parent.parent
TEMPLATES = root / "templates"
CSS = root / "assets/css/ai-components.css"

STAR = ('<svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" '
        'stroke="currentColor" class="ai:size-5 ai:shrink-0"><path stroke-linecap="round" stroke-linejoin="round" '
        'd="M11.48 3.5a.56.56 0 0 1 1.04 0l2.12 5.11 5.52.44c.5.04.7.67.32 1l-4.2 3.6 1.28 5.39a.56.56 0 0 1-.84.6'
        'L12 16.75l-4.72 2.89a.56.56 0 0 1-.84-.6l1.28-5.4-4.2-3.6a.56.56 0 0 1 .32-.99l5.52-.44 2.12-5.1Z" /></svg>')

SAMPLE = {
    "heading": "Everything you need, nothing you don't",
    "text": "Three things every site we ship is measured against before it goes live.",
    "title": "Fast delivery",
    "quote": "They understood our brand from day one and turned it into a website we are proud of.",
    "name": "Anita Shah", "role": "Founder, Acme Foods", "excerpt": "A short excerpt that gives readers a reason to click through.",
    "category": "Design", "date": "12 Oct 2026", "read_time": "5 min read", "label": "Start a project",
    "link_label": "Read more", "button_label": "Send message", "value": "hello@example.com",
}


def sample_for(expr: str) -> str:
    """A believable value for whatever the template was about to echo."""
    for key, val in SAMPLE.items():
        if key in expr:
            return val
    return SAMPLE["title"]


def strip_php(src: str) -> str:
    cls = dict(re.findall(r"\$(\w+)\s*=\s*ai_cls\(\s*'([^']*)'\s*\)", src))

    # class="<?php echo esc_attr( $x ); ?> more" -> the literal classes that variable holds
    def attr(m):
        return cls.get(m.group(1), "")
    src = re.sub(r"<\?php echo esc_attr\(\s*\$(\w+)\s*\); \?>", attr, src)
    src = re.sub(r"<\?php for \([^?]*?\) \{ ai_e_icon\([^?]*?\); \} \?>", STAR * 5, src)
    src = re.sub(r"<\?php ai_e_icon\([^?]*?\); \?>", STAR, src)
    src = re.sub(r"<\?php echo esc_(?:html|attr|url)\(\s*(.*?)\s*\); \?>", lambda m: sample_for(m.group(1)), src)
    # every foreach body three times over, so a grid shows as a grid
    src = re.sub(r"<\?php foreach[^?]*?\?>(.*?)<\?php endforeach; \?>", lambda m: m.group(1) * 3, src, flags=re.S)
    return re.sub(r"<\?php.*?\?>", "", src, flags=re.S)


IF = re.compile(r"<\?php (?:else)?if \([^?]*?\) : \?>")
ELSE = re.compile(r"<\?php else : \?>")
ENDIF = re.compile(r"<\?php endif; \?>")


def variants(src: str):
    """
    One copy of the WHOLE template per `$variant` branch, with the other branches removed.

    Slicing the file at the branch markers would cut it mid-element (the slider opens its <ul> before them), so the
    chain is resolved instead: find where it starts and ends by counting if/endif, then keep one branch and the markup
    around it. A template without variants is returned once, unchanged.
    """
    first = re.search(r"<\?php if \(\s*'\w+' === \$variant", src)
    if not first:
        return [("default", src)]

    # Walk the tags from the chain's start, counting nested if/endif, to find its own endif and its top-level branches.
    cuts, depth, pos = [], 0, first.start()
    end = len(src)
    for m in sorted(
        [*IF.finditer(src, first.start()), *ELSE.finditer(src, first.start()), *ENDIF.finditer(src, first.start())],
        key=lambda m: m.start(),
    ):
        tag = m.group(0)
        if ENDIF.fullmatch(tag):
            if depth == 1:
                end = m.start()
                cuts.append((m.start(), m.end()))
                break
            depth -= 1
            continue
        if ELSE.fullmatch(tag) or tag.startswith("<?php elseif"):
            if depth == 1:
                cuts.append((m.start(), m.end()))
                continue
        if tag.startswith("<?php if"):
            depth += 1
        elif tag.startswith("<?php elseif") and depth == 0:
            depth += 1
    if depth == 0 and not cuts:
        return [("default", src)]

    head, tail = src[: first.start()], src[cuts[-1][1] :]
    names = [m.group(1) for m in re.finditer(r"<\?php (?:else)?if \(\s*'(\w+)' === \$variant", src)]
    bounds = [first.end()] + [c[1] for c in cuts[:-1]]
    stops = [c[0] for c in cuts]
    out = []
    for i, start in enumerate(bounds):
        name = names[i] if i < len(names) else "(last)"
        out.append((name, head + src[start : stops[i]] + tail))
    return out


def main(names):
    parts = []
    for name in names:
        src = (TEMPLATES / f"{name}.php").read_text(encoding="utf-8")
        for variant, block in variants(src):
            parts.append(f'<p class="lbl">{name} &middot; variant "{variant}"</p>{strip_php(block)}')
    print(f"""<!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><title>Template mockup</title>
<style>{CSS.read_text(encoding='utf-8')}</style>
<style>body{{margin:0;font:16px system-ui,Segoe UI,Arial,sans-serif}}
.lbl{{margin:0;padding:8px 16px;background:#111827;color:#fff;font:600 13px ui-monospace,monospace}}</style>
</head><body>{''.join(parts)}</body></html>""")


if __name__ == "__main__":
    main(sys.argv[1:] or ["testimonials"])
