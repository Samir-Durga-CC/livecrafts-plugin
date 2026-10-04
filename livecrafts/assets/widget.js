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
	if (!C || !C.backend || window.__livecraftsWidget) return;
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
			approvalMode: C.approvalMode, token: C.token || "", user: C.user || "", parentOrigin: location.origin, tab: state.tab || "chat",
		};
		return C.backend.replace(/\/+$/, "") + "/?embed=1#cfg=" + encodeURIComponent(JSON.stringify(cfg));
	}

	function open() {
		if (!frame) {
			frame = document.createElement("iframe");
			frame.className = "lcw-frame";
			frame.title = C.botName || "Assistant";
			frame.allow = "clipboard-write";
			frame.src = frameUrl();
			var err = document.createElement("div");
			err.className = "lcw-offline";
			err.innerHTML = "<b>Cannot reach the Livecrafts app.</b><br>Is it running at <code>" + escapeHtml(C.backend) + "</code>?<br>Start it with <code>npm start</code>, then reopen this panel.";
			err.hidden = true;
			panel.appendChild(err);
			panel.appendChild(frame);
			// The chat says "lc:ready" when it loaded; if it never does, the app is not running (or blocked).
			var ready = false;
			setTimeout(function () { if (!ready) err.hidden = false; }, 8000);
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
		return {
			selector: uniqueSelector(el), label: describe(el), tag: el.tagName.toLowerCase(),
			id: el.id || "", classes: Array.prototype.slice.call(el.classList).filter(function (c) { return c.indexOf("lcw-") !== 0; }).join(" "),
			text: (el.innerText || el.textContent || "").replace(/\s+/g, " ").trim().slice(0, 400),
			html: el.outerHTML.replace(/\s+/g, " ").slice(0, 1200),
			image: img ? { src: img.currentSrc || img.src, alt: img.alt || "" } : null,
			link: el.closest && el.closest("a") ? el.closest("a").href : "",
			styles: styles, rect: { width: Math.round(r.width), height: Math.round(r.height) },
			section: sectionOf(el), pageUrl: location.href.split("#")[0], viewport: window.innerWidth,
		};
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
			// show the change: reload the page, keep the panel open on the same tab
			state.open = true; state.tab = d.tab || state.tab; save();
			var u = new URL(location.href); u.searchParams.set("lcv", String(Date.now()));
			location.replace(u.href);
		}
		else if (d.type === "lc:navigate" && d.url) {
			try { var t = new URL(d.url, location.href); if (t.origin === location.origin) { state.open = true; save(); location.href = t.href; } } catch (x) { /* ignore */ }
		}
	});

	if (state.open) open();
	// remove our cache-busting parameter from the address bar after a reload
	try { var cu = new URL(location.href); if (cu.searchParams.has("lcv")) { cu.searchParams.delete("lcv"); history.replaceState(null, "", cu.href); } } catch (e) { /* ignore */ }

	function escapeHtml(s) { return String(s).replace(/[&<>"']/g, function (c) { return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]; }); }
})();
