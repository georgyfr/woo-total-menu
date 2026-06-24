/**
 * wtm/menu store — handles the menu state (CRUD via REST API).
 *
 * @package WooTotalMenu
 * @since 1.1.0
 */

import { createReduxStore, register } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

const DEFAULT_STATE = {
	menu: null,
	isLoading: false,
	isSaving: false,
	error: null,
	isDirty: false,
};

const actions = {
	setMenu(menu) {
		return { type: 'SET_MENU', menu };
	},
	setIsLoading(isLoading) {
		return { type: 'SET_IS_LOADING', isLoading };
	},
	setIsSaving(isSaving) {
		return { type: 'SET_IS_SAVING', isSaving };
	},
	setError(error) {
		return { type: 'SET_ERROR', error };
	},
	setDirty(isDirty) {
		return { type: 'SET_DIRTY', isDirty };
	},
	updateMenuTitle(title) {
		return { type: 'UPDATE_MENU_TITLE', title };
	},
	updateMenuConfig(config) {
		return { type: 'UPDATE_MENU_CONFIG', config };
	},
	loadMenu(menuId, defaultMenu = null) {
		return async ({ dispatch, registry }) => {
			dispatch.setIsLoading(true);
			dispatch.setError(null);
			try {
				if (!menuId && defaultMenu) {
					dispatch.setMenu(defaultMenu);
					dispatch.setDirty(false);
				} else {
					const restUrl = registry.select('wtm/ui').getRestUrl();
					const restNonce = registry.select('wtm/ui').getRestNonce();
					apiFetch.use(apiFetch.createNonceMiddleware(restNonce));
					const menu = await apiFetch({ path: `/wtm/v1/menus/${menuId}` });
					dispatch.setMenu(menu);
					dispatch.setDirty(false);
				}
			} catch (err) {
				dispatch.setError(err.message || __('Erreur lors du chargement du menu.', 'woo-total-menu'));
			} finally {
				dispatch.setIsLoading(false);
			}
		};
	},
	saveMenu() {
		return async ({ dispatch, select, registry }) => {
			const menu = select.getMenu();
			if (!menu) return;
			dispatch.setIsSaving(true);
			dispatch.setError(null);
			try {
				const restNonce = registry.select('wtm/ui').getRestNonce();
				apiFetch.use(apiFetch.createNonceMiddleware(restNonce));
				let savedMenu;
				if (menu.id) {
					savedMenu = await apiFetch({
						path: `/wtm/v1/menus/${menu.id}`,
						method: 'PUT',
						data: {
							title: menu.title,
							status: menu.status,
							menu_type: menu.menu_type,
							location: menu.location,
							config: menu.config,
							header_config: menu.header_config,
							footer_config: menu.footer_config,
						},
					});
				} else {
					savedMenu = await apiFetch({
						path: '/wtm/v1/menus',
						method: 'POST',
						data: {
							title: menu.title || __('Nouveau menu', 'woo-total-menu'),
							menu_type: menu.menu_type || 'horizontal',
							location: menu.location || 'primary',
							config: menu.config || { version: 1, items: [] },
						},
					});
				}
				dispatch.setMenu(savedMenu);
				dispatch.setDirty(false);
			} catch (err) {
				dispatch.setError(err.message || __('Erreur lors de la sauvegarde.', 'woo-total-menu'));
			} finally {
				dispatch.setIsSaving(false);
			}
		};
	},
};

const selectors = {
	getMenu(state) {
		return state.menu;
	},
	getItems(state) {
		return state.menu?.config?.items || [];
	},
	getSelectedItem(state, itemId) {
		if (!state.menu?.config?.items) return null;
		return findItem(state.menu.config.items, itemId);
	},
	isLoading(state) {
		return state.isLoading;
	},
	isSaving(state) {
		return state.isSaving;
	},
	getError(state) {
		return state.error;
	},
	isDirty(state) {
		return state.isDirty;
	},
};

function findItem(items, id) {
	for (const item of items) {
		if (item.id === id) return item;
		if (item.children) {
			const found = findItem(item.children, id);
			if (found) return found;
		}
	}
	return null;
}

const reducer = (state = DEFAULT_STATE, action) => {
	switch (action.type) {
		case 'SET_MENU':
			return { ...state, menu: action.menu };
		case 'SET_IS_LOADING':
			return { ...state, isLoading: action.isLoading };
		case 'SET_IS_SAVING':
			return { ...state, isSaving: action.isSaving };
		case 'SET_ERROR':
			return { ...state, error: action.error };
		case 'SET_DIRTY':
			return { ...state, isDirty: action.isDirty };
		case 'UPDATE_MENU_TITLE':
			return {
				...state,
				menu: { ...state.menu, title: action.title },
				isDirty: true,
			};
		case 'UPDATE_MENU_CONFIG':
			return {
				...state,
				menu: { ...state.menu, config: action.config },
				isDirty: true,
			};
		default:
			return state;
	}
};

export const WTM_STORE_NAME = 'wtm/menu';

export const store = createReduxStore(WTM_STORE_NAME, {
	reducer,
	actions,
	selectors,
});

register(store);
