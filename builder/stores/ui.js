/**
 * wtm/ui store — handles UI state (selected item, device, REST config).
 *
 * v1.1.2 additions:
 * - `announcement` string for aria-live screen reader announcements
 * - `setAnnouncement(msg)` action
 * - `getAnnouncement` selector
 *
 * v1.1.5 additions:
 * - `isHistoryOpen` boolean — whether the History modal is open
 * - `openHistory()` / `closeHistory()` actions
 * - `previewRevisionId` — when set, the Builder preview shows this revision
 *   instead of the live menu config (so the user can preview before restore)
 *
 * v1.4.0 additions:
 * - `activeMode` ('menu' | 'header' | 'footer') — Builder mode switcher.
 *   Determines which canvas is shown in the central column and which
 *   configuration is sent to the preview iframe.
 *
 * v1.5.0 additions:
 * - `isTemplatesOpen` boolean — whether the Templates Gallery modal is open.
 *   `openTemplates()` / `closeTemplates()` actions toggle this flag.
 *
 * @package WooTotalMenu
 * @since 1.1.0
 */

import { createReduxStore, register } from '@wordpress/data';

const DEFAULT_STATE = {
        selectedItemId: null,
        device: 'desktop',
        restUrl: '',
        restNonce: '',
        // v1.1.4 — preview iframe URL (REST endpoint serving the preview HTML).
        previewFrameUrl: '',
        // v1.1.2 — aria-live announcements for screen readers (spec §6.7)
        announcement: '',
        // v1.1.5 — History modal open/closed
        isHistoryOpen: false,
        // v1.1.5 — when set, the preview iframe shows this revision's config
        // instead of the live menu config. Null = live config.
        previewRevisionId: null,
        // v1.4.0 — active Builder mode: 'menu' (default), 'header', or 'footer'.
        activeMode: 'menu',
        // v1.5.0 — Templates Gallery modal open/closed.
        isTemplatesOpen: false,
        // v1.7.0 — Conditions modal open/closed.
        isConditionsOpen: false,
};

const actions = {
        selectItem(itemId) {
                return { type: 'SELECT_ITEM', itemId };
        },
        setDevice(device) {
                return { type: 'SET_DEVICE', device };
        },
        setRestConfig({ restUrl, restNonce, previewFrameUrl }) {
                return { type: 'SET_REST_CONFIG', restUrl, restNonce, previewFrameUrl };
        },
        /**
         * Set the screen reader announcement message.
         *
         * @param {string} msg Announcement message.
         * @return {Object} Action.
         */
        setAnnouncement(msg) {
                return { type: 'SET_ANNOUNCEMENT', msg };
        },
        /**
         * Clear the announcement.
         *
         * @return {Object} Action.
         */
        clearAnnouncement() {
                return { type: 'SET_ANNOUNCEMENT', msg: '' };
        },
        // === v1.1.5 History modal ===
        openHistory() {
                return { type: 'SET_HISTORY_OPEN', isOpen: true };
        },
        closeHistory() {
                return { type: 'SET_HISTORY_OPEN', isOpen: false };
        },
        /**
         * Set the revision currently being previewed.
         * Pass null to restore the live config preview.
         *
         * @param {number|null} revisionId Revision ID or null.
         * @return {Object} Action.
         */
        setPreviewRevision(revisionId) {
                return { type: 'SET_PREVIEW_REVISION', revisionId };
        },
        // === v1.4.0 — Builder mode (Menu | Header | Footer) ===
        /**
         * Switch the active Builder mode.
         *
         * @param {string} mode 'menu' | 'header' | 'footer'.
         * @return {Object} Action.
         */
        setMode(mode) {
                return { type: 'SET_MODE', mode };
        },
        // === v1.5.0 — Templates Gallery modal ===
        /**
         * Open the Templates Gallery modal.
         *
         * @return {Object} Action.
         */
        openTemplates() {
                return { type: 'SET_TEMPLATES_OPEN', isOpen: true };
        },
        /**
         * Close the Templates Gallery modal.
         *
         * @return {Object} Action.
         */
        closeTemplates() {
                return { type: 'SET_TEMPLATES_OPEN', isOpen: false };
        },
        // === v1.7.0 — Conditions modal ===
        openConditions() {
                return { type: 'SET_CONDITIONS_OPEN', isOpen: true };
        },
        closeConditions() {
                return { type: 'SET_CONDITIONS_OPEN', isOpen: false };
        },
};

const selectors = {
        getSelectedItemId(state) {
                return state.selectedItemId;
        },
        getDevice(state) {
                return state.device;
        },
        getRestUrl(state) {
                return state.restUrl;
        },
        getRestNonce(state) {
                return state.restNonce;
        },
        getPreviewFrameUrl(state) {
                return state.previewFrameUrl;
        },
        getAnnouncement(state) {
                return state.announcement;
        },
        // v1.1.5
        isHistoryOpen(state) {
                return state.isHistoryOpen;
        },
        getPreviewRevisionId(state) {
                return state.previewRevisionId;
        },
        // v1.4.0
        getActiveMode(state) {
                return state.activeMode || 'menu';
        },
        // v1.5.0
        isTemplatesOpen(state) {
                return !!state.isTemplatesOpen;
        },
        // v1.7.0
        isConditionsOpen(state) {
                return !!state.isConditionsOpen;
        },
};

const reducer = (state = DEFAULT_STATE, action) => {
        switch (action.type) {
                case 'SELECT_ITEM':
                        return { ...state, selectedItemId: action.itemId };
                case 'SET_DEVICE':
                        return { ...state, device: action.device };
                case 'SET_REST_CONFIG':
                        return {
                                ...state,
                                restUrl: action.restUrl,
                                restNonce: action.restNonce,
                                previewFrameUrl: action.previewFrameUrl || state.previewFrameUrl,
                        };
                case 'SET_ANNOUNCEMENT':
                        return { ...state, announcement: action.msg };
                case 'SET_HISTORY_OPEN':
                        return { ...state, isHistoryOpen: action.isOpen };
                case 'SET_PREVIEW_REVISION':
                        return { ...state, previewRevisionId: action.revisionId };
                case 'SET_MODE':
                        return { ...state, activeMode: action.mode };
                case 'SET_TEMPLATES_OPEN':
                        return { ...state, isTemplatesOpen: action.isOpen };
                case 'SET_CONDITIONS_OPEN':
                        return { ...state, isConditionsOpen: action.isOpen };
                default:
                        return state;
        }
};

export const UI_STORE_NAME = 'wtm/ui';

export const store = createReduxStore(UI_STORE_NAME, {
        reducer,
        actions,
        selectors,
});

register(store);
