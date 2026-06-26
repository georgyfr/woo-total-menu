/**
 * wtm/templates store — handles the integrated templates catalog.
 *
 * v1.5.0 additions (spec §1.4.2 — bibliothèque de templates intégrés) :
 *  - Catalog cache (`templates` array) — fetched once via REST GET /wtm/v1/templates.
 *  - Loading / error state.
 *  - Filter state (`filterType`, `filterCategory`, `filterSearch`).
 *  - Apply state (`isApplying`, `applyError`, `lastApplied`).
 *  - Async actions:
 *    * `fetchTemplates()`   — fetches the catalog (cached).
 *    * `applyTemplate(id)`  — applies a template to the current menu
 *      (using the active mode from the UI store: menu | header | footer).
 *
 * @package WooTotalMenu
 * @since 1.5.0
 */

import { createReduxStore, register } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

const DEFAULT_STATE = {
        // Catalog (cached after first fetch).
        templates: [],
        isLoading: false,
        error: null,
        // Filters (controlled by the gallery UI).
        filterType: '',     // '' | 'menu' | 'header' | 'footer'
        filterCategory: '', // '' | 'ecommerce' | 'blog' | 'corporate' | 'minimal' | 'electronics'
        filterSearch: '',   // free-text search
        // Apply state.
        isApplying: false,
        applyError: null,
        lastApplied: null,  // { id, menu_id, mode, ts }
};

const actions = {
        // === Catalog ===
        setTemplates(templates) {
                return { type: 'SET_TEMPLATES', templates };
        },
        setIsLoading(isLoading) {
                return { type: 'SET_IS_LOADING', isLoading };
        },
        setError(error) {
                return { type: 'SET_ERROR', error };
        },
        // === Filters ===
        setFilterType(filterType) {
                return { type: 'SET_FILTER_TYPE', filterType };
        },
        setFilterCategory(filterCategory) {
                return { type: 'SET_FILTER_CATEGORY', filterCategory };
        },
        setFilterSearch(filterSearch) {
                return { type: 'SET_FILTER_SEARCH', filterSearch };
        },
        resetFilters() {
                return { type: 'RESET_FILTERS' };
        },
        // === Apply ===
        setIsApplying(isApplying) {
                return { type: 'SET_IS_APPLYING', isApplying };
        },
        setApplyError(applyError) {
                return { type: 'SET_APPLY_ERROR', applyError };
        },
        setLastApplied(lastApplied) {
                return { type: 'SET_LAST_APPLIED', lastApplied };
        },

        // === Async actions ===

        /**
         * Fetch the templates catalog. Idempotent — if templates are already
         * loaded, returns immediately. Pass `force=true` to bypass the cache.
         *
         * @param {Object}  [opts]         Options.
         * @param {boolean} [opts.force]   Force re-fetch.
         * @return {Function} Thunk.
         */
        fetchTemplates(opts = {}) {
                return async ({ dispatch, select, registry }) => {
                        const { force = false } = opts;
                        const existing = select.getTemplates();
                        if (existing.length > 0 && !force) {
                                return existing;
                        }
                        dispatch.setIsLoading(true);
                        dispatch.setError(null);
                        try {
                                const restNonce = registry.select('wtm/ui').getRestNonce();
                                const templates = await apiFetch({
                                        path: '/wtm/v1/templates',
                                        method: 'GET',
                                        headers: { 'X-WP-Nonce': restNonce },
                                });
                                dispatch.setTemplates(templates);
                                return templates;
                        } catch (err) {
                                dispatch.setError(err.message || __('Erreur lors du chargement des templates.', 'woo-total-menu'));
                                return [];
                        } finally {
                                dispatch.setIsLoading(false);
                        }
                };
        },

        /**
         * Apply a template to the current menu.
         *
         * Reads the current menu ID from the wtm/menu store and the active mode
         * (menu | header | footer) from the wtm/ui store, then POSTs to
         * /wtm/v1/templates/{id}/apply.
         *
         * After a successful apply:
         *  - Reloads the menu (so the new config is reflected in the Builder).
         *  - Reloads the layout if the applied mode was header or footer.
         *  - Closes the gallery modal.
         *
         * @param {string} templateId Template slug.
         * @return {Function} Thunk.
         */
        applyTemplate(templateId) {
                return async ({ dispatch, select, registry }) => {
                        const menu = registry.select('wtm/menu').getMenu();
                        if (!menu || !menu.id) {
                                dispatch.setApplyError(__('Aucun menu chargé — impossible d\'appliquer le template.', 'woo-total-menu'));
                                return;
                        }
                        const mode = registry.select('wtm/ui').getActiveMode() || 'menu';

                        dispatch.setIsApplying(true);
                        dispatch.setApplyError(null);
                        try {
                                const restNonce = registry.select('wtm/ui').getRestNonce();
                                const result = await apiFetch({
                                        path: `/wtm/v1/templates/${templateId}/apply`,
                                        method: 'POST',
                                        data: { menu_id: menu.id, mode },
                                        headers: { 'X-WP-Nonce': restNonce },
                                });
                                dispatch.setLastApplied({ id: templateId, menu_id: menu.id, mode, ts: Date.now() });

                                // Reload the menu so the new config is reflected.
                                await registry.dispatch('wtm/menu').loadMenu(menu.id);

                                // If header/footer mode, reload the layout store too.
                                if (mode === 'header' || mode === 'footer') {
                                        const freshMenu = registry.select('wtm/menu').getMenu();
                                        const layoutConfig = mode === 'header' ? freshMenu?.header_config : freshMenu?.footer_config;
                                        if (layoutConfig) {
                                                registry.dispatch('wtm/layout').loadFromMenu(mode, freshMenu);
                                        }
                                }

                                // Close the gallery modal on success.
                                registry.dispatch('wtm/ui').closeTemplates();
                                return result;
                        } catch (err) {
                                dispatch.setApplyError(err.message || __('Erreur lors de l\'application du template.', 'woo-total-menu'));
                                throw err;
                        } finally {
                                dispatch.setIsApplying(false);
                        }
                };
        },
};

