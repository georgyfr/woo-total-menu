/**
 * wtm/menu store — handles the menu state (CRUD via REST API).
 *
 * @package WooTotalMenu
 * @since 1.1.0
 */

import { createReduxStore, register } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';

const DEFAULT_STATE = {
	menu: null,
	isLoading: false,
	isSaving: false,
	error: null,
	isDirty: false,
};

// Counter used to generate unique IDs for new items.
let itemCounter = 0;

/**
 * Generate a unique ID for a new item.
 *
 * @param {string} prefix Prefix.
 * @return {string} Unique ID.
 */
function generateId(prefix = 'item') {
	itemCounter++;
	return `${prefix}-${Date.now()}-${itemCounter}`;
}

/**
 * Recursively find an item by ID in the items tree.
 *
 * @param {Array}  items Items array.
 * @param {string} id    Item ID.
 * @return {Object|null} The item or null.
 */
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

/**
 * Recursively map over the items tree (immutable).
 *
 * @param {Array}    items Items array.
 * @param {Function} fn    Callback (item) => newItem (or null to keep as-is, false to remove).
 * @return {Array} New items array.
 */
function mapItems(items, fn) {
	const result = [];
	for (const item of items) {
		const mapped = fn(item);
		if (mapped === false) continue;
		const newItem = mapped || item;
		if (newItem.children) {
			newItem = { ...newItem, children: mapItems(newItem.children, fn) };
		}
		result.push(newItem);
	}
	return result;
}

/**
 * Recursively update a single item by ID.
 *
 * @param {Array}  items    Items array.
 * @param {string} id       Item ID.
 * @param {Object} patch    Patch to apply.
 * @return {Array} New items array.
 */
function updateItemById(items, id, patch) {
	return mapItems(items, (item) => {
		if (item.id === id) {
			return { ...item, ...patch };
		}
		return null;
	});
}

/**
 * Recursively remove an item by ID.
 *
 * @param {Array}  items Items array.
 * @param {string} id    Item ID.
 * @return {Array} New items array.
 */
function removeItemById(items, id) {
	return mapItems(items, (item) => {
		if (item.id === id) {
			return false; // remove
		}
		return null;
	});
}

/**
 * Add a child to an item (by parent ID).
 * If parentId is null, add to root.
 *
 * @param {Array}  items    Items array.
 * @param {string} parentId Parent ID (or null).
 * @param {Object} newItem  New item to add.
 * @return {Array} New items array.
 */
function addChildToParent(items, parentId, newItem) {
	if (!parentId) {
		return [...items, newItem];
	}
	return mapItems(items, (item) => {
		if (item.id === parentId) {
			return {
				...item,
				children: [...(item.children || []), newItem],
			};
		}
		return null;
	});
}

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

	// === Item CRUD (added in v1.1.1) ===

	/**
	 * Add a new item to the menu (root or as child of a parent).
	 *
	 * @param {Object} item     Item to add (must have at least `type`).
	 * @param {string} parentId Parent ID (null for root).
	 * @return {Object} Action.
	 */
	addItem(item, parentId = null) {
		return { type: 'ADD_ITEM', item, parentId };
	},

	/**
	 * Update an item by ID with a patch.
	 *
	 * @param {string} id    Item ID.
	 * @param {Object} patch Patch to apply.
	 * @return {Object} Action.
	 */
	updateItem(id, patch) {
		return { type: 'UPDATE_ITEM', id, patch };
	},

	/**
	 * Remove an item by ID (and all its children).
	 *
	 * @param {string} id Item ID.
	 * @return {Object} Action.
	 */
	removeItem(id) {
		return { type: 'REMOVE_ITEM', id };
	},

	/**
	 * Move an item to a new parent (null for root) at a specific index.
	 *
	 * @param {string} id        Item ID.
	 * @param {string} parentId  New parent ID (null for root).
	 * @param {number} index     Index in the new parent's children.
	 * @return {Object} Action.
	 */
	moveItem(id, parentId, index) {
		return { type: 'MOVE_ITEM', id, parentId, index };
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
		case 'ADD_ITEM': {
			const newItem = {
				id: action.item.id || generateId(action.item.type),
				...action.item,
			};
			const items = addChildToParent(state.menu.config.items, action.parentId, newItem);
			return {
				...state,
				menu: {
					...state.menu,
					config: { ...state.menu.config, items },
				},
				isDirty: true,
			};
		}
		case 'UPDATE_ITEM': {
			const items = updateItemById(state.menu.config.items, action.id, action.patch);
			return {
				...state,
				menu: {
					...state.menu,
					config: { ...state.menu.config, items },
				},
				isDirty: true,
			};
		}
		case 'REMOVE_ITEM': {
			const items = removeItemById(state.menu.config.items, action.id);
			return {
				...state,
				menu: {
					...state.menu,
					config: { ...state.menu.config, items },
				},
				isDirty: true,
			};
		}
		case 'MOVE_ITEM': {
			// Find the item to move.
			const itemToMove = findItem(state.menu.config.items, action.id);
			if (!itemToMove) return state;
			// Remove from current location.
			let items = removeItemById(state.menu.config.items, action.id);
			// Insert at new location.
			if (!action.parentId) {
				// Insert at root at given index.
				items = [...items];
				items.splice(action.index, 0, itemToMove);
			} else {
				items = mapItems(items, (item) => {
					if (item.id === action.parentId) {
						const children = [...(item.children || [])];
						children.splice(action.index, 0, itemToMove);
						return { ...item, children };
					}
					return null;
				});
			}
			return {
				...state,
				menu: {
					...state.menu,
					config: { ...state.menu.config, items },
				},
				isDirty: true,
			};
		}
		default:
			return state;
	}
};

// Export helpers for testing.
export { generateId, findItem, mapItems, updateItemById, removeItemById, addChildToParent };

export const WTM_STORE_NAME = 'wtm/menu';

export const store = createReduxStore(WTM_STORE_NAME, {
	reducer,
	actions,
	selectors,
});

register(store);
