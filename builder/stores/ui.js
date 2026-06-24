/**
 * wtm/ui store — handles UI state (selected item, device, REST config).
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
};

const actions = {
	selectItem(itemId) {
		return { type: 'SELECT_ITEM', itemId };
	},
	setDevice(device) {
		return { type: 'SET_DEVICE', device };
	},
	setRestConfig({ restUrl, restNonce }) {
		return { type: 'SET_REST_CONFIG', restUrl, restNonce };
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
			};
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
