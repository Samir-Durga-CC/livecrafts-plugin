/* Livecrafts PICKER - standalone test tool. Works on ANY web page (no WordPress needed, nothing is saved).
 * Click an element -> see the generated selector, whether it is unique, and warnings about fragile parts.
 * Live-edit color / size / text to check the element is really the one you meant. Reload the page = everything resets.
 * Toggle on/off by running the script again, or press Esc.
 */
(function () {
  'use strict';
  var doc = document;
  if (window.__lcPicker) { window.__lcPicker.toggle(); return; }

  var state = { on: false, el: null, sel: '', warn: [] };

  /* ---------- fingerprint (same logic as the plugin's editor.js) ---------- */
  var BAD_CLASS = /^(is-|has-|js-|no-js|active$|open$|show$|hover|focus|current|selected|wp-theme|page-id|postid|page-template|logged-in|admin-bar|customize|e-con-inner|css-|sc-|jsx-)/;
  function stableClass(c) { return c && !BAD_CLASS.test(c) && !/\d{3,}/.test(c) && c.length <= 32; }
  function esc(s) { return (window.CSS && CSS.escape) ? CSS.escape(s) : String(s).replace(/[^a-zA-Z0-9_-]/g, '\\$&'); }
  function attrEsc(s) { return String(s).replace(/["\\]/g, '\\$&'); }
  function count(sel) { try { return doc.querySelectorAll(sel).length; } catch (e) { return 0; } }

  function segment(el, info) {
    if (el.id && !/\d{4,}|^[a-f0-9]{8,}$/.test(el.id)) { info.anchors++; return '#' + esc(el.id); }
    var dora = el.getAttribute('data-dora-id');
    if (dora) { info.anchors++; return '[data-dora-id="' + attrEsc(dora) + '"]'; }
    var eid = el.getAttribute('data-id');
    if (eid && el.classList.contains('elementor-element')) { info.anchors++; return '[data-id="' + attrEsc(eid) + '"]'; }
    var s = el.tagName.toLowerCase();
    var all = Array.prototype.slice.call(el.classList);
    var good = all.filter(stableClass).slice(0, 2);
    if (all.length && !good.length) info.hashOnly++;
    s += good.map(function (c) { return '.' + esc(c); }).join('');
    var p = el.parentElement;
    if (p) {
      var like = Array.prototype.filter.call(p.children, function (x) { try { return x.matches(s); } catch (e) { return false; } });
      if (like.length > 1) {
        var idx = Array.prototype.filter.call(p.children, function (x) { return x.tagName === el.tagName; }).indexOf(el) + 1;
        s += ':nth-of-type(' + idx + ')'; info.nth++;
      }
    }
    return s;
  }

  function build(el) {
    var info = { anchors: 0, nth: 0, hashOnly: 0 }, parts = [], cur = el, guard = 0, sel = '';
    while (cur && cur.nodeType === 1 && cur !== doc.documentElement && guard++ < 30) {
      parts.unshift(segment(cur, info));
      sel = parts.join(' > ');
      if (count(sel) === 1 && doc.querySelector(sel) === el) break;
      cur = cur.parentElement;
    }
    var warn = [];
    if (count(sel) !== 1) warn.push('NOT UNIQUE: matches ' + count(sel) + ' elements');
    if (info.nth) warn.push('uses ' + info.nth + '× :nth-of-type (breaks if siblings are added/removed)');
    if (info.hashOnly) warn.push(info.hashOnly + ' step(s) have only generated/hashed classes (fragile if the site rebuilds its CSS)');
    if (parts.length > 5) warn.push('long path (' + parts.length + ' levels) - deep structure, likely fragile');
    if (!warn.length) warn.push('looks stable ✓');
    return { sel: sel, warn: warn, anchors: info.anchors, depth: parts.length };
  }

  /* ---------- UI (Shadow DOM so the site's CSS can't interfere) ---------- */
  var host = doc.createElement('div');
  host.style.cssText = 'all:initial;position:fixed;z-index:2147483647;top:0;left:0;';
  var root = host.attachShadow({ mode: 'open' });
  root.innerHTML =
    '<style>*{box-sizing:border-box;font-family:system-ui,Segoe UI,Roboto,sans-serif}' +
    '.box{position:fixed;pointer-events:none;display:none;border:2px solid #2563eb;background:rgba(37,99,235,.1)}' +
    '.box.hv{border:2px dashed #f59e0b;background:rgba(245,158,11,.08)}' +
    '.panel{position:fixed;top:10px;right:10px;width:330px;max-height:calc(100vh - 20px);overflow:auto;background:#fff;color:#111827;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,.35);font-size:13px;display:none}' +
    '.hd{display:flex;justify-content:space-between;padding:10px 14px;font-weight:600;border-bottom:1px solid #e5e7eb}.hd button{border:0;background:none;font-size:18px;cursor:pointer}' +
    '.bd{padding:12px 14px}.sel{font-family:ui-monospace,Consolas,monospace;font-size:11px;background:#f3f4f6;padding:6px 8px;border-radius:6px;word-break:break-all;margin:6px 0}' +
    '.w{font-size:12px;margin:3px 0}.ok{color:#15803d}.bad{color:#b45309}' +
    '.row{display:flex;gap:8px;align-items:center;margin:7px 0}.row label{flex:0 0 100px}.row input[type=number],.row input[type=text]{flex:1;min-width:0;padding:4px 6px;border:1px solid #d1d5db;border-radius:6px}' +
    'textarea{width:100%;min-height:54px;border:1px solid #d1d5db;border-radius:6px;padding:5px}' +
    '.btn{border:1px solid #d1d5db;background:#fff;border-radius:6px;padding:5px 9px;font-size:12px;cursor:pointer;margin-right:4px}.btn.p{background:#2563eb;color:#fff;border-color:#2563eb}' +
    '.log{font-size:11px;color:#6b7280;margin-top:8px}</style>' +
    '<div class="box hv" id="hv"></div><div class="box" id="sl"></div>' +
    '<div class="panel" id="pn"><div class="hd"><span>Livecrafts picker</span><button id="x" title="Exit">×</button></div><div class="bd" id="bd"></div></div>';
  var $ = function (i) { return root.getElementById(i); };
  var hv = $('hv'), sl = $('sl'), pn = $('pn'), bd = $('bd');
  function h(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function inUI(e) { return (e.composedPath ? e.composedPath() : []).indexOf(host) > -1; }
  function place(box, el) {
    if (!el || !doc.contains(el)) { box.style.display = 'none'; return; }
    var r = el.getBoundingClientRect();
    box.style.cssText += ';display:block;left:' + r.left + 'px;top:' + r.top + 'px;width:' + r.width + 'px;height:' + r.height + 'px';
  }
  function hex(rgb) { var m = String(rgb).match(/(\d+)[, ]+(\d+)[, ]+(\d+)/); return m ? '#' + [m[1], m[2], m[3]].map(function (n) { return ('0' + (+n).toString(16)).slice(-2); }).join('') : '#000000'; }

  function render() {
    var el = state.el;
    if (!el) { bd.innerHTML = '<p>Click any element. Links/buttons are disabled while picking.<br><small>Esc = exit</small></p>'; return; }
    var cs = getComputedStyle(el), leaf = el.children.length === 0;
    var good = state.warn.length === 1 && state.warn[0].indexOf('stable') > -1;
    bd.innerHTML =
      '<b>' + h(el.tagName.toLowerCase()) + '</b> · ' + Math.round(el.getBoundingClientRect().width) + '×' + Math.round(el.getBoundingClientRect().height) + 'px' +
      '<div class="sel">' + h(state.sel) + '</div>' +
      state.warn.map(function (w) { return '<div class="w ' + (good ? 'ok' : 'bad') + '">' + (good ? '' : '⚠ ') + h(w) + '</div>'; }).join('') +
      '<div style="margin:8px 0"><button class="btn" id="up">↑ Parent</button><button class="btn" id="dn">↓ Child</button><button class="btn p" id="cp">Copy selector</button></div>' +
      (leaf ? '<textarea id="tx">' + h(el.textContent) + '</textarea>' : '<div class="w">Contains child elements - select a child to edit its text.</div>') +
      '<div class="row"><label>Text color</label><input type="color" data-p="color" value="' + hex(cs.color) + '"></div>' +
      '<div class="row"><label>Background</label><input type="color" data-p="background-color" value="' + hex(cs.backgroundColor) + '"></div>' +
      '<div class="row"><label>Font size px</label><input type="number" data-p="font-size" data-u="px" value="' + Math.round(parseFloat(cs.fontSize)) + '"></div>' +
      '<div class="row"><label>Padding px</label><input type="number" data-p="padding" data-u="px" value="' + Math.round(parseFloat(cs.paddingTop)) + '"></div>' +
      '<div class="row"><label>Radius px</label><input type="number" data-p="border-radius" data-u="px" value="' + Math.round(parseFloat(cs.borderTopLeftRadius)) + '"></div>' +
      '<div class="log" id="lg">Preview only - nothing is saved. Reload to reset.</div>';
  }

  function select(el) {
    if (!el || el === doc.documentElement || el === doc.body || el === host) return;
    state.el = el;
    var b = build(el); state.sel = b.sel; state.warn = b.warn;
    pn.style.display = 'block'; render(); place(sl, el);
    console.log('[Livecrafts picker]', { selector: b.sel, matches: count(b.sel), warnings: b.warn, depth: b.depth, element: el });
  }

  function onClick(e) { if (!state.on || inUI(e)) return; e.preventDefault(); e.stopPropagation(); select(e.target); }
  function onOver(e) { if (state.on && !inUI(e)) place(hv, e.target); }
  function onKey(e) { if (e.key === 'Escape' && state.on) toggle(); }

  bd.addEventListener('input', function (e) {
    var t = e.target; if (!state.el) return;
    if (t.id === 'tx') { state.el.textContent = t.value; place(sl, state.el); return; }
    var p = t.getAttribute('data-p'); if (!p) return;
    state.el.style.setProperty(p, t.value + (t.getAttribute('data-u') || ''), 'important');
    place(sl, state.el);
  });
  bd.addEventListener('click', function (e) {
    var t = e.target;
    if (t.id === 'up' && state.el.parentElement) select(state.el.parentElement);
    if (t.id === 'dn' && state.el.children[0]) select(state.el.children[0]);
    if (t.id === 'cp') { (navigator.clipboard ? navigator.clipboard.writeText(state.sel) : Promise.reject()).then(function () { $('lg').textContent = 'Selector copied ✓'; }, function () { $('lg').textContent = 'Copy failed - copy it from the box above.'; }); }
  });
  $('x').addEventListener('click', function () { toggle(); });
  window.addEventListener('scroll', function () { if (state.on) place(sl, state.el); }, true);
  window.addEventListener('resize', function () { if (state.on) place(sl, state.el); });

  function toggle() {
    state.on = !state.on;
    if (state.on) { doc.body.appendChild(host); pn.style.display = 'block'; render(); }
    else { hv.style.display = 'none'; sl.style.display = 'none'; pn.style.display = 'none'; if (host.parentNode) host.parentNode.removeChild(host); }
  }
  doc.addEventListener('click', onClick, true);
  doc.addEventListener('mouseover', onOver, true);
  doc.addEventListener('keydown', onKey, true);

  window.__lcPicker = { toggle: toggle };
  toggle();
})();
