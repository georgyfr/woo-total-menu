/**
 * wtm/menu store — handles the menu state (CRUD via REST API).
 *
 * Includes:
 *  - Item CRUD (added in v1.1.1)
 *  - Undo/Redo with past/future snapshots (added in v1.1.2)
 *  - Helper for drag & drop nesting validation (added in v1.1.2)
 *
 * @package WooTotalMenu
 * @since 1.1.0
 */

import { createReduxStore, register } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

const DEFAULT_STATE = {
        menu: null,
        isLoading: false,
        isSaving: false,
        error: null,
        isDirty: false,
        // Undo/Redo history (added in v1.1.2)
        past: [],   // Array of past menu configs (most recent last)
        future: [], // Array of future menu configs (most recent first)
        // v1.1.5 — WordPress revisions (server-side history, spec §6.6, §9.9)
        revisions: [],
        isLoadingRevisions: false,
        isRestoring: false,
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
 * Recursively find the parent ID and index of an item by ID.
 *
 * @param {Array}  items   Items array.
 * @param {string} id      Item ID.
 * @param {string} parentId Current parent ID (null for root).
 * @return {{parentId: string|null, index: number}|null} Location info or null.
 */
function findItemLocation(items, id, parentId = null) {
        for (let i = 0; i < items.length; i++) {
                if (items[i].id === id) {
                        return { parentId, index: i };
                }
                if (items[i].children) {
                        const found = findItemLocation(items[i].children, id, items[i].id);
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
                let newItem = mapped || item;
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

/**
 * Insert an item into a parent at a specific index.
 * If parentId is null, insert at root.
 *
 * @param {Array}  items    Items array.
 * @param {string} parentId Parent ID (or null for root).
 * @param {Object} newItem  New item to insert.
 * @param {number} index    Index in the parent's children.
 * @return {Array} New items array.
 */
function insertItemAtIndex(items, parentId, newItem, index) {
        if (!parentId) {
                const result = [...items];
                result.splice(index, 0, newItem);
                return result;
        }
        return mapItems(items, (item) => {
                if (item.id === parentId) {
                        const children = [...(item.children || [])];
                        children.splice(index, 0, newItem);
                        return { ...item, children };
                }
                return null;
        });
}

// === Nesting validation (added in v1.1.2 — Spec §3.4.2) ===

/**
 * Compute the depth of an item in the tree.
 *
 * @param {Array}  items Items array.
 * @param {string} id    Item ID.
 * @return {number} Depth (0 for root, -1 if not found).
 */
function getItemDepth(items, id) {
        function walk(node, depth) {
                if (node.id === id) return depth;
                if (node.children) {
                        for (const child of node.children) {
                                const found = walk(child, depth + 1);
                                if (found >= 0) return found;
                        }
                }
                return -1;
        }
        for (const root of items) {
                const d = walk(root, 0);
                if (d >= 0) return d;
        }
        return -1;
}

/**
 * Get the item type allowed to be a parent of the given child type.
 * Spec §3.4.2 — Max depth = 3 (root → mega_container → column → widget/link).
 *
 * @param {string} childType  Type of the item being moved.
 * @param {string} parentType Type of the proposed parent.
 * @param {string} menuType   Type of the menu (vertical allows accordion_parent).
 * @return {boolean} True if the parent accepts the child.
 */
function isNestingAllowed(childType, parentType, menuType = 'horizontal') {
        // Root container
        if (parentType === null) {
                if (menuType === 'vertical') {
                        return ['link', 'mega_container', 'title', 'separator', 'accordion_parent'].includes(childType);
                }
                return ['link', 'mega_container', 'title', 'separator'].includes(childType);
        }
        if (parentType === 'mega_container') {
                return childType === 'column';
        }
        if (parentType === 'column') {
                return ['link', 'title', 'widget', 'separator', 'accordion_parent'].includes(childType);
        }
        if (parentType === 'accordion_parent') {
                return ['link', 'widget'].includes(childType);
        }
        // link, widget, title, separator are terminal — cannot have children.
        return false;
}

/**
 * Compute the depth an item would have if placed inside a given parent.
 * Root parent = depth 0, mega_container = depth 1, column = depth 2, etc.
 *
 * @param {Array}  items    Items array.
 * @param {string} parentId Parent ID (or null).
 * @return {number} Depth (0 if root parent, 1 if root mega_container, etc.).
 */
function getParentDepth(items, parentId) {
        if (!parentId) return 0;
        return getItemDepth(items, parentId) + 1;
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

        // === Undo / Redo (added in v1.1.2 — Spec §9.9) ===

        /**
         * Undo the last change. Moves the current menu config to `future`
         * and restores the most recent from `past`.
         *
         * @return {Object} Action.
         */
        undo() {
                return { type: 'UNDO' };
        },

        /**
         * Redo a previously undone change.
         *
         * @return {Object} Action.
         */
        redo() {
                return { type: 'REDO' };
        },

        /**
         * Clear all undo/redo history.
         *
         * @return {Object} Action.
         */
        clearHistory() {
                return { type: 'CLEAR_HISTORY' };
        },

        // === v1.1.5 — WordPress revisions (spec §6.6, §9.9) ===

        /**
         * Set the revisions list (returned by the REST endpoint).
         *
         * @param {Array} revisions Revisions list.
         * @return {Object} Action.
         */
        setRevisions(revisions) {
                return { type: 'SET_REVISIONS', revisions };
        },

        /**
         * Set the loading-revisions flag.
         *
         * @param {boolean} isLoading Whether revisions are loading.
         * @return {Object} Action.
         */
        setIsLoadingRevisions(isLoading) {
                return { type: 'SET_IS_LOADING_REVISIONS', isLoading };
        },

        /**
         * Set the restoring flag.
         *
         * @param {boolean} isRestoring Whether a restore is in progress.
         * @return {Object} Action.
         */
        setIsRestoring(isRestoring) {
                return { type: 'SET_IS_RESTORING', isRestoring };
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
                                        const payload = {
                                                title: menu.title,
                                                status: menu.status,
                                                menu_type: menu.menu_type,
                                                location: menu.location,
                                                config: menu.config,
                                        };
                                        // Only include header_config / footer_config when they
                                        // actually hold a value. The REST controller's endpoint
                                        // args declare them as `object|string` and reject `null`,
                                        // so sending null breaks the whole PUT request (HTTP 400).
                                        if (menu.header_config) {
                                                payload.header_config = menu.header_config;
                                        }
                                        if (menu.footer_config) {
                                                payload.footer_config = menu.footer_config;
                                        }
                                        savedMenu = await apiFetch({
                                                path: `/wtm/v1/menus/${menu.id}`,
                                                method: 'PUT',
                                                data: payload,
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
                                dispatch.clearHistory();
                        } catch (err) {
                                dispatch.setError(err.message || __('Erreur lors de la sauvegarde.', 'woo-total-menu'));
                        } finally {
                                dispatch.setIsSaving(false);
                        }
                };
        },

        // === v1.1.5 — Revisions async actions ===

        /**
         * Load the WordPress revisions list for a menu.
         *
         * @param {number} menuId Menu ID.
         * @return {Function} Thunk.
         */
        loadRevisions(menuId) {
                return async ({ dispatch, registry }) => {
                        if (!menuId) return;
                        dispatch.setIsLoadingRevisions(true);
                        try {
                                const restNonce = registry.select('wtm/ui').getRestNonce();
                                apiFetch.use(apiFetch.createNonceMiddleware(restNonce));
                                const revisions = await apiFetch({
                                        path: `/wtm/v1/menus/${menuId}/revisions?per_page=50`,
                                        method: 'GET',
                                });
                                dispatch.setRevisions(revisions);
                        } catch (err) {
                                dispatch.setError(err.message || __('Erreur lors du chargement de l\'historique.', 'woo-total-menu'));
                        } finally {
                                dispatch.setIsLoadingRevisions(false);
                        }
                };
        },

        /**
         * Restore a past revision. After restoring, the local menu state is
         * replaced with the restored menu, and the local undo/redo stacks are
         * cleared (since the timeline just changed server-side).
         *
         * @param {number} menuId      Menu ID.
         * @param {number} revisionId  Revision ID to restore.
         * @return {Function} Thunk.
         */
        restoreRevision(menuId, revisionId) {
                return async ({ dispatch, registry }) => {
                        if (!menuId || !revisionId) return;
                        dispatch.setIsRestoring(true);
                        try {
                                const restNonce = registry.select('wtm/ui').getRestNonce();
                                apiFetch.use(apiFetch.createNonceMiddleware(restNonce));
                                const result = await apiFetch({
                                        path: `/wtm/v1/menus/${menuId}/revisions/${revisionId}/restore`,
                                        method: 'POST',
                                });
                                if (result && result.menu) {
                                        dispatch.setMenu(result.menu);
                                        dispatch.setDirty(false);
                                        dispatch.clearHistory();
                                        // Reload revisions list so the new "current" appears.
                                        dispatch.loadRevisions(menuId);
                                }
                        } catch (err) {
                                dispatch.setError(err.message || __('Erreur lors de la restauration.', 'woo-total-menu'));
                        } finally {
                                dispatch.setIsRestoring(false);
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
        // === Undo/Redo selectors (added in v1.1.2) ===
        canUndo(state) {
                return state.past.length > 0;
        },
        canRedo(state) {
                return state.future.length > 0;
        },
        getHistorySize(state) {
                return { past: state.past.length, future: state.future.length };
        },
        // === v1.1.5 — Revisions selectors ===
        getRevisions(state) {
                return state.revisions;
        },
        isLoadingRevisions(state) {
                return state.isLoadingRevisions;
        },
        isRestoring(state) {
                return state.isRestoring;
        },
};

/**
 * Helper: push the current menu config to the past stack (clears future).
 * Used before any mutating action.
 *
 * @param {Object} state Current state.
 * @return {Object} New state with the current config pushed to past.
 */
function pushHistory(state) {
        if (!state.menu?.config) return state;
        const MAX_HISTORY = 50;
        const newPast = [...state.past, state.menu.config].slice(-MAX_HISTORY);
        return { ...state, past: newPast, future: [] };
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
                                ...pushHistory(state),
                                menu: { ...state.menu, title: action.title },
                                isDirty: true,
                        };
                case 'UPDATE_MENU_CONFIG':
                        return {
                                ...pushHistory(state),
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
                                ...pushHistory(state),
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
                                ...pushHistory(state),
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
                                ...pushHistory(state),
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
                        items = insertItemAtIndex(items, action.parentId, itemToMove, action.index);
                        return {
                                ...pushHistory(state),
                                menu: {
                                        ...state.menu,
                                        config: { ...state.menu.config, items },
                                },
                                isDirty: true,
                        };
                }
                // === Undo / Redo cases (added in v1.1.2) ===
                case 'UNDO': {
                        if (state.past.length === 0) return state;
                        const previous = state.past[state.past.length - 1];
                        const newPast = state.past.slice(0, -1);
                        const newFuture = state.menu?.config
                                ? [state.menu.config, ...state.future]
                                : state.future;
                        return {
                                ...state,
                                menu: state.menu ? { ...state.menu, config: previous } : state.menu,
                                past: newPast,
                                future: newFuture,
                                isDirty: true,
                        };
                }
                case 'REDO': {
                        if (state.future.length === 0) return state;
                        const next = state.future[0];
                        const newFuture = state.future.slice(1);
                        const newPast = state.menu?.config
                                ? [...state.past, state.menu.config]
                                : state.past;
                        return {
                                ...state,
                                menu: state.menu ? { ...state.menu, config: next } : state.menu,
                                past: newPast,
                                future: newFuture,
                                isDirty: true,
                        };
                }
                case 'CLEAR_HISTORY':
                        return { ...state, past: [], future: [] };
                // === v1.1.5 — Revisions cases ===
                case 'SET_REVISIONS':
                        return { ...state, revisions: action.revisions };
                case 'SET_IS_LOADING_REVISIONS':
                        return { ...state, isLoadingRevisions: action.isLoading };
                case 'SET_IS_RESTORING':
                        return { ...state, isRestoring: action.isRestoring };
                default:
                        return state;
        }
};

// Export helpers for testing and reuse.
export {
        generateId,
        findItem,
        findItemLocation,
        mapItems,
        updateItemById,
        removeItemById,
        addChildToParent,
        insertItemAtIndex,
        getItemDepth,
        isNestingAllowed,
        getParentDepth,
};

export const WTM_STORE_NAME = 'wtm/menu';

export const store = createReduxStore(WTM_STORE_NAME, {
        reducer,
        actions,
        selectors,
});

register(store);
