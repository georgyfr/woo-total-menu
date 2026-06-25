/**
 * PreviewPanel — center column showing a live preview of the menu inside an
 * iframe, driven by postMessage (spec §6.3, §6.6, §15.4).
 *
 * v1.1.4 — first functional version (the v1.1.0 stub is replaced):
 *
 *  - The iframe loads a self-contained HTML document served by the REST
 *    endpoint `/wtm/v1/preview-frame` (Preview_Controller.php). This document
 *    contains its own minimal CSS + a JS postMessage listener that re-renders
 *    the menu HTML on demand.
 *  - Whenever the menu config OR the active device changes, the builder
 *    sends a `{ type: 'wtm-render', config, device }` message to the iframe.
 *  - Updates are debounced by 250 ms (spec §6.6 — "postMessage debounced
 *    200-300 ms") so that rapid drag operations do not flood the iframe.
 *  - Device modes (desktop/tablet/mobile) are reflected by:
 *      (a) adjusting the iframe CSS width via the parent frame
 *      (b) sending the `device` field so the iframe can adjust its layout
 *          (e.g. stack items vertically on mobile).
 *  - The iframe signals readiness via a `wtm-preview-ready` message back to
 *    the parent. We use this to send the initial render immediately.
 *
 * @package WooTotalMenu
 * @since 1.1.4
 */

