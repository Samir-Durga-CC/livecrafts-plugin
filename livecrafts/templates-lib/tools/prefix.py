#!/usr/bin/env python3
"""src/templates -> templates: adds the Tailwind 'ai:' prefix to class tokens.
Handles   class="a b c"   (no PHP inside)   and   ai_cls( 'a b c' )   (PHP class maps).
Tokens starting with 'ai-' (e.g. the ai-c wrapper) are left alone. Never edit templates/ by hand."""
import re, pathlib
root = pathlib.Path(__file__).resolve().parent.parent
src, out = root / "src/templates", root / "templates"
out.mkdir(exist_ok=True)
for old in out.glob("*.php"):
    old.unlink()

def pre(tokens):
    return " ".join(t if t.startswith("ai-") else "ai:" + t for t in tokens.split())

def attr(m):
    return 'class="' + pre(m.group(1)) + '"'

def fn(m):
    return "ai_cls( '" + pre(m.group(1)) + "' )"

n = 0
for f in sorted(src.glob("*.php")):
    t = f.read_text()
    t = re.sub(r'class="([^"<>]*)"', attr, t)
    t = re.sub(r"ai_cls\( '([^']*)' \)", fn, t)
    (out / f.name).write_text(t)
    n += 1
print(f"prefixed {n} templates")
