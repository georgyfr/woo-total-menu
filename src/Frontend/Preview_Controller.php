<?php
/**
 * Preview Controller — serves a self-contained HTML page that renders a
 * WTM menu configuration inside an iframe, driven by postMessage from the
 * React builder (spec §6.3, §6.6, §15.4).
 *
 * The iframe receives messages of the form:
 *
 *     { type: 'wtm-render', config: {…}, device: 'desktop' }
 *
 * and re-renders the menu HTML without reloading the iframe. This avoids a
 * full HTTP round-trip on every change and keeps the preview responsive.
 *
 * === Why admin-ajax.php instead of REST? ===
 *
 * An `<iframe src="…">` browser request can only send cookies, NOT custom
 * headers like `X-WP-Nonce`. WP REST requires the nonce header for cookie
 * auth (to prevent CSRF), so a plain iframe src to a REST URL gets 401.
 *
 * admin-ajax.php, on the other hand, authenticates logged-in users via the
 * `wordpress_logged_in_*` cookie alone (the `wp_ajax_*` hook fires only for
 * authenticated users). This is exactly what we need for the iframe.
 *
 * Endpoint: `POST/GET /wp-admin/admin-ajax.php?action=wtm_preview_frame`
 *
 * @package WooTotalMenu
 * @since 1.1.4
 */

namespace WooTotalMenu\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class Preview_Controller
 *
 * Hooks into `wp_ajax_wtm_preview_frame` to serve the iframe HTML document
 * to authenticated admin users.
 */
class Preview_Controller {

        const AJAX_ACTION = 'wtm_preview_frame';
        const CAPABILITY  = 'wtm_manage_menus';

