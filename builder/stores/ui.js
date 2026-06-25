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
