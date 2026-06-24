/**
 * PropertiesPanel — right column showing properties of the selected item.
 *
 * Supports in v1.1.1:
 * - Edit label, url, target, icon for "link" items
 * - Edit label for "title" items
 * - Edit widget_type + widget_settings.content for "html" widget
 * - Edit badge (text, color, background) for items that can have badges
 * - Edit width for "column" items
 * - Edit menu title (top-level)
 *
 * @package WooTotalMenu
 * @since 1.1.0
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useSelect, useDispatch } from '@wordpress/data';

import { WTM_STORE_NAME } from '../stores/menu';
import { UI_STORE_NAME } from '../stores/ui';

export default function PropertiesPanel() {
        const selectedItemId = useSelect((select) => select(UI_STORE_NAME).getSelectedItemId(), []);
        const selectedItem = useSelect(
                (select) => selectedItemId ? select(WTM_STORE_NAME).getSelectedItem(selectedItemId) : null,
                [selectedItemId]
        );
        const menu = useSelect((select) => select(WTM_STORE_NAME).getMenu(), []);

        if (selectedItem) {
                return <ItemProperties item={selectedItem} />;
        }
        if (menu) {
                return <MenuProperties menu={menu} />;
        }
        return <EmptyState />;
}

function EmptyState() {
        return (
                <div className="wtm-properties">
                        <div className="wtm-properties__header">
                                <h2>
                                        <span className="dashicons dashicons-admin-customizer"></span>
                                        {__('Propriétés', 'woo-total-menu')}
                                </h2>
                        </div>
                        <div className="wtm-properties__body">
                                <div className="wtm-properties__empty">
                                        <span className="dashicons dashicons-info"></span>
                                        <p>{__('Sélectionnez un élément dans l\'arborescence pour afficher ses propriétés.', 'woo-total-menu')}</p>
                                </div>
                        </div>
                </div>
        );
}

function MenuProperties({ menu }) {
        const { updateMenuTitle } = useDispatch(WTM_STORE_NAME);
        const [title, setTitle] = useStateValue(menu.title);

        return (
                <div className="wtm-properties">
                        <div className="wtm-properties__header">
                                <h2>
                                        <span className="dashicons dashicons-admin-customizer"></span>
                                        {__('Propriétés du menu', 'woo-total-menu')}
                                </h2>
                        </div>
                        <div className="wtm-properties__body">
                                <div className="wtm-properties__row">
                                        <label>{__('Titre', 'woo-total-menu')}</label>
                                        <input
                                                type="text"
                                                value={title}
                                                onChange={(e) => setTitle(e.target.value)}
                                                onBlur={() => title !== menu.title && updateMenuTitle(title)}
                                        />
                                </div>
                                <div className="wtm-properties__row">
                                        <label>{__('Slug', 'woo-total-menu')}</label>
                                        <code>{menu.slug}</code>
                                </div>
                                <div className="wtm-properties__row">
                                        <label>{__('Statut', 'woo-total-menu')}</label>
                                        <code>{menu.status}</code>
                                </div>
                                <div className="wtm-properties__row">
                                        <label>{__('Type de menu', 'woo-total-menu')}</label>
                                        <code>{menu.menu_type}</code>
                                </div>
                                <div className="wtm-properties__row">
                                        <label>{__('Emplacement', 'woo-total-menu')}</label>
                                        <code>{menu.location}</code>
                                </div>
                                <div className="wtm-properties__row">
                                        <label>{__('Version du schéma', 'woo-total-menu')}</label>
                                        <code>{menu.version}</code>
                                </div>
                                <div className="wtm-properties__row">
                                        <label>{__('ID', 'woo-total-menu')}</label>
                                        <code>{menu.id}</code>
                                </div>
                        </div>
                </div>
        );
}

function ItemProperties({ item }) {
        const { updateItem } = useDispatch(WTM_STORE_NAME);

        const updateField = (field, value) => {
                updateItem(item.id, { [field]: value });
        };

        const updateBadge = (field, value) => {
                const newBadge = { ...(item.badge || {}), [field]: value };
                updateItem(item.id, { badge: newBadge });
        };

        const updateWidgetSetting = (key, value) => {
                const newSettings = { ...(item.widget_settings || {}), [key]: value };
                updateItem(item.id, { widget_settings: newSettings });
        };

        return (
                <div className="wtm-properties">
                        <div className="wtm-properties__header">
                                <h2>
                                        <span className="dashicons dashicons-admin-customizer"></span>
                                        {__('Propriétés de l\'élément', 'woo-total-menu')}
                                </h2>
                        </div>
                        <div className="wtm-properties__body">
                                <div className="wtm-properties__row">
                                        <label>{__('ID', 'woo-total-menu')}</label>
                                        <code>{item.id}</code>
                                </div>
                                <div className="wtm-properties__row">
                                        <label>{__('Type', 'woo-total-menu')}</label>
                                        <code>{item.type}</code>
                                </div>

                                {item.type === 'link' && (
                                        <>
                                                <div className="wtm-properties__row">
                                                        <label>{__('Libellé', 'woo-total-menu')}</label>
                                                        <input
                                                                type="text"
                                                                value={item.label || ''}
                                                                onChange={(e) => updateField('label', e.target.value)}
                                                        />
                                                </div>
                                                <div className="wtm-properties__row">
                                                        <label>{__('URL', 'woo-total-menu')}</label>
                                                        <input
                                                                type="text"
                                                                value={item.url || ''}
                                                                onChange={(e) => updateField('url', e.target.value)}
                                                        />
                                                </div>
                                                <div className="wtm-properties__row">
                                                        <label>{__('Cible', 'woo-total-menu')}</label>
                                                        <select
                                                                value={item.target || '_self'}
                                                                onChange={(e) => updateField('target', e.target.value)}
                                                        >
                                                                <option value="_self">{__('Même fenêtre (_self)', 'woo-total-menu')}</option>
                                                                <option value="_blank">{__('Nouvelle fenêtre (_blank)', 'woo-total-menu')}</option>
                                                        </select>
                                                </div>
                                                <div className="wtm-properties__row">
                                                        <label>{__('Icône (nom ou URL SVG)', 'woo-total-menu')}</label>
                                                        <input
                                                                type="text"
                                                                value={item.icon || ''}
                                                                onChange={(e) => updateField('icon', e.target.value)}
                                                                placeholder="ex: house, envelope, ou /icon.svg"
                                                        />
                                                </div>
                                        </>
                                )}

                                {item.type === 'title' && (
                                        <div className="wtm-properties__row">
                                                <label>{__('Libellé', 'woo-total-menu')}</label>
                                                <input
                                                        type="text"
                                                        value={item.label || ''}
                                                        onChange={(e) => updateField('label', e.target.value)}
                                                />
                                        </div>
                                )}

                                {item.type === 'column' && (
                                        <div className="wtm-properties__row">
                                                <label>{__('Largeur (1-12)', 'woo-total-menu')}</label>
                                                <input
                                                        type="number"
                                                        min="1"
                                                        max="12"
                                                        value={item.width || 6}
                                                        onChange={(e) => updateField('width', parseInt(e.target.value, 10))}
                                                />
                                        </div>
                                )}

                                {item.type === 'mega_container' && (
                                        <>
                                                <div className="wtm-properties__row">
                                                        <label>{__('Libellé', 'woo-total-menu')}</label>
                                                        <input
                                                                type="text"
                                                                value={item.label || ''}
                                                                onChange={(e) => updateField('label', e.target.value)}
                                                        />
                                                </div>
                                                <div className="wtm-properties__row">
                                                        <label>{__('Déclencheur', 'woo-total-menu')}</label>
                                                        <select
                                                                value={item.trigger || 'hover'}
                                                                onChange={(e) => updateField('trigger', e.target.value)}
                                                        >
                                                                <option value="hover">{__('Survol (hover)', 'woo-total-menu')}</option>
                                                                <option value="click">{__('Clic (click)', 'woo-total-menu')}</option>
                                                        </select>
                                                </div>
                                                <div className="wtm-properties__row">
                                                        <label>{__('Largeur (px, 200-2000, ou "full")', 'woo-total-menu')}</label>
                                                        <input
                                                                type="text"
                                                                value={item.width || ''}
                                                                onChange={(e) => {
                                                                        const v = e.target.value;
                                                                        if (v === 'full' || v === '') {
                                                                                updateField('width', v === '' ? undefined : v);
                                                                        } else {
                                                                                const n = parseInt(v, 10);
                                                                                if (!isNaN(n)) updateField('width', n);
                                                                        }
                                                                }}
                                                                placeholder="ex: 800 ou full"
                                                        />
                                                </div>
                                        </>
                                )}

                                {item.type === 'widget' && (
                                        <>
                                                <div className="wtm-properties__row">
                                                        <label>{__('Type de widget', 'woo-total-menu')}</label>
                                                        <code>{item.widget_type}</code>
                                                </div>
                                                <div className="wtm-properties__row">
                                                        <label>{__('Libellé (optionnel)', 'woo-total-menu')}</label>
                                                        <input
                                                                type="text"
                                                                value={item.label || ''}
                                                                onChange={(e) => updateField('label', e.target.value)}
                                                        />
                                                </div>
                                                {item.widget_type === 'html' && (
                                                        <div className="wtm-properties__row">
                                                                <label>{__('Contenu HTML', 'woo-total-menu')}</label>
                                                                <textarea
                                                                        rows="4"
                                                                        value={item.widget_settings?.content || ''}
                                                                        onChange={(e) => updateWidgetSetting('content', e.target.value)}
                                                                />
                                                        </div>
                                                )}
                                                {item.widget_type === 'banner' && (
                                                        <>
                                                                <div className="wtm-properties__row">
                                                                        <label>{__('URL de l\'image', 'woo-total-menu')}</label>
                                                                        <input
                                                                                type="text"
                                                                                value={item.widget_settings?.image_url || ''}
                                                                                onChange={(e) => updateWidgetSetting('image_url', e.target.value)}
                                                                        />
                                                                </div>
                                                                <div className="wtm-properties__row">
                                                                        <label>{__('Lien au clic', 'woo-total-menu')}</label>
                                                                        <input
                                                                                type="text"
                                                                                value={item.widget_settings?.link_url || ''}
                                                                                onChange={(e) => updateWidgetSetting('link_url', e.target.value)}
                                                                        />
                                                                </div>
                                                                <div className="wtm-properties__row">
                                                                        <label>{__('Texte alternatif', 'woo-total-menu')}</label>
                                                                        <input
                                                                                type="text"
                                                                                value={item.widget_settings?.alt || ''}
                                                                                onChange={(e) => updateWidgetSetting('alt', e.target.value)}
                                                                        />
                                                                </div>
                                                        </>
                                                )}
                                                {item.widget_type === 'product_grid' && (
                                                        <>
                                                                <div className="wtm-properties__row">
                                                                        <label>{__('Source', 'woo-total-menu')}</label>
                                                                        <select
                                                                                value={item.widget_settings?.product_source || 'featured'}
                                                                                onChange={(e) => updateWidgetSetting('product_source', e.target.value)}
                                                                        >
                                                                                <option value="featured">{__('Mis en avant', 'woo-total-menu')}</option>
                                                                                <option value="best_selling">{__('Meilleures ventes', 'woo-total-menu')}</option>
                                                                                <option value="recent">{__('Récentes', 'woo-total-menu')}</option>
                                                                                <option value="on_sale">{__('En promo', 'woo-total-menu')}</option>
                                                                                <option value="custom">{__('Personnalisé', 'woo-total-menu')}</option>
                                                                        </select>
                                                                </div>
                                                                <div className="wtm-properties__row">
                                                                        <label>{__('Colonnes (1-6)', 'woo-total-menu')}</label>
                                                                        <input
                                                                                type="number"
                                                                                min="1"
                                                                                max="6"
                                                                                value={item.widget_settings?.columns || 4}
                                                                                onChange={(e) => updateWidgetSetting('columns', parseInt(e.target.value, 10))}
                                                                        />
                                                                </div>
                                                                <div className="wtm-properties__row">
                                                                        <label>{__('Limite (1-12)', 'woo-total-menu')}</label>
                                                                        <input
                                                                                type="number"
                                                                                min="1"
                                                                                max="12"
                                                                                value={item.widget_settings?.limit || 4}
                                                                                onChange={(e) => updateWidgetSetting('limit', parseInt(e.target.value, 10))}
                                                                        />
                                                                </div>
                                                        </>
                                                )}
                                                {item.widget_type === 'category_grid' && (
                                                        <>
                                                                <div className="wtm-properties__row">
                                                                        <label>{__('Colonnes (1-6)', 'woo-total-menu')}</label>
                                                                        <input
                                                                                type="number"
                                                                                min="1"
                                                                                max="6"
                                                                                value={item.widget_settings?.columns || 3}
                                                                                onChange={(e) => updateWidgetSetting('columns', parseInt(e.target.value, 10))}
                                                                        />
                                                                </div>
                                                                <div className="wtm-properties__row">
                                                                        <label>{__('Afficher images', 'woo-total-menu')}</label>
                                                                        <input
                                                                                type="checkbox"
                                                                                checked={item.widget_settings?.show_images !== false}
                                                                                onChange={(e) => updateWidgetSetting('show_images', e.target.checked)}
                                                                        />
                                                                </div>
                                                                <div className="wtm-properties__row">
                                                                        <label>{__('Afficher compteurs', 'woo-total-menu')}</label>
                                                                        <input
                                                                                type="checkbox"
                                                                                checked={item.widget_settings?.show_counts === true}
                                                                                onChange={(e) => updateWidgetSetting('show_counts', e.target.checked)}
                                                                        />
                                                                </div>
                                                        </>
                                                )}
                                        </>
                                )}

                                {/* Badge editor (for link, mega_container, title) */}
                                {(item.type === 'link' || item.type === 'mega_container' || item.type === 'title') && (
                                        <BadgeEditor
                                                badge={item.badge}
                                                onChange={(field, value) => updateBadge(field, value)}
                                                onRemove={() => updateField('badge', undefined)}
                                        />
                                )}

                                {/* Visibility */}
                                <div className="wtm-properties__row">
                                        <label>{__('Visibilité', 'woo-total-menu')}</label>
                                        <select
                                                value={item.visibility || 'show'}
                                                onChange={(e) => updateField('visibility', e.target.value)}
                                        >
                                                <option value="show">{__('Toujours visible', 'woo-total-menu')}</option>
                                                <option value="hide">{__('Toujours masqué', 'woo-total-menu')}</option>
                                                <option value="show_on_mobile">{__('Visible sur mobile', 'woo-total-menu')}</option>
                                                <option value="hide_on_mobile">{__('Masqué sur mobile', 'woo-total-menu')}</option>
                                        </select>
                                </div>

                                <div className="wtm-properties__hint">
                                        {__('Les modifications sont appliquées immédiatement et marquées comme "à sauvegarder" (●).', 'woo-total-menu')}
                                </div>
                        </div>
                </div>
        );
}

