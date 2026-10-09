#!/usr/bin/env python3
"""Verify every ai: class used in templates/ and includes/ exists in the compiled CSS."""
import re, glob, sys, pathlib
root = pathlib.Path(__file__).resolve().parent.parent
css = (root / "assets/css/ai-components.css").read_text()
used = set()
for f in glob.glob(str(root / "templates/*.php")) + [str(root / "includes/helpers.php")]:
    t = open(f).read()
    for m in re.finditer(r'class="([^"<>]*)"', t):
        used.update(m.group(1).split())
    for m in re.finditer(r"ai_cls\( '([^']*)' \)", t):
        used.update(m.group(1).split())
used = {u for u in used if u.startswith("ai:") and "'" not in u}
def esc(t):
    return re.sub(r'([:/.\[\]%])', r'\\\1', t)
missing = sorted(u for u in used if esc(u) not in css)
print(f"{len(used)} distinct classes, {len(missing)} missing")
for m in missing:
    print("  MISSING", m)
sys.exit(1 if missing else 0)
