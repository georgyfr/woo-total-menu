/**
 * AddItemButton — dropdown button to add a new item (6 types).
 *
 * v1.3.0: when user picks "Widget", a secondary panel opens to choose
 * among 12 widget subtypes (html, banner, product_grid, category_grid,
 * mini_cart, search, custom_link, recent_posts, social_icons, newsletter,
 * filters, title). Each subtype carries its own defaults + icon.
 *
 * @package WooTotalMenu
 * @since 1.1.1
 */

import { useState, useRef, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useDispatch } from '@wordpress/data';

import { WTM_STORE_NAME } from '../stores/menu';

const ITEM_TYPES = [
        {
                value: 'link',
                label: __('Lien', 'woo-total-menu'),
                icon: 'admin-links',
                description: __('Un lien simple avec URL', 'woo-total-menu'),
                defaults: { label: __('Nouveau lien', 'woo-total-menu'), url: '#' },
        },
        {
                value: 'mega_container',
                label: __('Méga conteneur', 'woo-total-menu'),
                icon: 'screenoptions',
                description: __('Conteneur multi-colonnes', 'woo-total-menu'),
                defaults: { label: __('Nouveau méga', 'woo-total-menu'), children: [] },
        },
        {
                value: 'column',
                label: __('Colonne', 'woo-total-menu'),
                icon: 'columns',
                description: __('Colonne (dans un méga)', 'woo-total-menu'),
                defaults: { width: 6, children: [] },
        },
        {
                value: 'widget',
                label: __('Widget', 'woo-total-menu'),
                icon: 'admin-generic',
                description: __('Widget dynamique WooCommerce / WordPress', 'woo-total-menu'),
                defaults: { widget_type: 'html', widget_settings: { content: '<p>Nouveau widget</p>' } },
        },
        {
                value: 'title',
                label: __('Titre', 'woo-total-menu'),
                icon: 'heading',
                description: __('Titre de section', 'woo-total-menu'),
                defaults: { label: __('Nouveau titre', 'woo-total-menu') },
        },
        {
                value: 'separator',
                label: __('Séparateur', 'woo-total-menu'),
                icon: 'minus',
                description: __('Séparateur visuel', 'woo-total-menu'),
                defaults: {},
        },
];

/**
 * Widget subtypes — shown when user picks "Widget" in the dropdown.
 *
 * Compact format: [value, label, icon, defaults].
 * The list must stay in sync with the PHP Schema_Validator::WIDGET_TYPES constant.
 *
 * @since 1.3.0
 */
const WIDGET_SUBTYPES = [
        ['html', __('HTML libre', 'woo-total-menu'), 'editor-code',
                { widget_settings: { content: '<p>Nouveau widget</p>' } }],
        ['banner', __('Bannière', 'woo-total-menu'), 'format-image',
                { widget_settings: { image_url: '', link_url: '', alt: '' } }],
        ['custom_link', __('Lien stylé', 'woo-total-menu'), 'admin-links',
                { widget_settings: { label: __('Mon lien', 'woo-total-menu'), url: '#', background: '#6C5CE7', color: '#FFFFFF' } }],
        ['product_grid', __('Grille produits', 'woo-total-menu'), 'products',
                { widget_settings: { product_source: 'featured', columns: 2, limit: 4 } }],
        ['category_grid', __('Grille catégories', 'woo-total-menu'), 'category',
                { widget_settings: { columns: 3, limit: 6, show_images: true, show_counts: false } }],
        ['mini_cart', __('Mini-panier', 'woo-total-menu'), 'cart',
                { widget_settings: { display_mode: 'link', drawer_position: 'right', show_subtotal: true, show_checkout_button: true, show_thumbnail: true } }],
        ['search', __('Recherche', 'woo-total-menu'), 'search',
                { widget_settings: { placeholder: __('Rechercher un produit…', 'woo-total-menu'), live_suggestions: true, min_chars: 3, show_category_filter: false } }],
        ['recent_posts', __('Articles récents', 'woo-total-menu'), 'admin-post',
                { widget_settings: { columns: 2, limit: 4, show_image: true, show_date: true, show_excerpt: false, orderby: 'date', category: '' } }],
        ['social_icons', __('Icônes sociales', 'woo-total-menu'), 'share',
                { widget_settings: { size: 24, items: [
                        { network: 'facebook', url: '' },
                        { network: 'twitter', url: '' },
                ] } }],
        ['newsletter', __('Newsletter', 'woo-total-menu'), 'email',
                { widget_settings: { placeholder: '', button_label: __('S\'abonner', 'woo-total-menu'), provider: 'internal', list_id: '', success_message: '', layout: 'inline' } }],
        ['filters', __('Filtres WC', 'woo-total-menu'), 'filter',
                { widget_settings: { show_categories: true, show_price: false, show_attributes: false, attributes: [], columns: 1 } }],
        ['title', __('Titre de section', 'woo-total-menu'), 'heading',
                { widget_settings: { text: __('Nouveau titre', 'woo-total-menu'), level: 4 } }],
];

