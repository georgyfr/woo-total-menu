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
import { TextRow, NumberRow, CheckboxRow, SelectRow, ColorRow, TextareaRow } from './FieldRow';

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
                                                <TextRow label={__('Libellé (optionnel)', 'woo-total-menu')} value={item.label} onChange={(v) => updateField('label', v)} />
                                                {item.widget_type === 'html' && (
                                                        <TextareaRow label={__('Contenu HTML', 'woo-total-menu')} value={item.widget_settings?.content} rows={4} onChange={(v) => updateWidgetSetting('content', v)} />
                                                )}
                                                {item.widget_type === 'banner' && (
                                                        <>
                                                                <TextRow label={__('URL de l\'image', 'woo-total-menu')} value={item.widget_settings?.image_url} onChange={(v) => updateWidgetSetting('image_url', v)} />
                                                                <TextRow label={__('Lien au clic', 'woo-total-menu')} value={item.widget_settings?.link_url} onChange={(v) => updateWidgetSetting('link_url', v)} />
                                                                <TextRow label={__('Texte alternatif', 'woo-total-menu')} value={item.widget_settings?.alt} onChange={(v) => updateWidgetSetting('alt', v)} />
                                                        </>
                                                )}
                                                {item.widget_type === 'custom_link' && (
                                                        <>
                                                                <TextRow label={__('Libellé du lien', 'woo-total-menu')} value={item.widget_settings?.label} onChange={(v) => updateWidgetSetting('label', v)} />
                                                                <TextRow label={__('URL', 'woo-total-menu')} value={item.widget_settings?.url} onChange={(v) => updateWidgetSetting('url', v)} />
                                                                <ColorRow label={__('Couleur de fond', 'woo-total-menu')} value={item.widget_settings?.background || '#6C5CE7'} onChange={(v) => updateWidgetSetting('background', v)} />
                                                                <ColorRow label={__('Couleur du texte', 'woo-total-menu')} value={item.widget_settings?.color || '#FFFFFF'} onChange={(v) => updateWidgetSetting('color', v)} />
                                                        </>
                                                )}
                                                {item.widget_type === 'product_grid' && (
                                                        <>
                                                                <SelectRow
                                                                        label={__('Source', 'woo-total-menu')}
                                                                        value={item.widget_settings?.product_source || 'featured'}
                                                                        onChange={(v) => updateWidgetSetting('product_source', v)}
                                                                        options={[
                                                                                { value: 'featured', label: __('Mis en avant', 'woo-total-menu') },
                                                                                { value: 'best_selling', label: __('Meilleures ventes', 'woo-total-menu') },
                                                                                { value: 'recent', label: __('Récentes', 'woo-total-menu') },
                                                                                { value: 'on_sale', label: __('En promo', 'woo-total-menu') },
                                                                                { value: 'custom', label: __('Personnalisé', 'woo-total-menu') },
                                                                        ]}
                                                                />
                                                                <NumberRow label={__('Colonnes (1-6)', 'woo-total-menu')} value={item.widget_settings?.columns || 4} min="1" max="6" onChange={(v) => updateWidgetSetting('columns', v)} />
                                                                <NumberRow label={__('Limite (1-12)', 'woo-total-menu')} value={item.widget_settings?.limit || 4} min="1" max="12" onChange={(v) => updateWidgetSetting('limit', v)} />
                                                        </>
                                                )}
                                                {item.widget_type === 'category_grid' && (
                                                        <>
                                                                <NumberRow label={__('Colonnes (1-6)', 'woo-total-menu')} value={item.widget_settings?.columns || 3} min="1" max="6" onChange={(v) => updateWidgetSetting('columns', v)} />
                                                                <NumberRow label={__('Limite (1-12)', 'woo-total-menu')} value={item.widget_settings?.limit || 6} min="1" max="12" onChange={(v) => updateWidgetSetting('limit', v)} />
                                                                <CheckboxRow label={__('Afficher images', 'woo-total-menu')} checked={item.widget_settings?.show_images !== false} onChange={(v) => updateWidgetSetting('show_images', v)} />
                                                                <CheckboxRow label={__('Afficher compteurs', 'woo-total-menu')} checked={item.widget_settings?.show_counts === true} onChange={(v) => updateWidgetSetting('show_counts', v)} />
                                                        </>
                                                )}
                                                {item.widget_type === 'mini_cart' && (
                                                        <>
                                                                <SelectRow
                                                                        label={__('Mode d\'affichage', 'woo-total-menu')}
                                                                        value={item.widget_settings?.display_mode || 'link'}
                                                                        onChange={(v) => updateWidgetSetting('display_mode', v)}
                                                                        options={[
                                                                                { value: 'link', label: __('Lien vers le panier', 'woo-total-menu') },
                                                                                { value: 'drawer', label: __('Drawer latéral AJAX', 'woo-total-menu') },
                                                                        ]}
                                                                />
                                                                {item.widget_settings?.display_mode === 'drawer' && (
                                                                        <SelectRow
                                                                                label={__('Position du drawer', 'woo-total-menu')}
                                                                                value={item.widget_settings?.drawer_position || 'right'}
                                                                                onChange={(v) => updateWidgetSetting('drawer_position', v)}
                                                                                options={[
                                                                                        { value: 'right', label: __('Droite', 'woo-total-menu') },
                                                                                        { value: 'left', label: __('Gauche', 'woo-total-menu') },
                                                                                ]}
                                                                        />
                                                                )}
                                                                <CheckboxRow label={__('Afficher le sous-total', 'woo-total-menu')} checked={item.widget_settings?.show_subtotal !== false} onChange={(v) => updateWidgetSetting('show_subtotal', v)} />
                                                                <CheckboxRow label={__('Afficher bouton "Commander"', 'woo-total-menu')} checked={item.widget_settings?.show_checkout_button === true} onChange={(v) => updateWidgetSetting('show_checkout_button', v)} />
                                                                <CheckboxRow label={__('Afficher miniature produit', 'woo-total-menu')} checked={item.widget_settings?.show_thumbnail === true} onChange={(v) => updateWidgetSetting('show_thumbnail', v)} />
                                                        </>
                                                )}
                                                {item.widget_type === 'search' && (
                                                        <>
                                                                <TextRow label={__('Placeholder', 'woo-total-menu')} value={item.widget_settings?.placeholder} onChange={(v) => updateWidgetSetting('placeholder', v)} />
                                                                <CheckboxRow label={__('Suggestions live (AJAX)', 'woo-total-menu')} checked={item.widget_settings?.live_suggestions === true} onChange={(v) => updateWidgetSetting('live_suggestions', v)} />
                                                                {item.widget_settings?.live_suggestions && (
                                                                        <NumberRow label={__('Caractères min. (2-5)', 'woo-total-menu')} value={item.widget_settings?.min_chars || 3} min="2" max="5" onChange={(v) => updateWidgetSetting('min_chars', v)} />
                                                                )}
                                                                <CheckboxRow label={__('Filtre par catégorie', 'woo-total-menu')} checked={item.widget_settings?.show_category_filter === true} onChange={(v) => updateWidgetSetting('show_category_filter', v)} />
                                                        </>
                                                )}
                                                {item.widget_type === 'recent_posts' && (
                                                        <>
                                                                <NumberRow label={__('Colonnes (1-4)', 'woo-total-menu')} value={item.widget_settings?.columns || 2} min="1" max="4" onChange={(v) => updateWidgetSetting('columns', v)} />
                                                                <NumberRow label={__('Limite (1-12)', 'woo-total-menu')} value={item.widget_settings?.limit || 4} min="1" max="12" onChange={(v) => updateWidgetSetting('limit', v)} />
                                                                <SelectRow
                                                                        label={__('Tri', 'woo-total-menu')}
                                                                        value={item.widget_settings?.orderby || 'date'}
                                                                        onChange={(v) => updateWidgetSetting('orderby', v)}
                                                                        options={[
                                                                                { value: 'date', label: __('Date (récent)', 'woo-total-menu') },
                                                                                { value: 'title', label: __('Titre (A-Z)', 'woo-total-menu') },
                                                                                { value: 'comment_count', label: __('Plus commentés', 'woo-total-menu') },
                                                                                { value: 'rand', label: __('Aléatoire', 'woo-total-menu') },
                                                                        ]}
                                                                />
                                                                <CheckboxRow label={__('Afficher image', 'woo-total-menu')} checked={item.widget_settings?.show_image === true} onChange={(v) => updateWidgetSetting('show_image', v)} />
                                                                <CheckboxRow label={__('Afficher date', 'woo-total-menu')} checked={item.widget_settings?.show_date !== false} onChange={(v) => updateWidgetSetting('show_date', v)} />
                                                                <CheckboxRow label={__('Afficher extrait', 'woo-total-menu')} checked={item.widget_settings?.show_excerpt === true} onChange={(v) => updateWidgetSetting('show_excerpt', v)} />
                                                        </>
                                                )}
                                                {item.widget_type === 'social_icons' && (
                                                        <>
                                                                <NumberRow label={__('Taille icône (12-64 px)', 'woo-total-menu')} value={item.widget_settings?.size || 24} min="12" max="64" onChange={(v) => updateWidgetSetting('size', v)} />
                                                                <TextareaRow
                                                                        label={__('Réseaux (un par ligne : network,url)', 'woo-total-menu')}
                                                                        rows={4}
                                                                        value={(item.widget_settings?.items || []).map((s) => (s.network || '') + ',' + (s.url || '')).join('\n')}
                                                                        onChange={(v) => {
                                                                                const items = v.split('\n').filter(Boolean).map((line) => {
                                                                                        const parts = line.split(',').map((s) => s.trim());
                                                                                        return { network: parts[0] || '', url: parts[1] || '' };
                                                                                });
                                                                                updateWidgetSetting('items', items);
                                                                        }}
                                                                />
                                                        </>
                                                )}
                                                {item.widget_type === 'newsletter' && (
                                                        <>
                                                                <TextRow label={__('Placeholder', 'woo-total-menu')} value={item.widget_settings?.placeholder} onChange={(v) => updateWidgetSetting('placeholder', v)} />
                                                                <TextRow label={__('Libellé du bouton', 'woo-total-menu')} value={item.widget_settings?.button_label} onChange={(v) => updateWidgetSetting('button_label', v)} />
                                                                <SelectRow
                                                                        label={__('Provider', 'woo-total-menu')}
                                                                        value={item.widget_settings?.provider || 'internal'}
                                                                        onChange={(v) => updateWidgetSetting('provider', v)}
                                                                        options={[
                                                                                { value: 'internal', label: __('Interne (sauvegarde en base)', 'woo-total-menu') },
                                                                                { value: 'mailchimp', label: __('Mailchimp (via hook)', 'woo-total-menu') },
                                                                                { value: 'none', label: __('Aucun (démo)', 'woo-total-menu') },
                                                                        ]}
                                                                />
                                                                <TextRow label={__('ID de liste (optionnel)', 'woo-total-menu')} value={item.widget_settings?.list_id} onChange={(v) => updateWidgetSetting('list_id', v)} />
                                                                <SelectRow
                                                                        label={__('Disposition', 'woo-total-menu')}
                                                                        value={item.widget_settings?.layout || 'inline'}
                                                                        onChange={(v) => updateWidgetSetting('layout', v)}
                                                                        options={[
                                                                                { value: 'inline', label: __('Inline (champ + bouton côte à côte)', 'woo-total-menu') },
                                                                                { value: 'stacked', label: __('Empilé (champ au-dessus du bouton)', 'woo-total-menu') },
                                                                        ]}
                                                                />
                                                                <TextareaRow label={__('Message de succès', 'woo-total-menu')} value={item.widget_settings?.success_message} rows={2} onChange={(v) => updateWidgetSetting('success_message', v)} />
                                                        </>
                                                )}
                                                {item.widget_type === 'filters' && (
                                                        <>
                                                                <CheckboxRow label={__('Filtre catégories', 'woo-total-menu')} checked={item.widget_settings?.show_categories !== false} onChange={(v) => updateWidgetSetting('show_categories', v)} />
                                                                <CheckboxRow label={__('Filtre prix', 'woo-total-menu')} checked={item.widget_settings?.show_price === true} onChange={(v) => updateWidgetSetting('show_price', v)} />
                                                                <CheckboxRow label={__('Filtre attributs', 'woo-total-menu')} checked={item.widget_settings?.show_attributes === true} onChange={(v) => updateWidgetSetting('show_attributes', v)} />
                                                                {item.widget_settings?.show_attributes && (
                                                                        <TextRow
                                                                                label={__('Slugs d\'attributs (séparés par virgule)', 'woo-total-menu')}
                                                                                value={(item.widget_settings?.attributes || []).join(',')}
                                                                                placeholder="couleur,taille"
                                                                                onChange={(v) => {
                                                                                        const arr = v.split(',').map((s) => s.trim()).filter(Boolean);
                                                                                        updateWidgetSetting('attributes', arr);
                                                                                }}
                                                                        />
                                                                )}
                                                        </>
                                                )}
                                                {item.widget_type === 'title' && (
                                                        <>
                                                                <TextRow label={__('Texte', 'woo-total-menu')} value={item.widget_settings?.text} onChange={(v) => updateWidgetSetting('text', v)} />
                                                                <NumberRow label={__('Niveau (1-6)', 'woo-total-menu')} value={item.widget_settings?.level || 4} min="1" max="6" onChange={(v) => updateWidgetSetting('level', v)} />
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