        /**
         * Constructor — registers the admin-ajax hook.
         */
        public function __construct() {
                // Fires for logged-in users only (wp_ajax_nopriv_* is for guests).
                add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'render_frame' ) );
        }

        /**
         * Build the AJAX endpoint URL.
         *
         * @return string
         */
        public static function get_endpoint_url() {
                return admin_url( 'admin-ajax.php?action=' . self::AJAX_ACTION );
        }

        /**
         * Render the iframe HTML document (admin-ajax handler).
         *
         * Sends a complete HTML page (text/html) with:
         *  - basic styles for the menu
         *  - a postMessage listener that re-renders the menu on demand
         *  - an initial "waiting for menu…" state
         *
         * @return void  Outputs HTML and dies.
         */
        public function render_frame() {
                // Permission check — even though wp_ajax_* only fires for
                // logged-in users, we still verify the capability.
                if ( ! current_user_can( self::CAPABILITY ) ) {
                        status_header( 403 );
                        wp_die( esc_html__( 'Permission denied.', 'woo-total-menu' ) );
                }

                // Force HTML content-type (admin-ajax defaults to text/html anyway
                // but we set it explicitly for clarity + caching headers).
                header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );
                header( 'X-Frame-Options: SAMEORIGIN' );
                header( 'Cache-Control: no-cache, no-store, must-revalidate' );

                $plugin_url  = WTM_PLUGIN_URL;
                $plugin_ver  = WTM_VERSION;
                $default_bg  = '#FFFFFF';
                $primary     = '#6C5CE7';
                $text_color  = '#2D3436';

                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — HTML template, all dynamic values escaped below.
                echo $this->get_frame_html( $plugin_url, $plugin_ver, $primary, $default_bg, $text_color );
                exit;
        }

        /**
         * Build the iframe HTML document.
         *
         * @param string $plugin_url Plugin URL (for asset references).
         * @param string $plugin_ver Plugin version (cache-busting).
         * @param string $primary    Primary brand color.
         * @param string $bg         Background color.
         * @param string $text_color Text color.
         * @return string Full HTML document.
         */
        private function get_frame_html( $plugin_url, $plugin_ver, $primary, $bg, $text_color ) {
                $plugin_url = esc_url_raw( $plugin_url );
                $plugin_ver = esc_attr( $plugin_ver );
                $primary    = esc_attr( $primary );
                $bg         = esc_attr( $bg );
                $text_color = esc_attr( $text_color );

                // Inline JS — kept minimal so the iframe loads fast (<5 KB).
                // It listens for postMessage events from the parent and renders
                // the menu HTML by walking the items tree.
                $script = <<<'JS'
(function () {
  "use strict";

  var MESSAGES = {
    empty: "Menu vide — ajoutez des éléments dans l'arborescence.",
    emptyLayout: "Layout vide — ajoutez une ligne et des modules.",
    waiting: "En attente de la configuration…",
    error: "Configuration invalide.",
  };

  function el(tag, attrs, children) {
    var node = document.createElement(tag);
    if (attrs) {
      for (var k in attrs) {
        if (!Object.prototype.hasOwnProperty.call(attrs, k)) continue;
        if (k === "className") node.className = attrs[k];
        else if (k === "textContent") node.textContent = attrs[k];
        else if (k === "innerHTML") node.innerHTML = attrs[k];
        else if (k.indexOf("on") === 0 && typeof attrs[k] === "function") {
          node.addEventListener(k.slice(2), attrs[k]);
        } else if (attrs[k] != null) {
          node.setAttribute(k, attrs[k]);
        }
      }
    }
    if (children) {
      if (!Array.isArray(children)) children = [children];
      children.forEach(function (c) {
        if (c == null) return;
        if (typeof c === "string" || typeof c === "number") {
          node.appendChild(document.createTextNode(String(c)));
        } else {
          node.appendChild(c);
        }
      });
    }
    return node;
  }

  // Map WTM item types → icon glyph (WordPress dashicons font).
  var ICON = {
    link: "\\f103",
    mega_container: "\\f180",
    column: "\\f479",
    widget: "\\f111",
    title: "\\f215",
    separator: "\\f460",
    accordion_parent: "\\f347",
  };

  function renderLink(item, depth) {
    var li = el("li", { className: "wtm-pv-item wtm-pv-item--link", "data-depth": depth });
    var a = el("a", {
      className: "wtm-pv-link",
      href: item.url || "#",
      textContent: item.label || item.type,
    });
    if (item.badge && item.badge.text) {
      a.appendChild(el("span", { className: "wtm-pv-badge", textContent: item.badge.text }));
    }
    li.appendChild(a);
    if (item.children && item.children.length > 0) {
      li.appendChild(renderChildren(item.children, depth + 1));
    }
    return li;
  }

  function renderMegaContainer(item, depth) {
    var li = el("li", { className: "wtm-pv-item wtm-pv-item--mega", "data-depth": depth });
    var trigger = el("button", {
      className: "wtm-pv-trigger",
      type: "button",
      textContent: item.label || "Méga menu",
    });
    li.appendChild(trigger);
    if (item.children && item.children.length > 0) {
      var panel = el("div", { className: "wtm-pv-mega-panel" });
      var row = el("div", { className: "wtm-pv-mega-row" });
      item.children.forEach(function (col) {
        row.appendChild(renderColumn(col, depth + 1));
      });
      panel.appendChild(row);
      li.appendChild(panel);
    }
    return li;
  }

  function renderColumn(col, depth) {
    var colEl = el("div", { className: "wtm-pv-col" });
    if (col.children && col.children.length > 0) {
      var ul = el("ul", { className: "wtm-pv-list" });
      col.children.forEach(function (child) {
        ul.appendChild(renderItem(child, depth + 1));
      });
      colEl.appendChild(ul);
    }
    return colEl;
  }

  function renderItem(item, depth) {
    if (item.type === "separator") {
      return el("li", { className: "wtm-pv-separator", role: "separator" });
    }
    if (item.type === "title") {
      return el("li", {
        className: "wtm-pv-title",
        "data-depth": depth,
        textContent: item.label || "",
      });
    }
    if (item.type === "mega_container") return renderMegaContainer(item, depth);
    if (item.type === "widget") return renderWidget(item, depth);
    // link, accordion_parent, default
    return renderLink(item, depth);
  }

  function renderWidget(item, depth) {
    var li = el("li", { className: "wtm-pv-item wtm-pv-item--widget", "data-depth": depth });
    var widgetType = item.widget_type || "html";
    var label = item.label || "Widget " + widgetType;
    var body;
    if (widgetType === "html" && item.settings && item.settings.html) {
      body = el("div", { className: "wtm-pv-widget-body wtm-pv-widget-body--html", innerHTML: item.settings.html });
    } else if (widgetType === "banner") {
      body = el("div", { className: "wtm-pv-widget-body wtm-pv-widget-body--banner" },
        el("div", { className: "wtm-pv-widget-banner", textContent: label })
      );
    } else if (widgetType === "product_grid") {
      body = el("div", { className: "wtm-pv-widget-body wtm-pv-widget-body--products" });
      for (var i = 0; i < 4; i++) {
        body.appendChild(el("div", { className: "wtm-pv-product-card" },
          el("div", { className: "wtm-pv-product-img" }),
          el("div", { className: "wtm-pv-product-name", textContent: "Produit " + (i + 1) }),
          el("div", { className: "wtm-pv-product-price", textContent: "0,00 €" })
        ));
      }
    } else if (widgetType === "category_grid") {
      body = el("div", { className: "wtm-pv-widget-body wtm-pv-widget-body--cats" });
      for (var j = 0; j < 4; j++) {
        body.appendChild(el("div", { className: "wtm-pv-cat-card", textContent: "Catégorie " + (j + 1) }));
      }
    } else if (widgetType === "mini_cart") {
      body = el("div", { className: "wtm-pv-widget-body wtm-pv-widget-body--cart" },
        el("span", { className: "wtm-pv-cart-icon", textContent: "🛒" }),
        el("span", { className: "wtm-pv-cart-count", textContent: "0 article" })
      );
    } else if (widgetType === "search") {
      body = el("div", { className: "wtm-pv-widget-body wtm-pv-widget-body--search" },
        el("input", { type: "search", placeholder: "Rechercher…", className: "wtm-pv-search-input" })
      );
    } else {
      body = el("div", { className: "wtm-pv-widget-body", textContent: "[" + widgetType + "]" });
    }
    li.appendChild(el("div", { className: "wtm-pv-widget-label", textContent: label }));
    li.appendChild(body);
    return li;
  }

  function renderChildren(children, depth) {
    var ul = el("ul", { className: "wtm-pv-list wtm-pv-list--sub", "data-depth": depth });
    children.forEach(function (child) { ul.appendChild(renderItem(child, depth)); });
    return ul;
  }

  function renderHorizontal(menu) {
    var items = (menu.config && menu.config.items) || [];
    var nav = el("nav", { className: "wtm-pv-nav", "aria-label": menu.title || "Aperçu" });
    var ul = el("ul", { className: "wtm-pv-list wtm-pv-list--root" });
    items.forEach(function (item) { ul.appendChild(renderItem(item, 0)); });
    nav.appendChild(ul);
    return nav;
  }

  function renderVertical(menu) {
    var items = (menu.config && menu.config.items) || [];
    var nav = el("nav", { className: "wtm-pv-nav wtm-pv-nav--vertical", "aria-label": menu.title || "Aperçu" });
    var ul = el("ul", { className: "wtm-pv-list wtm-pv-list--root wtm-pv-list--vertical" });
    items.forEach(function (item) { ul.appendChild(renderItem(item, 0)); });
    nav.appendChild(ul);
    return nav;
  }

  function renderMenu(menu) {
    if (!menu || !menu.config) {
      return el("div", { className: "wtm-pv-empty", textContent: MESSAGES.waiting });
    }
    var items = menu.config.items || [];
    if (!items.length) {
      return el("div", { className: "wtm-pv-empty", textContent: MESSAGES.empty });
    }
    return menu.menu_type === "vertical" ? renderVertical(menu) : renderHorizontal(menu);
  }

  // === Header / Footer layout rendering (v1.4.0) ===
  // Walks rows -> columns -> modules and produces a visual approximation
  // of what the live site will render. Module renderers are deliberately
  // lightweight (no WC cart fragments, no AJAX) — the iframe is just a preview.
  function renderModule(mod) {
    if (!mod || !mod.type) return el("div", { className: "wtm-pv-empty", textContent: "[module]" });
    var s = mod.settings || {};
    var inner;
    switch (mod.type) {
      case "logo":
        inner = el("a", { className: "wtm-pv-logo", href: s.url || "#", textContent: s.alt || "Logo" });
        break;
      case "menu":
        var menuLabel = s.menu_source === "wp"
          ? "[Menu WP #" + (s.menu_id || "?") + "]"
          : "[Menu WTM #" + (s.menu_id || "?") + "]";
        inner = el("div", { className: "wtm-pv-mod-menu", textContent: menuLabel });
        break;
      case "search":
        inner = el("input", { type: "search", className: "wtm-pv-search-input", placeholder: s.placeholder || "Rechercher…" });
        break;
      case "cart":
        inner = el("div", { className: "wtm-pv-mod-cart" }, [
          el("span", { className: "wtm-pv-cart-icon", textContent: "\uD83D\uDED2" }),
          el("span", { className: "wtm-pv-cart-count", textContent: "0" })
        ]);
        break;
      case "button":
        inner = el("a", { className: "wtm-pv-button wtm-pv-button--" + (s.style || "primary"), href: s.url || "#", textContent: s.text || "Bouton" });
        break;
      case "html":
        inner = el("div", { className: "wtm-pv-html", innerHTML: s.content || "" });
        break;
      case "social":
        var links = s.links || [];
        inner = el("div", { className: "wtm-pv-social" });
        links.forEach(function (l) {
          inner.appendChild(el("span", { className: "wtm-pv-social-link wtm-pv-social-link--" + (l.network || ""), textContent: (l.network || "social").charAt(0).toUpperCase() }));
        });
        break;
      case "newsletter":
        inner = el("form", { className: "wtm-pv-newsletter", onsubmit: function (e) { e.preventDefault(); } }, [
          el("input", { type: "email", className: "wtm-pv-newsletter-input", placeholder: s.placeholder || "Email" }),
          el("button", { type: "submit", className: "wtm-pv-newsletter-btn", textContent: s.button_text || "S'abonner" })
        ]);
        break;
      case "text":
        var txt = (s.content || "").replace(/\[year\]/g, new Date().getFullYear());
        inner = el("div", { className: "wtm-pv-text", textContent: txt });
        break;
      default:
        inner = el("div", { className: "wtm-pv-empty", textContent: "[" + mod.type + "]" });
    }
    return el("div", { className: "wtm-pv-module wtm-pv-module--" + mod.type }, [inner]);
  }

  function renderColumn(col) {
    var colEl = el("div", { className: "wtm-pv-hf-col" });
    if (col.width) colEl.style.flex = "0 0 " + (col.width / 12 * 100) + "%";
    var mods = col.modules || [];
    mods.forEach(function (mod) { colEl.appendChild(renderModule(mod)); });
    return colEl;
  }

  function renderRow(row) {
    var rowEl = el("div", { className: "wtm-pv-hf-row" });
    var settings = row.settings || {};
    if (settings.background) rowEl.style.background = settings.background;
    if (settings.height) rowEl.style.minHeight = settings.height + "px";
    if (settings.padding_y) { rowEl.style.paddingTop = settings.padding_y + "px"; rowEl.style.paddingBottom = settings.padding_y + "px"; }
    if (settings.align) {
      var alignMap = { left: "flex-start", center: "center", right: "flex-end", "space-between": "space-between" };
      rowEl.style.justifyContent = alignMap[settings.align] || "space-between";
    }
    var cols = row.columns || [];
    cols.forEach(function (col) { rowEl.appendChild(renderColumn(col)); });
    return rowEl;
  }

  function renderLayout(payload, type) {
    var config = payload.config || payload;
    var rows = (config && config.rows) || [];
    if (!rows.length) {
      return el("div", { className: "wtm-pv-empty", textContent: MESSAGES.emptyLayout });
    }
    var tag = type === "footer" ? "footer" : "header";
    var wrapper = el(tag, { className: "wtm-pv-hf wtm-pv-hf--" + type, "aria-label": type === "footer" ? "Pied de page" : "En-tête" });
    rows.forEach(function (row) { wrapper.appendChild(renderRow(row)); });
    return wrapper;
  }

  function render(payload) {
    var root = document.getElementById("wtm-preview-root");
    root.innerHTML = "";
    try {
      // Dispatch based on `mode` (explicit) or auto-detect by structure.
      var mode = payload && payload.mode;
      if (!mode && payload && payload.config && payload.config.rows) {
        mode = "header"; // default for layout configs
      }
      var node;
      if (mode === "header" || mode === "footer") {
        node = renderLayout(payload, mode);
      } else {
        node = renderMenu(payload);
      }
      root.appendChild(node);
    } catch (err) {
      root.appendChild(el("div", { className: "wtm-pv-error", textContent: MESSAGES.error + " " + err.message }));
    }
  }

  // === postMessage bridge ===
  // Listen for messages from the parent window (the React builder).
  // Only accept messages from the same origin (admin URL).
  function onMessage(event) {
    if (event.source !== window.parent) return;
    var data = event.data || {};
    if (data.type !== "wtm-render") return;
    // Set device class on <body> for responsive preview
    if (data.device) {
      document.body.className = "wtm-pv-device--" + data.device;
    }
    render(data.config || data.menu || null);
  }

  window.addEventListener("message", onMessage);

  // Signal to parent that the iframe is ready to receive messages.
  function signalReady() {
    if (window.parent && window.parent !== window) {
      window.parent.postMessage({ type: "wtm-preview-ready", src: "wtm" }, "*");
    }
  }

  // Initial state — waiting for first message.
  document.addEventListener("DOMContentLoaded", function () {
    render(null);
    signalReady();
  });
})();
JS;

                // Inline CSS — minimal, scoped under #wtm-preview-root.
                // Uses WordPress dashicons font (loaded by parent admin theme,
                // but we re-link it inside the iframe for safety).
                $css = <<<CSS
:root {
  --wtm-pv-primary: {$primary};
  --wtm-pv-bg: {$bg};
  --wtm-pv-text: {$text_color};
}
* { box-sizing: border-box; }
html, body {
  margin: 0;
  padding: 0;
  background: var(--wtm-pv-bg);
  color: var(--wtm-pv-text);
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
  font-size: 14px;
  line-height: 1.5;
}
body { padding: 16px 20px; }
#wtm-preview-root { min-height: 200px; }
.wtm-pv-empty, .wtm-pv-error {
  padding: 24px;
  text-align: center;
  color: #6b7280;
  font-style: italic;
  background: #f9fafb;
  border: 1px dashed #e5e7eb;
  border-radius: 6px;
}
.wtm-pv-error { color: #FF7675; border-color: #fecaca; background: #fef2f2; }
.wtm-pv-nav { background: var(--wtm-pv-bg); }
.wtm-pv-list { list-style: none; margin: 0; padding: 0; display: flex; gap: 4px; align-items: stretch; }
.wtm-pv-list--vertical { flex-direction: column; gap: 0; }
.wtm-pv-list--sub { flex-direction: column; position: absolute; top: 100%; left: 0; min-width: 240px; background: #fff; border: 1px solid #e5e7eb; border-radius: 0 0 6px 6px; box-shadow: 0 8px 24px rgba(0,0,0,0.08); padding: 8px 0; z-index: 10; display: none; }
.wtm-pv-item:hover > .wtm-pv-list--sub,
.wtm-pv-item:focus-within > .wtm-pv-list--sub { display: block; }
.wtm-pv-list--vertical .wtm-pv-list--sub { position: static; box-shadow: none; border: none; padding-left: 12px; }
.wtm-pv-item { position: relative; }
.wtm-pv-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 10px 14px;
  color: var(--wtm-pv-text);
  text-decoration: none;
  border-radius: 4px;
  font-weight: 500;
  transition: background 0.15s, color 0.15s;
}
.wtm-pv-link:hover { background: rgba(108, 92, 231, 0.08); color: var(--wtm-pv-primary); }
.wtm-pv-badge {
  background: var(--wtm-pv-primary);
  color: #fff;
  font-size: 11px;
  padding: 2px 6px;
  border-radius: 10px;
  font-weight: 600;
}
.wtm-pv-trigger {
  display: inline-flex;
  align-items: center;
  padding: 10px 14px;
  background: transparent;
  border: none;
  cursor: pointer;
  font-weight: 600;
  color: var(--wtm-pv-text);
  font-family: inherit;
  font-size: 14px;
}
.wtm-pv-trigger::after {
  content: "▾";
  margin-left: 6px;
  font-size: 10px;
  color: #6b7280;
}
.wtm-pv-mega-panel {
  position: absolute;
  top: 100%;
  left: 0;
  min-width: 600px;
  max-width: 900px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 0 0 8px 8px;
  box-shadow: 0 12px 32px rgba(0,0,0,0.10);
  padding: 16px;
  z-index: 20;
  display: none;
}
.wtm-pv-item:hover > .wtm-pv-mega-panel,
.wtm-pv-item:focus-within > .wtm-pv-mega-panel { display: block; }
.wtm-pv-mega-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; }
.wtm-pv-col { min-width: 0; }
.wtm-pv-list--vertical .wtm-pv-list--root { flex-direction: column; }
.wtm-pv-title {
  list-style: none;
  padding: 6px 14px;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: #6b7280;
}
.wtm-pv-separator {
  list-style: none;
  height: 1px;
  background: #e5e7eb;
  margin: 4px 8px;
}
.wtm-pv-item--widget { padding: 4px 0; }
.wtm-pv-widget-label {
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: #9ca3af;
  margin-bottom: 4px;
  padding: 0 14px;
}
.wtm-pv-widget-body { padding: 0 14px; }
.wtm-pv-widget-body--html { font-size: 13px; color: var(--wtm-pv-text); }
.wtm-pv-widget-body--banner {
  background: linear-gradient(135deg, var(--wtm-pv-primary), #8b7bff);
  color: #fff;
  padding: 12px 16px;
  border-radius: 6px;
  font-weight: 600;
}
.wtm-pv-widget-body--products { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; padding: 0; }
.wtm-pv-product-card {
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  padding: 8px;
  text-align: center;
}
.wtm-pv-product-img { height: 60px; background: #f3f4f6; border-radius: 4px; margin-bottom: 6px; }
.wtm-pv-product-name { font-size: 12px; color: var(--wtm-pv-text); }
.wtm-pv-product-price { font-size: 12px; color: var(--wtm-pv-primary); font-weight: 600; margin-top: 2px; }
.wtm-pv-widget-body--cats { display: grid; grid-template-columns: repeat(2, 1fr); gap: 6px; }
.wtm-pv-cat-card {
  background: #f3f4f6;
  padding: 8px 10px;
  border-radius: 4px;
  font-size: 12px;
  text-align: center;
}
.wtm-pv-widget-body--cart { display: flex; align-items: center; gap: 6px; padding: 6px 14px; font-size: 13px; }
.wtm-pv-cart-count { color: #6b7280; }
.wtm-pv-widget-body--search { padding: 6px 14px; }
.wtm-pv-search-input {
  width: 100%;
  padding: 6px 10px;
  border: 1px solid #d1d5db;
  border-radius: 4px;
  font-size: 13px;
  font-family: inherit;
}
/* Device modes — the iframe body adapts its width via parent CSS, but we
   also tune typography slightly for mobile. */
body.wtm-pv-device--mobile .wtm-pv-list--root { flex-direction: column; }
body.wtm-pv-device--mobile .wtm-pv-list--sub { position: static; box-shadow: none; border: none; padding-left: 12px; }
body.wtm-pv-device--mobile .wtm-pv-mega-panel { position: static; box-shadow: none; border: none; min-width: 0; padding: 8px 0; }
body.wtm-pv-device--mobile .wtm-pv-mega-row { grid-template-columns: 1fr; }
body.wtm-pv-device--tablet .wtm-pv-mega-panel { min-width: 500px; }

/* === Header/Footer layout preview (v1.4.0) === */
.wtm-pv-hf { display: flex; flex-direction: column; gap: 0; background: var(--wtm-pv-bg); width: 100%; }
.wtm-pv-hf--footer { background: #1E293B; color: #fff; }
.wtm-pv-hf--footer .wtm-pv-empty { color: #94a3b8; background: transparent; border-color: #334155; }
.wtm-pv-hf-row { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; padding: 12px 16px; min-height: 60px; background: var(--wtm-pv-bg); border-bottom: 1px solid #e5e7eb; }
.wtm-pv-hf--footer .wtm-pv-hf-row { background: #1E293B; border-bottom-color: #334155; color: #fff; }
.wtm-pv-hf-col { display: flex; flex-direction: column; gap: 6px; min-width: 0; padding: 4px 6px; }
.wtm-pv-module { display: inline-flex; align-items: center; gap: 4px; padding: 2px 4px; border: 1px dashed transparent; border-radius: 4px; }
.wtm-pv-module:hover { border-color: var(--wtm-pv-primary); }
.wtm-pv-logo { font-weight: 700; font-size: 16px; color: var(--wtm-pv-text); text-decoration: none; }
.wtm-pv-hf--footer .wtm-pv-logo { color: #fff; }
.wtm-pv-mod-menu { font-size: 12px; color: #6b7280; padding: 4px 8px; background: #f3f4f6; border-radius: 4px; }
.wtm-pv-hf--footer .wtm-pv-mod-menu { background: #334155; color: #cbd5e1; }
.wtm-pv-mod-cart { display: inline-flex; align-items: center; gap: 6px; font-size: 14px; }
.wtm-pv-button { display: inline-block; padding: 6px 14px; border-radius: 4px; font-size: 13px; font-weight: 600; text-decoration: none; }
.wtm-pv-button--primary { background: var(--wtm-pv-primary); color: #fff; }
.wtm-pv-button--secondary { background: #e5e7eb; color: var(--wtm-pv-text); }
.wtm-pv-button--ghost { background: transparent; border: 1px solid currentColor; color: var(--wtm-pv-text); }
.wtm-pv-html { font-size: 13px; color: var(--wtm-pv-text); }
.wtm-pv-text { font-size: 13px; color: var(--wtm-pv-text); }
.wtm-pv-hf--footer .wtm-pv-text { color: #cbd5e1; }
.wtm-pv-social { display: inline-flex; gap: 4px; }
.wtm-pv-social-link { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; background: rgba(255,255,255,0.15); color: #fff; font-size: 12px; font-weight: 700; }
.wtm-pv-newsletter { display: inline-flex; gap: 4px; }
.wtm-pv-newsletter-input { padding: 6px 10px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 13px; min-width: 140px; }
.wtm-pv-newsletter-btn { padding: 6px 14px; background: var(--wtm-pv-primary); color: #fff; border: none; border-radius: 4px; font-size: 13px; font-weight: 600; cursor: pointer; }
/* Mobile layout preview: stack columns vertically. */
body.wtm-pv-device--mobile .wtm-pv-hf-row { flex-direction: column; align-items: stretch; }
body.wtm-pv-device--mobile .wtm-pv-hf-col { width: 100% !important; flex: 0 0 100% !important; }
CSS;

                return <<<HTML
<!DOCTYPE html>
<html lang="{$this->get_locale()}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>WTM Preview</title>
<style>{$css}</style>
</head>
<body>
<div id="wtm-preview-root" role="region" aria-label="Aperçu du menu"></div>
<script>{$script}</script>
</body>
</html>
HTML;
        }

        /**
         * Get the current locale for the HTML lang attribute.
         *
         * @return string
         */
        private function get_locale() {
                $locale = get_locale();
                return $locale ? esc_attr( $locale ) : 'fr-FR';
        }
}