export default function AddItemButton({ parentId = null, onAdded = null, label = null, compact = false }) {
        const [isOpen, setIsOpen] = useState(false);
        const [widgetPanelFor, setWidgetPanelFor] = useState(null); // When non-null, show widget subtype picker.
        const ref = useRef(null);
        const { addItem } = useDispatch(WTM_STORE_NAME);

        // Close on outside click.
        useEffect(() => {
                if (!isOpen) return;
                const handler = (e) => {
                        if (ref.current && !ref.current.contains(e.target)) {
                                setIsOpen(false);
                                setWidgetPanelFor(null);
                        }
                };
                document.addEventListener('mousedown', handler);
                return () => document.removeEventListener('mousedown', handler);
        }, [isOpen]);

        const handleSelect = (type) => {
                const typeDef = ITEM_TYPES.find((t) => t.value === type);
                if (!typeDef) return;
                // For widget, default to first subtype (html).
                if (type === 'widget') {
                        setWidgetPanelFor('widget');
                        return;
                }
                const newItem = {
                        type: typeDef.value,
                        ...typeDef.defaults,
                };
                addItem(newItem, parentId);
                setIsOpen(false);
                if (onAdded) onAdded();
        };

        const handleWidgetSubtype = (subtype) => {
                const subDef = WIDGET_SUBTYPES.find((s) => s[0] === subtype);
                if (!subDef) return;
                // Compact format: [value, label, icon, defaults]
                const newItem = {
                        type: 'widget',
                        widget_type: subtype,
                        ...subDef[3],
                };
                if (!newItem.widget_settings) {
                        newItem.widget_settings = {};
                }
                addItem(newItem, parentId);
                setIsOpen(false);
                setWidgetPanelFor(null);
                if (onAdded) onAdded();
        };

        return (
                <div className="wtm-add-item" ref={ref}>
                        <button
                                type="button"
                                className={`wtm-add-item__button ${compact ? 'is-compact' : ''}`}
                                onClick={() => setIsOpen(!isOpen)}
                                title={__('Ajouter un élément', 'woo-total-menu')}
                        >
                                <span className="dashicons dashicons-plus-alt2"></span>
                                {!compact && (label || __('Ajouter un élément', 'woo-total-menu'))}
                        </button>

                        {isOpen && !widgetPanelFor && (
                                <div className="wtm-add-item__dropdown">
                                        <div className="wtm-add-item__dropdown-header">
                                                {__('Choisir un type d\'élément', 'woo-total-menu')}
                                        </div>
                                        <ul className="wtm-add-item__list">
                                                {ITEM_TYPES.map((type) => (
                                                        <li key={type.value}>
                                                                <button
                                                                        type="button"
                                                                        className="wtm-add-item__option"
                                                                        onClick={() => handleSelect(type.value)}
                                                                >
                                                                        <span className={`dashicons dashicons-${type.icon}`}></span>
                                                                        <span className="wtm-add-item__option-text">
                                                                                <strong>{type.label}</strong>
                                                                                <small>{type.description}</small>
                                                                        </span>
                                                                        {type.value === 'widget' && (
                                                                                <span className="dashicons dashicons-arrow-right-alt2 wtm-add-item__arrow"></span>
                                                                        )}
                                                                </button>
                                                        </li>
                                                ))}
                                        </ul>
                                </div>
                        )}

                        {isOpen && widgetPanelFor === 'widget' && (
                                <div className="wtm-add-item__dropdown wtm-add-item__dropdown--widgets">
                                        <div className="wtm-add-item__dropdown-header">
                                                <button
                                                        type="button"
                                                        className="wtm-add-item__back"
                                                        onClick={() => setWidgetPanelFor(null)}
                                                >
                                                        <span className="dashicons dashicons-arrow-left-alt2"></span>
                                                        {__('Retour', 'woo-total-menu')}
                                                </button>
                                                <span>{__('Choisir un widget', 'woo-total-menu')}</span>
                                        </div>
                                        <ul className="wtm-add-item__list">
                                                {WIDGET_SUBTYPES.map((sub) => (
                                                        <li key={sub[0]}>
                                                                <button
                                                                        type="button"
                                                                        className="wtm-add-item__option"
                                                                        onClick={() => handleWidgetSubtype(sub[0])}
                                                                >
                                                                        <span className={`dashicons dashicons-${sub[2]}`}></span>
                                                                        <span className="wtm-add-item__option-text">
                                                                                <strong>{sub[1]}</strong>
                                                                        </span>
                                                                </button>
                                                        </li>
                                                ))}
                                        </ul>
                                </div>
                        )}
                </div>
        );
}
