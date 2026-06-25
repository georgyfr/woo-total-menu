/**
 * wtm/ui store — handles UI state (selected item, device, REST config).
 *
 * v1.1.2 additions:
 * - `announcement` string for aria-live screen reader announcements
 * - `setAnnouncement(msg)` action
 * - `getAnnouncement` selector
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
