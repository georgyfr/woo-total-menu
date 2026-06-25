/**
 * ModuleProperties — right panel for editing the selected row/column/module.
 *
 * Spec reference: §4.6.5 (Propriétés), §3.6.3 (paramètres lignes/colonnes),
 * §3.7.1 (modules spécifiques au footer).
 *
 * @package WooTotalMenu
 * @since 1.4.0
 */

import { __ } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';
import { useState, useEffect, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { LAYOUT_STORE_NAME, findRow, findColumn, findModule } from '../stores/layout';
import { UI_STORE_NAME } from '../stores/ui';
import FieldRow from './FieldRow';

/**
 * Module-level cache for the available menus list.
 *
 * Both wtm_menu posts and WP native nav_menus are fetched once and cached
 * for the lifetime of the Builder session — they rarely change during a
 * single editing session and we want the dropdown to feel instant.
 */
let _availableMenusCache = null;

/**
 * Fetch the list of all menus selectable from the `menu` module.
 *
 * Returns a structured object:
 *   {
 *     wtm: [{ id, title }, ...],          // wtm_menu posts (post_id)
 *     wp:  [{ id, name, count }, ...],    // WP native nav_menus (term_id)
 *   }
 *
 * The result is cached in a module-level variable so subsequent calls
 * resolve immediately.
 *
 * @param {string} restNonce REST nonce for authentication.
 * @return {Promise<Object>} Resolves to { wtm, wp } arrays.
 */
async function fetchAvailableMenus(restNonce) {
        if (_availableMenusCache) {
                return _availableMenusCache;
        }

        apiFetch.use(apiFetch.createNonceMiddleware(restNonce));

        const [wtmResp, wpResp] = await Promise.all([
                apiFetch({ path: '/wtm/v1/menus?per_page=100&status=any' }).catch(() => []),
                apiFetch({ path: '/wtm/v1/wp-menus' }).catch(() => ({ menus: [] })),
        ]);

        const wtm = (Array.isArray(wtmResp) ? wtmResp : []).map((m) => ({
                id: m.id,
                title: m.title || `#${m.id}`,
                status: m.status || 'publish',
        }));

        const wpMenus = wpResp && Array.isArray(wpResp.menus) ? wpResp.menus : [];
        const wp = wpMenus.map((m) => ({
                id: m.id,
                name: m.name || `#${m.id}`,
                count: m.count || 0,
        }));

        _availableMenusCache = { wtm, wp };
        return _availableMenusCache;
}

/**
 * React hook returning the available menus (wtm + wp) plus a refresh callback.
 *
 * @param {string} restNonce REST nonce.
 * @return {{ wtm: Array, wp: Array, isLoading: boolean, refresh: Function }}
 */
function useAvailableMenus(restNonce) {
        const [state, setState] = useState({ wtm: [], wp: [], isLoading: true });

        const load = useCallback(async () => {
                if (!restNonce) return;
                setState((s) => ({ ...s, isLoading: true }));
                try {
                        const data = await fetchAvailableMenus(restNonce);
                        setState({ ...data, isLoading: false });
                } catch (err) {
                        setState({ wtm: [], wp: [], isLoading: false });
                }
        }, [restNonce]);

        useEffect(() => {
                load();
        }, [load]);

        return { ...state, refresh: load };
}

/**
 * Get the selected element from the active layout.
 *
 * @param {Object} layout         Layout object.
 * @param {string} selectedId     Selected element ID.
 * @param {string} selectedType   Selected element type.
 * @return {Object|null} { kind, row?, column?, module? }
 */
function resolveSelected(layout, selectedId, selectedType) {
        if (!layout || !selectedId) return null;
        if (selectedType === 'row') {
                const row = findRow(layout, selectedId);
                return row ? { kind: 'row', row } : null;
        }
        if (selectedType === 'column') {
                const result = findColumn(layout, selectedId);
                return result ? { kind: 'column', ...result } : null;
        }
        if (selectedType === 'module') {
                const result = findModule(layout, selectedId);
                return result ? { kind: 'module', ...result } : null;
        }
        return null;
}

export default function ModuleProperties() {
        const activeMode = useSelect((select) => select(UI_STORE_NAME).getActiveMode(), []);
        const layout = useSelect((select) => select(LAYOUT_STORE_NAME).getLayout(activeMode), [activeMode]);
        const selectedId = useSelect((select) => select(LAYOUT_STORE_NAME).getSelectedElementId(), []);
        const selectedType = useSelect((select) => select(LAYOUT_STORE_NAME).getSelectedElementType(), []);
        const { updateRow, updateColumn, updateModule } = useDispatch(LAYOUT_STORE_NAME);

        const selected = resolveSelected(layout, selectedId, selectedType);

        if (!selected) {
                return (
                        <aside className="wtm-builder__properties wtm-builder__properties--empty">
                                <div className="wtm-builder__properties-empty">
                                        <span className="dashicons dashicons-admin-customizer"></span>
                                        <p>{__('Sélectionnez un élément du canevas pour modifier ses propriétés.', 'woo-total-menu')}</p>
                                </div>
                        </aside>
                );
        }

        if (selected.kind === 'row') {
                const s = selected.row.settings || {};
                return (
                        <aside className="wtm-builder__properties">
                                <div className="wtm-builder__properties-header">
                                        <h2>
                                                <span className="dashicons dashicons-tag"></span>
                                                {__('Propriétés de la ligne', 'woo-total-menu')}
                                        </h2>
                                </div>
                                <FieldRow label={__('Couleur de fond', 'woo-total-menu')}>
                                        <input
                                                type="text"
                                                className="wtm-field-input"
                                                value={s.background || ''}
                                                placeholder="#FFFFFF"
                                                onChange={(e) => updateRow(activeMode, selected.row.id, { background: e.target.value })}
                                        />
                                </FieldRow>
                                <FieldRow label={__('Hauteur (px)', 'woo-total-menu')}>
                                        <input
                                                type="number"
                                                min="0"
                                                className="wtm-field-input"
                                                value={s.height || 0}
                                                onChange={(e) => updateRow(activeMode, selected.row.id, { height: parseInt(e.target.value, 10) || 0 })}
                                        />
                                </FieldRow>
                                <FieldRow label={__('Padding vertical (px)', 'woo-total-menu')}>
                                        <input
                                                type="number"
                                                min="0"
                                                className="wtm-field-input"
                                                value={s.padding_y ?? 12}
                                                onChange={(e) => updateRow(activeMode, selected.row.id, { padding_y: parseInt(e.target.value, 10) || 0 })}
                                        />
                                </FieldRow>
                                <FieldRow label={__('Alignement', 'woo-total-menu')}>
                                        <select
                                                className="wtm-field-input"
                                                value={s.align || 'space-between'}
                                                onChange={(e) => updateRow(activeMode, selected.row.id, { align: e.target.value })}
                                        >
                                                <option value="left">{__('Gauche', 'woo-total-menu')}</option>
                                                <option value="center">{__('Centre', 'woo-total-menu')}</option>
                                                <option value="right">{__('Droite', 'woo-total-menu')}</option>
                                                <option value="space-between">{__('Espace entre', 'woo-total-menu')}</option>
                                        </select>
                                </FieldRow>
                                <FieldRow label={__('Sticky', 'woo-total-menu')} hint={__('Reste collé en haut au scroll', 'woo-total-menu')}>
                                        <input
                                                type="checkbox"
                                                checked={!!s.sticky}
                                                onChange={(e) => updateRow(activeMode, selected.row.id, { sticky: e.target.checked })}
                                        />
                                </FieldRow>
                                <FieldRow label={__('Masquer desktop', 'woo-total-menu')}>
                                        <input
                                                type="checkbox"
                                                checked={!!s.hide_desktop}
                                                onChange={(e) => updateRow(activeMode, selected.row.id, { hide_desktop: e.target.checked })}
                                        />
                                </FieldRow>
                                <FieldRow label={__('Masquer mobile', 'woo-total-menu')}>
                                        <input
                                                type="checkbox"
                                                checked={!!s.hide_mobile}
                                                onChange={(e) => updateRow(activeMode, selected.row.id, { hide_mobile: e.target.checked })}
                                        />
                                </FieldRow>
                        </aside>
                );
        }

        if (selected.kind === 'column') {
                const c = selected.column;
                const s = c.settings || {};
                return (
                        <aside className="wtm-builder__properties">
                                <div className="wtm-builder__properties-header">
                                        <h2>
                                                <span className="dashicons dashicons-columns"></span>
                                                {__('Propriétés de la colonne', 'woo-total-menu')}
                                        </h2>
                                </div>
                                <FieldRow label={__('Largeur (1-12)', 'woo-total-menu')}>
                                        <input
                                                type="number"
                                                min="1"
                                                max="12"
                                                className="wtm-field-input"
                                                value={c.width ?? 6}
                                                onChange={(e) => updateColumn(activeMode, c.id, { width: parseInt(e.target.value, 10) || 1 })}
                                        />
                                </FieldRow>
                                <FieldRow label={__('Alignement horizontal', 'woo-total-menu')}>
                                        <select
                                                className="wtm-field-input"
                                                value={s.align || 'left'}
                                                onChange={(e) => updateColumn(activeMode, c.id, { align: e.target.value })}
                                        >
                                                <option value="left">{__('Gauche', 'woo-total-menu')}</option>
                                                <option value="center">{__('Centre', 'woo-total-menu')}</option>
                                                <option value="right">{__('Droite', 'woo-total-menu')}</option>
                                        </select>
                                </FieldRow>
                                <FieldRow label={__('Alignement vertical', 'woo-total-menu')}>
                                        <select
                                                className="wtm-field-input"
                                                value={s.valign || 'center'}
                                                onChange={(e) => updateColumn(activeMode, c.id, { valign: e.target.value })}
                                        >
                                                <option value="top">{__('Haut', 'woo-total-menu')}</option>
                                                <option value="center">{__('Centre', 'woo-total-menu')}</option>
                                                <option value="bottom">{__('Bas', 'woo-total-menu')}</option>
                                        </select>
                                </FieldRow>
                        </aside>
                );
        }

        if (selected.kind === 'module') {
                return <ModuleEditor activeMode={activeMode} module={selected.module} onUpdate={(patch) => updateModule(activeMode, selected.module.id, patch)} />;
        }

        return null;
}

/**
 * Menu module editor — exposes a unified dropdown of all selectable menus:
 *
 *   - wtm_menu posts (post_id)        → menu_source = "wtm"
 *   - WordPress native nav_menus      → menu_source = "wp"
 *     (taxonomy=nav_menu, term_id)
 *
 * The dropdown value is a composite "source:id" string so a single <select>
 * can represent both sources. The selection is split back into two separate
 * settings (`menu_source` and `menu_id`) when the user picks an option.
 *
 * @since 1.7.1
 *
 * @param {Object}   props          { settings, onUpdate }
 * @param {Object}   props.settings Module settings (incl. menu_source, menu_id, location).
 * @param {Function} props.onUpdate (patch) => void.
 * @return {JSX.Element} Menu module fields.
 */
function MenuModuleEditor({ settings, onUpdate }) {
        const restNonce = useSelect((select) => select(UI_STORE_NAME).getRestNonce(), []);
        const { wtm, wp, isLoading } = useAvailableMenus(restNonce);

        const s = settings || {};
        const setS = (key, value) => onUpdate({ settings: { [key]: value } });

        const source = s.menu_source || (s.menu_id ? 'wtm' : '');
        const compositeValue = source && s.menu_id ? `${source}:${s.menu_id}` : '';

        const handleChange = (e) => {
                const val = e.target.value;
                if (!val) {
                        setS('menu_id', 0);
                        setS('menu_source', '');
                        return;
                }
                const [src, idStr] = val.split(':');
                setS('menu_source', src);
                setS('menu_id', parseInt(idStr, 10) || 0);
        };

        const showWtmLocation = source !== 'wp';

        return (
                <>
                        <FieldRow
                                label={__('Menu à afficher', 'woo-total-menu')}
                                hint={__('Liste combinée des menus WTM et des menus WordPress natifs', 'woo-total-menu')}
                        >
                                <select
                                        className="wtm-field-input"
                                        value={compositeValue}
                                        onChange={handleChange}
                                        disabled={isLoading}
                                >
                                        <option value="">
                                                {isLoading
                                                        ? __('Chargement…', 'woo-total-menu')
                                                        : __('— Sélectionner un menu —', 'woo-total-menu')}
                                        </option>
                                        {wtm.length > 0 && (
                                                <optgroup label={__('Menus Woo Total Menu', 'woo-total-menu')}>
                                                        {wtm.map((m) => (
                                                                <option key={`wtm-${m.id}`} value={`wtm:${m.id}`}>
                                                                        {m.title} (#{m.id}){m.status && m.status !== 'publish' ? ` — ${m.status}` : ''}
                                                                </option>
                                                        ))}
                                                </optgroup>
                                        )}
                                        {wp.length > 0 && (
                                                <optgroup label={__('Menus WordPress natifs', 'woo-total-menu')}>
                                                        {wp.map((m) => (
                                                                <option key={`wp-${m.id}`} value={`wp:${m.id}`}>
                                                                        {m.name} (#{m.id}){m.count > 0 ? ` — ${m.count} items` : ''}
                                                                </option>
                                                        ))}
                                                </optgroup>
                                        )}
                                        {wtm.length === 0 && wp.length === 0 && !isLoading && (
                                                <option value="" disabled>
                                                        {__('Aucun menu disponible', 'woo-total-menu')}
                                                </option>
                                        )}
                                </select>
                        </FieldRow>
                        {showWtmLocation && (
                                <FieldRow label={__('Emplacement (optionnel)', 'woo-total-menu')}>
                                        <input
                                                type="text"
                                                className="wtm-field-input"
                                                value={s.location || ''}
                                                onChange={(e) => setS('location', e.target.value)}
                                        />
                                </FieldRow>
                        )}
                        {source === 'wp' && (
                                <p className="wtm-field-hint">
                                        <span className="dashicons dashicons-info"></span>
                                        {__('Les menus WordPress natifs sont gérés dans Apparence → Menus.', 'woo-total-menu')}
                                </p>
                        )}
                </>
        );
}

/**
 * Module-type-specific editor.
 *
 * @param {Object}   props      { activeMode, module, onUpdate }
 * @param {string}   props.activeMode 'header' | 'footer'.
 * @param {Object}   props.module Module object.
 * @param {Function} props.onUpdate (patch) => void.
 * @return {JSX.Element} Module editor.
 */
function ModuleEditor({ activeMode, module, onUpdate }) {
        const s = module.settings || {};
        const setS = (key, value) => onUpdate({ settings: { [key]: value } });

        const title = (
                <h2>
                        <span className="dashicons dashicons-admin-generic"></span>
                        {__('Module:', 'woo-total-menu')} <code>{module.type}</code>
                </h2>
        );

        let fields;
        switch (module.type) {
                case 'logo':
                        fields = (
                                <>
                                        <FieldRow label={__('Image (ID média)', 'woo-total-menu')}>
                                                <input
                                                        type="number"
                                                        min="0"
                                                        className="wtm-field-input"
                                                        value={s.image_id || 0}
                                                        onChange={(e) => setS('image_id', parseInt(e.target.value, 10) || 0)}
                                                />
                                        </FieldRow>
                                        <FieldRow label={__('URL', 'woo-total-menu')}>
                                                <input
                                                        type="url"
                                                        className="wtm-field-input"
                                                        value={s.url || ''}
                                                        placeholder={__('https://… (vide = accueil)', 'woo-total-menu')}
                                                        onChange={(e) => setS('url', e.target.value)}
                                                />
                                        </FieldRow>
                                        <FieldRow label={__('Largeur max (px)', 'woo-total-menu')}>
                                                <input
                                                        type="number"
                                                        min="0"
                                                        className="wtm-field-input"
                                                        value={s.max_width || 180}
                                                        onChange={(e) => setS('max_width', parseInt(e.target.value, 10) || 0)}
                                                />
                                        </FieldRow>
                                        <FieldRow label={__('Texte alternatif', 'woo-total-menu')}>
                                                <input
                                                        type="text"
                                                        className="wtm-field-input"
                                                        value={s.alt || ''}
                                                        onChange={(e) => setS('alt', e.target.value)}
                                                />
                                        </FieldRow>
                                </>
                        );
                        break;
                case 'menu':
                        fields = <MenuModuleEditor settings={s} onUpdate={onUpdate} />;
                        break;
                case 'search':
                        fields = (
                                <>
                                        <FieldRow label={__('Placeholder', 'woo-total-menu')}>
                                                <input
                                                        type="text"
                                                        className="wtm-field-input"
                                                        value={s.placeholder || ''}
                                                        onChange={(e) => setS('placeholder', e.target.value)}
                                                />
                                        </FieldRow>
                                        <FieldRow label={__('Style', 'woo-total-menu')}>
                                                <select
                                                        className="wtm-field-input"
                                                        value={s.style || 'inline'}
                                                        onChange={(e) => setS('style', e.target.value)}
                                                >
                                                        <option value="inline">{__('Inline', 'woo-total-menu')}</option>
                                                        <option value="overlay">{__('Overlay', 'woo-total-menu')}</option>
                                                </select>
                                        </FieldRow>
                                        <FieldRow label={__('Rechercher par SKU', 'woo-total-menu')}>
                                                <input
                                                        type="checkbox"
                                                        checked={!!s.search_sku}
                                                        onChange={(e) => setS('search_sku', e.target.checked)}
                                                />
                                        </FieldRow>
                                </>
                        );
                        break;
                case 'cart':
                        fields = (
                                <>
                                        <FieldRow label={__('Afficher le total', 'woo-total-menu')}>
                                                <input
                                                        type="checkbox"
                                                        checked={!!s.show_total}
                                                        onChange={(e) => setS('show_total', e.target.checked)}
                                                />
                                        </FieldRow>
                                        <FieldRow label={__('Comportement', 'woo-total-menu')}>
                                                <select
                                                        className="wtm-field-input"
                                                        value={s.behavior || 'drawer'}
                                                        onChange={(e) => setS('behavior', e.target.value)}
                                                >
                                                        <option value="drawer">{__('Drawer latéral', 'woo-total-menu')}</option>
                                                        <option value="dropdown">{__('Dropdown', 'woo-total-menu')}</option>
                                                </select>
                                        </FieldRow>
                                </>
                        );
                        break;
                case 'button':
                        fields = (
                                <>
                                        <FieldRow label={__('Texte', 'woo-total-menu')}>
                                                <input
                                                        type="text"
                                                        className="wtm-field-input"
                                                        value={s.text || ''}
                                                        onChange={(e) => setS('text', e.target.value)}
                                                />
                                        </FieldRow>
                                        <FieldRow label={__('URL', 'woo-total-menu')}>
                                                <input
                                                        type="url"
                                                        className="wtm-field-input"
                                                        value={s.url || '#'}
                                                        onChange={(e) => setS('url', e.target.value)}
                                                />
                                        </FieldRow>
                                        <FieldRow label={__('Style', 'woo-total-menu')}>
                                                <select
                                                        className="wtm-field-input"
                                                        value={s.style || 'primary'}
                                                        onChange={(e) => setS('style', e.target.value)}
                                                >
                                                        <option value="primary">{__('Primaire', 'woo-total-menu')}</option>
                                                        <option value="secondary">{__('Secondaire', 'woo-total-menu')}</option>
                                                        <option value="ghost">{__('Ghost', 'woo-total-menu')}</option>
                                                </select>
                                        </FieldRow>
                                        <FieldRow label={__('Cible', 'woo-total-menu')}>
                                                <select
                                                        className="wtm-field-input"
                                                        value={s.target || '_self'}
                                                        onChange={(e) => setS('target', e.target.value)}
                                                >
                                                        <option value="_self">{__('Même onglet', 'woo-total-menu')}</option>
                                                        <option value="_blank">{__('Nouvel onglet', 'woo-total-menu')}</option>
                                                </select>
                                        </FieldRow>
                                </>
                        );
                        break;
                case 'html':
                        fields = (
                                <FieldRow label={__('Contenu HTML', 'woo-total-menu')}>
                                        <textarea
                                                rows="6"
                                                className="wtm-field-input"
                                                value={s.content || ''}
                                                onChange={(e) => setS('content', e.target.value)}
                                        />
                                </FieldRow>
                        );
                        break;
                case 'social':
                        fields = (
                                <FieldRow label={__('Réseaux (JSON)', 'woo-total-menu')} hint={__('Format: [{"network":"facebook","url":"https://…"}]', 'woo-total-menu')}>
                                        <textarea
                                                rows="6"
                                                className="wtm-field-input"
                                                value={JSON.stringify(s.links || [], null, 2)}
                                                onChange={(e) => {
                                                        try {
                                                                const parsed = JSON.parse(e.target.value);
                                                                setS('links', Array.isArray(parsed) ? parsed : []);
                                                        } catch (err) {
                                                                // invalid JSON — keep textarea editable but don't update store.
                                                        }
                                                }}
                                        />
                                </FieldRow>
                        );
                        break;
                case 'newsletter':
                        fields = (
                                <>
                                        <FieldRow label={__('Titre', 'woo-total-menu')}>
                                                <input
                                                        type="text"
                                                        className="wtm-field-input"
                                                        value={s.title || ''}
                                                        onChange={(e) => setS('title', e.target.value)}
                                                />
                                        </FieldRow>
                                        <FieldRow label={__('Placeholder', 'woo-total-menu')}>
                                                <input
                                                        type="text"
                                                        className="wtm-field-input"
                                                        value={s.placeholder || ''}
                                                        onChange={(e) => setS('placeholder', e.target.value)}
                                                />
                                        </FieldRow>
                                        <FieldRow label={__('Texte du bouton', 'woo-total-menu')}>
                                                <input
                                                        type="text"
                                                        className="wtm-field-input"
                                                        value={s.button_text || ''}
                                                        onChange={(e) => setS('button_text', e.target.value)}
                                                />
                                        </FieldRow>
                                        <FieldRow label={__('Fournisseur', 'woo-total-menu')}>
                                                <select
                                                        className="wtm-field-input"
                                                        value={s.provider || 'internal'}
                                                        onChange={(e) => setS('provider', e.target.value)}
                                                >
                                                        <option value="internal">{__('Interne (email stocké)', 'woo-total-menu')}</option>
                                                        <option value="mailchimp">{__('Mailchimp', 'woo-total-menu')}</option>
                                                        <option value="brevo">{__('Brevo', 'woo-total-menu')}</option>
                                                </select>
                                        </FieldRow>
                                </>
                        );
                        break;
                case 'text':
                        fields = (
                                <FieldRow label={__('Contenu', 'woo-total-menu')} hint={__('Supporte [year] pour l\'année courante', 'woo-total-menu')}>
                                        <textarea
                                                rows="4"
                                                className="wtm-field-input"
                                                value={s.content || ''}
                                                onChange={(e) => setS('content', e.target.value)}
                                        />
                                </FieldRow>
                        );
                        break;
                default:
                        fields = <p className="wtm-field-empty">{__('Type de module inconnu.', 'woo-total-menu')}</p>;
        }

        // Common fields: custom CSS class, hide on desktop/mobile.
        const commonFields = (
                <>
                        <hr className="wtm-field-separator" />
                        <FieldRow label={__('Classe CSS personnalisée', 'woo-total-menu')}>
                                <input
                                        type="text"
                                        className="wtm-field-input"
                                        value={s.custom_class || ''}
                                        onChange={(e) => setS('custom_class', e.target.value)}
                                />
                        </FieldRow>
                        <FieldRow label={__('ID personnalisé', 'woo-total-menu')}>
                                <input
                                        type="text"
                                        className="wtm-field-input"
                                        value={s.custom_id || ''}
                                        onChange={(e) => setS('custom_id', e.target.value)}
                                />
                        </FieldRow>
                        <FieldRow label={__('Masquer desktop', 'woo-total-menu')}>
                                <input
                                        type="checkbox"
                                        checked={!!s.hide_desktop}
                                        onChange={(e) => setS('hide_desktop', e.target.checked)}
                                />
                        </FieldRow>
                        <FieldRow label={__('Masquer mobile', 'woo-total-menu')}>
                                <input
                                        type="checkbox"
                                        checked={!!s.hide_mobile}
                                        onChange={(e) => setS('hide_mobile', e.target.checked)}
                                />
                        </FieldRow>
                </>
        );

        return (
                <aside className="wtm-builder__properties">
                        <div className="wtm-builder__properties-header">{title}</div>
                        {fields}
                        {commonFields}
                </aside>
        );
}
