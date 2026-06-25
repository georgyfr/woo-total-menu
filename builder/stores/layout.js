/**
 * wtm/layout store — handles the header/footer layout state
 * (rows → columns → modules).
 *
 * v1.4.0 — Header & Footer Builder.
 *
 * Spec reference: §3.6 (Header), §3.7 (Footer), §4.6.5, §9.5.2.
 *
 * The store manages two separate layouts: `header` and `footer`. The active
 * one is selected via the `type` argument. Both are stored in the same Redux
 * state under `state.header` and `state.footer`.
 *
 * Each layout has the shape:
 *   { version: 1, settings: {}, rows: [ { id, settings, columns: [ { id, width, settings, modules: [ { id, type, settings } ] } ] } ] }
 *
 * @package WooTotalMenu
 * @since 1.4.0
 */

import { createReduxStore, register } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

// Counter for unique IDs (reset per session).
let idCounter = 0;

/**
 * Generate a unique ID with a prefix.
 *
 * @param {string} prefix Prefix.
 * @return {string} Unique ID.
 */
function genId(prefix = 'm') {
        idCounter++;
        return `${prefix}-${Date.now()}-${idCounter}`;
}

/**
 * Default module settings per module type.
 */
const MODULE_DEFAULTS = {
        logo: { image_id: 0, url: '', max_width: 180, alt: '' },
        menu: { menu_id: 0, location: '' },
        search: { placeholder: __('Rechercher…', 'woo-total-menu'), style: 'inline', search_sku: false },
        cart: { show_total: false, behavior: 'drawer' },
        button: { text: '', url: '#', target: '_self', style: 'primary', icon: '' },
        html: { content: '' },
        social: { links: [] },
        newsletter: { title: '', placeholder: __('Votre email', 'woo-total-menu'), button_text: __('S\'abonner', 'woo-total-menu'), provider: 'internal' },
        text: { content: '' },
};

/**
 * Create a new module of the given type with default settings.
 *
 * @param {string} type Module type.
 * @return {Object} Module object.
 */
function createModule(type) {
        return {
                id: genId('mod'),
                type,
                settings: MODULE_DEFAULTS[type] ? { ...MODULE_DEFAULTS[type] } : {},
        };
}

/**
 * Create a new column with default settings.
 *
 * @return {Object} Column object.
 */
function createColumn() {
        return {
                id: genId('col'),
                width: 6,
                settings: { align: 'left', valign: 'center' },
                modules: [],
        };
}

/**
 * Create a new row with default settings.
 *
 * @return {Object} Row object.
 */
function createRow() {
        return {
                id: genId('row'),
                settings: { background: '', height: 0, padding_y: 12, align: 'space-between', hide_desktop: false, hide_mobile: false, sticky: false },
                columns: [createColumn()],
        };
}

/**
 * Default empty layout.
 *
 * @return {Object} Empty layout.
 */
function emptyLayout() {
        return { version: 1, settings: {}, rows: [] };
}

const DEFAULT_STATE = {
        header: null, // Layout object or null when not loaded.
        footer: null,
        isLoading: false,
        isSaving: false,
        error: null,
        isDirty: false,
        selectedElementId: null,
        selectedElementType: null, // 'row' | 'column' | 'module' | null
        // Undo/Redo
        past: [],
        future: [],
};

/**
 * Deep-clone a layout (cheap JSON-based clone — configs are plain data).
 *
 * @param {Object} layout Layout.
 * @return {Object} Cloned layout.
 */
function cloneLayout(layout) {
        if (!layout) return null;
        return JSON.parse(JSON.stringify(layout));
}

/**
 * Find a row by ID.
 *
 * @param {Object} layout Layout.
 * @param {string} rowId  Row ID.
 * @return {Object|null} Row or null.
 */
function findRow(layout, rowId) {
        return (layout.rows || []).find((r) => r.id === rowId) || null;
}

/**
 * Find a column by ID across all rows.
 *
 * @param {Object} layout Layout.
 * @param {string} colId  Column ID.
 * @return {{row: Object, column: Object}|null} Result.
 */
function findColumn(layout, colId) {
        for (const row of layout.rows || []) {
                const column = (row.columns || []).find((c) => c.id === colId);
                if (column) return { row, column };
        }
        return null;
}

/**
 * Find a module by ID across all columns of all rows.
 *
 * @param {Object} layout Layout.
 * @param {string} modId  Module ID.
 * @return {{row: Object, column: Object, module: Object}|null} Result.
 */
function findModule(layout, modId) {
        for (const row of layout.rows || []) {
                for (const column of row.columns || []) {
                        const module = (column.modules || []).find((m) => m.id === modId);
                        if (module) return { row, column, module };
                }
        }
        return null;
}

