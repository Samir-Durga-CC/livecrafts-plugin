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
	hint.innerHTML = "<b>Pick an element</b> - click anything on the page. <kbd>Esc</kbd> to cancel.";
	hint.hidden = true;

	var box = document.createElement("div");
	box.className = "lcw-hoverbox";
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
		};
		return C.backend.replace(/\/+$/, "") + "/?embed=1&v=" + encodeURIComponent(C.version || "") + "#cfg=" + encodeURIComponent(JSON.stringify(cfg));
	}

	function open() {
		if (!frame) {
			frame = document.createElement("iframe");
			frame.className = "lcw-frame";
			frame.title = C.botName || "Assistant";
			// "local-network-access": Chrome/Edge only let a public site load an app on this computer after the person allows it.
			frame.allow = "clipboard-write; local-network-access; local-network";
			frame.src = frameUrl();
			var isLocal = /^https?:\/\/(localhost|127\.0\.0\.1|\[::1\])(:\d+)?$/i.test(backendOrigin);
			var err = document.createElement("div");
			err.className = "lcw-offline";
			err.innerHTML = "<b>Cannot reach the Livecrafts app.</b>" +
				(isLocal ? "<p>1. Is it running? Start it with <code>npm start</code> (it should say <code>" + escapeHtml(C.backend) + "</code>).</p>" +
					"<p>2. Did the browser ask to <b>“access other apps and services on this device”</b>? Click <b>Allow</b>. If you closed it: click the icon left of the address bar → Site settings → <b>Local network access</b> → Allow.</p>"
					: "<p>Check that the Livecrafts app is online at <code>" + escapeHtml(C.backend) + "</code>.</p>") +
				'<button type="button" class="lcw-retry">Try again</button>';
			err.hidden = true;
			panel.appendChild(err);
			panel.appendChild(frame);
			// The chat says "lc:ready" when it loaded; if it never does, the app is not running (or blocked).
			var ready = false;
			var watch = function () { setTimeout(function () { if (!ready) err.hidden = false; }, 8000); };
			watch();
			err.querySelector(".lcw-retry").addEventListener("click", function () { err.hidden = true; frame.src = frameUrl(); watch(); });
			window.addEventListener("message", function (e) { if (e.origin === backendOrigin && e.data && e.data.type === "lc:ready") { ready = true; err.hidden = true; } });
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
	function inWidget(el) { return !!(el && el.closest && (el.closest("[data-livecrafts]") || el === box || el === hint || box.contains(el))); }
	function onMove(e) {
		var el = document.elementFromPoint(e.clientX, e.clientY);
		if (!el || inWidget(el) || el === document.body || el === document.documentElement) { box.hidden = true; hovered = null; return; }
		hovered = el;
		outline(el);
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
		var el = hovered || e.target;
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
			block: snap.block || "", menuItem: snap.menuItem || 0, tag: snap.tag, text: snap.rawText || snap.text,
			imageSrc: snap.image ? snap.image.src : "", bgImage: snap.bgImage || "", href: snap.link || "", selector: snap.selector,
		});
	}
	/** The block of the page content this element belongs to ("<post>:<path>", only on pages Livecrafts marked for editors). */
	function blockOf(el) { var b = el.closest && el.closest("[data-lc-block]"); return b ? b.getAttribute("data-lc-block") : ""; }
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
			pageKey: C.pageKey || "", elementor: elementorOf(el), hasChildren: hasMarkup(el), block: blockOf(el), menuItem: menuItemOf(el),
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
		else if (d.type === "lc:tab") { state.tab = d.tab; save(); }
		else if (d.type === "lc:highlight" && d.selector) {
			try { var el = document.querySelector(d.selector); if (el) { el.scrollIntoView({ behavior: "smooth", block: "center" }); outline(el); setTimeout(function () { if (!picking) box.hidden = true; }, 1800); } } catch (x) { /* bad selector */ }
		}
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
			if (el.closest("[data-livecrafts]")) return;
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
	function withDoc(args, fn) {
		var device = args.device || deviceOf(window.innerWidth);
		if (samePath(args.url) && device === deviceOf(window.innerWidth)) {
			try { return Promise.resolve(fn(document, window, device)); } catch (e) { return Promise.reject(e); }
		}
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
			var t = setTimeout(function () { fail("The page took too long to load in the browser."); }, 25000);
			f.onload = function () {
				setTimeout(function () {
					if (done) return;
					clearTimeout(t);
					var doc; try { doc = f.contentDocument; } catch (e) { doc = null; }
					if (!doc) return fail("The page could not be opened in the browser.");
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
		if (args.selector) { try { return Array.prototype.slice.call(doc.querySelectorAll(args.selector)).filter(function (e) { return !e.closest("[data-livecrafts]"); }); } catch (e) { throw new Error("Invalid CSS selector."); } }
		var t = norm(args.text);
		if (!t) return [];
		return Array.prototype.filter.call(doc.body.querySelectorAll("*"), function (e) {
			if (/^(SCRIPT|STYLE|NOSCRIPT)$/.test(e.tagName) || e.closest("[data-livecrafts]")) return false;
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
			headings: Array.prototype.slice.call(doc.querySelectorAll("h1,h2,h3"), 0, 40).map(function (h) { return { tag: h.tagName.toLowerCase(), text: norm(h.textContent).slice(0, 160) }; }),
			text: String(doc.body.innerText || "").replace(/\n{3,}/g, "\n\n").slice(0, 15000),
			images: Array.prototype.slice.call(doc.images, 0, 30).filter(function (i) { return !i.closest("[data-livecrafts]"); }).map(function (i) { return { src: i.currentSrc || i.src, alt: i.alt, width: i.naturalWidth, height: i.naturalHeight }; }),
			links: Array.prototype.slice.call(doc.querySelectorAll("a[href]"), 0, 50).filter(function (l) { return !l.closest("[data-livecrafts]"); }).map(function (l) { return { text: norm(l.textContent).slice(0, 60), href: l.href }; }),
		};
	}
	function eyesDesign(doc, win) {
		var cs = function (el) { return win.getComputedStyle(el); };
		var pick = function (sel) { var el = doc.querySelector(sel); if (!el || el.closest("[data-livecrafts]")) return null; var s = cs(el); return { family: s.fontFamily.split(",")[0].replace(/["']/g, "").trim(), size: s.fontSize, weight: s.fontWeight, lineHeight: s.lineHeight, color: s.color, letterSpacing: s.letterSpacing, textTransform: s.textTransform }; };
		var typography = {}; ["h1", "h2", "h3", "h4", "p", "a", "li", "button"].forEach(function (t) { typography[t] = pick(t); });
		var count = function (m, k) { if (k && !/rgba\(0, 0, 0, 0\)|transparent/.test(k)) m[k] = (m[k] || 0) + 1; };
		var text = {}, bg = {}, radius = {}, gaps = {};
		var all = Array.prototype.slice.call(doc.body.querySelectorAll("*"), 0, 3000).filter(function (e) { return !e.closest("[data-livecrafts]"); });
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
				filter: function (n) { return !(n && n.getAttribute && n.getAttribute("data-livecrafts")); } };
			// the page: what fits the screen (or the full page); an element: let the library measure it exactly
			if (whole) { opts.width = w; opts.height = Math.max(1, Math.min(doc.documentElement.scrollHeight, args.fullPage ? 6000 : win.innerHeight)); }
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
	function endpoint(path) {
		var q = path.indexOf("?"), route = q < 0 ? path : path.slice(0, q), query = q < 0 ? "" : path.slice(q + 1);
		var u = new URL(String(C.restUrl || ""), location.href);
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
		if (!window.confirm("Discard all " + drafts.count + " unpublished change" + (drafts.count === 1 ? "" : "s") + "?\n\nThe live site is not touched. New pages that were never deployed go to the Trash.")) return;
		wp("POST", "drafts/discard", {}).then(function () { state.open = !panel.hidden; save(); hardReload(); },
			function (e) { window.alert("Could not discard: " + e.message); });
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
	});

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