const selectors = {
        // === Catalog ===
        getTemplates(state) {
                return state.templates;
        },
        isLoading(state) {
                return state.isLoading;
        },
        getError(state) {
                return state.error;
        },
        // === Filters ===
        getFilterType(state) {
                return state.filterType;
        },
        getFilterCategory(state) {
                return state.filterCategory;
        },
        getFilterSearch(state) {
                return state.filterSearch;
        },
        /**
         * Get the templates filtered by the current filter state.
         *
         * @param {Object} state Store state.
         * @return {Array} Filtered templates.
         */
        getFilteredTemplates(state) {
                const { templates, filterType, filterCategory, filterSearch } = state;
                let out = templates;
                if (filterType) {
                        out = out.filter((t) => t.type === filterType);
                }
                if (filterCategory) {
                        out = out.filter((t) => t.category === filterCategory);
                }
                if (filterSearch) {
                        const q = filterSearch.toLowerCase();
                        out = out.filter((t) => {
                                const hay = (
                                        (t.name || '') + ' ' +
                                        (t.description || '') + ' ' +
                                        (t.preview || '') + ' ' +
                                        ((t.tags || []).join(' '))
                                ).toLowerCase();
                                return hay.includes(q);
                        });
                }
                return out;
        },
        /**
         * Get the list of distinct categories present in the catalog.
         *
         * @param {Object} state Store state.
         * @return {Array<{slug:string,label:string,count:number}>} Categories.
         */
        getCategories(state) {
                const counts = {};
                state.templates.forEach((t) => {
                        const c = t.category || 'general';
                        if (!counts[c]) counts[c] = 0;
                        counts[c]++;
                });
                const labels = {
                        ecommerce: __('E-commerce', 'woo-total-menu'),
                        blog: __('Blog', 'woo-total-menu'),
                        corporate: __('Corporate', 'woo-total-menu'),
                        minimal: __('Minimaliste', 'woo-total-menu'),
                        electronics: __('Électronique', 'woo-total-menu'),
                        general: __('Général', 'woo-total-menu'),
                };
                return Object.keys(counts).map((slug) => ({
                        slug,
                        label: labels[slug] || slug,
                        count: counts[slug],
                }));
        },
        // === Apply ===
        isApplying(state) {
                return state.isApplying;
        },
        getApplyError(state) {
                return state.applyError;
        },
        getLastApplied(state) {
                return state.lastApplied;
        },
};

const reducer = (state = DEFAULT_STATE, action) => {
        switch (action.type) {
                case 'SET_TEMPLATES':
                        return { ...state, templates: action.templates };
                case 'SET_IS_LOADING':
                        return { ...state, isLoading: action.isLoading };
                case 'SET_ERROR':
                        return { ...state, error: action.error };
                case 'SET_FILTER_TYPE':
                        return { ...state, filterType: action.filterType };
                case 'SET_FILTER_CATEGORY':
                        return { ...state, filterCategory: action.filterCategory };
                case 'SET_FILTER_SEARCH':
                        return { ...state, filterSearch: action.filterSearch };
                case 'RESET_FILTERS':
                        return { ...state, filterType: '', filterCategory: '', filterSearch: '' };
                case 'SET_IS_APPLYING':
                        return { ...state, isApplying: action.isApplying };
                case 'SET_APPLY_ERROR':
                        return { ...state, applyError: action.applyError };
                case 'SET_LAST_APPLIED':
                        return { ...state, lastApplied: action.lastApplied };
                default:
                        return state;
        }
};

export const TEMPLATES_STORE_NAME = 'wtm/templates';

export const store = createReduxStore(TEMPLATES_STORE_NAME, {
        reducer,
        actions,
        selectors,
});

register(store);
