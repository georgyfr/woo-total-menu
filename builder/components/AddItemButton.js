/**
 * AddItemButton — dropdown button to add a new item (6 types).
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
		description: __('Widget dynamique WooCommerce', 'woo-total-menu'),
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

export default function AddItemButton({ parentId = null, onAdded = null, label = null, compact = false }) {
	const [isOpen, setIsOpen] = useState(false);
	const ref = useRef(null);
	const { addItem } = useDispatch(WTM_STORE_NAME);

	// Close on outside click.
	useEffect(() => {
		if (!isOpen) return;
		const handler = (e) => {
			if (ref.current && !ref.current.contains(e.target)) {
				setIsOpen(false);
			}
		};
		document.addEventListener('mousedown', handler);
		return () => document.removeEventListener('mousedown', handler);
	}, [isOpen]);

	const handleSelect = (type) => {
		const typeDef = ITEM_TYPES.find((t) => t.value === type);
		if (!typeDef) return;
		const newItem = {
			type: typeDef.value,
			...typeDef.defaults,
		};
		addItem(newItem, parentId);
		setIsOpen(false);
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

			{isOpen && (
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
								</button>
							</li>
						))}
					</ul>
				</div>
			)}
		</div>
	);
}