/**
 * Immutably update a row by ID.
 *
 * @param {Object}   layout Layout.
 * @param {string}   rowId  Row ID.
 * @param {Function} fn     Updater (row) => newRow.
 * @return {Object} New layout.
 */
function updateRow(layout, rowId, fn) {
        return {
                ...layout,
                rows: (layout.rows || []).map((r) => (r.id === rowId ? fn(r) : r)),
        };
}

/**
 * Immutably update a column by ID.
 *
 * @param {Object}   layout Layout.
 * @param {string}   colId  Column ID.
 * @param {Function} fn     Updater (column) => newColumn.
 * @return {Object} New layout.
 */
function updateColumn(layout, colId, fn) {
        return {
                ...layout,
                rows: (layout.rows || []).map((r) => ({
                        ...r,
                        columns: (r.columns || []).map((c) => (c.id === colId ? fn(c) : c)),
                })),
        };
}

/**
 * Immutably update a module by ID.
 *
 * @param {Object}   layout Layout.
 * @param {string}   modId  Module ID.
 * @param {Function} fn     Updater (module) => newModule.
 * @return {Object} New layout.
 */
function updateModule(layout, modId, fn) {
        return {
                ...layout,
                rows: (layout.rows || []).map((r) => ({
                        ...r,
                        columns: (r.columns || []).map((c) => ({
                                ...c,
                                modules: (c.modules || []).map((m) => (m.id === modId ? fn(m) : m)),
                        })),
                })),
        };
}

/**
 * Remove a row by ID.
 *
 * @param {Object} layout Layout.
 * @param {string} rowId  Row ID.
 * @return {Object} New layout.
 */
function removeRow(layout, rowId) {
        return {
                ...layout,
                rows: (layout.rows || []).filter((r) => r.id !== rowId),
        };
}

/**
 * Remove a column by ID.
 *
 * @param {Object} layout Layout.
 * @param {string} colId  Column ID.
 * @return {Object} New layout.
 */
function removeColumn(layout, colId) {
        return {
                ...layout,
                rows: (layout.rows || []).map((r) => ({
                        ...r,
                        columns: (r.columns || []).filter((c) => c.id !== colId),
                })),
        };
}

/**
 * Remove a module by ID.
 *
 * @param {Object} layout Layout.
 * @param {string} modId  Module ID.
 * @return {Object} New layout.
 */
function removeModule(layout, modId) {
        return {
                ...layout,
                rows: (layout.rows || []).map((r) => ({
                        ...r,
                        columns: (r.columns || []).map((c) => ({
                                ...c,
                                modules: (c.modules || []).filter((m) => m.id !== modId),
                        })),
                })),
        };
}

