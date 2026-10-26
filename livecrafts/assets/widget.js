/**
 * Livecrafts chat widget (runs on the live site, for logged-in editors only).
 *  - A floating button opens a panel; the chat itself is the Livecrafts backend's widget page in an iframe.
 *  - "Pick an element": hover-highlight + click on this page sends the element's details (selector, text, styles)
 *    to the chat's Quick actions panel.
 *  - After the assistant changes the site, the chat asks this page to reload; the panel re-opens where it was.
 * Messages are only accepted from the backend's origin, and only sent to it.
 */
(function () {
	"use strict";
	var C = window.LIVECRAFTS_WIDGET;
	if (!C || !C.backend || window.__livecraftsWidget || window.name === "lcw-probe") return;
	window.__livecraftsWidget = true;

	var backendOrigin;
	try { backendOrigin = new URL(C.backend).origin; } catch (e) { return; }
	var KEY = "lcw:" + C.siteUrl;
	var state = {};
	try { state = JSON.parse(sessionStorage.getItem(KEY) || "{}"); } catch (e) { state = {}; }
	var save = function () { try { sessionStorage.setItem(KEY, JSON.stringify(state)); } catch (e) { /* private mode */ } };

	// ------------------------------------------------------------------ DOM
	var root = document.createElement("div");
	root.className = "lcw-root lcw-" + (C.position === "left" ? "left" : "right");
	root.style.setProperty("--lcw-accent", C.accent || "#5b5bd6");
	root.setAttribute("data-livecrafts", "widget");

	var initial = String(C.botName || "L").trim().charAt(0).toUpperCase() || "L";
	var btn = document.createElement("button");
	btn.type = "button";
	btn.className = "lcw-launcher";
	btn.setAttribute("aria-label", "Open " + (C.botName || "assistant"));
	btn.innerHTML = '<span class="lcw-launcher-ico" aria-hidden="true">' + escapeHtml(initial) + '</span><span class="lcw-launcher-close" aria-hidden="true">&times;</span>';

	var panel = document.createElement("div");
	panel.className = "lcw-panel";
	panel.setAttribute("role", "dialog");
	panel.setAttribute("aria-label", C.botName || "Assistant");
	panel.hidden = true;

	var frame = null;
	var hint = document.createElement("div");
	hint.className = "lcw-pickhint";
	hint.setAttribute("data-livecrafts", "pickhint");
	hint.innerHTML = "<b>Pick an element</b> - click anything on the page. <kbd>Esc</kbd> to cancel.";
	hint.hidden = true;

	var box = document.createElement("div");
	box.className = "lcw-hoverbox";
	box.setAttribute("data-livecrafts", "hoverbox");
	box.hidden = true;
	var tag = document.createElement("span");
	tag.className = "lcw-hovertag";
	box.appendChild(tag);

	root.appendChild(panel);
	root.appendChild(btn);
	document.body.appendChild(root);
	document.body.appendChild(box);
	document.body.appendChild(hint);
	box.style.setProperty("--lcw-accent", C.accent || "#5b5bd6");
	document.documentElement.style.setProperty("--lcw-accent", C.accent || "#5b5bd6");

	function frameUrl() {
		var cfg = {
			siteUrl: C.siteUrl, pageUrl: location.href.split("#")[0], botName: C.botName, welcome: C.welcome, accent: C.accent,
			approvalMode: C.approvalMode, user: C.user || "", parentOrigin: location.origin, tab: state.tab || "chat",
			widgetVersion: C.version || "0.9.0", pageKey: C.pageKey || "", postId: C.postId || 0,
			// who the person is, signed by this site (the backend credits every change to them)
			widgetToken: C.widgetToken || "", view: C.view || "draft", drafts: Number(C.drafts) || 0, canDeploy: !!C.canDeploy,
			canUpload: !!C.canUpload, maxUpload: Number(C.maxUpload) || 0,
		};
		return C.backend.replace(/\/+$/, "") + "/?embed=1&v=" + encodeURIComponent(C.version || "") + "#cfg=" + encodeURIComponent(JSON.stringify(cfg));
	}

	function open() {
		if (!frame) {
			frame = document.createElement("iframe");
			frame.className = "lcw-frame";
			frame.title = C.botName || "Assistant";
			// "local-network-access": Chrome/Edge only let a public site load an app on this computer after the person allows it.
			// microphone + autoplay: the voice mode listens and answers aloud (the browser still asks the person first).
			frame.allow = "clipboard-write; local-network-access; local-network; microphone; autoplay";
			frame.src = frameUrl();
			var isLocal = /^https?:\/\/(localhost|127\.0\.0\.1|\[::1\])(:\d+)?$/i.test(backendOrigin);
			var err = document.createElement("div");
			err.className = "lcw-offline";
			err.setAttribute("role", "alert");
			err.innerHTML = "<b>Livecrafts is not running</b>" +
				(isLocal
					? "<p>The Livecrafts app runs on one computer and this widget only works on that computer.</p>" +
					  "<ol><li>On that computer, double-click <b>start-livecrafts.bat</b> (in the <code>livecrafts-backend</code> folder) and keep its black window open.</li>" +
					  "<li>Wait until it says <code>Starting at " + escapeHtml(C.backend) + "</code>.</li>" +
					  "<li>If the browser asks to <b>“access other apps and services on this device”</b>, click <b>Allow</b>. (Closed it? Click the icon left of the address bar → Site settings → Local network access → Allow.)</li></ol>" +
					  "<p class=\"lcw-off-note\">This panel reconnects by itself as soon as the app is running.</p>"
					: "<p>The Livecrafts app at <code>" + escapeHtml(C.backend) + "</code> does not answer. Check that it is online, then try again.</p>") +
				'<button type="button" class="lcw-retry">Try again now</button>';
			err.hidden = true;
			panel.appendChild(err);
			panel.appendChild(frame);
			// The chat says "lc:ready" when it loaded; if it never does, the app is not running (or blocked).
			// A quick probe shows the message at once when nothing listens, then keeps checking and reconnects by itself.
			var ready = false, probeTimer = null;
			var probe = function () {
				return fetch(backendOrigin + "/health", { mode: "no-cors", cache: "no-store" }).then(function () { return true; }, function () { return false; });
			};
			var reconnect = function () {
				clearInterval(probeTimer);
				probeTimer = setInterval(function () {
					if (ready) { clearInterval(probeTimer); return; }
					probe().then(function (up) { if (up && !ready) { frame.src = frameUrl(); setTimeout(function () { if (ready) err.hidden = true; }, 3000); } });
				}, 5000);
			};
			var showError = function () { if (!ready) { err.hidden = false; reconnect(); } };
			var watch = function () {
				probe().then(function (up) { if (!up) setTimeout(showError, 1500); });
				setTimeout(showError, 9000);
			};
			watch();
			err.querySelector(".lcw-retry").addEventListener("click", function () { err.hidden = true; frame.src = frameUrl(); watch(); });
			window.addEventListener("message", function (e) { if (e.origin === backendOrigin && e.data && e.data.type === "lc:ready") { ready = true; err.hidden = true; clearInterval(probeTimer); } });
		}
		panel.hidden = false;
		root.classList.add("lcw-open");
		state.open = true; save();
	}
	function close() {
		panel.hidden = true;
		root.classList.remove("lcw-open");
		state.open = false; save();
		stopPick();
	}
	btn.addEventListener("click", function () { panel.hidden ? open() : close(); });

	function send(msg) { if (frame && frame.contentWindow) frame.contentWindow.postMessage(msg, backendOrigin); }

	// ------------------------------------------------------------------ the site's content width (Section Gallery)
	/**
	 * Themes keep their content in a centred box ("container", "wrap", "inner"...) with a max-width and side padding.
	 * Find the one the page uses most - the header's counts more, it is the line everything else follows - and report
	 * its full width and side padding, so gallery sections can use exactly the same box. null when there is none.
	 */
	function measureLayout() {
		try {
			var vw = document.documentElement.clientWidth, tally = {}, byKey = {}, best = null;
			var els = document.querySelectorAll("body *");
			for (var i = 0; i < els.length && i < 6000; i++) {
				var el = els[i];
				if (el.closest(".lcs, .livecrafts-section, .lcw-root, #wpadminbar, script, style, svg")) continue;
				var cs = getComputedStyle(el);
				var mw = parseFloat(cs.maxWidth);
				if (!(mw >= 600 && mw <= 2600)) continue;
				var r = el.getBoundingClientRect();
				if (r.width < 300 || r.height < 10) continue;
				if (Math.abs(r.left - (vw - r.right)) > 2) continue; // centred on the page
				var pl = parseFloat(cs.paddingLeft) || 0, pr = parseFloat(cs.paddingRight) || 0;
				var full = cs.boxSizing === "border-box" ? mw : mw + pl + pr;
				var key = Math.round(full) + "|" + Math.round(Math.min(pl, pr));
				tally[key] = (tally[key] || 0) + (el.closest("header, .site-header, #masthead") ? 3 : 1);
				(byKey[key] = byKey[key] || []).push(el);
			}
			Object.keys(tally).forEach(function (k) { if (!best || tally[k] > tally[best]) best = k; });
			if (!best) { var only = measureBrand(); return only ? { brand: only } : null; } // no content box, still a brand
			var parts = best.split("|");
			return { container: Number(parts[0]), gutter: Number(parts[1]), viewport: vw, className: containerClass(byKey[best] || []), brand: measureBrand() };
		} catch (e) { return null; }
	}

	/**
	 * The site's brand colour, from its own buttons: the filled background its buttons use most (header buttons count
	 * double), the text colour on it, and their corner radius. A colourful fill beats a grey or black one; a site whose
	 * buttons are all neutral still gets its dark button colour. null when the page has no filled buttons at all.
	 */
	function measureBrand() {
		try {
			var tally = {}, info = {}, sel = "a, button, input[type=submit], input[type=button], [class*=btn], [class*=button], [class*=cta]";
			var els = document.querySelectorAll(sel);
			for (var i = 0; i < els.length && i < 1500; i++) {
				var el = els[i];
				if (el.closest(".lcs, .livecrafts-section, .lcw-root, #wpadminbar")) continue;
				var cs = getComputedStyle(el);
				if (cs.display === "none" || cs.visibility === "hidden") continue;
				var bg = rgba(cs.backgroundColor);
				if (!bg || bg[3] < 0.9 || cs.backgroundImage !== "none") continue;
				var r = el.getBoundingClientRect();
				if (r.height > 120 || r.width > 600) continue; // a coloured band or card, not a button
				var max = Math.max(bg[0], bg[1], bg[2]), min = Math.min(bg[0], bg[1], bg[2]);
				if (max - min < 24 && max > 200) continue; // white / light grey: the page, not the brand
				var hex = toHex(bg), w = el.closest("header, .site-header, #masthead") ? 2 : 1;
				tally[hex] = (tally[hex] || 0) + w * (max - min < 24 ? 0.01 : 1); // grey/black only when nothing else
				if (!info[hex]) { var fg = rgba(cs.color); info[hex] = { on: fg ? toHex(fg) : "", radius: Math.round(parseFloat(cs.borderTopLeftRadius) || 0) }; }
			}
			var best = null;
			Object.keys(tally).forEach(function (k) { if (!best || tally[k] > tally[best]) best = k; });
			return best ? { color: best, on: info[best].on, radius: info[best].radius } : null;
		} catch (e) { return null; }
	}
	function rgba(v) {
		var m = String(v).match(/rgba?\(\s*(\d+)[,\s]+(\d+)[,\s]+(\d+)(?:[,\s/]+([\d.]+))?/);
		return m ? [Number(m[1]), Number(m[2]), Number(m[3]), m[4] === undefined ? 1 : Number(m[4])] : null;
	}
	function toHex(c) { return "#" + c.slice(0, 3).map(function (n) { return ("0" + Math.round(n).toString(16)).slice(-2); }).join(""); }

	/**
	 * The theme's own class for that box ("wrap", "container"...), when the class ALONE makes it: a bare probe with just
	 * that class, at the top of the body, gets the same width and padding and adds no background or border. A section
	 * wearing it then follows the theme at every screen size - its own breakpoints, not a guess from one width.
	 */
	function containerClass(els) {
		var counts = {};
		els.forEach(function (el) {
			Array.prototype.forEach.call(el.classList, function (c) {
				if (/^[A-Za-z_][\w-]{0,40}$/.test(c) && !/^(lcw|lcs|wp-|elementor|e-con|is-|has-|js-|active|open)/.test(c)) counts[c] = (counts[c] || 0) + 1;
			});
		});
		var want = els[0] ? boxOf(els[0]) : null, names = Object.keys(counts).sort(function (a, b) { return counts[b] - counts[a]; });
		if (!want) return null;
		var holder = document.createElement("div");
		holder.setAttribute("aria-hidden", "true");
		holder.style.cssText = "position:absolute;left:0;top:0;width:100%;height:0;overflow:hidden;visibility:hidden;pointer-events:none";
		document.body.appendChild(holder);
		try {
			for (var i = 0; i < names.length && i < 8; i++) {
				var probe = document.createElement("div");
				probe.className = names[i];
				holder.appendChild(probe);
				var got = boxOf(probe), cs = getComputedStyle(probe); // read before the probe leaves the page
				var plain = /^(block|flow-root)$/.test(cs.display) && cs.backgroundImage === "none" && /rgba\(0, 0, 0, 0\)|transparent/.test(cs.backgroundColor) && parseFloat(cs.borderLeftWidth) === 0;
				holder.removeChild(probe);
				if (plain && got && got.max === want.max && got.pad === want.pad) return names[i];
			}
		} finally { document.body.removeChild(holder); }
		return null;
	}
	function boxOf(el) {
		var cs = getComputedStyle(el), mw = parseFloat(cs.maxWidth);
		if (!(mw > 0)) return null;
		return { max: Math.round(mw), pad: Math.round(parseFloat(cs.paddingLeft) || 0) + "/" + Math.round(parseFloat(cs.paddingRight) || 0) };
	}

	// ------------------------------------------------------------------ element picker
	var picking = false, hovered = null;
	function startPick() {
		picking = true; hint.hidden = false; document.documentElement.classList.add("lcw-picking");
		document.addEventListener("mousemove", onMove, true);
		document.addEventListener("click", onClick, true);
		document.addEventListener("keydown", onKey, true);
	}
	function stopPick() {
		if (!picking) return;
		picking = false; hint.hidden = true; box.hidden = true; hovered = null; document.documentElement.classList.remove("lcw-picking");
		document.removeEventListener("mousemove", onMove, true);
		document.removeEventListener("click", onClick, true);
		document.removeEventListener("keydown", onKey, true);
		send({ type: "lc:pick-ended" });
	}
	function inWidget(el) { return !!(el && el.closest && (el.closest(CHROME) || el === box || el === hint || box.contains(el))); }
	function onMove(e) {
		var el = targetAt(e.clientX, e.clientY);
		if (!el) { box.hidden = true; hovered = null; return; }
		hovered = el;
		outline(el);
	}
	/**
	 * What a click at this point means. Usually the top element; but an EMPTY layer on top of a picture (a link layer, a
	 * gradient overlay, a hover effect) would swallow the click - then the picture underneath is meant.
	 */
	function targetAt(x, y) {
		var stack = document.elementsFromPoint ? document.elementsFromPoint(x, y) : [document.elementFromPoint(x, y)];
		var top = null, i;
		for (i = 0; i < stack.length; i++) { if (stack[i] && !inWidget(stack[i])) { top = stack[i]; break; } }
		if (!top || top === document.body || top === document.documentElement) return null;
		if (top.tagName === "IMG" || (top.querySelector && top.querySelector("img")) || (top.innerText || "").trim() !== "") return top;
		for (i = 0; i < stack.length; i++) {
			var el = stack[i];
			if (!el || el === top || inWidget(el)) continue;
			if (el === document.body || el === document.documentElement) break;
			if (el.tagName === "IMG") return el;
		}
		return top;
	}
	function outline(el) {
		var r = el.getBoundingClientRect();
		box.hidden = false;
		box.style.top = r.top + "px"; box.style.left = r.left + "px"; box.style.width = r.width + "px"; box.style.height = r.height + "px";
		tag.textContent = describe(el);
	}
	function onClick(e) {
		if (inWidget(e.target)) return;
		e.preventDefault(); e.stopPropagation();
		var el = targetAt(e.clientX, e.clientY) || hovered || e.target;
		stopPick();
		select(el);
	}
	function onKey(e) { if (e.key === "Escape") { e.preventDefault(); stopPick(); } }

	var selected = null;
	function select(el) {
		selected = el;
		var snap = snapshot(el); // before our highlight class is added
		el.classList.add("lcw-selected");
		setTimeout(function () { el.classList.remove("lcw-selected"); }, 1600);
		send({ type: "lc:selected", element: snap });
		// What is it, and what can be done to it? WordPress answers from the element's real source - no AI involved.
		resolve(snap).then(function (r) { send({ type: "lc:resolved", selector: snap.selector, resolved: r }); },
			function (e) { send({ type: "lc:resolved", selector: snap.selector, error: e.message }); });
	}
	function resolve(snap) {
		return wp("POST", "resolve", {
			page: Number(C.postId) || 0, device: deviceOf(window.innerWidth), elementor: snap.elementor ? { doc: snap.elementor.post, id: snap.elementor.id } : null,
			block: snap.block || "", divi: snap.divi || "", menuItem: snap.menuItem || 0, tag: snap.tag, text: snap.rawText || snap.text,
			imageSrc: snap.image ? snap.image.src : "", bgImage: snap.bgImage || "", href: snap.link || "", selector: snap.selector,
		});
	}
	/** The block of the page content this element belongs to ("<post>:<path>", only on pages Livecrafts marked for editors). */
	function blockOf(el) { var b = el.closest && el.closest("[data-lc-block]"); return b ? b.getAttribute("data-lc-block") : ""; }
	/**
	 * The Divi module this element belongs to ("<post>:<path>"). Divi has no id of its own on the page, so for editors
	 * Livecrafts gives every module the class lcd-<path with dashes> (see divi.php). The NEAREST one wins, so a click
	 * inside a Text module finds that module and not the row or section around it.
	 */
	function diviOf(el) {
		var d = el.closest && el.closest("[class*='lcd-']");
		var m = d && /(?:^|\s)lcd-(\d+(?:-\d+)*)(?:\s|$)/.exec(d.className || "");
		return m ? (Number(C.postId) || 0) + ":" + m[1].split("-").join(".") : "";
	}
	/** The classic menu link this element belongs to (WordPress puts menu-item-<id> on each item). */
	function menuItemOf(el) {
		var li = el.closest && el.closest("li[class*='menu-item-']");
		var m = li && /(?:^|\s)menu-item-(\d+)(?:\s|$)/.exec(li.className);
		return m ? Number(m[1]) : 0;
	}

	function describe(el) {
		var s = el.tagName.toLowerCase();
		if (el.id) s += "#" + el.id;
		else {
			var cls = Array.prototype.filter.call(el.classList, function (c) { return c.indexOf("lcw-") !== 0; }).slice(0, 2);
			if (cls.length) s += "." + cls.join(".");
		}
		return s;
	}

	var PROPS = ["font-family", "font-size", "font-weight", "line-height", "letter-spacing", "text-transform", "text-align", "color", "background-color",
		"padding-top", "padding-right", "padding-bottom", "padding-left", "margin-top", "margin-bottom", "width", "height", "max-width", "border-radius", "display", "gap", "justify-content", "align-items"];

	function snapshot(el) {
		var cs = getComputedStyle(el), styles = {};
		for (var i = 0; i < PROPS.length; i++) styles[PROPS[i]] = cs.getPropertyValue(PROPS[i]);
		var r = el.getBoundingClientRect();
		var img = el.tagName === "IMG" ? el : el.querySelector && el.querySelector("img");
		var similar = similarOf(el);
		var bg = (cs.getPropertyValue("background-image").match(/url\(["']?([^"')]+)["']?\)/) || [])[1] || "";
		return {
			selector: uniqueSelector(el), label: describe(el), tag: el.tagName.toLowerCase(),
			id: el.id || "", classes: Array.prototype.slice.call(el.classList).filter(function (c) { return c.indexOf("lcw-") !== 0; }).join(" "),
			text: (el.innerText || el.textContent || "").replace(/\s+/g, " ").trim().slice(0, 400),
			html: el.outerHTML.replace(/\s+/g, " ").slice(0, 1200),
			image: img ? { src: img.currentSrc || img.src, alt: img.alt || "", selector: uniqueSelector(img) } : null, bgImage: bg,
			rawText: (el.textContent || "").replace(/\s+/g, " ").trim().slice(0, 2000),
			link: el.closest && el.closest("a") ? el.closest("a").href : "",
			styles: styles, rect: { width: Math.round(r.width), height: Math.round(r.height) },
			section: sectionOf(el), pageUrl: location.href.split("#")[0].replace(/[?&]lcv=\d+/, ""), viewport: window.innerWidth,
			pageKey: C.pageKey || "", elementor: elementorOf(el), hasChildren: hasMarkup(el), block: blockOf(el), divi: diviOf(el), menuItem: menuItemOf(el),
			similarSelector: similar.selector, similarCount: similar.count,
		};
	}
	/** The Elementor widget an element belongs to (its id + the page it is stored on) - lets text/images be edited at the source. */
	function elementorOf(el) {
		var w = el.closest && el.closest(".elementor-element[data-id]");
		var doc = el.closest && el.closest("[data-elementor-id]");
		if (!w || !doc) return null;
		return { post: Number(doc.getAttribute("data-elementor-id")) || 0, id: w.getAttribute("data-id"), widget: w.getAttribute("data-widget_type") || w.getAttribute("data-element_type") || "" };
	}
	/** True when the element holds more than plain text (links, icons, spans ...) - plain-text overlays are refused then. */
	function hasMarkup(el) { return Array.prototype.some.call(el.children, function (c) { return c.tagName !== "BR"; }); }
	/** tag + classes, without per-instance classes - "all similar elements". */
	function similarOf(el) {
		var cls = Array.prototype.filter.call(el.classList, function (c) { return c.indexOf("lcw-") !== 0 && !/^elementor-element-[a-z0-9]+$/.test(c) && /^[A-Za-z_-][\w-]*$/.test(c); }).slice(0, 3);
		var sel = el.tagName.toLowerCase() + (cls.length ? "." + cls.join(".") : "");
		var n = 0; try { n = document.querySelectorAll(sel).length; } catch (e) { n = 0; }
		return { selector: sel, count: n };
	}
	/** The nearest landmark/section, so the assistant knows where on the page the element is. */
	function sectionOf(el) {
		var s = el.closest && el.closest("header, footer, nav, main section, section, aside, [class*='hero'], [class*='section']");
		return s && s !== el ? describe(s) : "";
	}
	function uniqueSelector(el) {
		if (el.id && /^[A-Za-z][\w-]*$/.test(el.id) && document.querySelectorAll("#" + el.id).length === 1) return "#" + el.id;
		var parts = [], cur = el;
		while (cur && cur.nodeType === 1 && cur !== document.body && parts.length < 5) {
			var part = cur.tagName.toLowerCase();
			var cls = Array.prototype.slice.call(cur.classList).filter(function (c) { return /^[A-Za-z_-][\w-]*$/.test(c) && c.indexOf("lcw-") !== 0; }).slice(0, 2);
			if (cls.length) part += "." + cls.join(".");
			var parent = cur.parentElement;
			if (parent) {
				var same = Array.prototype.filter.call(parent.children, function (c) { return c.tagName === cur.tagName; });
				if (same.length > 1 && !cls.length) part += ":nth-of-type(" + (same.indexOf(cur) + 1) + ")";
			}
			parts.unshift(part);
			var sel = parts.join(" > ");
			try { if (document.querySelectorAll(sel).length === 1) return sel; } catch (e) { /* keep going */ }
			cur = parent;
		}
		return parts.join(" > ");
	}

	// ------------------------------------------------------------------ messages from the chat
	window.addEventListener("message", function (e) {
		if (e.origin !== backendOrigin || !e.data || typeof e.data.type !== "string") return;
		var d = e.data;
		if (d.type === "lc:pick") { startPick(); }
		else if (d.type === "lc:cancel-pick") { stopPick(); }
		else if (d.type === "lc:close") { close(); }
		// The Section Gallery: the panel becomes a large popup over the dimmed page while the gallery is open (0.24).
		else if (d.type === "lc:wide") { root.classList.toggle("lcw-wide", !!d.on); }
		// The Section Gallery: how wide this site's content is, so a section lines up with the theme (0.24.3; its container class 0.24.4).
		else if (d.type === "lc:measure") { send({ type: "lc:measure-result", id: d.id, layout: measureLayout() }); }
		else if (d.type === "lc:tab") { state.tab = d.tab; save(); }
		else if (d.type === "lc:highlight" && d.selector) {
			try { var el = document.querySelector(d.selector); if (el) { el.scrollIntoView({ behavior: "smooth", block: "center" }); outline(el); setTimeout(function () { if (!picking) box.hidden = true; }, 1800); } } catch (x) { /* bad selector */ }
		}
		else if (d.type === "lc:soft-refresh") { softRefresh(d.selector); }
		else if (d.type === "lc:reload") {
			// show the change like Ctrl+Shift+R: refresh this site's cached CSS/JS first, then reload; keep the panel open
			state.open = true; state.tab = d.tab || state.tab; save();
			hardReload();
		}
		else if (d.type === "lc:eyes" && d.id) { handleEyes(d); }
		else if (d.type === "lc:preview") { preview(d.selector, d.styles || {}); }
		else if (d.type === "lc:preview-clear") { clearPreview(); }
		else if (d.type === "lc:edit-text" && d.selector) { editText(d.selector); }
		else if (d.type === "lc:navigate" && d.url) {
			try { var t = new URL(d.url, location.href); if (t.origin === location.origin) { state.open = true; save(); location.href = t.href; } } catch (x) { /* ignore */ }
		}
	});

	// ------------------------------------------------------------------ live preview of manual style edits (nothing saved)
	var previewed = [];
	function clearPreview() {
		previewed.forEach(function (p) { p.el.setAttribute("style", p.style); if (!p.style) p.el.removeAttribute("style"); });
		previewed = [];
	}
	function preview(selector, styles) {
		clearPreview();
		var els = [];
		try { els = Array.prototype.slice.call(document.querySelectorAll(selector), 0, 200); } catch (e) { return; }
		els.forEach(function (el) {
			if (el.closest(CHROME)) return;
			previewed.push({ el: el, style: el.getAttribute("style") || "" });
			Object.keys(styles).forEach(function (k) { if (styles[k]) el.style.setProperty(k, styles[k], "important"); });
		});
	}

	// ------------------------------------------------------------------ inline text editing on the page (manual mode)
	var editing = null;
	function editText(selector) {
		stopEditing(false);
		var el; try { el = document.querySelector(selector); } catch (e) { el = null; }
		if (!el) { send({ type: "lc:text-error", error: "That element is no longer on the page." }); return; }
		// textContent = the real text (innerText would return CSS-uppercased text and save it in capitals)
		var oldText = (el.textContent || "").replace(/\s+/g, " ").trim();
		var bar = document.createElement("div");
		bar.className = "lcw-editbar"; bar.setAttribute("data-livecrafts", "editbar");
		bar.innerHTML = '<span>Editing text</span><button type="button" class="lcw-eb-cancel">Cancel</button><button type="button" class="lcw-eb-save">Save</button>';
		document.body.appendChild(bar);
		var r = el.getBoundingClientRect();
		bar.style.top = Math.max(8, r.top - 46) + "px"; bar.style.left = Math.max(8, Math.min(r.left, window.innerWidth - 260)) + "px";
		editing = { el: el, oldText: oldText, oldHtml: el.innerHTML, bar: bar, selector: selector };
		try { el.contentEditable = "plaintext-only"; } catch (e) { el.contentEditable = "true"; }
		if (el.contentEditable !== "plaintext-only") el.contentEditable = "true";
		el.classList.add("lcw-editing");
		el.focus();
		var range = document.createRange(); range.selectNodeContents(el); var sel = window.getSelection(); sel.removeAllRanges(); sel.addRange(range);
		el.addEventListener("keydown", onEditKey);
		send({ type: "lc:edit-started" });
		bar.querySelector(".lcw-eb-save").addEventListener("click", function () { stopEditing(true); });
		bar.querySelector(".lcw-eb-cancel").addEventListener("click", function () { stopEditing(false); });
	}
	function onEditKey(e) {
		if (e.key === "Escape") { e.preventDefault(); stopEditing(false); }
		else if (e.key === "Enter" && !e.shiftKey) { e.preventDefault(); stopEditing(true); }
	}
	function stopEditing(save) {
		if (!editing) return;
		var ed = editing; editing = null;
		ed.el.removeEventListener("keydown", onEditKey);
		ed.el.contentEditable = "false"; ed.el.removeAttribute("contenteditable");
		ed.el.classList.remove("lcw-editing");
		ed.bar.remove();
		var newText = (ed.el.textContent || "").replace(/\s+/g, " ").trim();
		if (!save || newText === ed.oldText) { ed.el.innerHTML = ed.oldHtml; send({ type: "lc:text-cancelled" }); return; }
		send({ type: "lc:text-edited", selector: ed.selector, oldText: ed.oldText, newText: newText });
	}

	/**
	 * Show what changed WITHOUT reloading the page.
	 *
	 * The page is fetched again in the background and compared with what is on screen, section by section; only the
	 * parts that actually differ are swapped in. Nothing else moves: no white flash, no lost scroll position, no
	 * menu or slider re-initialising. If anything about it fails, the person is offered a normal refresh instead -
	 * it never leaves a half-updated page.
	 */
	function softRefresh(selector) {
		var url = new URL(location.href);
		url.searchParams.set("lcfresh", String(Date.now()));
		return fetch(url.href, { credentials: "same-origin", cache: "reload" })
			.then(function (r) { return r.ok ? r.text() : Promise.reject(new Error("HTTP " + r.status)); })
			.then(function (html) {
				// Patching in place is only worth it on a page small enough to compare quickly. Comparing blocks means
				// serialising each one, and on a builder page a single section can be megabytes - doing that every few
				// seconds is what froze the tab. Past this size the person is offered a refresh instead.
				if (html.length > 1500000) throw new Error("page too large to patch in place");
				var doc = new DOMParser().parseFromString(html, "text/html");
				var fresh = doc.body, live = document.body;
				if (!fresh || !live) throw new Error("no body");

				/**
				 * The new section may need a stylesheet this page has never loaded.
				 *
				 * The template library's CSS is enqueued only on pages that actually render a component, so the FIRST
				 * section placed on a page arrives with its stylesheet missing from the document - and patching the
				 * markup in alone put unstyled markup on screen: the broken page that needed two manual refreshes to
				 * come right. So the head is brought up to date first, and the body is not touched until the new
				 * stylesheets have actually loaded.
				 */
				return syncHead(doc).then(function () {
					var a = Array.prototype.filter.call(live.children, keepable);
					var b = Array.prototype.filter.call(fresh.children, keepable);
					if (a.length > 80 || b.length > 80) throw new Error("too many blocks to patch in place");
					var changed = 0;
					if (a.length === b.length) {
						// same shape: replace only the blocks whose markup differs. The cheap signature (tag, id, class,
						// child count, text length) is checked first, so the expensive comparison runs on few blocks.
						var swap = [];
						for (var i = 0; i < a.length; i++) {
							if (sig(a[i]) === sig(b[i])) continue;
							if (a[i].outerHTML === b[i].outerHTML) continue;
							swap.push(i);
						}
						// Markup that brings its own script cannot be patched in: a cloned script never runs, so the
						// slider, menu or accordion inside it would sit there dead. That page gets a real reload instead.
						if (swap.some(function (i) { return hasScript(b[i]); })) throw new Error("the new markup brings scripts of its own");
						swap.forEach(function (i) { a[i].replaceWith(b[i].cloneNode(true)); changed++; });
					} else {
						// A section was added or removed. Rebuilding the blocks re-creates every one of them, so anything
						// the theme's JavaScript had set up on them (sticky header, slider, menu, accordion) is gone and
						// does not come back without a reload - which is exactly what looked broken. Only a page whose
						// blocks carry no scripts at all is safe to rebuild in place; anything else is reloaded.
						if (Array.prototype.some.call(fresh.children, hasScript) || Array.prototype.some.call(live.children, hasScript)) {
							throw new Error("a section was added and the page runs scripts of its own");
						}
						for (var j = 0; j < a.length; j++) a[j].remove();
						var at = live.firstChild;
						for (var k = 0; k < b.length; k++) live.insertBefore(b[k].cloneNode(true), at);
						changed = Math.abs(b.length - a.length) + 1;
					}
					if (changed && selector) {
						var el = document.querySelector(selector);
						if (el) { el.scrollIntoView({ behavior: "smooth", block: "center" }); outline(el); }
					}
					send({ type: "lc:refreshed", changed: changed, soft: true });
					return changed;
				});
			})
			.catch(function (e) {
				/**
				 * Could not patch it in place - so show the change the sure way, by reloading, instead of leaving a
				 * half-updated page on screen for the person to refresh themselves (twice, as it turned out: the plain
				 * refresh they reached for was served the very page they already had).
				 */
				send({ type: "lc:refreshed", changed: 0, soft: false, reloading: true, error: e && e.message ? e.message : String(e) });
				state.open = true; save(); // the panel comes back open, on the same tab, exactly as after lc:reload
				hardReload();
			});
	}

	/** Does this block contain a script? Cloned scripts never run, so such a block cannot be patched in. */
	function hasScript(el) {
		return el.tagName === "SCRIPT" || !!el.querySelector("script");
	}

	/** A stylesheet or script URL without our own cache-busting parameters, so the same file is recognised as the same. */
	function assetKey(u) {
		try {
			var x = new URL(u, location.href);
			x.searchParams.delete("lcfresh"); x.searchParams.delete("lcv");
			return x.origin + x.pathname + x.search;
		} catch (e) { return String(u); }
	}

	/**
	 * Bring the head up to date with the page as it is served now, and resolve once the new stylesheets have loaded.
	 *
	 * Stylesheets and inline style blocks are added, never removed: taking one away mid-patch would unstyle the page for
	 * as long as the patch takes, and an unused stylesheet costs nothing. A newly added script src cannot be dealt with
	 * this way - it has to run in order, from the top of a fresh document - so that page is reloaded instead.
	 */
	function syncHead(doc) {
		var head = doc.head, live = document.head;
		if (!head || !live) return Promise.resolve();

		var haveScripts = {};
		Array.prototype.forEach.call(document.querySelectorAll("script[src]"), function (n) { haveScripts[assetKey(n.src)] = 1; });
		var newScript = Array.prototype.some.call(doc.querySelectorAll("script[src]"), function (n) {
			var src = n.getAttribute("src") || "";
			return !!src && !haveScripts[assetKey(src)];
		});
		if (newScript) return Promise.reject(new Error("the page now loads scripts it did not have"));

		var haveStyles = {};
		Array.prototype.forEach.call(live.querySelectorAll('link[rel~="stylesheet"][href]'), function (n) { haveStyles[assetKey(n.href)] = 1; });
		var waits = [];
		Array.prototype.forEach.call(head.querySelectorAll('link[rel~="stylesheet"][href]'), function (n) {
			var href = n.getAttribute("href") || "";
			if (!href || haveStyles[assetKey(href)]) return;
			haveStyles[assetKey(href)] = 1;
			var link = document.createElement("link");
			link.rel = "stylesheet";
			link.href = new URL(href, location.href).href;
			if (n.media) link.media = n.media;
			link.setAttribute("data-lc-synced", "1");
			waits.push(new Promise(function (done) {
				link.addEventListener("load", done);
				link.addEventListener("error", done);
				setTimeout(done, 3000); // never hold the page back longer than this
			}));
			live.appendChild(link);
		});

		// Inline style the page did not have (the library's brand variables and a section's "fit" arrive this way).
		var haveInline = {};
		Array.prototype.forEach.call(live.querySelectorAll("style"), function (n) { haveInline[(n.textContent || "").trim()] = 1; });
		Array.prototype.forEach.call(head.querySelectorAll("style"), function (n) {
			var css = (n.textContent || "").trim();
			if (!css || haveInline[css]) return;
			haveInline[css] = 1;
			var s = document.createElement("style");
			s.setAttribute("data-lc-synced", "1");
			s.textContent = css;
			live.appendChild(s);
		});

		return waits.length ? Promise.all(waits).then(function () { /* the new stylesheets are in */ }) : Promise.resolve();
	}

	/** A cheap fingerprint of a block: enough to tell "certainly unchanged" without serialising the whole subtree. */
	function sig(el) {
		return el.tagName + "|" + (el.id || "") + "|" + (el.className || "") + "|" +
			el.childElementCount + "|" + (el.textContent ? el.textContent.length : 0);
	}

	/** Our own chrome is never touched by a soft refresh (the chat, the draft bar, the picker overlays). */
	function keepable(el) {
		return !el.hasAttribute("data-livecrafts") && el.tagName !== "SCRIPT" && el.tagName !== "NOSCRIPT" && el.id !== "wpadminbar";
	}

	/** Like Ctrl+Shift+R: re-download this site's stylesheets/scripts into the browser cache, then reload. */
	function hardReload() {
		var urls = [];
		Array.prototype.forEach.call(document.querySelectorAll('link[rel="stylesheet"][href], script[src]'), function (n) {
			var u = n.href || n.src;
			try { if (new URL(u).origin === location.origin) urls.push(u); } catch (e) { /* ignore */ }
		});
		var go = function () { var u = new URL(location.href); u.searchParams.set("lcv", String(Date.now())); location.replace(u.href); };
		if (!urls.length || !window.fetch) return go();
		Promise.all(urls.slice(0, 40).map(function (u) { return fetch(u, { cache: "reload", credentials: "same-origin" }).catch(function () { /* still reload */ }); }))
			.then(go, go);
		setTimeout(go, 4000); // never wait longer than this
	}

	// ------------------------------------------------------------------ "eyes": the assistant looks through THIS browser
	// (exactly what the person sees: logged in, real fonts, past any "checking your browser" page). Other pages and other
	// screen sizes are opened in a hidden same-site frame of the right width.
	var DEVW = { desktop: 1366, tablet: 820, mobile: 390 };
	var EPROPS = ["color", "background-color", "background-image", "font-family", "font-size", "font-weight", "line-height", "letter-spacing", "text-transform", "text-align", "margin", "padding", "border-radius", "display", "width", "max-width"];
	function deviceOf(w) { return w <= 767 ? "mobile" : w <= 1024 ? "tablet" : "desktop"; }
	function samePath(url) {
		if (!url) return true;
		try { var u = new URL(url, location.href); return u.origin === location.origin && u.pathname.replace(/\/+$/, "") === location.pathname.replace(/\/+$/, ""); } catch (e) { return false; }
	}
	/**
	 * Chrome only EDITORS see: this widget, the element picker's overlays and the WordPress admin bar (with whatever
	 * plugins put in it). A visitor never sees any of it, so the assistant must not see it either - otherwise it reads
	 * "Howdy, admin" as page content, measures the admin bar's colours as the design, and builds around a chat panel
	 * that is not there in real life.
	 */
	var CHROME = "[data-livecrafts],#wpadminbar,#wpadminbar *,.lcw-hoverbox,.lcw-pickhint";
	/** Takes that chrome out of the copy of the page the assistant looks at, so it sees exactly the visitor's view. */
	function hideChrome(doc) {
		try {
			var s = doc.createElement("style");
			s.setAttribute("data-livecrafts", "hide-chrome");
			s.textContent = "#wpadminbar,[data-livecrafts],.lcw-hoverbox,.lcw-pickhint{display:none !important}"
				+ "html{margin-top:0 !important;padding-top:0 !important}body{margin-top:0 !important}";
			(doc.head || doc.documentElement).appendChild(s);
			// WordPress sets html{margin-top:32px !important}: only an inline !important beats that.
			if (doc.documentElement) doc.documentElement.style.setProperty("margin-top", "0", "important");
		} catch (e) { /* a page we may not touch: the filters below still apply */ }
	}

	/**
	 * One look at a time.
	 *
	 * Every look loads the whole page again in a hidden frame - with the builder's own scripts, fonts and images.
	 * Two of those at once, on top of the page the person is reading, is what made the tab stop responding. They now
	 * queue, so the browser is only ever rendering one extra copy.
	 */
	var probeQueue = Promise.resolve();
	function withDoc(args, fn) {
		var run = function () { return withDocNow(args, fn); };
		var next = probeQueue.then(run, run);
		probeQueue = next.then(function () {}, function () {}); // the queue never inherits a rejection
		return next;
	}

	function withDocNow(args, fn) {
		var device = args.device || deviceOf(window.innerWidth);
		// Always look at a FRESH copy of the page (a hidden frame, cache-busted): the tab the person has open was loaded before the
		// assistant's changes and would show the old look.
		var u;
		try { u = new URL(args.url || location.href, location.href); } catch (e) { return Promise.reject(new Error("That is not a valid page address.")); }
		if (u.origin !== location.origin) return Promise.reject(new Error("Only pages of this site can be opened."));
		u.searchParams.set("lcprobe", String(Date.now()));
		return new Promise(function (resolve, reject) {
			var f = document.createElement("iframe");
			f.name = "lcw-probe"; f.setAttribute("data-livecrafts", "probe"); f.setAttribute("aria-hidden", "true");
			f.style.cssText = "position:fixed;left:-30000px;top:0;width:" + (DEVW[device] || 1366) + "px;height:900px;border:0;opacity:0;pointer-events:none";
			var done = false;
			var fail = function (msg) { if (done) return; done = true; f.remove(); reject(new Error(msg)); };
			// A heavy page (a builder home page with lazy images) needs more than 25s on a slow connection; the backend
			// waits longer than this before it gives up and uses the server's own browser instead.
			var t = setTimeout(function () { fail("The page took too long to load in this browser."); }, 45000);
			f.onload = function () {
				setTimeout(function () {
					if (done) return;
					clearTimeout(t);
					var doc; try { doc = f.contentDocument; } catch (e) { doc = null; }
					if (!doc) return fail("The page could not be opened in the browser.");
					hideChrome(doc); // the assistant looks at the visitor's page, never at the editor's chrome
					Promise.resolve().then(function () { return fn(doc, f.contentWindow, device); })
						.then(function (r) { done = true; f.remove(); resolve(r); }, function (e) { fail(e && e.message ? e.message : String(e)); });
				}, 1200); // let fonts and late scripts settle
			};
			f.src = u.href;
			document.body.appendChild(f);
		});
	}
	function norm(s) { return String(s || "").replace(/\s+/g, " ").trim().toLowerCase(); }
	function findEls(doc, args) {
		if (args.selector) { try { return Array.prototype.slice.call(doc.querySelectorAll(args.selector)).filter(function (e) { return !e.closest(CHROME); }); } catch (e) { throw new Error("Invalid CSS selector."); } }
		var t = norm(args.text);
		if (!t) return [];
		return Array.prototype.filter.call(doc.body.querySelectorAll("*"), function (e) {
			if (/^(SCRIPT|STYLE|NOSCRIPT)$/.test(e.tagName) || e.closest(CHROME)) return false;
			return Array.prototype.some.call(e.childNodes, function (n) { return n.nodeType === 3 && norm(n.textContent).indexOf(t) !== -1; });
		});
	}
	function fileOf(href) {
		var base = String(C.siteUrl || location.origin).replace(/\/+$/, "") + "/";
		return href && href.indexOf(base) === 0 ? href.slice(base.length).split("?")[0] : undefined;
	}
	function eyesInspect(doc, win, args) {
		if (!args.text && !args.selector) throw new Error("Give the visible text of the element or a CSS selector.");
		var els = findEls(doc, args);
		var out = els.slice(0, 3).map(function (el) {
			var cs = win.getComputedStyle(el), computed = {}, rules = [];
			EPROPS.forEach(function (p) { computed[p] = cs.getPropertyValue(p); });
			var visit = function (list, sheet, media) {
				Array.prototype.forEach.call(list, function (r) {
					if (r.type === 4) { if (win.matchMedia(r.conditionText || r.media.mediaText).matches) visit(r.cssRules, sheet, r.conditionText || r.media.mediaText); return; }
					if (r.type !== 1) return;
					var hit = false; try { hit = el.matches(r.selectorText); } catch (e) { /* unsupported selector */ }
					if (!hit) return;
					var decl = {};
					EPROPS.forEach(function (p) { var v = r.style.getPropertyValue(p); if (v) decl[p] = v + (r.style.getPropertyPriority(p) ? " !important" : ""); });
					if (Object.keys(decl).length) rules.push({ selector: r.selectorText, stylesheet: sheet.href || (sheet.ownerNode && sheet.ownerNode.id ? "<style id=" + sheet.ownerNode.id + ">" : "inline <style> in the page"), file: fileOf(sheet.href), media: media || undefined, declarations: decl });
				});
			};
			Array.prototype.forEach.call(doc.styleSheets, function (sheet) {
				try { visit(sheet.cssRules, sheet); } catch (e) { rules.push({ stylesheet: sheet.href, note: "cross-origin stylesheet: its rules cannot be read" }); }
			});
			var r = el.getBoundingClientRect();
			return {
				tag: el.tagName.toLowerCase(), id: el.id || undefined, classes: Array.prototype.filter.call(el.classList, function (c) { return c.indexOf("lcw-") !== 0; }).join(" ") || undefined,
				text: norm(el.textContent).slice(0, 120), inlineStyle: el.getAttribute("style") || undefined, computed: computed, rules: rules,
				box: { width: Math.round(r.width), height: Math.round(r.height) }, visible: r.width > 0 && r.height > 0 && cs.visibility !== "hidden" && cs.display !== "none",
			};
		});
		return { ok: true, url: doc.location.href.replace(/[?&]lcprobe=\d+/, ""), count: els.length, elements: out,
			howToRead: "computed = what the visitor sees. rules = every CSS rule that sets those properties, in cascade order (later ones win unless !important). Edit the rule in `file`, or use style_patch." };
	}
	function eyesRead(doc) {
		return {
			ok: true, url: doc.location.href.replace(/[?&]lcprobe=\d+/, ""), title: doc.title,
			headings: Array.prototype.slice.call(doc.querySelectorAll("h1,h2,h3"), 0, 40).filter(function (h) { return !h.closest(CHROME); }).map(function (h) { return { tag: h.tagName.toLowerCase(), text: norm(h.textContent).slice(0, 160) }; }),
			text: String(doc.body.innerText || "").replace(/\n{3,}/g, "\n\n").slice(0, 15000),
			images: Array.prototype.slice.call(doc.images, 0, 30).filter(function (i) { return !i.closest(CHROME); }).map(function (i) { return { src: i.currentSrc || i.src, alt: i.alt, width: i.naturalWidth, height: i.naturalHeight }; }),
			links: Array.prototype.slice.call(doc.querySelectorAll("a[href]"), 0, 50).filter(function (l) { return !l.closest(CHROME); }).map(function (l) { return { text: norm(l.textContent).slice(0, 60), href: l.href }; }),
		};
	}
	function eyesDesign(doc, win) {
		var cs = function (el) { return win.getComputedStyle(el); };
		var pick = function (sel) { var el = doc.querySelector(sel); if (!el || el.closest(CHROME)) return null; var s = cs(el); return { family: s.fontFamily.split(",")[0].replace(/["']/g, "").trim(), size: s.fontSize, weight: s.fontWeight, lineHeight: s.lineHeight, color: s.color, letterSpacing: s.letterSpacing, textTransform: s.textTransform }; };
		var typography = {}; ["h1", "h2", "h3", "h4", "p", "a", "li", "button"].forEach(function (t) { typography[t] = pick(t); });
		var count = function (m, k) { if (k && !/rgba\(0, 0, 0, 0\)|transparent/.test(k)) m[k] = (m[k] || 0) + 1; };
		var text = {}, bg = {}, radius = {}, gaps = {};
		var all = Array.prototype.slice.call(doc.body.querySelectorAll("*"), 0, 3000).filter(function (e) { return !e.closest(CHROME); });
		all.forEach(function (el) {
			var s = cs(el);
			if (Array.prototype.some.call(el.childNodes, function (n) { return n.nodeType === 3 && n.textContent.trim(); })) count(text, s.color);
			count(bg, s.backgroundColor);
			if (s.borderRadius !== "0px") count(radius, s.borderRadius);
			if (/^(SECTION|HEADER|FOOTER)$/.test(el.tagName) || /section|container|wrap/i.test(String(el.className))) { count(gaps, s.paddingTop); count(gaps, s.paddingBottom); }
		});
		var top = function (m, n) { return Object.keys(m).sort(function (a, b) { return m[b] - m[a]; }).slice(0, n).map(function (k) { return { value: k, uses: m[k] }; }); };
		var btn = doc.querySelector("a.btn, .btn, .button, .wp-block-button__link, .elementor-button");
		var button = btn ? (function () { var s = cs(btn); return { selector: btn.className ? "." + String(btn.className).trim().split(/\s+/).join(".") : btn.tagName.toLowerCase(), background: s.backgroundColor, color: s.color, padding: s.padding, radius: s.borderRadius, font: s.fontWeight + " " + s.fontSize + " " + s.fontFamily.split(",")[0], textTransform: s.textTransform }; })() : null;
		var container = null;
		for (var i = 0; i < all.length; i++) { var mw = cs(all[i]).maxWidth; if (/px$/.test(mw)) { var v = parseFloat(mw); if (v >= 900 && v <= 1600) { container = v + "px"; break; } } }
		var vars = {}, media = {};
		Array.prototype.forEach.call(doc.styleSheets, function (sheet) {
			var rules; try { rules = sheet.cssRules; } catch (e) { return; }
			Array.prototype.forEach.call(rules, function (r) {
				if (r.type === 4) { var m = String(r.conditionText || r.media.mediaText).match(/(max|min)-width:\s*([\d.]+)px/); if (m) media[m[1] + "-width " + m[2] + "px"] = 1; }
				if (r.type === 1 && /^(:root|html|body)$/.test(r.selectorText.trim())) for (var k = 0; k < r.style.length; k++) { var p = r.style[k]; if (p.indexOf("--") === 0 && Object.keys(vars).length < 60) vars[p] = r.style.getPropertyValue(p).trim(); }
			});
		});
		var builder = doc.querySelector("[data-elementor-id]") ? "elementor" : doc.querySelector(".wp-block-group, .wp-site-blocks") ? "blocks" : "classic theme";
		return { ok: true, url: doc.location.href.replace(/[?&]lcprobe=\d+/, ""), builder: builder, typography: typography, textColors: top(text, 6), backgrounds: top(bg, 6), radii: top(radius, 4), sectionSpacing: top(gaps, 4), button: button, containerMaxWidth: container, cssVariables: vars, breakpoints: Object.keys(media).slice(0, 12),
			howToUse: "Reuse these fonts, sizes, colours (prefer the CSS variables), radii, spacing and breakpoints so new work looks native to this site." };
	}
	var shotLib = null;
	function loadShotLib() {
		if (window.modernScreenshot) return Promise.resolve(window.modernScreenshot);
		if (shotLib) return shotLib;
		shotLib = new Promise(function (resolve, reject) {
			var s = document.createElement("script");
			s.src = String(C.assets || "").replace(/\/?$/, "/") + "vendor/modern-screenshot.js?ver=" + encodeURIComponent(C.version || "");
			s.onload = function () { window.modernScreenshot ? resolve(window.modernScreenshot) : reject(new Error("Screenshot library did not load.")); };
			s.onerror = function () { shotLib = null; reject(new Error("Screenshot library could not be loaded.")); };
			document.head.appendChild(s);
		});
		return shotLib;
	}
	function eyesScreenshot(doc, win, args, device) {
		return loadShotLib().then(function (lib) {
			var node = doc.body, target = "page";
			if (args.selector || args.text) { var els = findEls(doc, args); if (els.length) { node = els[0]; target = args.selector || ('text "' + args.text + '"'); } }
			var whole = node === doc.body;
			var w = whole ? win.innerWidth : Math.ceil(node.getBoundingClientRect().width);
			var opts = { scale: Math.min(1, 1400 / Math.max(1, w)), quality: 0.82, backgroundColor: "#ffffff",
				filter: function (n) { return !(n && n.nodeType === 1 && (n.id === "wpadminbar" || (n.getAttribute && n.getAttribute("data-livecrafts")))); } };
			// the page: what fits the screen (or the full page); an element: let the library measure it exactly
			// Painting a copy of the whole page is the slow part: 6000px of a builder page could take longer than the
			// request was allowed to. 3500px is about four screens - enough to judge a section in place.
			if (whole) { opts.width = w; opts.height = Math.max(1, Math.min(doc.documentElement.scrollHeight, args.fullPage ? 3500 : win.innerHeight)); }
			else opts.style = { margin: "0" }; // the copy must not pick up default margins (they push the content out of the picture)
			return lib.domToJpeg(node, opts).then(function (dataUrl) {
				return { ok: true, image: dataUrl, device: device, target: target, url: doc.location.href.replace(/[?&]lcprobe=\d+/, ""), note: "Captured in the person's own browser." };
			});
		});
	}
	function handleEyes(d) {
		var a = d.args || {};
		var job = d.action === "inspect" ? withDoc(a, function (doc, win) { return eyesInspect(doc, win, a); })
			: d.action === "read" ? withDoc(a, function (doc) { return eyesRead(doc); })
			: d.action === "design" ? withDoc(a, function (doc, win) { return eyesDesign(doc, win); })
			: d.action === "screenshot" ? withDoc(a, function (doc, win, device) { return eyesScreenshot(doc, win, a, device); })
			: Promise.reject(new Error("Unknown request."));
		job.then(function (result) { send({ type: "lc:eyes-result", id: d.id, ok: true, result: result }); },
			function (e) { send({ type: "lc:eyes-result", id: d.id, ok: false, error: e && e.message ? e.message : String(e) }); });
	}

	// ------------------------------------------------------------------ drafts: preview / live, deploy, discard
	// These talk to WordPress directly (cookie + nonce). The deploy password never leaves this site.
	var drafts = { count: Number(C.drafts) || 0, view: C.view === "live" ? "live" : "draft" };
	var bar = document.createElement("div");
	bar.className = "lcw-draftbar";
	bar.setAttribute("data-livecrafts", "draftbar");
	bar.setAttribute("role", "region");
	bar.setAttribute("aria-label", "Draft changes");
	root.appendChild(bar);

	/** A Livecrafts REST address. Works with pretty permalinks (/wp-json/...) and with ?rest_route=/... sites. */
	function endpoint(path, root) {
		var q = path.indexOf("?"), route = q < 0 ? path : path.slice(0, q), query = q < 0 ? "" : path.slice(q + 1);
		var u = new URL(String(root || C.restUrl || ""), location.href);
		if (u.searchParams.has("rest_route")) {
			u.searchParams.set("rest_route", u.searchParams.get("rest_route").replace(/\/?$/, "/") + route);
		} else {
			u.pathname = u.pathname.replace(/\/?$/, "/") + route;
		}
		if (query) new URLSearchParams(query).forEach(function (v, k) { u.searchParams.set(k, v); });
		return u.href;
	}
	function wp(method, path, body) {
		return fetch(endpoint(path), {
			method: method, credentials: "same-origin",
			headers: { "Content-Type": "application/json", "X-WP-Nonce": C.nonce },
			body: body ? JSON.stringify(body) : undefined,
		}).then(function (r) {
			return r.json().catch(function () { return {}; }).then(function (j) {
				if (!r.ok) { var e = new Error(j && j.message ? j.message : "The site answered " + r.status + " for " + r.url.replace(/[?&]_wpnonce=[^&]*/, "") + " (not a WordPress REST answer: check permalinks or a security plugin blocking /wp-json)."); e.code = j && j.code; e.data = j && j.data; throw e; }
				return j;
			});
		});
	}
	function renderBar() {
		var preview = drafts.view === "draft";
		bar.hidden = drafts.count === 0 && preview;
		bar.innerHTML =
			'<span class="lcw-db-dot' + (preview ? "" : " lcw-db-live") + '" aria-hidden="true"></span>' +
			'<span class="lcw-db-text">' + (preview
				? "<b>Preview</b> · " + drafts.count + " unpublished change" + (drafts.count === 1 ? "" : "s")
				: "<b>Live site</b> · drafts hidden") + "</span>" +
			'<button type="button" class="lcw-db-btn" data-act="view">' + (preview ? "Show live" : "Show preview") + "</button>" +
			(drafts.count ? '<button type="button" class="lcw-db-btn" data-act="discard">Discard all</button>' : "") +
			(drafts.count && C.canDeploy ? '<button type="button" class="lcw-db-btn lcw-db-primary" data-act="deploy">Deploy…</button>' : "");
	}
	bar.addEventListener("click", function (e) {
		var b = e.target.closest && e.target.closest("button[data-act]");
		if (!b) return;
		var act = b.getAttribute("data-act");
		if (act === "view") setView(drafts.view === "draft" ? "live" : "draft");
		else if (act === "discard") discardAll();
		else if (act === "deploy") openDeploy();
	});
	function setView(view) {
		document.cookie = "livecrafts_view=" + (view === "live" ? "live" : "draft") + "; path=/; SameSite=Lax" + (location.protocol === "https:" ? "; Secure" : "");
		state.open = !panel.hidden; save();
		hardReload();
	}
	function refreshDrafts() {
		return wp("GET", "status").then(function (s) {
			drafts.count = s && s.drafts ? Number(s.drafts.count) || 0 : 0;
			renderBar();
			send({ type: "lc:drafts", count: drafts.count, view: drafts.view, canDeploy: !!C.canDeploy });
			return s;
		}).catch(function () { /* keep the last known count */ });
	}
	function discardAll() {
		// Ask the site first: theme-file edits are live at once, so Discard also puts those files back.
		refreshDrafts().then(function (s) {
			var files = s && Number(s.files) || 0;
			var msg = "Discard all " + drafts.count + " unpublished change" + (drafts.count === 1 ? "" : "s") + "?\n\n" +
				(files ? files + " of them " + (files === 1 ? "is a theme-file edit" : "are theme-file edits") + " that is already live: " + (files === 1 ? "that file is" : "those files are") + " put back as it was. " : "The live site is not touched. ") +
				"New pages that were never deployed go to the Trash.";
			if (!window.confirm(msg)) return;
			wp("POST", "drafts/discard", {}).then(function (r) {
				if (r && r.file_errors && r.file_errors.length) window.alert(r.note);
				state.open = !panel.hidden; save(); hardReload();
			}, function (e) { window.alert("Could not discard: " + e.message); });
		});
	}

	// The deploy dialog: what will go live, conflicts, notes, password.
	var dialog = null;
	function openDeploy() {
		if (dialog) dialog.remove();
		dialog = document.createElement("div");
		dialog.className = "lcw-modal";
		dialog.setAttribute("data-livecrafts", "deploy");
		dialog.innerHTML =
			'<div class="lcw-modal-card" role="dialog" aria-modal="true" aria-labelledby="lcw-dep-title">' +
			'<h2 id="lcw-dep-title">Deploy to the live site</h2>' +
			'<div class="lcw-dep-list" aria-live="polite">Checking the drafts…</div>' +
			'<label class="lcw-field"><span>Deploy notes <small>(what and why - shown in the history)</small></span><textarea rows="2" maxlength="2000" class="lcw-dep-notes"></textarea></label>' +
			'<label class="lcw-field"><span>' + escapeHtml(C.asksFor === "deploy password" ? "Deploy password" : "Your WordPress password") + '</span><input type="password" class="lcw-dep-pw" autocomplete="current-password" required></label>' +
			'<label class="lcw-check" hidden><input type="checkbox" class="lcw-dep-force"> Someone changed these on the live site after the draft was made. Deploy anyway (the draft wins).</label>' +
			'<p class="lcw-dep-error" role="alert" hidden></p>' +
			'<div class="lcw-modal-actions"><button type="button" class="lcw-db-btn" data-act="cancel">Cancel</button><button type="button" class="lcw-db-btn lcw-db-primary" data-act="go" disabled>Deploy</button></div>' +
			"</div>";
		document.body.appendChild(dialog);
		var q = function (s) { return dialog.querySelector(s); };
		var go = q('[data-act="go"]'), err = q(".lcw-dep-error"), force = q(".lcw-check");
		var close = function () { if (dialog) { dialog.remove(); dialog = null; } document.removeEventListener("keydown", onEsc, true); };
		var onEsc = function (e) { if (e.key === "Escape") { e.preventDefault(); close(); } };
		document.addEventListener("keydown", onEsc, true);
		q('[data-act="cancel"]').addEventListener("click", close);
		dialog.addEventListener("click", function (e) { if (e.target === dialog) close(); });
		wp("GET", "deploy/check").then(function (c) {
			var html = (c.items || []).map(function (i) {
				return "<div class='lcw-dep-item'><b>" + escapeHtml(i.object) + "</b><ul>" + i.changes.map(function (s) { return "<li>" + escapeHtml(s) + "</li>"; }).join("") + "</ul></div>";
			}).join("");
			if ((c.errors || []).length) html += "<p class='lcw-dep-warn'>Cannot deploy: " + c.errors.map(function (x) { return escapeHtml(x.object + ": " + x.error); }).join("; ") + "</p>";
			if ((c.conflicts || []).length) {
				html += "<p class='lcw-dep-warn'>Changed on the live site meanwhile: " + c.conflicts.map(function (x) { return escapeHtml(x.object); }).join(", ") + "</p>";
				force.hidden = false;
			}
			q(".lcw-dep-list").innerHTML = html || "Nothing to deploy.";
			go.disabled = !(c.items || []).length || (c.errors || []).length > 0;
			q(".lcw-dep-pw").focus();
		}, function (e) { q(".lcw-dep-list").textContent = "Could not check the drafts: " + e.message; });
		go.addEventListener("click", function () {
			var pw = q(".lcw-dep-pw").value;
			if (!pw) { err.hidden = false; err.textContent = "Type the password."; return; }
			go.disabled = true; go.textContent = "Deploying…"; err.hidden = true;
			wp("POST", "deploy", { password: pw, notes: q(".lcw-dep-notes").value, force: !!q(".lcw-dep-force").checked }).then(function (r) {
				if (r.failed && r.failed.length) throw new Error("Deployed with problems: " + r.failed.map(function (f) { return f.object + ": " + f.error; }).join("; "));
				close();
				send({ type: "lc:deployed", release: r.release });
				state.open = !panel.hidden; save();
				hardReload();
			}).catch(function (e) {
				go.disabled = false; go.textContent = "Deploy";
				err.hidden = false; err.textContent = e.message;
				if (e.code === "livecrafts_conflict") force.hidden = false;
				q(".lcw-dep-pw").value = ""; q(".lcw-dep-pw").focus();
			});
		});
	}

	// The chat can ask for these too (its own Deploy / Discard buttons and the preview switch).
	window.addEventListener("message", function (e) {
		if (e.origin !== backendOrigin || !e.data || typeof e.data.type !== "string") return;
		if (e.data.type === "lc:deploy" && C.canDeploy) openDeploy();
		else if (e.data.type === "lc:discard") discardAll();
		else if (e.data.type === "lc:view") setView(e.data.view);
		else if (e.data.type === "lc:drafts-changed" || e.data.type === "lc:ready") refreshDrafts();
		else if (e.data.type === "lc:wp" && e.data.id) wpForChat(e.data);
		else if (e.data.type === "lc:media" && e.data.id) pickMedia(e.data.id);
		else if (e.data.type === "lc:upload" && e.data.id) uploadMedia(e.data);
	});

	// An image from the person's computer, sent by the chat: saved into the WordPress Media Library as the logged-in
	// person (core route wp/v2/media, their nonce). The Media Library is not public content, so this is not a draft.
	var EXT = { "image/jpeg": "jpg", "image/png": "png", "image/gif": "gif", "image/webp": "webp", "image/avif": "avif", "image/svg+xml": "svg" };
	function uploadMedia(d) {
		var reply = function (ok, data) { send({ type: "lc:upload-result", id: d.id, ok: ok, attachment: ok ? data : undefined, error: ok ? undefined : data }); };
		var file = d.file;
		if (!C.canUpload) return reply(false, "Your WordPress account cannot upload files.");
		if (!file || typeof file.size !== "number" || !/^image\//.test(file.type || "")) return reply(false, "Only image files can be uploaded here.");
		if (C.maxUpload && file.size > C.maxUpload) return reply(false, "The image is " + (file.size / 1048576).toFixed(1) + " MB; this site accepts up to " + (C.maxUpload / 1048576).toFixed(0) + " MB per file.");
		var name = String(d.name || file.name || "image").replace(/[^\w.\- ]+/g, "-").replace(/^[.\- ]+/, "").slice(0, 120) || "image";
		if (!/\.[a-z0-9]{2,5}$/i.test(name)) name += "." + (EXT[file.type] || "jpg");
		fetch(endpoint("wp/v2/media", C.restRoot), {
			method: "POST", credentials: "same-origin", body: file,
			headers: { "X-WP-Nonce": C.nonce, "Content-Type": file.type, "Content-Disposition": 'attachment; filename="' + name + '"' },
		}).then(function (r) {
			return r.json().catch(function () { return {}; }).then(function (j) {
				if (!r.ok || !j.id) throw new Error(j && j.message ? j.message : "WordPress refused the upload (HTTP " + r.status + ").");
				var meta = {};
				if (d.alt) meta.alt_text = String(d.alt).slice(0, 300);
				if (d.title) meta.title = String(d.title).slice(0, 200);
				var done = function (a) {
					var det = a.media_details || {};
					reply(true, { id: a.id, url: a.source_url, alt: a.alt_text || "", width: det.width, height: det.height, title: a.title && a.title.rendered ? a.title.rendered : name });
				};
				if (!Object.keys(meta).length) return done(j);
				return fetch(endpoint("wp/v2/media/" + j.id, C.restRoot), {
					method: "POST", credentials: "same-origin", headers: { "X-WP-Nonce": C.nonce, "Content-Type": "application/json" }, body: JSON.stringify(meta),
				}).then(function (r2) { return r2.ok ? r2.json() : j; }, function () { return j; }).then(done);
			});
		}).catch(function (e) { reply(false, e && e.message ? e.message : "The upload failed."); });
	}

	// The chat panel's own edits (click panel, Changes tab) go to WordPress from here, as the logged-in person, with
	// their nonce - never through the backend. Only these Livecrafts routes; deploying stays in the dialog above.
	var CHAT_ROUTES = [
		[/^GET$/, /^(status|changes|changes\/\d+|post|map|notes|releases)(\?.*)?$/],
		[/^POST$/, /^(changes|changes\/\d+\/revert|resolve|pages|notes|drafts\/discard)$/],
	];
	function wpForChat(d) {
		var method = String(d.method || "GET").toUpperCase(), path = String(d.path || "");
		var allowed = CHAT_ROUTES.some(function (r) { return r[0].test(method) && r[1].test(path); });
		var reply = function (ok, data, error) { send({ type: "lc:wp-result", id: d.id, ok: ok, data: data, error: error }); };
		if (!allowed) return reply(false, null, "Not allowed from the chat.");
		var body = d.body && typeof d.body === "object" ? d.body : undefined;
		if (body && method === "POST" && /^(changes|pages)$/.test(path)) body.source = "widget";
		wp(method, path, body).then(function (j) {
			reply(true, j);
			if (method === "POST" && path !== "resolve") refreshDrafts();
		}, function (e) { reply(false, e.data || null, e.message); });
	}

	// The WordPress Media Library (upload or choose), for "Replace image" in the click panel.
	function pickMedia(id) {
		if (!window.wp || !window.wp.media) { send({ type: "lc:media-result", id: id, ok: false, error: "The Media Library is not available on this page." }); return; }
		var frame = window.wp.media({ title: "Choose an image", library: { type: "image" }, multiple: false, button: { text: "Use this image" } });
		var picked = false;
		// The chat panel sits on top of everything; WordPress's media window must come above it while it is open
		// (otherwise the panel covers its "Use this image" button and the upload area).
		document.documentElement.classList.add("lcw-media-open");
		frame.on("close", function () { document.documentElement.classList.remove("lcw-media-open"); });
		frame.on("select", function () {
			picked = true;
			var a = frame.state().get("selection").first().toJSON();
			send({ type: "lc:media-result", id: id, ok: true, attachment: { id: a.id, url: a.url, alt: a.alt || "", width: a.width, height: a.height, title: a.title } });
		});
		frame.on("close", function () { setTimeout(function () { if (!picked) send({ type: "lc:media-result", id: id, ok: false, cancelled: true }); }, 0); });
		frame.open();
	}
	renderBar();

	if (state.open) open();
	// remove our cache-busting parameter from the address bar after a reload
	try { var cu = new URL(location.href); if (cu.searchParams.has("lcv")) { cu.searchParams.delete("lcv"); history.replaceState(null, "", cu.href); } } catch (e) { /* ignore */ }

	function escapeHtml(s) { return String(s).replace(/[&<>"']/g, function (c) { return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]; }); }
})();
