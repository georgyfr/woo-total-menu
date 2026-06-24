/**
 * TreePanel — left column showing the menu items hierarchy.
 *
 * Supports in v1.1.1:
 * - Add item (root or child) via AddItemButton
 * - Inline rename (double-click on label)
 * - Remove item (hover → trash icon)
 * - Add child for mega_container/column (hover → "+" icon)
 *
 * @package WooTotalMenu
 * @since 1.1.0
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useSelect, useDispatch } from '@wordpress/data';

import { WTM_STORE_NAME } from '../stores/menu';
import { UI_STORE_NAME } from '../stores/ui';
import AddItemButton from './AddItemButton';

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
				<AddItemButton compact parentId={null} />
			</div>

			<div className="wtm-tree__body">
				{items.length === 0 ? (
					<div className="wtm-tree__empty">
						<span className="dashicons dashicons-menu"></span>
						<p>{__('Aucun élément pour l\'instant.', 'woo-total-menu')}</p>
						<AddItemButton parentId={null} label={__('Ajouter le premier élément', 'woo-total-menu')} />
					</div>
				) : (
					<ul className="wtm-tree__list">
						{items.map((item) => (
							<TreeItem
								key={item.id}
								item={item}
								selectedItemId={selectedItemId}
								onSelect={selectItem}
								depth={0}
							/>
						))}
					</ul>
				)}
			</div>

			{items.length > 0 && (
				<div className="wtm-tree__footer">
					<AddItemButton parentId={null} label={__('Ajouter un élément', 'woo-total-menu')} />
				</div>
			)}
		</div>
	);
}

function TreeItem({ item, selectedItemId, onSelect, depth = 0 }) {
	const [isEditing, setIsEditing] = useState(false);
	const [editValue, setEditValue] = useState(item.label || '');
	const [showAddChild, setShowAddChild] = useState(false);

	const { removeItem, updateItem } = useDispatch(WTM_STORE_NAME);
	const { selectItem } = useDispatch(UI_STORE_NAME);

	const isSelected = selectedItemId === item.id;
	const hasChildren = item.children && item.children.length > 0;
	const canHaveChildren = item.type === 'mega_container' || item.type === 'column';

	const handleSelect = () => {
		if (!isEditing) {
			onSelect(item.id);
		}
	};

	const handleDoubleClick = () => {
		if (item.type === 'separator') return;
		setIsEditing(true);
		setEditValue(item.label || item.widget_type || '');
	};

	const handleEditSubmit = () => {
		setIsEditing(false);
		if (item.label !== editValue && editValue.trim()) {
			updateItem(item.id, { label: editValue.trim() });
		}
	};

	const handleEditKeyDown = (e) => {
		if (e.key === 'Enter') {
			e.preventDefault();
			handleEditSubmit();
		} else if (e.key === 'Escape') {
			setIsEditing(false);
			setEditValue(item.label || '');
		}
	};

	const handleRemove = (e) => {
		e.stopPropagation();
		if (confirm(__('Supprimer cet élément et tous ses enfants ?', 'woo-total-menu'))) {
			removeItem(item.id);
		}
	};

	const displayLabel = item.label || item.widget_type || item.type;

	return (
		<li
			className={`wtm-tree__item ${isSelected ? 'is-selected' : ''}`}
			style={{ paddingLeft: `${12 + depth * 16}px` }}
		>
			<div className="wtm-tree__item-row">
				<button
					type="button"
					className="wtm-tree__item-button"
					onClick={handleSelect}
					onDoubleClick={handleDoubleClick}
				>
					<span className={`dashicons dashicons-${getItemIcon(item.type)}`}></span>
					{isEditing ? (
						<input
							type="text"
							className="wtm-tree__item-edit"
							value={editValue}
							onChange={(e) => setEditValue(e.target.value)}
							onBlur={handleEditSubmit}
							onKeyDown={handleEditKeyDown}
							onClick={(e) => e.stopPropagation()}
							autoFocus
						/>
					) : (
						<span className="wtm-tree__item-label">{displayLabel}</span>
					)}
					{item.badge && (
						<span className="wtm-tree__item-badge">{item.badge.text}</span>
					)}
				</button>

				<div className="wtm-tree__item-actions">
					{canHaveChildren && (
						<button
							type="button"
							className="wtm-tree__item-action"
							title={__('Ajouter un enfant', 'woo-total-menu')}
							onClick={(e) => {
								e.stopPropagation();
								setShowAddChild(!showAddChild);
							}}
						>
							<span className="dashicons dashicons-plus"></span>
						</button>
					)}
					<button
						type="button"
						className="wtm-tree__item-action wtm-tree__item-action--danger"
						title={__('Supprimer', 'woo-total-menu')}
						onClick={handleRemove}
					>
						<span className="dashicons dashicons-trash"></span>
					</button>
				</div>
			</div>

			{showAddChild && canHaveChildren && (
				<div className="wtm-tree__add-child">
					<AddItemButton
						parentId={item.id}
						label={__('Ajouter un enfant', 'woo-total-menu')}
						onAdded={() => setShowAddChild(false)}
					/>
					<button
						type="button"
						className="wtm-tree__add-child-close"
						onClick={() => setShowAddChild(false)}
					>
						{__('Annuler', 'woo-total-menu')}
					</button>
				</div>
			)}

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