const actions = {
        setLayout(type, layout) {
                return { type: 'SET_LAYOUT', layoutType: type, layout };
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
        selectElement(elementId, elementType) {
                return { type: 'SELECT_ELEMENT', elementId, elementType };
        },
        clearSelection() {
                return { type: 'SELECT_ELEMENT', elementId: null, elementType: null };
        },

        // === Layout-level actions ===

        /**
         * Load the header or footer layout from the menu's header_config / footer_config.
         *
         * @param {string} type    'header' or 'footer'.
         * @param {Object} payload Menu payload from the wtm/menu store (must contain
         *                          header_config or footer_config).
         * @return {Object} Action.
         */
        loadFromMenu(type, payload) {
                const key = type === 'header' ? 'header_config' : 'footer_config';
                const layout = (payload && payload[key]) || emptyLayout();
                return { type: 'SET_LAYOUT', layoutType: type, layout: cloneLayout(layout) };
        },

        /**
         * Reset the layout store to an empty state (e.g. when switching menu).
         *
         * @return {Object} Action.
         */
        reset() {
                return { type: 'RESET' };
        },

        // === Row actions ===

        addRow(type) {
                return { type: 'ADD_ROW', layoutType: type, row: createRow() };
        },

        updateRow(type, rowId, patch) {
                return { type: 'UPDATE_ROW', layoutType: type, rowId, patch };
        },

        removeRow(type, rowId) {
                return { type: 'REMOVE_ROW', layoutType: type, rowId };
        },

        moveRow(type, rowId, newIndex) {
                return { type: 'MOVE_ROW', layoutType: type, rowId, newIndex };
        },

        // === Column actions ===

        addColumn(type, rowId) {
                return { type: 'ADD_COLUMN', layoutType: type, rowId, column: createColumn() };
        },

        updateColumn(type, colId, patch) {
                return { type: 'UPDATE_COLUMN', layoutType: type, colId, patch };
        },

        removeColumn(type, colId) {
                return { type: 'REMOVE_COLUMN', layoutType: type, colId };
        },

        // === Module actions ===

        addModule(type, colId, moduleType) {
                return { type: 'ADD_MODULE', layoutType: type, colId, module: createModule(moduleType) };
        },

        updateModule(type, modId, patch) {
                return { type: 'UPDATE_MODULE', layoutType: type, modId, patch };
        },

        removeModule(type, modId) {
                return { type: 'REMOVE_MODULE', layoutType: type, modId };
        },

        moveModule(type, modId, targetColId, newIndex) {
                return { type: 'MOVE_MODULE', layoutType: type, modId, targetColId, newIndex };
        },

        // === Settings ===

        updateLayoutSettings(type, patch) {
                return { type: 'UPDATE_LAYOUT_SETTINGS', layoutType: type, patch };
        },

        // === Undo / Redo ===

        undo() {
                return { type: 'UNDO' };
        },
        redo() {
                return { type: 'REDO' };
        },
        clearHistory() {
                return { type: 'CLEAR_HISTORY' };
        },
};

const selectors = {
        getLayout(state, type) {
                return type === 'header' ? state.header : state.footer;
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
        getSelectedElementId(state) {
                return state.selectedElementId;
        },
        getSelectedElementType(state) {
                return state.selectedElementType;
        },
        canUndo(state) {
                return state.past.length > 0;
        },
        canRedo(state) {
                return state.future.length > 0;
        },
};

/**
 * Push a snapshot of the current layouts to the past stack (for undo).
 *
 * @param {Object} state Current state.
 * @return {Object} State with updated past stack.
 */
function snapshotForUndo(state) {
        return {
                ...state,
                past: [...state.past, {
                        header: cloneLayout(state.header),
                        footer: cloneLayout(state.footer),
                }].slice(-50), // cap history at 50 entries.
                future: [], // any new action clears the redo stack.
        };
}

const reducer = (state = DEFAULT_STATE, action) => {
        switch (action.type) {
                case 'SET_LAYOUT': {
                        const newState = { ...state, [action.layoutType]: action.layout, isDirty: true };
                        return newState;
                }
                case 'SET_IS_LOADING':
                        return { ...state, isLoading: action.isLoading };
                case 'SET_IS_SAVING':
                        return { ...state, isSaving: action.isSaving };
                case 'SET_ERROR':
                        return { ...state, error: action.error };
                case 'SET_DIRTY':
                        return { ...state, isDirty: action.isDirty };
                case 'SELECT_ELEMENT':
                        return { ...state, selectedElementId: action.elementId, selectedElementType: action.elementType };
                case 'RESET':
                        return { ...DEFAULT_STATE };

                // === Row actions ===
                case 'ADD_ROW': {
                        const layout = state[action.layoutType] || emptyLayout();
                        const newLayout = { ...layout, rows: [...layout.rows, action.row] };
                        const snapshotted = snapshotForUndo(state);
                        return { ...snapshotted, [action.layoutType]: newLayout, isDirty: true };
                }
                case 'UPDATE_ROW': {
                        const layout = state[action.layoutType];
                        if (!layout) return state;
                        const newLayout = updateRow(layout, action.rowId, (r) => ({
                                ...r,
                                settings: { ...r.settings, ...action.patch },
                        }));
                        const snapshotted = snapshotForUndo(state);
                        return { ...snapshotted, [action.layoutType]: newLayout, isDirty: true };
                }
                case 'REMOVE_ROW': {
                        const layout = state[action.layoutType];
                        if (!layout) return state;
                        const newLayout = removeRow(layout, action.rowId);
                        const snapshotted = snapshotForUndo(state);
                        const selection = (state.selectedElementId && findRow(layout, state.selectedElementId))
                                ? { selectedElementId: null, selectedElementType: null }
                                : {};
                        return { ...snapshotted, [action.layoutType]: newLayout, isDirty: true, ...selection };
                }
                case 'MOVE_ROW': {
                        const layout = state[action.layoutType];
                        if (!layout) return state;
                        const rows = [...layout.rows];
                        const currentIndex = rows.findIndex((r) => r.id === action.rowId);
                        if (currentIndex === -1) return state;
                        const [moved] = rows.splice(currentIndex, 1);
                        rows.splice(action.newIndex, 0, moved);
                        const snapshotted = snapshotForUndo(state);
                        return { ...snapshotted, [action.layoutType]: { ...layout, rows }, isDirty: true };
                }

                // === Column actions ===
                case 'ADD_COLUMN': {
                        const layout = state[action.layoutType];
                        if (!layout) return state;
                        const newLayout = updateRow(layout, action.rowId, (r) => ({
                                ...r,
                                columns: [...r.columns, action.column],
                        }));
                        const snapshotted = snapshotForUndo(state);
                        return { ...snapshotted, [action.layoutType]: newLayout, isDirty: true };
                }
                case 'UPDATE_COLUMN': {
                        const layout = state[action.layoutType];
                        if (!layout) return state;
                        const newLayout = updateColumn(layout, action.colId, (c) => ({
                                ...c,
                                settings: { ...c.settings, ...action.patch },
                                width: action.patch.width !== undefined ? action.patch.width : c.width,
                        }));
                        const snapshotted = snapshotForUndo(state);
                        return { ...snapshotted, [action.layoutType]: newLayout, isDirty: true };
                }
                case 'REMOVE_COLUMN': {
                        const layout = state[action.layoutType];
                        if (!layout) return state;
                        const newLayout = removeColumn(layout, action.colId);
                        const snapshotted = snapshotForUndo(state);
                        return { ...snapshotted, [action.layoutType]: newLayout, isDirty: true };
                }

                // === Module actions ===
                case 'ADD_MODULE': {
                        const layout = state[action.layoutType];
                        if (!layout) return state;
                        const newLayout = updateColumn(layout, action.colId, (c) => ({
                                ...c,
                                modules: [...c.modules, action.module],
                        }));
                        const snapshotted = snapshotForUndo(state);
                        return { ...snapshotted, [action.layoutType]: newLayout, isDirty: true, selectedElementId: action.module.id, selectedElementType: 'module' };
                }
                case 'UPDATE_MODULE': {
                        const layout = state[action.layoutType];
                        if (!layout) return state;
                        const newLayout = updateModule(layout, action.modId, (m) => ({
                                ...m,
                                ...action.patch,
                                settings: { ...m.settings, ...(action.patch.settings || {}) },
                        }));
                        const snapshotted = snapshotForUndo(state);
                        return { ...snapshotted, [action.layoutType]: newLayout, isDirty: true };
                }
                case 'REMOVE_MODULE': {
                        const layout = state[action.layoutType];
                        if (!layout) return state;
                        const newLayout = removeModule(layout, action.modId);
                        const snapshotted = snapshotForUndo(state);
                        const selection = state.selectedElementId === action.modId
                                ? { selectedElementId: null, selectedElementType: null }
                                : {};
                        return { ...snapshotted, [action.layoutType]: newLayout, isDirty: true, ...selection };
                }
                case 'MOVE_MODULE': {
                        const layout = state[action.layoutType];
                        if (!layout) return state;
                        // Find source module.
                        const found = findModule(layout, action.modId);
                        if (!found) return state;
                        const { module } = found;
                        // Remove from source.
                        let newLayout = removeModule(layout, action.modId);
                        // Insert into target column at the requested index.
                        newLayout = updateColumn(newLayout, action.targetColId, (c) => {
                                const mods = [...c.modules];
                                mods.splice(action.newIndex, 0, module);
                                return { ...c, modules: mods };
                        });
                        const snapshotted = snapshotForUndo(state);
                        return { ...snapshotted, [action.layoutType]: newLayout, isDirty: true };
                }

                case 'UPDATE_LAYOUT_SETTINGS': {
                        const layout = state[action.layoutType];
                        if (!layout) return state;
                        const newLayout = { ...layout, settings: { ...layout.settings, ...action.patch } };
                        const snapshotted = snapshotForUndo(state);
                        return { ...snapshotted, [action.layoutType]: newLayout, isDirty: true };
                }

                // === Undo / Redo ===
                case 'UNDO': {
                        if (state.past.length === 0) return state;
                        const previous = state.past[state.past.length - 1];
                        const newPast = state.past.slice(0, -1);
                        const newFuture = [{
                                header: cloneLayout(state.header),
                                footer: cloneLayout(state.footer),
                        }, ...state.future].slice(0, 50);
                        return {
                                ...state,
                                header: previous.header,
                                footer: previous.footer,
                                past: newPast,
                                future: newFuture,
                                isDirty: true,
                        };
                }
                case 'REDO': {
                        if (state.future.length === 0) return state;
                        const next = state.future[0];
                        const newFuture = state.future.slice(1);
                        const newPast = [...state.past, {
                                header: cloneLayout(state.header),
                                footer: cloneLayout(state.footer),
                        }].slice(-50);
                        return {
                                ...state,
                                header: next.header,
                                footer: next.footer,
                                past: newPast,
                                future: newFuture,
                                isDirty: true,
                        };
                }
                case 'CLEAR_HISTORY':
                        return { ...state, past: [], future: [] };

                default:
                        return state;
        }
};

export const LAYOUT_STORE_NAME = 'wtm/layout';

export const store = createReduxStore(LAYOUT_STORE_NAME, {
        reducer,
        actions,
        selectors,
});

register(store);

// Export helpers for use in components.
export { createModule, createColumn, createRow, emptyLayout, findRow, findColumn, findModule, MODULE_DEFAULTS };