function BadgeEditor({ badge, onChange, onRemove }) {
        if (!badge) {
                return (
                        <div className="wtm-properties__row">
                                <label>{__('Badge', 'woo-total-menu')}</label>
                                <button type="button" className="wtm-properties__add-badge" onClick={() => onChange('text', 'Nouveau')}>
                                        + {__('Ajouter un badge', 'woo-total-menu')}
                                </button>
                        </div>
                );
        }
        return (
                <div className="wtm-properties__badge-editor">
                        <div className="wtm-properties__row">
                                <label>{__('Badge — Texte', 'woo-total-menu')}</label>
                                <input
                                        type="text"
                                        value={badge.text || ''}
                                        onChange={(e) => onChange('text', e.target.value)}
                                />
                        </div>
                        <div className="wtm-properties__row">
                                <label>{__('Badge — Couleur du texte', 'woo-total-menu')}</label>
                                <input
                                        type="color"
                                        value={badge.color || '#FFFFFF'}
                                        onChange={(e) => onChange('color', e.target.value)}
                                />
                        </div>
                        <div className="wtm-properties__row">
                                <label>{__('Badge — Couleur de fond', 'woo-total-menu')}</label>
                                <input
                                        type="color"
                                        value={badge.background || '#6C5CE7'}
                                        onChange={(e) => onChange('background', e.target.value)}
                                />
                        </div>
                        <button type="button" className="wtm-properties__remove-badge" onClick={onRemove}>
                                {__('Retirer le badge', 'woo-total-menu')}
                        </button>
                </div>
        );
}

/**
 * Simple local state helper that syncs with a prop value.
 * Used for the menu title (which is editable only onBlur).
 */
function useStateValue(initialValue) {
        const [value, setValue] = useState(initialValue);
        useEffect(() => setValue(initialValue), [initialValue]);
        return [value, setValue];
}