import { useState, useEffect, useRef, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';

import { WTM_STORE_NAME } from '../stores/menu';
import { UI_STORE_NAME } from '../stores/ui';

// Spec §6.6 — postMessage debounced 200-300 ms.
const DEBOUNCE_MS = 250;

// Device widths (used for the iframe container, NOT for the iframe itself —
// the iframe is always 100% wide; the container clips/resizes the view).
const DEVICE_WIDTH = {
        desktop: '100%',
        tablet: '768px',
        mobile: '375px',
};

export default function PreviewPanel() {
        const menu = useSelect((select) => select(WTM_STORE_NAME).getMenu(), []);
        const device = useSelect((select) => select(UI_STORE_NAME).getDevice(), []);
        const isLoading = useSelect((select) => select(WTM_STORE_NAME).isLoading(), []);
        const previewFrameUrl = useSelect(
                (select) => select(UI_STORE_NAME).getPreviewFrameUrl(),
                []
        );
        // v1.1.5 — when set, the preview shows this revision instead of the live config.
        const previewRevisionId = useSelect(
                (select) => select(UI_STORE_NAME).getPreviewRevisionId(),
                []
        );
        const revisions = useSelect((select) => select(WTM_STORE_NAME).getRevisions(), []);

        const iframeRef = useRef(null);
        const sendTimerRef = useRef(null);
        const readyRef = useRef(false);
        const lastConfigRef = useRef(null);
        const [iframeLoaded, setIframeLoaded] = useState(false);

        // === Send the current menu config + device to the iframe via postMessage ===
        const sendToIframe = useCallback((configToSend, deviceToSend) => {
                const frame = iframeRef.current;
                if (!frame || !frame.contentWindow) return;
                if (!readyRef.current) return; // iframe not ready yet
                frame.contentWindow.postMessage(
                        {
                                type: 'wtm-render',
                                config: configToSend,
                                device: deviceToSend,
                        },
                        window.location.origin
                );
        }, []);

        // === Listen for the iframe's "ready" signal ===
        useEffect(() => {
                const onMessage = (event) => {
                        if (event.source !== iframeRef.current?.contentWindow) return;
                        if (event.data?.type === 'wtm-preview-ready') {
                                readyRef.current = true;
                                // Send the current state immediately (no debounce) so
                                // the iframe shows something as soon as it loads.
                                if (menu) {
                                        sendToIframe(menu, device);
                                }
                        }
                };
                window.addEventListener('message', onMessage);
                return () => window.removeEventListener('message', onMessage);
        }, [menu, device, sendToIframe]);

        // === v1.1.5 — Compute the effective config: live menu OR previewed revision ===
        const previewRevision = previewRevisionId
                ? revisions.find((r) => r.id === previewRevisionId)
                : null;
        const effectiveConfig = previewRevision?.config || menu?.config;

        // === Debounced postMessage on menu or device change ===
        useEffect(() => {
                if (!menu || !effectiveConfig) return;
                // Skip if config hasn't changed (deep-ish compare via JSON).
                const snapshot = JSON.stringify({
                        config: effectiveConfig,
                        device,
                        previewRevisionId,
                });
                if (lastConfigRef.current === snapshot) return;
                lastConfigRef.current = snapshot;

                if (sendTimerRef.current) clearTimeout(sendTimerRef.current);
                sendTimerRef.current = setTimeout(() => {
                        sendToIframe({ ...menu, config: effectiveConfig }, device);
                }, DEBOUNCE_MS);

                return () => {
                        if (sendTimerRef.current) clearTimeout(sendTimerRef.current);
                };
        }, [menu, effectiveConfig, device, previewRevisionId, sendToIframe]);

        // === When device changes, also send immediately (no debounce) so the
        // device class on <body> updates instantly for the user.
        useEffect(() => {
                if (menu && readyRef.current) {
                        sendToIframe(menu, device);
                }
                // eslint-disable-next-line react-hooks/exhaustive-deps
        }, [device]);

        const handleIframeLoad = () => {
                setIframeLoaded(true);
        };

        // === Render ===
        const showIframe = previewFrameUrl && !isLoading;
        const showEmptyState = !menu && !isLoading;
        const showLoading = isLoading;

        return (
                <div className="wtm-preview">
                        <div className="wtm-preview__header">
                                <h2>
                                        <span className="dashicons dashicons-visibility"></span>
                                        {__('Aperçu', 'woo-total-menu')}
                                </h2>
                                {previewRevisionId && (
                                        <span className="wtm-preview__revision-pill">
                                                <span className="dashicons dashicons-backup"></span>
                                                {__('Révision', 'woo-total-menu')} #{previewRevisionId}
                                        </span>
                                )}
                                <span className="wtm-preview__device-pill">
                                        {device === 'desktop' && __('Bureau', 'woo-total-menu')}
                                        {device === 'tablet' && __('Tablette', 'woo-total-menu')}
                                        {device === 'mobile' && __('Mobile', 'woo-total-menu')}
                                </span>
                        </div>

                        <div className="wtm-preview__body">
                                <div
                                        className={`wtm-preview__frame wtm-preview__frame--${device}`}
                                        style={{ maxWidth: DEVICE_WIDTH[device] }}
                                >
                                        {showLoading ? (
                                                <div className="wtm-preview__loading">
                                                        <span className="dashicons dashicons-update spin"></span>
                                                        <p>{__('Chargement du menu…', 'woo-total-menu')}</p>
                                                </div>
                                        ) : showEmptyState ? (
                                                <div className="wtm-preview__empty">
                                                        <span className="dashicons dashicons-menu"></span>
                                                        <p>{__('Aucun menu chargé.', 'woo-total-menu')}</p>
                                                </div>
                                        ) : showIframe ? (
                                                <>
                                                        <iframe
                                                                ref={iframeRef}
                                                                src={previewFrameUrl}
                                                                title={__('Aperçu live du menu', 'woo-total-menu')}
                                                                className="wtm-preview__iframe"
                                                                onLoad={handleIframeLoad}
                                                                sandbox="allow-same-origin allow-scripts"
                                                        />
                                                        {!iframeLoaded && (
                                                                <div className="wtm-preview__iframe-loading">
                                                                        <span className="dashicons dashicons-update spin"></span>
                                                                        <p>{__('Chargement de l\'aperçu…', 'woo-total-menu')}</p>
                                                                </div>
                                                        )}
                                                </>
                                        ) : null}
                                </div>
                        </div>

                        <div className="wtm-preview__footer">
                                <span className="dashicons dashicons-info"></span>
                                <span>{__('Aperçu live via iframe + postMessage (250 ms debounce).', 'woo-total-menu')}</span>
                        </div>
                </div>
        );
}
