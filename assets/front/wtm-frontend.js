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
        // v1.3.0 — Mini-cart drawer (display_mode: 'drawer')
        // Opens a side panel fetched via REST /wtm/v1/mini-cart-contents.
        // ========================================================================

        function initCartDrawer() {
                var buttons = document.querySelectorAll("[data-wtm-cart-drawer]");
                if (!buttons.length) return;

                // Ensure the drawer + overlay exist (created once).
                var drawer = document.getElementById("wtm-cart-drawer");
                var overlay = document.getElementById("wtm-cart-drawer-overlay");
                if (!drawer) {
                        drawer = document.createElement("div");
                        drawer.id = "wtm-cart-drawer";
                        drawer.className = "wtm-cart-drawer";
                        drawer.setAttribute("role", "dialog");
                        drawer.setAttribute("aria-modal", "true");
                        drawer.setAttribute("aria-label", i18n.openCart || "Cart");
                        drawer.setAttribute("aria-hidden", "true");
                        drawer.innerHTML =
                                '<div class="wtm-cart-drawer__header">' +
                                '<h3 class="wtm-cart-drawer__title">' + (i18n.openCart || "Panier") + '</h3>' +
                                '<button type="button" class="wtm-cart-drawer__close" data-wtm-cart-drawer-close aria-label="' + (i18n.closeCart || "Fermer") + '">&times;</button>' +
                                '</div>' +
                                '<div class="wtm-cart-drawer__body" data-wtm-cart-drawer-body></div>' +
                                '<div class="wtm-cart-drawer__footer" data-wtm-cart-drawer-footer hidden></div>';
                        document.body.appendChild(drawer);
                }
                if (!overlay) {
                        overlay = document.createElement("div");
                        overlay.id = "wtm-cart-drawer-overlay";
                        overlay.className = "wtm-cart-drawer-overlay";
                        document.body.appendChild(overlay);
                }

                function closeDrawer() {
                        drawer.classList.remove("is-open");
                        drawer.setAttribute("aria-hidden", "true");
                        overlay.classList.remove("is-visible");
                        document.body.classList.remove("wtm-cart-drawer-open");
                        buttons.forEach(function (b) { b.setAttribute("aria-expanded", "false"); });
                }

                function openDrawer(btn) {
                        var position = btn.getAttribute("data-position") || "right";
                        drawer.setAttribute("data-position", position);
                        drawer.classList.add("is-open");
                        drawer.setAttribute("aria-hidden", "false");
                        overlay.classList.add("is-visible");
                        document.body.classList.add("wtm-cart-drawer-open");
                        btn.setAttribute("aria-expanded", "true");
                        fetchCartContents();
                        // Focus first element after animation.
                        setTimeout(function () {
                                var closeBtn = drawer.querySelector("[data-wtm-cart-drawer-close]");
                                if (closeBtn) closeBtn.focus();
                        }, 50);
                }

                function fetchCartContents() {
                        var body = drawer.querySelector("[data-wtm-cart-drawer-body]");
                        var footer = drawer.querySelector("[data-wtm-cart-drawer-footer]");
                        if (!body) return;
                        body.innerHTML = '<div class="wtm-cart-drawer__loading">' + (i18n.searching || "Chargement…") + '</div>';
                        if (footer) footer.hidden = true;

                        var restUrl = (config.restUrl || "") + "/mini-cart-contents";

                        fetch(restUrl, { credentials: "same-origin" })
                                .then(function (r) { return r.json(); })
                                .then(function (data) {
                                        renderCartContents(data, body, footer);
                                })
                                .catch(function () {
                                        body.innerHTML = '<div class="wtm-widget-empty">' + (i18n.cartEmpty || "Erreur de chargement.") + '</div>';
                                });
                }

                function renderCartContents(data, body, footer) {
                                        if (!data || !data.items || !data.items.length) {
                                                body.innerHTML = '<div class="wtm-cart-drawer__empty">' + (i18n.cartEmpty || "Votre panier est vide.") + '</div>';
                                                if (footer) footer.hidden = true;
                                                return;
                                        }
                                        var html = '<ul class="wtm-cart-drawer__items">';
                                        data.items.forEach(function (it) {
                                                var img = it.thumbnail ? '<img src="' + it.thumbnail + '" alt="" class="wtm-cart-drawer__item-img" />' : '<span class="wtm-cart-drawer__item-img wtm-cart-drawer__item-img--placeholder"></span>';
                                                html += '<li class="wtm-cart-drawer__item">' +
                                                        '<a href="' + it.permalink + '" class="wtm-cart-drawer__item-media">' + img + '</a>' +
                                                        '<div class="wtm-cart-drawer__item-body">' +
                                                                '<a href="' + it.permalink + '" class="wtm-cart-drawer__item-name">' + escapeHtml(it.name) + '</a>' +
                                                                '<div class="wtm-cart-drawer__item-meta">' +
                                                                        '<span class="wtm-cart-drawer__item-qty">×' + it.quantity + '</span>' +
                                                                        '<span class="wtm-cart-drawer__item-price">' + it.price_html + '</span>' +
                                                                '</div>' +
                                                        '</div>' +
                                                '</li>';
                                        });
                                        html += '</ul>';
                                        body.innerHTML = html;

                                        if (footer) {
                                                footer.hidden = false;
                                                footer.innerHTML =
                                                        '<div class="wtm-cart-drawer__total"><span>' + (i18n.total || "Total") + ':</span> <strong>' + (data.total || "") + '</strong></div>' +
                                                        '<div class="wtm-cart-drawer__actions">' +
                                                                '<a href="' + (data.cart_url || "#") + '" class="wtm-cart-drawer__btn wtm-cart-drawer__btn--secondary">' + (i18n.viewCart || "Voir le panier") + '</a>' +
                                                                '<a href="' + (data.checkout_url || "#") + '" class="wtm-cart-drawer__btn wtm-cart-drawer__btn--primary">' + (i18n.checkout || "Commander") + '</a>' +
                                                        '</div>';
                                        }
                }

                function escapeHtml(s) {
                        return String(s == null ? "" : s)
                                .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
                                .replace(/"/g, "&quot;").replace(/'/g, "&#039;");
                }

                buttons.forEach(function (btn) {
                        if (btn.__wtmInit) return;
                        btn.__wtmInit = true;
                        btn.addEventListener("click", function () {
                                var isOpen = drawer.classList.contains("is-open");
                                if (isOpen) {
                                        closeDrawer();
                                } else {
                                        openDrawer(btn);
                                }
                        });
                });

                var closeBtn = drawer.querySelector("[data-wtm-cart-drawer-close]");
                if (closeBtn && !closeBtn.__wtmInit) {
                        closeBtn.__wtmInit = true;
                        closeBtn.addEventListener("click", closeDrawer);
                }
                if (!overlay.__wtmInit) {
                        overlay.__wtmInit = true;
                        overlay.addEventListener("click", closeDrawer);
                }
                document.addEventListener("keydown", function (e) {
                        if (e.key === "Escape" && drawer.classList.contains("is-open")) {
                                closeDrawer();
                        }
                });

                // Refresh drawer contents if open when WC fragments refresh.
                if (typeof jQuery !== "undefined") {
                        jQuery(document.body).on("wc_fragments_refreshed", function () {
                                if (drawer.classList.contains("is-open")) {
                                        fetchCartContents();
                                }
                        });
                }
        }

        // ========================================================================
        // v1.3.0 — Live search suggestions (data-wtm-live-search)
        // Fetches REST /wtm/v1/search-suggest?s=… and renders a dropdown.
        // ========================================================================

        function initLiveSearch() {
                var inputs = document.querySelectorAll("[data-wtm-live-search]");
                inputs.forEach(function (input) {
                        if (input.__wtmInit) return;
                        input.__wtmInit = true;

                        var form = input.closest("form");
                        var box = form ? form.querySelector("[data-wtm-suggestions]") : null;
                        if (!box) {
                                // Create one if missing.
                                box = document.createElement("div");
                                box.className = "wtm-search__suggestions";
                                box.setAttribute("data-wtm-suggestions", "");
                                box.setAttribute("role", "listbox");
                                if (form) form.appendChild(box);
                        }

                        var minChars = parseInt(input.getAttribute("data-min-chars") || "3", 10);
                        var timer = null;
                        var lastQuery = "";
                        var restUrl = (config.restUrl || "") + "/search-suggest";
                        var nonce = config.restNonce || "";

                        function clearBox() {
                                box.innerHTML = "";
                                box.classList.remove("is-open");
                        }

                        function renderLoading() {
                                box.innerHTML = '<div class="wtm-search__suggestion wtm-search__suggestion--loading">' + (i18n.searching || "Recherche…") + '</div>';
                                box.classList.add("is-open");
                        }

                        function renderResults(data) {
                                if (!data || !data.products || !data.products.length) {
                                        box.innerHTML = '<div class="wtm-search__suggestion wtm-search__suggestion--empty">' + (i18n.noResults || "Aucun produit trouvé.") + '</div>';
                                        box.classList.add("is-open");
                                        return;
                                }
                                var html = "";
                                data.products.forEach(function (p) {
                                        var img = p.thumbnail ? '<img src="' + p.thumbnail + '" alt="" class="wtm-search__suggestion-img" />' : '<span class="wtm-search__suggestion-img wtm-search__suggestion-img--placeholder"></span>';
                                        var sale = p.on_sale ? '<span class="wtm-search__suggestion-sale">' + (i18n.sale || "Promo") + '</span>' : '';
                                        html += '<a href="' + p.permalink + '" class="wtm-search__suggestion" role="option">' +
                                                '<span class="wtm-search__suggestion-media">' + img + sale + '</span>' +
                                                '<span class="wtm-search__suggestion-body">' +
                                                        '<span class="wtm-search__suggestion-title">' + escapeHtmlS(p.title) + '</span>' +
                                                        '<span class="wtm-search__suggestion-price">' + (p.price_html || "") + '</span>' +
                                                '</span>' +
                                        '</a>';
                                });
                                box.innerHTML = html;
                                box.classList.add("is-open");
                        }

                        function escapeHtmlS(s) {
                                return String(s == null ? "" : s)
                                        .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
                                        .replace(/"/g, "&quot;").replace(/'/g, "&#039;");
                        }

                        function doSearch(q) {
                                if (q === lastQuery) return;
                                lastQuery = q;
                                renderLoading();
                                var url = restUrl + "?s=" + encodeURIComponent(q) + "&limit=5";
                                var headers = { "Content-Type": "application/json" };
                                if (nonce) headers["X-WP-Nonce"] = nonce;
                                fetch(url, { credentials: "same-origin", headers: headers })
                                        .then(function (r) { return r.json(); })
                                        .then(function (data) { renderResults(data); })
                                        .catch(function () {
                                                box.innerHTML = '<div class="wtm-search__suggestion wtm-search__suggestion--empty">' + (i18n.noResults || "Erreur.") + '</div>';
                                                box.classList.add("is-open");
                                        });
                        }

                        input.addEventListener("input", function () {
                                var v = input.value.trim();
                                if (v.length < minChars) {
                                        clearBox();
                                        lastQuery = "";
                                        return;
                                }
                                if (timer) clearTimeout(timer);
                                timer = setTimeout(function () { doSearch(v); }, 250);
                        });

                        input.addEventListener("focus", function () {
                                if (box.children.length) box.classList.add("is-open");
                        });

                        input.addEventListener("blur", function () {
                                // Delay to allow click on suggestion.
                                setTimeout(function () { box.classList.remove("is-open"); }, 200);
                        });

                        // Keyboard navigation inside suggestions.
                        input.addEventListener("keydown", function (e) {
                                if (!box.classList.contains("is-open")) return;
                                var items = box.querySelectorAll(".wtm-search__suggestion[href]");
                                if (!items.length) return;
                                var idx = -1;
                                items.forEach(function (it, i) {
                                        if (it === document.activeElement) idx = i;
                                });
                                if (e.key === "ArrowDown") {
                                        e.preventDefault();
                                        var next = idx + 1;
                                        if (next >= items.length) next = 0;
                                        items[next].focus();
                                } else if (e.key === "ArrowUp") {
                                        e.preventDefault();
                                        var prev = idx - 1;
                                        if (prev < 0) prev = items.length - 1;
                                        items[prev].focus();
                                } else if (e.key === "Escape") {
                                        clearBox();
                                        input.focus();
                                }
                        });
                });
        }

        // ========================================================================
        // v1.3.0 — Newsletter form handler (data-wtm-newsletter)
        // POSTs to admin-ajax.php?action=wtm_newsletter_subscribe.
        // ========================================================================

        function initNewsletter() {
                var forms = document.querySelectorAll("[data-wtm-newsletter]");
                forms.forEach(function (form) {
                        if (form.__wtmInit) return;
                        form.__wtmInit = true;

                        var provider = form.getAttribute("data-provider") || "internal";
                        var listId = form.getAttribute("data-list-id") || "";
                        var nonce = form.getAttribute("data-nonce") || config.newsletterNonce || "";
                        var msgEl = form.querySelector("[data-wtm-newsletter-message]");
                        var configScript = form.querySelector("[data-wtm-newsletter-config]");
                        var successMsg = (i18n.subscribed || "Merci !");
                        if (configScript) {
                                try {
                                        var cfg = JSON.parse(configScript.textContent);
                                        if (cfg && cfg.success) successMsg = cfg.success;
                                } catch (e) {}
                        }
                        var btn = form.querySelector("button[type=submit]");
                        var btnLabel = btn ? btn.textContent : "";

                        form.addEventListener("submit", function (e) {
                                e.preventDefault();
                                var emailInput = form.querySelector('input[name="email"]');
                                if (!emailInput) return;
                                var email = emailInput.value.trim();

                                if (!isValidEmail(email)) {
                                        showMsg(i18n.invalidEmail || "Email invalide.", "error");
                                        return;
                                }

                                if (btn) { btn.disabled = true; btn.textContent = (i18n.subscribing || "Inscription…"); }

                                var formData = new FormData();
                                formData.append("action", "wtm_newsletter_subscribe");
                                formData.append("email", email);
                                formData.append("provider", provider);
                                formData.append("list_id", listId);
                                formData.append("nonce", nonce);

                                fetch(config.ajaxUrl, {
                                        method: "POST",
                                        credentials: "same-origin",
                                        body: formData
                                })
                                        .then(function (r) { return r.json(); })
                                        .then(function (data) {
                                                if (data && data.success) {
                                                        showMsg((data.data && data.data.message) || successMsg, "success");
                                                        form.reset();
                                                } else {
                                                        var msg = (data && data.data && data.data.message) || (i18n.error || "Erreur.");
                                                        showMsg(msg, "error");
                                                }
                                        })
                                        .catch(function () {
                                                showMsg(i18n.error || "Erreur réseau.", "error");
                                        })
                                        .finally(function () {
                                                if (btn) { btn.disabled = false; btn.textContent = btnLabel; }
                                        });
                        });

                        function showMsg(text, type) {
                                if (!msgEl) return;
                                msgEl.textContent = text;
                                msgEl.className = "wtm-newsletter__message is-" + type;
                                msgEl.hidden = false;
                        }

                        function isValidEmail(v) {
                                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
                        }
                });
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
                        initCartDrawer();
                        initLiveSearch();
                        initNewsletter();
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
