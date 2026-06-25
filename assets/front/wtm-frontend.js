/**
 * Woo Total Menu — Frontend JS
 *
 * Vanilla JS (no jQuery) — spec §2.6.1 (~5 Ko target).
 *
 * Handles:
 *   - Off-canvas drawer open/close, overlay click, ESC, focus trap
 *   - Click-trigger mega containers (data-wtm-mega-trigger)
 *   - Mobile accordion toggles (footer titles + sub-menu carets)
 *   - WC mini-cart fragment refresh
 *
 * Version: 1.2.0
 */

(function () {
	"use strict";

	if (window.wtmFrontendInit) {
		return; // Prevent double-init.
	}
	window.wtmFrontendInit = true;

	var config = window.wtmFrontend || {};
	var breakpoint = config.breakpoint || 768;
	var i18n = config.i18n || {};

	// ========================================================================
	// Helpers
	// ========================================================================

	function on(el, ev, sel, handler) {
		if (!el) return;
		el.addEventListener(
			ev,
			function (e) {
				if (!sel) {
					handler.call(el, e);
					return;
				}
				var target = e.target.closest(sel);
				if (target && el.contains(target)) {
					handler.call(target, e, target);
				}
			},
			true
		);
	}

	function toggleAttr(el, name, val) {
		if (!el) return;
		el.setAttribute(name, String(val));
	}

	// ========================================================================
	// Off-canvas drawer — spec §5.6
	// ========================================================================

	function openOffcanvas(drawer, overlay, toggle) {
		if (!drawer) return;
		drawer.classList.add("is-open");
		drawer.setAttribute("aria-hidden", "false");
		if (overlay) overlay.classList.add("is-visible");
		if (toggle) toggle.setAttribute("aria-expanded", "true");
		document.body.classList.add("wtm-offcanvas-open");
		// Move focus to first focusable element inside drawer.
		setTimeout(function () {
			var first = drawer.querySelector("a[href], button:not([disabled]), input, [tabindex]:not([tabindex='-1'])");
			if (first) first.focus();
			else drawer.focus();
		}, 50);
	}

	function closeOffcanvas(drawer, overlay, toggle) {
		if (!drawer) return;
		drawer.classList.remove("is-open");
		drawer.setAttribute("aria-hidden", "true");
		if (overlay) overlay.classList.remove("is-visible");
		if (toggle) toggle.setAttribute("aria-expanded", "false");
		document.body.classList.remove("wtm-offcanvas-open");
		if (toggle) toggle.focus();
	}

	function initOffcanvas() {
		var toggles = document.querySelectorAll("[data-wtm-offcanvas-toggle]");
		toggles.forEach(function (toggle) {
			if (toggle.__wtmInit) return;
			toggle.__wtmInit = true;

			var drawerId = toggle.getAttribute("aria-controls");
			var drawer = drawerId ? document.getElementById(drawerId) : null;
			if (!drawer) return;
			var overlay = drawer.parentElement.querySelector("[data-wtm-offcanvas-overlay]");

			toggle.addEventListener("click", function () {
				var isOpen = drawer.classList.contains("is-open");
				if (isOpen) {
					closeOffcanvas(drawer, overlay, toggle);
				} else {
					openOffcanvas(drawer, overlay, toggle);
				}
			});

			// Close button inside drawer.
			var closeBtn = drawer.querySelector("[data-wtm-offcanvas-close]");
			if (closeBtn) {
				closeBtn.addEventListener("click", function () {
					closeOffcanvas(drawer, overlay, toggle);
				});
			}

			// Overlay click closes.
			if (overlay) {
				overlay.addEventListener("click", function () {
					closeOffcanvas(drawer, overlay, toggle);
				});
			}

			// Focus trap + Escape inside drawer.
			drawer.addEventListener("keydown", function (e) {
				if (e.key === "Escape") {
					closeOffcanvas(drawer, overlay, toggle);
					return;
				}
				if (e.key !== "Tab") return;
				var focusables = drawer.querySelectorAll("a[href], button:not([disabled]), input, [tabindex]:not([tabindex='-1'])");
				if (!focusables.length) return;
				var first = focusables[0];
				var last = focusables[focusables.length - 1];
				if (e.shiftKey && document.activeElement === first) {
					e.preventDefault();
					last.focus();
				} else if (!e.shiftKey && document.activeElement === last) {
					e.preventDefault();
					first.focus();
				}
			});
		});
	}

	// ========================================================================
	// Click-trigger mega containers (spec §5.4.1 — "ou clic selon réglage")
	// ========================================================================

	function initMegaClick() {
		var triggers = document.querySelectorAll("[data-wtm-mega-trigger]");
		triggers.forEach(function (trigger) {
			if (trigger.__wtmInit) return;
			trigger.__wtmInit = true;

			trigger.addEventListener("click", function (e) {
				// Only intercept click on mobile OR when explicitly in click-trigger mode.
				var item = trigger.closest(".wtm-menu__item--mega");
				if (!item) return;
				var isClickMode = item.classList.contains("wtm-menu__item--trigger-click");
				var isMobile = window.matchMedia("(max-width: " + breakpoint + "px)").matches;
				if (!isClickMode && !isMobile) {
					return; // Hover behavior is handled by CSS.
				}
				e.preventDefault();
				var isOpen = item.classList.contains("is-open");
				// Close other open mega items at the same level.
				var parent = item.parentElement;
				if (parent) {
					parent.querySelectorAll(":scope > .wtm-menu__item--mega.is-open").forEach(function (other) {
						if (other !== item) {
							other.classList.remove("is-open");
							var btn = other.querySelector("[data-wtm-mega-trigger]");
							if (btn) btn.setAttribute("aria-expanded", "false");
						}
					});
				}
				item.classList.toggle("is-open", !isOpen);
				toggleAttr(trigger, "aria-expanded", !isOpen);
			});
		});
	}

	// ========================================================================
	// Mobile accordion toggles (sub-menu carets)
	// ========================================================================

	function initMobileAccordion() {
		var items = document.querySelectorAll(".wtm-menu__item--has-children");
		items.forEach(function (item) {
			if (item.__wtmInit) return;
			item.__wtmInit = true;

			var link = item.querySelector(":scope > .wtm-menu__link");
			if (!link) return;

			link.addEventListener("click", function (e) {
				var isMobile = window.matchMedia("(max-width: " + breakpoint + "px)").matches;
				if (!isMobile) return;
				// On mobile, only toggle if the link is "#" or empty.
				var href = link.getAttribute("href") || "#";
				if (href !== "#" && href !== "" && link.tagName === "A") {
					return; // Let the link navigate.
				}
				e.preventDefault();
				item.classList.toggle("is-open");
			});
		});
	}

	// ========================================================================
	// Footer accordion (mobile) — spec §5.7.2
	// ========================================================================

	function initFooterAccordion() {
		var cols = document.querySelectorAll(".wtm-menu__footer-col");
		cols.forEach(function (col) {
			if (col.__wtmInit) return;
			col.__wtmInit = true;

			var title = col.querySelector(":scope > .wtm-menu__footer-title");
			if (!title) return;

			title.setAttribute("role", "button");
			title.setAttribute("tabindex", "0");
			title.setAttribute("aria-expanded", "true");

			function toggle() {
				var isCollapsed = col.classList.toggle("is-collapsed");
				title.setAttribute("aria-expanded", String(!isCollapsed));
			}

			title.addEventListener("click", function () {
				var isMobile = window.matchMedia("(max-width: " + breakpoint + "px)").matches;
				if (!isMobile) return;
				toggle();
			});
			title.addEventListener("keydown", function (e) {
				if (e.key !== "Enter" && e.key !== " ") return;
				e.preventDefault();
				toggle();
			});
		});
	}

	// ========================================================================
	// Close menus on outside click
	// ========================================================================

	function initOutsideClick() {
		document.addEventListener("click", function (e) {
			// Close any open mega panels if click is outside the menu.
			var openItems = document.querySelectorAll(".wtm-menu__item--mega.is-open");
			openItems.forEach(function (item) {
				if (!item.contains(e.target)) {
					item.classList.remove("is-open");
					var btn = item.querySelector("[data-wtm-mega-trigger]");
					if (btn) btn.setAttribute("aria-expanded", "false");
				}
			});
		});

		document.addEventListener("keydown", function (e) {
			if (e.key !== "Escape") return;
			// Close open mega panels.
			var openItems = document.querySelectorAll(".wtm-menu__item--mega.is-open");
			openItems.forEach(function (item) {
				item.classList.remove("is-open");
				var btn = item.querySelector("[data-wtm-mega-trigger]");
				if (btn) {
					btn.setAttribute("aria-expanded", "false");
					btn.focus();
				}
			});
		});
	}

	// ========================================================================
	// WooCommerce mini-cart fragment refresh (spec §5.10 — cart counter animation)
	// ========================================================================

	function initMiniCartSync() {
		if (config.wooCartFragments !== "yes") return;
		if (typeof jQuery === "undefined") return;

		jQuery(document.body).on("wc_fragments_refreshed wc_fragments_loaded", function () {
			var counts = document.querySelectorAll("[data-wtm-cart-count]");
			var total  = jQuery(".woocommerce-Price-amount").first().text() || "";
			counts.forEach(function (el) {
				// Re-fetch from WC via wc.js
				if (window.wc_cart_fragments_params) {
					// Slight bounce animation.
					el.classList.remove("wtm-bounce");
					void el.offsetWidth;
					el.classList.add("wtm-bounce");
				}
			});
		});

		// Bump animation keyframe (injected once).
		if (!document.getElementById("wtm-bounce-style")) {
			var style = document.createElement("style");
			style.id = "wtm-bounce-style";
			style.textContent = "@keyframes wtm-bounce{0%{transform:scale(1)}40%{transform:scale(1.3)}100%{transform:scale(1)}}.wtm-bounce{animation:wtm-bounce 0.4s ease}";
			document.head.appendChild(style);
		}
	}

	// ========================================================================
	// Init on DOM ready + when new content is injected (e.g. AJAX)
	// ========================================================================

	function initAll() {
		try {
			initOffcanvas();
			initMegaClick();
			initMobileAccordion();
			initFooterAccordion();
			initOutsideClick();
			initMiniCartSync();
		} catch (err) {
			if (window.console) console.error("[WTM] init error:", err);
		}
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", initAll);
	} else {
		initAll();
	}

	// Re-init when WooCommerce cart fragments refresh (the mini-cart may be replaced).
	jQuery(document.body).on("wc_fragments_refreshed", initAll);
})();
