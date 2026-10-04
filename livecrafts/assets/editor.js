/* Livecrafts editor v0.3
 *  1. buildSelector(): a stable CSS address for the clicked element (styles + fallback patches).
 *  2. resolve*():      match the element's text / link / image against the page's ACF field list
 *                      (TRACE = values the page read while rendering, SCAN = every field of the post).
 *  3. Save:            ACF target found -> /target (server reads the value back).  Else -> patch (/save).
 *  4. Verify:          after saving, reload the page HTML (cache-busted) and check the element really shows it.
 *  5. Debug:           panel "Debug" box + window.Livecrafts.* helpers call the same server functions.
 * The UI lives in a Shadow DOM so the site's CSS can't break it.
 */
(function () {
  'use strict';
  var C = window.LIVECRAFTS;
  if (!C) return;
  var doc = document;
  var TRACE = window.LIVECRAFTS_TRACE || [];   // ACF values the page read while rendering
  var SCAN = window.LIVECRAFTS_SCAN || [];     // every ACF field of this post
  var EL = window.LIVECRAFTS_EL || [];         // every editable Elementor setting of this post (v0.4)

  var state = {
    on: false, el: null, sel: '', scope: 'page',
    styles: {}, text: null, hadPatch: false, needsReload: false,
    textDirty: false, link: null, linkDirty: false, imageId: null,
    res: { text: null, link: null, image: null },
    dbg: '', dbgOpen: false
  };
  var patches = C.patches || { site: {}, page: {} };

  /* ================= 1. FINGERPRINT: stable, unique CSS selector ================= */

  var BAD_CLASS = /^(is-|has-|js-|no-js|active$|open$|show$|hover|focus|current|selected|wp-theme|page-id|postid|page-template|logged-in|admin-bar|customize|e-con-inner|css-|sc-|jsx-)/;
  function stableClass(c) { return c && !BAD_CLASS.test(c) && !/\d{3,}/.test(c) && c.length <= 32; }
  function esc(s) { return (window.CSS && CSS.escape) ? CSS.escape(s) : String(s).replace(/[^a-zA-Z0-9_-]/g, '\\$&'); }
  function attrEsc(s) { return String(s).replace(/["\\]/g, '\\$&'); }
  function count(sel) { try { return doc.querySelectorAll(sel).length; } catch (e) { return 0; } }

  function segment(el) {
    if (el.id && !/\d{4,}|^[a-f0-9]{8,}$/.test(el.id)) return '#' + esc(el.id);
    var dora = el.getAttribute('data-dora-id');
    if (dora) return '[data-dora-id="' + attrEsc(dora) + '"]';
    var eid = el.getAttribute('data-id');
    if (eid && el.classList.contains('elementor-element')) return '[data-id="' + attrEsc(eid) + '"]';

    var s = el.tagName.toLowerCase();
    var cls = Array.prototype.filter.call(el.classList, stableClass).slice(0, 2);
    s += cls.map(function (c) { return '.' + esc(c); }).join('');
    var p = el.parentElement;
    if (p) {
      var like = Array.prototype.filter.call(p.children, function (x) { try { return x.matches(s); } catch (e) { return false; } });
      if (like.length > 1) {
        var idx = Array.prototype.filter.call(p.children, function (x) { return x.tagName === el.tagName; }).indexOf(el) + 1;
        s += ':nth-of-type(' + idx + ')';
      }
    }
    return s;
  }

  function buildSelector(el) {
    var parts = [], cur = el, guard = 0;
    while (cur && cur.nodeType === 1 && cur !== doc.documentElement && guard++ < 30) {
      parts.unshift(segment(cur));
      var sel = parts.join(' > ');
      if (count(sel) === 1 && doc.querySelector(sel) === el) return sel;
      cur = cur.parentElement;
    }
    return parts.join(' > ');
  }

  /* ================= 2. RESOLVER: element -> ACF target ================= */

  function norm(s) { return String(s == null ? '' : s).replace(/\s+/g, ' ').trim(); }
  function plain(s) { try { return new DOMParser().parseFromString(String(s), 'text/html').body.textContent || ''; } catch (e) { return String(s); } }
  function bgUrl(el) { var m = getComputedStyle(el).backgroundImage.match(/url\(["']?(.*?)["']?\)/); return m ? m[1] : ''; }
  function baseUrl(u) { return String(u || '').split('?')[0].replace(/-\d+x\d+(?=\.\w+$)/, ''); }
  function imgUrlOf(x) { return x.tagName === 'IMG' ? (x.currentSrc || x.src) : bgUrl(x); }
  function tid(t) { return t.tid; }  // "acf:<field_key>:<post>"  or  "el:<post>:<widget_id>:<setting>"
  var SKIP_TAG = /^(SCRIPT|STYLE|NOSCRIPT|TEMPLATE)$/;

  // ---- Elementor: every element carries data-id, so the match is exact (no text guessing) ----
  function elWidget(el) { var w = el && el.closest ? el.closest('.elementor-element[data-id]') : null; return w ? w.getAttribute('data-id') : null; }
  function resolveElText(el, text) {
    var id = elWidget(el); if (!id) return null;
    var c = EL.filter(function (e) { return e.id === id && (e.ftype === 'text' || e.ftype === 'html') && norm(plain(e.value)) === text; });
    if (c.length === 1) return { t: c[0], how: 'id' };
    return c.length > 1 ? { ambiguous: c } : null;
  }
  function resolveElLink(a, href) {
    var id = elWidget(a); if (!id) return null;
    var c = EL.filter(function (e) { return e.id === id && e.ftype === 'url' && String(e.value) === href; });
    return c.length === 1 ? { t: c[0], how: 'id' } : null;
  }
  function resolveElImage(el) {
    var id = elWidget(el); if (!id) return null;
    var c = EL.filter(function (e) {
      if (e.id !== id || e.ftype !== 'image') return false;
      return el.tagName === 'IMG' ? e.name === 'image' : (e.name === 'background_image' && el.getAttribute('data-id') === id);
    });
    return c.length === 1 ? { t: c[0], how: 'id' } : null;
  }

  // Candidate fields for a predicate: TRACE first (render order), SCAN as fallback.
  function candidates(match) {
    var c = TRACE.filter(match);
    if (c.length) return { list: c, from: 'trace' };
    return { list: SCAN.filter(match), from: 'scan' };
  }

  // One field -> exact. Several fields with the same value -> use render order (trace only); otherwise do NOT guess.
  function choose(cs, domMatches, el) {
    if (!cs.list.length) return null;
    if (cs.list.length === 1) return { t: cs.list[0], how: cs.from === 'trace' ? 'exact' : 'scan' };
    if (cs.from === 'trace') {
      var idx = domMatches.indexOf(el);
      if (idx > -1 && domMatches.length === cs.list.length) return { t: cs.list[idx], how: 'position' };
    }
    return { ambiguous: cs.list };
  }

  function textMatcher(text) { return function (t) { return t.ftype !== 'image' && t.value !== '' && norm(plain(t.value)) === text; }; }
  function resolveText(el) {
    if (el.children.length) return null;
    var text = norm(el.textContent);
    if (!text) return null;
    var viaEl = resolveElText(el, text);
    if (viaEl) return viaEl;
    var cs = candidates(textMatcher(text));
    if (!cs.list.length) return null;
    var same = Array.prototype.filter.call(doc.body.querySelectorAll('*'), function (x) {
      return !x.children.length && !SKIP_TAG.test(x.tagName) && !host.contains(x) && norm(x.textContent) === text;
    });
    return choose(cs, same, el);
  }

  function linkMatcher(href) { return function (t) { return (t.ftype === 'text' || t.ftype === 'url') && t.value !== '' && String(t.value) === href; }; }
  function resolveLink(el) {
    var a = el.closest ? el.closest('a[href]') : null;
    if (!a) return null;
    var href = a.getAttribute('href');
    var viaEl = resolveElLink(a, href);
    if (viaEl) { viaEl.anchor = a; return viaEl; }
    var cs = candidates(linkMatcher(href));
    if (!cs.list.length) return null;
    var same = Array.prototype.filter.call(doc.querySelectorAll('a[href]'), function (x) { return !host.contains(x) && x.getAttribute('href') === href; });
    var r = choose(cs, same, a);
    if (r) r.anchor = a;
    return r;
  }

  function imageMatcher(b) { return function (t) { return t.ftype === 'image' && t.url && baseUrl(t.url) === b; }; }
  function resolveImage(el) {
    var u = imgUrlOf(el);
    if (!u) return null;
    var viaEl = resolveElImage(el);
    if (viaEl) return viaEl;
    var b = baseUrl(u);
    var cs = candidates(imageMatcher(b));
    if (!cs.list.length) return null;
    var same = Array.prototype.filter.call(doc.body.querySelectorAll('*'), function (x) {
      return !SKIP_TAG.test(x.tagName) && !host.contains(x) && baseUrl(imgUrlOf(x)) === b;
    });
    return choose(cs, same, el);
  }

  // Everything the resolver looked at, for debugging ("why was this field (not) chosen?").
  function explain(el) {
    var text = el.children.length ? null : norm(el.textContent);
    var a = el.closest ? el.closest('a[href]') : null;
    var u = imgUrlOf(el);
    function pack(match) { return { trace: TRACE.filter(match).map(tid), scan: SCAN.filter(match).map(tid) }; }
    var wid = elWidget(el);
    return {
      elementorWidgetId: wid || 'not inside an Elementor element',
      elementorSettingsOnThisWidget: wid ? EL.filter(function (e) { return e.id === wid; }).map(function (e) { return { target: tid(e), type: e.ftype, value: String(e.value).slice(0, 50) }; }) : [],
      element: { tag: el.tagName.toLowerCase(), isLeaf: !el.children.length, text: text, href: a ? a.getAttribute('href') : null, imageUrl: u || null },
      textCandidates: text ? pack(textMatcher(text)) : 'not a leaf element (text is only matched on leaf elements)',
      linkCandidates: a ? pack(linkMatcher(a.getAttribute('href'))) : 'no <a> around this element',
      imageCandidates: u ? pack(imageMatcher(baseUrl(u))) : 'no image / background image on this element',
      traceCount: TRACE.length, scanCount: SCAN.length
    };
  }

  /* ================= 3. API ================= */

  function handle(r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.message || ('HTTP ' + r.status)); return j; }); }
  function api(path, body) {
    return fetch(C.restUrl + path, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': C.nonce },
      body: JSON.stringify(body)
    }).then(handle);
  }
  function apiGet(path, params) {
    var q = Object.keys(params || {}).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); }).join('&');
    var base = C.restUrl + path;
    return fetch(base + (base.indexOf('?') > -1 ? '&' : '?') + q, { credentials: 'same-origin', headers: { 'X-WP-Nonce': C.nonce } }).then(handle);
  }
  function setTarget(t, value) { return api('/target', { targetId: tid(t), value: value }); }
  function pagePostId() { var m = String(C.pageKey).match(/^p(\d+)$/); return m ? +m[1] : 0; }

  /* ================= 4. UI SHELL (Shadow DOM) ================= */

  var host = doc.createElement('div');
  host.id = 'livecrafts-root';
  host.style.cssText = 'all:initial;position:fixed;z-index:2147483000;top:0;left:0;';
  var root = host.attachShadow({ mode: 'open' });
  root.innerHTML =
    '<style>' +
    ':host{all:initial}*{box-sizing:border-box;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif}' +
    '.fab{position:fixed;left:16px;bottom:16px;background:#111827;color:#fff;border:0;border-radius:999px;padding:11px 18px;font-size:14px;cursor:pointer;box-shadow:0 6px 20px rgba(0,0,0,.3)}' +
    '.fab.on{background:#2563eb}' +
    '.box{position:fixed;pointer-events:none;display:none;border:2px solid #2563eb;background:rgba(37,99,235,.08);border-radius:2px}' +
    '.box.hover{border-style:dashed;border-color:#f59e0b;background:rgba(245,158,11,.07)}' +
    '.tag{position:absolute;left:-2px;top:-22px;background:#2563eb;color:#fff;font-size:11px;padding:2px 6px;border-radius:3px;white-space:nowrap}' +
    '.panel{position:fixed;top:12px;right:12px;bottom:12px;width:330px;background:#fff;color:#111827;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,.28);display:none;flex-direction:column;font-size:13px}' +
    '.panel.open{display:flex}' +
    '.hd{display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border-bottom:1px solid #e5e7eb;font-weight:600}' +
    '.hd button{background:none;border:0;font-size:18px;cursor:pointer;color:#6b7280}' +
    '.bd{padding:12px 14px;overflow:auto;flex:1}' +
    '.sel{font-family:ui-monospace,Consolas,monospace;font-size:11px;background:#f3f4f6;border-radius:6px;padding:6px 8px;word-break:break-all;margin:6px 0 8px}' +
    '.src{font-size:12px;border-radius:6px;padding:6px 8px;margin:6px 0;line-height:1.4}' +
    '.src.ok{background:#ecfdf5;color:#065f46}.src.warn{background:#fffbeb;color:#92400e}.src.none{background:#f3f4f6;color:#4b5563}' +
    '.src code{font-size:11px}.src .how{opacity:.75}' +
    '.row{display:flex;align-items:center;gap:8px;margin:8px 0}.row label{flex:0 0 110px;color:#374151}' +
    '.row input[type=number],.row input[type=text],.row select{flex:1;min-width:0;padding:5px 6px;border:1px solid #d1d5db;border-radius:6px;font-size:13px}' +
    '.row input[type=color]{width:44px;height:28px;padding:0;border:1px solid #d1d5db;border-radius:6px;background:#fff}' +
    '.row .x{background:none;border:0;color:#9ca3af;cursor:pointer;font-size:15px}' +
    'textarea{width:100%;min-height:70px;padding:6px;border:1px solid #d1d5db;border-radius:6px;font-size:13px}' +
    '.hint{color:#6b7280;font-size:12px;margin:6px 0}' +
    '.nav{display:flex;gap:6px;margin:6px 0 10px}' +
    '.btn{border:1px solid #d1d5db;background:#fff;border-radius:6px;padding:6px 10px;font-size:12px;cursor:pointer}' +
    '.btn.pri{background:#2563eb;color:#fff;border-color:#2563eb}.btn.dng{color:#b91c1c}' +
    '.ft{padding:10px 14px;border-top:1px solid #e5e7eb;display:flex;gap:6px;flex-wrap:wrap;align-items:center}' +
    '.st{flex-basis:100%;font-size:12px;color:#6b7280;min-height:16px;line-height:1.4;white-space:pre-line}' +
    'h4{margin:14px 0 4px;font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:#6b7280}' +
    'details{margin-top:14px;border-top:1px solid #e5e7eb;padding-top:8px}summary{cursor:pointer;font-weight:600;color:#374151}' +
    'pre{background:#0f172a;color:#e2e8f0;border-radius:6px;padding:8px;font-size:11px;white-space:pre-wrap;word-break:break-word;max-height:220px;overflow:auto;margin:8px 0 0}' +
    '</style>' +
    '<button class="fab" type="button">✎ Edit</button>' +
    '<div class="box hover" id="hoverBox"><span class="tag" id="hoverTag"></span></div>' +
    '<div class="box" id="selBox"><span class="tag" id="selTag"></span></div>' +
    '<div class="panel" id="panel"><div class="hd"><span>Livecrafts</span><button type="button" id="close" title="Exit edit mode">×</button></div>' +
    '<div class="bd" id="bd"></div>' +
    '<div class="ft"><button class="btn pri" id="save" type="button">Save</button><button class="btn" id="undo" type="button">Undo last</button><button class="btn dng" id="revert" type="button">Reset patch</button><div class="st" id="st"></div></div></div>';

  var $ = function (id) { return root.getElementById(id); };
  var fab = root.querySelector('.fab');
  var panel = $('panel'), bd = $('bd'), st = $('st');
  var hoverBox = $('hoverBox'), selBox = $('selBox');

  // kind: undefined = neutral, 'bad' = red, 'warn' = amber, 'good' = green
  function setStatus(msg, kind) {
    st.textContent = msg || '';
    st.style.color = kind === true || kind === 'bad' ? '#b91c1c' : kind === 'warn' ? '#b45309' : kind === 'good' ? '#047857' : '#6b7280';
  }
  function isInUI(e) { var p = e.composedPath ? e.composedPath() : []; return p.indexOf(host) > -1; }

  /* ================= 5. HIGHLIGHT BOXES ================= */

  function place(box, tagEl, el, text) {
    if (!el || !doc.contains(el)) { box.style.display = 'none'; return; }
    var r = el.getBoundingClientRect();
    box.style.display = 'block';
    box.style.left = r.left + 'px'; box.style.top = r.top + 'px';
    box.style.width = r.width + 'px'; box.style.height = r.height + 'px';
    tagEl.textContent = text;
  }
  function label(el) { var c = Array.prototype.filter.call(el.classList, stableClass)[0]; return el.tagName.toLowerCase() + (c ? '.' + c : ''); }
  var raf = 0;
  function refreshSel() { cancelAnimationFrame(raf); raf = requestAnimationFrame(function () { place(selBox, $('selTag'), state.el, state.el ? label(state.el) : ''); }); }
  window.addEventListener('scroll', refreshSel, true);
  window.addEventListener('resize', refreshSel);

  /* ================= 6. LIVE PREVIEW ================= */

  var live = doc.createElement('style');
  live.id = 'livecrafts-live';
  doc.head.appendChild(live);

  function cssFor(sel, styles) {
    var d = Object.keys(styles).map(function (p) { return p + ':' + styles[p] + ' !important'; });
    return d.length ? sel + '{' + d.join(';') + '}' : '';
  }
  function refreshLive() { live.textContent = state.sel ? cssFor(state.sel, state.styles) : ''; refreshSel(); }

  /* ================= 7. PANEL ================= */

  var CONTROLS = [
    { h: 'Text' },
    { p: 'color', l: 'Text color', t: 'color' },
    { p: 'font-size', l: 'Font size (px)', t: 'num', u: 'px', step: 1 },
    { p: 'font-weight', l: 'Weight', t: 'sel', o: ['', '300', '400', '500', '600', '700'] },
    { p: 'text-align', l: 'Align', t: 'sel', o: ['', 'left', 'center', 'right'] },
    { p: 'letter-spacing', l: 'Letter spacing (px)', t: 'num', u: 'px', step: 0.5 },
    { p: 'line-height', l: 'Line height', t: 'num', u: '', step: 0.1 },
    { h: 'Box' },
    { p: 'background-color', l: 'Background', t: 'color' },
    { p: 'background-image', l: 'Image URL', t: 'img' },
    { p: 'padding', l: 'Padding (px)', t: 'num', u: 'px', step: 2 },
    { p: 'margin', l: 'Margin (px)', t: 'num', u: 'px', step: 2 },
    { p: 'border-radius', l: 'Radius (px)', t: 'num', u: 'px', step: 1 },
    { p: 'opacity', l: 'Opacity', t: 'num', u: '', step: 0.1, min: 0, max: 1 },
    { p: 'display', l: 'Hide element', t: 'hide' }
  ];

  function rgbToHex(rgb) {
    var m = String(rgb).match(/rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?/);
    if (!m) return '#000000';
    return '#' + [m[1], m[2], m[3]].map(function (n) { return ('0' + (+n).toString(16)).slice(-2); }).join('');
  }
  function h(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

  function controlHtml(c) {
    if (c.h) return '<h4>' + c.h + '</h4>';
    var cs = getComputedStyle(state.el), cur = state.styles[c.p], set = cur !== undefined;
    var input = '';
    if (c.t === 'color') {
      input = '<input type="color" data-p="' + c.p + '" value="' + (set ? cur : rgbToHex(cs.getPropertyValue(c.p))) + '">';
    } else if (c.t === 'num') {
      var v = parseFloat(set ? cur : cs.getPropertyValue(c.p)); if (isNaN(v)) v = '';
      input = '<input type="number" data-p="' + c.p + '" data-u="' + c.u + '" step="' + c.step + '"' + (c.min !== undefined ? ' min="' + c.min + '" max="' + c.max + '"' : '') + ' value="' + (v === '' ? '' : Math.round(v * 100) / 100) + '">';
    } else if (c.t === 'sel') {
      var sv = set ? cur : '';
      input = '<select data-p="' + c.p + '">' + c.o.map(function (o) { return '<option value="' + o + '"' + (o === sv ? ' selected' : '') + '>' + (o || '(theme)') + '</option>'; }).join('') + '</select>';
    } else if (c.t === 'img') {
      var m = set && String(cur).match(/^url\(["']?(.*?)["']?\)$/);
      input = '<input type="text" data-p="' + c.p + '" placeholder="https://…" value="' + h(m ? m[1] : '') + '">';
    } else if (c.t === 'hide') {
      input = '<input type="checkbox" data-p="display"' + (set && cur === 'none' ? ' checked' : '') + '>';
    }
    return '<div class="row"><label>' + c.l + (set ? ' •' : '') + '</label>' + input + (set ? '<button class="x" type="button" data-clear="' + c.p + '" title="Remove my change">×</button>' : '') + '</div>';
  }

  var HOW = { exact: 'exact match', id: 'Elementor widget id - exact', position: 'matched by position - please verify', scan: 'found in the post\'s field list; the theme did not print it through ACF while rendering - please verify' };
  function srcHtml(kind, r) {
    if (!r) return '';
    if (r.ambiguous) {
      return '<div class="src warn">' + kind + ' matches ' + r.ambiguous.length + ' fields/settings (' + r.ambiguous.map(function (t) { return h(t.name); }).join(', ') + ') - cannot tell which, so it is NOT linked.</div>';
    }
    var isEl = r.t.kind === 'el';
    return '<div class="src ok">' + kind + ' → <b>' + (isEl ? 'Elementor' : 'ACF') + ' · ' + h(r.t.label) + '</b> <code>' + h(r.t.name) + '</code> · ' +
      (isEl ? 'widget <code>' + h(r.t.id) + '</code> · ' : '') + 'post ' + r.t.post + ' <span class="how">(' + HOW[r.how] + ')</span></div>';
  }
  function hasTarget(r) { return !!(r && r.t); }
  function srcName(r) { return r && r.t && r.t.kind === 'el' ? 'Elementor' : 'ACF'; }

  function render() {
    var el = state.el;
    if (!el) { bd.innerHTML = '<p class="hint">Click any element on the page to select it. Links are disabled while editing.</p>' + debugHtml(); return; }
    var rs = state.res, leaf = el.children.length === 0;
    var anyTarget = hasTarget(rs.text) || hasTarget(rs.link) || hasTarget(rs.image);
    var anyAmb = (rs.text && rs.text.ambiguous) || (rs.link && rs.link.ambiguous) || (rs.image && rs.image.ambiguous);

    var html =
      '<div class="hint"><b>' + h(label(el)) + '</b> selected</div>' +
      srcHtml('Text', rs.text) + srcHtml('Link', rs.link) + srcHtml('Image', rs.image) +
      (!anyTarget && !anyAmb ? '<div class="src none">No ACF source found for this element - changes are saved as a patch (CSS / text overlay).</div>' : '') +
      '<div class="sel">' + h(state.sel) + '</div>' +
      '<div class="nav"><button class="btn" type="button" id="up">↑ Parent</button><button class="btn" type="button" id="down">↓ Child</button>' +
      '<select id="scope" class="btn"><option value="page"' + (state.scope === 'page' ? ' selected' : '') + '>This page only</option><option value="site"' + (state.scope === 'site' ? ' selected' : '') + '>Whole site</option></select></div>';

    if (leaf) {
      html += '<h4>Content' + (hasTarget(rs.text) ? ' (saves to ' + srcName(rs.text) + ')' : ' (saves as patch)') + '</h4><textarea id="txt">' + h(state.text !== null ? state.text : el.textContent) + '</textarea>';
    } else {
      html += '<p class="hint">This element contains other elements. Use ↓ Child or click the text itself to edit its words. Style changes still work here.</p>';
    }

    if (hasTarget(rs.link)) {
      html += '<h4>Link (saves to ' + srcName(rs.link) + ')</h4><div class="row"><input type="text" id="lnk" value="' + h(state.link !== null ? state.link : rs.link.anchor.getAttribute('href')) + '"></div>';
    }

    var isImg = el.tagName === 'IMG', hasBg = !isImg && !!bgUrl(el);
    if (hasTarget(rs.image)) {
      html += '<h4>Image (saves to ' + srcName(rs.image) + ')</h4><div class="row"><button class="btn" type="button" id="pickimg">Choose from Media Library…</button></div>';
    } else if (hasBg) {
      html += '<h4>Background image (saves as patch)</h4><div class="row"><button class="btn" type="button" id="pickimg">Choose from Media Library…</button></div>';
    }

    html += CONTROLS.map(controlHtml).join('') + debugHtml();
    bd.innerHTML = html;
  }

  function debugHtml() {
    return '<details id="dbg"' + (state.dbgOpen ? ' open' : '') + '><summary>Debug</summary>' +
      '<div class="nav" style="margin-top:8px"><button class="btn" type="button" id="dbgRead">Read this element\'s ACF value</button></div>' +
      '<div class="nav"><button class="btn" type="button" id="dbgFields">List this page\'s ACF fields</button><button class="btn" type="button" id="dbgExplain">Why this match?</button></div>' +
      '<div class="nav"><button class="btn pri" type="button" id="dbgAudit">Run site audit (read-only)</button></div>' +
      '<pre id="dbgOut">' + h(state.dbg || 'Console: window.Livecrafts.help()') + '</pre></details>';
  }
  function showDbg(obj) {
    state.dbg = typeof obj === 'string' ? obj : JSON.stringify(obj, null, 2);
    state.dbgOpen = true;
    var o = $('dbgOut'); if (o) o.textContent = state.dbg;
    var d = $('dbg'); if (d) d.open = true;
    try { console.log('[Livecrafts debug]', obj); } catch (e) { /* ignore */ }
  }

  /* ================= 8. SELECTING ================= */

  function existingPatch(sel) {
    if (patches.page && patches.page[sel]) return { scope: 'page', patch: patches.page[sel] };
    if (patches.site && patches.site[sel]) return { scope: 'site', patch: patches.site[sel] };
    return null;
  }

  function select(el) {
    if (!el || el === doc.documentElement || el === host) return;
    state.el = el;
    state.sel = buildSelector(el);
    var ex = existingPatch(state.sel);
    state.styles = ex && ex.patch.styles ? JSON.parse(JSON.stringify(ex.patch.styles)) : {};
    state.text = ex && typeof ex.patch.text === 'string' ? ex.patch.text : null;
    state.hadPatch = !!ex;
    state.scope = ex ? ex.scope : 'page';
    state.needsReload = false; state.textDirty = false; state.link = null; state.linkDirty = false; state.imageId = null;
    state.res = { text: resolveText(el), link: resolveLink(el), image: resolveImage(el) };
    panel.classList.add('open');
    render(); refreshLive(); setStatus('');
    try { console.log('[Livecrafts] selected', { selector: state.sel, resolved: state.res }); } catch (e) { /* ignore */ }
  }

  function setProp(p, v) { state.styles[p] = v; refreshLive(); }
  function clearProp(p) { delete state.styles[p]; state.needsReload = true; refreshLive(); }

  /* ================= 9. EVENTS ================= */

  function setEdit(on) {
    state.on = on;
    fab.classList.toggle('on', on);
    fab.textContent = on ? '✎ Editing – click an element' : '✎ Edit';
    if (!on) { panel.classList.remove('open'); hoverBox.style.display = 'none'; selBox.style.display = 'none'; state.el = null; live.textContent = ''; }
    else { panel.classList.add('open'); render(); }
  }
  fab.addEventListener('click', function () { setEdit(!state.on); });
  $('close').addEventListener('click', function () { setEdit(false); });
  doc.addEventListener('keydown', function (e) { if (e.key === 'Escape' && state.on) setEdit(false); });

  doc.addEventListener('click', function (e) {
    if (!state.on || isInUI(e)) return;
    e.preventDefault(); e.stopPropagation();
    select(e.target);
  }, true);

  doc.addEventListener('mouseover', function (e) {
    if (!state.on || isInUI(e)) { if (!state.on) hoverBox.style.display = 'none'; return; }
    place(hoverBox, $('hoverTag'), e.target, label(e.target));
  }, true);

  bd.addEventListener('input', function (e) {
    var t = e.target;
    if (t.id === 'txt') {
      state.text = t.value; state.textDirty = true;
      if (state.el.children.length === 0) state.el.textContent = t.value;
      refreshSel(); return;
    }
    if (t.id === 'lnk') {
      state.link = t.value; state.linkDirty = true;
      var a = state.res.link && state.res.link.anchor; if (a) a.setAttribute('href', t.value);
      return;
    }
    var p = t.getAttribute('data-p'); if (!p) return;
    if (t.type === 'checkbox') { t.checked ? setProp('display', 'none') : clearProp('display'); return; }
    if (t.type === 'color') { setProp(p, t.value); return; }
    if (t.tagName === 'SELECT') { t.value ? setProp(p, t.value) : clearProp(p); return; }
    if (p === 'background-image') { var v = t.value.trim(); v ? setProp(p, 'url(' + v + ')') : clearProp(p); return; }
    if (t.type === 'number') { t.value === '' ? clearProp(p) : setProp(p, t.value + (t.getAttribute('data-u') || '')); }
  });
  bd.addEventListener('change', function (e) {
    if (e.target.id === 'scope') state.scope = e.target.value;
    else if (e.target.getAttribute('data-p')) render();
  });

  function pickMedia(cb) {
    if (!window.wp || !wp.media) { setStatus('The Media Library is not available on this page.', 'bad'); return; }
    var frame = wp.media({ title: 'Choose image', multiple: false, library: { type: 'image' } });
    frame.on('select', function () { cb(frame.state().get('selection').first().toJSON()); });
    frame.open();
  }

  /* ---- debug actions (same server functions the editor uses) ---- */

  function readSelected() {
    var el = state.el;
    if (!el) return Promise.resolve({ message: 'Select an element first.' });
    var rs = state.res, jobs = [];
    [['text', rs.text], ['link', rs.link], ['image', rs.image]].forEach(function (p) {
      if (!hasTarget(p[1])) return;
      jobs.push(apiGet('/debug/target', { targetId: tid(p[1].t) }).then(function (d) {
        var shown = p[0] === 'text' ? norm(el.textContent) : p[0] === 'link' ? p[1].anchor.getAttribute('href') : imgUrlOf(el);
        var out = { kind: p[0], target: tid(p[1].t), matchedBy: p[1].how, field: d.field, stored_in_database: d.stored_raw, formatted_by_acf: d.formatted, shown_on_page_now: shown, post: d.post, recent_changes: d.recent_changes };
        if (p[0] === 'text') out.database_equals_page = norm(plain(d.stored_raw)) === shown;
        if (p[0] === 'link') out.database_equals_page = String(d.stored_raw) === shown;
        return out;
      }));
    });
    if (!jobs.length) return Promise.resolve([{ message: 'No ACF target for this element.', why: explain(el) }]);
    return Promise.all(jobs);
  }

  bd.addEventListener('click', function (e) {
    var t = e.target;
    if (t.id === 'up') { var p = state.el.parentElement; if (p && p !== doc.body && p !== doc.documentElement) select(p); }
    else if (t.id === 'down') { if (state.el.children[0]) select(state.el.children[0]); }
    else if (t.getAttribute('data-clear')) { clearProp(t.getAttribute('data-clear')); render(); }
    else if (t.id === 'dbgRead') { showDbg('Reading from the server…'); readSelected().then(showDbg).catch(function (err) { showDbg('ERROR: ' + err.message); }); }
    else if (t.id === 'dbgFields') { showDbg('Asking the server…'); apiGet('/debug/fields', { post: pagePostId() }).then(showDbg).catch(function (err) { showDbg('ERROR: ' + err.message); }); }
    else if (t.id === 'dbgExplain') { showDbg(state.el ? explain(state.el) : 'Select an element first.'); }
    else if (t.id === 'dbgAudit') { showDbg('Running site audit…'); auditNow(true).catch(function () { /* shown in the box */ }); }
    else if (t.id === 'pickimg') {
      pickMedia(function (a) {
        var el = state.el;
        if (hasTarget(state.res.image)) {
          state.imageId = a.id;
          if (el.tagName === 'IMG') { el.removeAttribute('srcset'); el.src = a.url; }
          else { el.style.setProperty('background-image', 'url(' + a.url + ')', 'important'); }
          setStatus('Image chosen - click Save to write it to ACF.');
        } else {
          setProp('background-image', 'url(' + a.url + ')');
          render(); setStatus('Image chosen - click Save (saved as a patch).');
        }
      });
    }
  });

  /* ================= 10. SAVE + VERIFY ================= */

  // Re-load this page's HTML from the server (cache-busted) and check that the saved change is really in it.
  function verifyOnPage(checks) {
    var url = location.pathname + (location.search ? location.search + '&' : '?') + 'lcv=' + Date.now();
    return fetch(url, { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { return r.text(); }).then(function (html) {
      var pd = new DOMParser().parseFromString(html, 'text/html'), out = [];
      var css = (pd.getElementById('livecrafts-patches') || {}).textContent || '';
      var textMap = {};
      var ap = pd.getElementById('livecrafts-apply');
      if (ap) { var mm = ap.textContent.match(/var m=(\{.*?\});Object/); if (mm) { try { textMap = JSON.parse(mm[1]); } catch (e) { /* ignore */ } } }

      checks.forEach(function (c) {
        var el = c.sel ? pd.querySelector(c.sel) : null;
        if (c.kind === 'text') {
          if (!el) return out.push({ ok: null, msg: 'text: element not found in the fresh page (its selector may rely on attributes added by JavaScript)' });
          out.push(norm(el.textContent) === norm(c.expect)
            ? { ok: true, msg: 'text: page output shows the new value' }
            : { ok: false, msg: 'text: SAVED but the page output still shows "' + norm(el.textContent).slice(0, 60) + '" (cache, theme transforms the value, or JavaScript fills this element)' });
        } else if (c.kind === 'link') {
          if (!el) return out.push({ ok: null, msg: 'link: element not found in the fresh page' });
          var a = el.closest('a[href]');
          out.push(a && a.getAttribute('href') === c.expect
            ? { ok: true, msg: 'link: page output shows the new link' }
            : { ok: false, msg: 'link: SAVED but the page output has "' + (a ? a.getAttribute('href') : 'no link') + '"' });
        } else if (c.kind === 'image') {
          if (!el) return out.push({ ok: null, msg: 'image: element not found in the fresh page' });
          var src = el.tagName === 'IMG' ? (el.getAttribute('src') || '') : (el.getAttribute('style') || '');
          out.push(baseUrl(src).indexOf(baseUrl(c.expect)) > -1 || src.indexOf(baseUrl(c.expect)) > -1
            ? { ok: true, msg: 'image: page output uses the new image' }
            : { ok: null, msg: 'image: could not confirm in the HTML (the image may be set by a stylesheet) - check visually, and use Debug > Read to see the stored attachment' });
        } else if (c.kind === 'patch') {
          var rule = css.indexOf(c.sel + '{') > -1;
          var hasText = c.text === undefined || textMap[c.sel] === c.text;
          if (c.hasStyles && !rule) out.push({ ok: false, msg: 'style: patch saved but its CSS is NOT in the page output' });
          else if (!hasText) out.push({ ok: false, msg: 'text patch: saved but not present in the page output' });
          else out.push({ ok: true, msg: 'patch: present in the page output' });
        }
      });
      return out;
    }).catch(function () { return [{ ok: null, msg: 'could not re-load the page to verify' }]; });
  }

  $('save').addEventListener('click', function () {
    if (!state.el) return setStatus('Select an element first.', 'bad');
    var rs = state.res, jobs = [], kinds = [], checks = [];
    var tr = hasTarget(rs.text) ? rs.text.t : null, lr = hasTarget(rs.link) ? rs.link.t : null, ir = hasTarget(rs.image) ? rs.image.t : null;
    var sel = state.sel, anchorSel = rs.link && rs.link.anchor ? buildSelector(rs.link.anchor) : sel;

    if (tr && state.textDirty) { jobs.push(setTarget(tr, state.text)); kinds.push('text'); }
    if (lr && state.linkDirty) { jobs.push(setTarget(lr, state.link)); kinds.push('link'); }
    if (ir && state.imageId)   { jobs.push(setTarget(ir, state.imageId)); kinds.push('image'); }

    var hasStyles = Object.keys(state.styles).length > 0;
    var patchText = (!tr && state.text !== null) ? state.text : undefined;
    var bucket = state.scope === 'site' ? 'site' : 'page';
    if (hasStyles || state.hadPatch || patchText !== undefined) {
      var body = { pageKey: C.pageKey, scope: state.scope, selector: sel, styles: state.styles };
      if (patchText !== undefined) body.text = patchText;
      jobs.push(api('/save', body)); kinds.push('patch');
    }
    if (!jobs.length) return setStatus('Nothing to save yet.');

    setStatus('Saving…');
    Promise.all(jobs).then(function (results) {
      results.forEach(function (r, i) {
        var k = kinds[i];
        if (k === 'text') checks.push({ kind: 'text', sel: sel, expect: r.value });
        else if (k === 'link') checks.push({ kind: 'link', sel: anchorSel, expect: r.value });
        else if (k === 'image') checks.push({ kind: 'image', sel: sel, expect: r.url });
        else if (k === 'patch') {
          if (r.patch) patches[bucket][sel] = r.patch; else delete patches[bucket][sel];
          state.hadPatch = !!r.patch;
          if (r.patch) checks.push({ kind: 'patch', sel: sel, hasStyles: !!(r.patch.styles && Object.keys(r.patch.styles).length), text: r.patch.text });
        }
      });
      state.textDirty = false; state.linkDirty = false; state.imageId = null;
      if (state.needsReload) { setStatus('Saved – reloading…'); location.reload(); return; }
      setStatus('Saved. Verifying on the live page…');
      return verifyOnPage(checks).then(function (out) {
        var bad = out.filter(function (o) { return o.ok === false; }), unk = out.filter(function (o) { return o.ok === null; });
        var db = kinds.filter(function (k) { return k !== 'patch'; }).length ? 'Stored value read back from the database ✓\n' : '';
        var lines = out.map(function (o) { return (o.ok === true ? '✓ ' : o.ok === false ? '✗ ' : '? ') + o.msg; }).join('\n');
        setStatus((bad.length ? '⚠ CHECK THIS\n' : unk.length ? 'Saved (partly verified)\n' : 'Saved & verified ✓\n') + db + lines, bad.length ? 'bad' : unk.length ? 'warn' : 'good');
      });
    }).catch(function (err) { setStatus('NOT SAVED: ' + err.message, 'bad'); });
  });

  $('revert').addEventListener('click', function () {
    if (!state.el) return;
    api('/revert', { pageKey: C.pageKey, scope: state.scope, selector: state.sel })
      .then(function () { location.reload(); })
      .catch(function (err) { setStatus(err.message, 'bad'); });
  });

  $('undo').addEventListener('click', function () {
    api('/undo', { pageKey: C.pageKey })
      .then(function (r) { if (r.ok) location.reload(); else setStatus(r.message || 'Nothing to undo.'); })
      .catch(function (err) { setStatus(err.message, 'bad'); });
  });

  /* ================= 10b. SITE AUDIT: how well do we understand THIS page? (read-only) ================= */

  function isVisible(x) { return !!(x.offsetWidth || x.offsetHeight || (x.getClientRects && x.getClientRects().length)); }

  function detectBuilders() {
    var sigs = {
      'Elementor': '.elementor-element', 'Gutenberg blocks': '[class*="wp-block-"]', 'WPBakery': '.vc_row,.wpb_wrapper,.vc_column_container',
      'Divi': '[class*="et_pb_"]', 'Beaver Builder': '.fl-builder-content,.fl-row', 'Bricks': '[class*="brxe-"]', 'Oxygen': '.ct-section,.ct-div-block'
    };
    var out = {};
    Object.keys(sigs).forEach(function (k) { var n = doc.querySelectorAll(sigs[k]).length; if (n) out[k] = n; });
    return out;
  }

  // Every visible leaf text, link and image / background image on the page (capped).
  function collectAuditItems() {
    var items = [], all = doc.body.querySelectorAll('*');
    for (var i = 0; i < all.length && items.length < 700; i++) {
      var x = all[i];
      if (x === host || SKIP_TAG.test(x.tagName) || host.contains(x) || (x.closest && x.closest('#wpadminbar'))) continue;
      if (!x.children.length) {
        var t = norm(x.textContent);
        if (t && isVisible(x)) items.push({ kind: 'text', el: x, text: t });
      }
      if (x.tagName === 'A' && x.getAttribute('href') && isVisible(x)) items.push({ kind: 'link', el: x, text: x.getAttribute('href') });
      if (x.tagName === 'IMG') { if (isVisible(x)) items.push({ kind: 'image', el: x, text: x.currentSrc || x.src }); }
      else if (x.offsetWidth > 40 && x.offsetHeight > 20) { var b = bgUrl(x); if (b) items.push({ kind: 'image', el: x, text: b }); }
    }
    return items;
  }

  function classify(item) {
    var r = item.kind === 'text' ? resolveText(item.el) : item.kind === 'link' ? resolveLink(item.el) : resolveImage(item.el);
    if (!r) return { status: 'none' };
    if (r.ambiguous) return { status: 'ambiguous' };
    return { status: (r.how === 'exact' || r.how === 'id') ? 'linked' : 'weak', how: r.how, t: r.t };
  }

  function likelySource(hits) {
    if (!hits || !hits.length) return 'not found in the database search: probably hard-coded in theme/plugin PHP, generated by JavaScript, or computed';
    var h0 = hits[0], p = h0.where;
    if (p === 'wp_postmeta' && h0.meta_key === '_elementor_data') return 'inside Elementor JSON of post ' + h0.post + ' (an Elementor setting we do not read yet, e.g. Pro/dynamic widget)';
    if (p === 'wp_postmeta') return 'post meta "' + h0.meta_key + '" of post ' + h0.post + ' (a custom field plugin other than ACF, or an ACF sub-field)';
    if (p === 'wp_posts') return 'post ' + h0.post + ' (' + h0.post_type + ') ' + h0.column + ' - needs a post-content adapter (Gutenberg / classic / WPBakery)';
    if (p === 'wp_options') return 'site option "' + h0.option + '" (widget, site title or theme setting)';
    if (p === 'nav_menu_item') return 'navigation menu "' + h0.menu + '"';
    return p;
  }

  function pool(list, size, fn) {
    var i = 0, out = new Array(list.length);
    function worker() { if (i >= list.length) return Promise.resolve(); var k = i++; return fn(list[k], k).then(function (r) { out[k] = r; }).then(worker); }
    var ws = []; for (var n = 0; n < Math.min(size, list.length); n++) ws.push(worker());
    return Promise.all(ws).then(function () { return out; });
  }

  function runAudit(progress) {
    var say = progress || function () {};
    say('Scanning the page…');
    var items = collectAuditItems(), tally = { text: {}, link: {}, image: {} }, byStatus = function (k, s) { tally[k][s] = (tally[k][s] || 0) + 1; };
    items.forEach(function (it) { it.c = classify(it); byStatus(it.kind, it.c.status); });

    // 1) Read-only correctness check of linked TEXT / LINK targets: does the stored value equal what is shown?
    var linked = items.filter(function (it) { return (it.kind === 'text' || it.kind === 'link') && it.c.t && it.c.status !== 'none'; });
    var seen = {}, uniq = linked.filter(function (it) { var k = it.c.t.tid; if (seen[k]) return false; seen[k] = 1; return true; }).slice(0, 40);
    say('Checking ' + uniq.length + ' linked fields against the database…');
    var verifyP = pool(uniq, 4, function (it) {
      return apiGet('/debug/target', { targetId: it.c.t.tid }).then(function (d) {
        var stored = d.stored_raw, shown = it.kind === 'text' ? it.text : it.text;
        var ok = it.kind === 'text' ? norm(plain(stored)) === shown : String(stored) === shown;
        return { tid: it.c.t.tid, ok: ok, how: it.c.how, shown: shown.slice(0, 60), stored: String(stored).slice(0, 60) };
      }).catch(function (e) { return { tid: it.c.t.tid, ok: null, error: e.message }; });
    });

    // 2) Where does the NOT-linked text live in the database?
    var unlinkedText = items.filter(function (it) { return it.kind === 'text' && it.c.status === 'none'; });
    var texts = []; unlinkedText.forEach(function (it) { if (texts.indexOf(it.text) < 0 && it.text.length >= 3) texts.push(it.text); });
    say('Searching the database for ' + Math.min(texts.length, 40) + ' unlinked texts…');
    var locateP = texts.length ? api('/debug/locate', { post: pagePostId(), texts: texts.slice(0, 40) }).catch(function (e) { return { results: {}, error: e.message }; }) : Promise.resolve({ results: {} });

    return Promise.all([verifyP, locateP]).then(function (res) {
      var verified = res[0], located = res[1].results || {};
      var tt = tally.text, total = items.filter(function (i) { return i.kind === 'text'; }).length;
      var linkedN = (tt.linked || 0) + (tt.weak || 0), coverage = total ? Math.round(100 * linkedN / total) : 0;
      var bad = verified.filter(function (v) { return v.ok === false; });
      var grade = (coverage >= 85 && !bad.length) ? 'FULL' : coverage >= 40 ? 'PARTIAL' : 'LIMITED (style overlay only)';
      var unlinked = unlinkedText.slice(0, 40).map(function (it) {
        return { text: it.text.slice(0, 70), selector: buildSelector(it.el), likelySource: likelySource(located[it.text]) };
      });
      return {
        page: location.pathname, builders: detectBuilders(),
        acf: { renderedFields: TRACE.length, fieldsOnPost: SCAN.length }, elementor: { editableSettings: EL.length },
        text: { total: total, linked: tt.linked || 0, linkedByPositionOrScan: tt.weak || 0, ambiguous: tt.ambiguous || 0, notLinked: tt.none || 0 },
        links: tally.link, images: tally.image,
        coveragePercent: coverage, grade: grade,
        mappingCheck: { checked: verified.length, storedEqualsShown: verified.filter(function (v) { return v.ok === true; }).length, mismatches: bad, errors: verified.filter(function (v) { return v.ok === null; }).length },
        unlinked: unlinked
      };
    });
  }

  function auditSummary(r) {
    var b = Object.keys(r.builders).map(function (k) { return k + ' ×' + r.builders[k]; }).join(', ') || 'none detected';
    var L = [];
    L.push('PAGE ' + r.page + '   GRADE: ' + r.grade);
    L.push('Builders: ' + b);
    L.push('ACF fields rendered: ' + r.acf.renderedFields + ' · Elementor settings: ' + r.elementor.editableSettings);
    L.push('TEXT  ' + r.text.total + ' elements → linked ' + r.text.linked + ', by position/scan ' + r.text.linkedByPositionOrScan + ', ambiguous ' + r.text.ambiguous + ', NOT linked ' + r.text.notLinked + '  (coverage ' + r.coveragePercent + '%)');
    L.push('LINKS ' + JSON.stringify(r.links) + '   IMAGES ' + JSON.stringify(r.images));
    L.push('MAPPING CHECK: ' + r.mappingCheck.storedEqualsShown + '/' + r.mappingCheck.checked + ' linked fields have a stored value equal to what the page shows' + (r.mappingCheck.mismatches.length ? '  ⚠ ' + r.mappingCheck.mismatches.length + ' MISMATCH(ES) - see console' : ''));
    if (r.unlinked.length) {
      L.push('');
      L.push('NOT LINKED (first 8):');
      r.unlinked.slice(0, 8).forEach(function (u) { L.push(' • "' + u.text + '" → ' + u.likelySource); });
      if (r.unlinked.length > 8) L.push(' … ' + (r.unlinked.length - 8) + ' more in the console report');
    }
    return L.join('\n');
  }

  function auditNow(show) {
    return runAudit(function (m) { if (show) showDbg(m); }).then(function (r) {
      try { console.groupCollapsed('[Livecrafts] site audit ' + r.grade); console.log(auditSummary(r)); console.table(r.unlinked); if (r.mappingCheck.mismatches.length) console.table(r.mappingCheck.mismatches); console.log(r); console.groupEnd(); } catch (e) { /* ignore */ }
      if (show) showDbg(auditSummary(r));
      return r;
    }).catch(function (err) { if (show) showDbg('AUDIT ERROR: ' + err.message); throw err; });
  }

  /* ================= 11. CONSOLE HELPERS: window.Livecrafts ================= */

  window.Livecrafts = {
    version: '0.6.0',
    help: function () {
      var t = [
        'Livecrafts.trace()            values the page READ through ACF while rendering (render order)',
        'Livecrafts.scan()             every ACF field of this post (what the server scan found)',
        'Livecrafts.pageFields()       ask the server NOW: get all ACF fields of this page (live, from the database)',
        'Livecrafts.audit()            SITE AUDIT (read-only): what share of this page we can link to a real source, and where the rest lives',
        'Livecrafts.el()               every editable Elementor setting found on this page (widget id, setting, value)',
        'Livecrafts.elementorNow()     ask the server NOW: the same list, read from the database (_elementor_data)',
        'Livecrafts.selected()         the currently selected element + what it resolved to',
        'Livecrafts.resolve($0)        run the resolver on any element (in DevTools: select it, then use $0)',
        'Livecrafts.explain($0)        every candidate field considered for an element, and why',
        'Livecrafts.readTarget(id)     ask the server for a field: definition, stored value, formatted value, recent changes',
        'Livecrafts.readSelected()     read from the database the ACF value(s) behind the selected element'
      ].join('\n');
      console.log(t); return t;
    },
    trace: function () { console.table(TRACE.map(function (t) { return { target: tid(t), name: t.name, type: t.ftype, value: String(t.value).slice(0, 60) }; })); return TRACE; },
    scan: function () { console.table(SCAN.map(function (t) { return { target: tid(t), name: t.name, type: t.ftype, group: t.group, value: String(t.value).slice(0, 60) }; })); return SCAN; },
    pageFields: function () { return apiGet('/debug/fields', { post: pagePostId() }).then(function (d) { console.table(d.fields.map(function (t) { return { target: tid(t), name: t.name, type: t.ftype, value: String(t.value).slice(0, 60) }; })); return d; }); },
    audit: function () { return auditNow(false); },
    el: function () { console.table(EL.map(function (t) { return { target: tid(t), widget: t.widget, setting: t.name, type: t.ftype, value: String(t.value).slice(0, 50) }; })); return EL; },
    elementorNow: function () { return apiGet('/debug/elementor', { post: pagePostId() }).then(function (d) { console.table((d.items || []).map(function (t) { return { target: tid(t), widget: t.widget, setting: t.name, value: String(t.value).slice(0, 50) }; })); return d; }); },
    selected: function () { return { selector: state.sel, element: state.el, resolved: state.res, styles: state.styles }; },
    resolve: function (el) { el = el || state.el; return el ? { text: resolveText(el), link: resolveLink(el), image: resolveImage(el) } : 'no element'; },
    explain: function (el) { el = el || state.el; return el ? explain(el) : 'no element'; },
    readTarget: function (id) { return apiGet('/debug/target', { targetId: id }); },
    readSelected: readSelected
  };

  render();
  doc.body.appendChild(host);
})();
