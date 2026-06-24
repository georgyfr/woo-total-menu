/**
 * TreePanel — left column showing the menu items hierarchy.
 *
 * @package WooTotalMenu
 * @since 1.1.0
 */

import { __ } from '@wordpress/i18n';
import { useSelect, useDispatch } from '@wordpress/data';

import { WTM_STORE_NAME } from '../stores/menu';
import { UI_STORE_NAME } from '../stores/ui';

export default function TreePanel() {
	const items = useSelect((select) => select(WTM_STORE_NAME).getItems(), []);
	const selectedItemId = useSelect((select) => select(UI_STORE_NAME).getSelectedItemId(), []);
	const { selectItem } = useDispatch(UI_STORE_NAME);

	return (
		<div className="wtm-tree">
			<div className="wtm-tree__header">
				<h2>
					<span className="dashicons dashicons-list-view"></span>
					{__('Arborescence', 'woo-total-menu')}
				</h2>
				<button type="button" className="wtm-tree__add" title={__('Ajouter un élément', 'woo-total-menu')} disabled>
					<span className="dashicons dashicons-plus-alt2"></span>
				</button>
			</div>

			<div className="wtm-tree__body">
				{items.length === 0 ? (
					<div className="wtm-tree__empty">
						<span className="dashicons dashicons-menu"></span>
						<p>{__('Aucun élément pour l\'instant.', 'woo-total-menu')}</p>
						<p className="wtm-tree__hint">
							{__('Le CRUD complet arrive en v1.1.2.', 'woo-total-menu')}
						</p>
					</div>
				) : (
					<ul className="wtm-tree__list">
						{items.map((item) => (
							<TreeItem
								key={item.id}
								item={item}
								selectedItemId={selectedItemId}
								onSelect={selectItem}
							/>
						))}
					</ul>
				)}
			</div>
		</div>
	);
}

function TreeItem({ item, selectedItemId, onSelect, depth = 0 }) {
	const isSelected = selectedItemId === item.id;
	const hasChildren = item.children && item.children.length > 0;

	return (
		<li className={`wtm-tree__item ${isSelected ? 'is-selected' : ''}`} style={{ paddingLeft: `${12 + depth * 16}px` }}>
			<button
				type="button"
				className="wtm-tree__item-button"
				onClick={() => onSelect(item.id)}
			>
				<span className={`dashicons dashicons-${getItemIcon(item.type)}`}></span>
				<span className="wtm-tree__item-label">
					{item.label || item.widget_type || item.type}
				</span>
				{item.badge && (
					<span className="wtm-tree__item-badge">{item.badge.text}</span>
				)}
			</button>

			{hasChildren && (
				<ul className="wtm-tree__sublist">
					{item.children.map((child) => (
						<TreeItem
							key={child.id}
							item={child}
							selectedItemId={selectedItemId}
							onSelect={onSelect}
							depth={depth + 1}
						/>
					))}
				</ul>
			)}
		</li>
	);
}

function getItemIcon(type) {
	const icons = {
		link: 'admin-links',
		mega_container: 'screenoptions',
		column: 'columns',
		widget: 'admin-generic',
		title: 'heading',
		separator: 'minus',
	};
	return icons[type] || 'marker';
}
