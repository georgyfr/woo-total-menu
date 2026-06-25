/**
 * SortableTreeItem — a single tree item with drag & drop support via @dnd-kit.
 *
 * Spec §6.3:
 * - Drag handle (⠿ six-dot icon) on the left
 * - Drop indicators: before/after = blue line, inside = dashed border + light bg
 * - On drag start, original becomes 50% transparent; ghost follows cursor
 * - Collapsed mega_containers auto-expand on hover (delay 500 ms)
 * - Invalid drop → no indicator + ghost returns to origin
 *
 * Spec §6.3.5 — Keyboard accessibility:
 * - Tab to focus item
 * - Ctrl+↑/↓ to reorder among siblings
 * - Ctrl+→ to indent (becomes child of preceding sibling)
 * - Ctrl+← to outdent
 * - aria-live announcements of new position
 *
 * @package WooTotalMenu
 * @since 1.1.2
 */

import { useState, useEffect, useRef } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { useSelect, useDispatch } from '@wordpress/data';
import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';

import { WTM_STORE_NAME } from '../stores/menu';
import { UI_STORE_NAME } from '../stores/ui';
import AddItemButton from './AddItemButton';
import {
	DROP_POSITION,
	computeDropPosition,
	isValidDrop,
	isAncestorOf,
} from './dnd-helpers';

const ITEM_ICON = {
	link: 'admin-links',
	mega_container: 'screenoptions',
	column: 'columns',
	widget: 'admin-generic',
	title: 'heading',
	separator: 'minus',
};

/**
 * Can the given item type contain children? Used to allow INSIDE drops.
 *
 * @param {string} itemType Item type.
 * @return {boolean} True if children allowed.
 */
function canContainChildren(itemType) {
	return ['mega_container', 'column', 'accordion_parent'].includes(itemType);
}

export default function SortableTreeItem({
	item,
	depth,
	parentId,
	selectedItemId,
	onSelect,
	announcementRef,
}) {
	const [isEditing, setIsEditing] = useState(false);
	const [editValue, setEditValue] = useState(item.label || '');
	const [showAddChild, setShowAddChild] = useState(false);
	const [dropPosition, setDropPosition] = useState(null); // null | 'before' | 'after' | 'inside'
	const [isExpanded, setIsExpanded] = useState(true);
	const rowRef = useRef(null);
	const hoverTimerRef = useRef(null);

	const { removeItem, updateItem, moveItem } = useDispatch(WTM_STORE_NAME);
	const { selectItem, setAnnouncement } = useDispatch(UI_STORE_NAME);

	const items = useSelect((select) => select(WTM_STORE_NAME).getItems(), []);
	const menuType = useSelect(
		(select) => select(WTM_STORE_NAME).getMenu()?.menu_type || 'horizontal',
		[]
	);

	const isSelected = selectedItemId === item.id;
	const hasChildren = item.children && item.children.length > 0;
	const canHaveChildren = canContainChildren(item.type);

	// === @dnd-kit setup ===
	const sortable = useSortable({
		id: item.id,
		data: {
			type: 'tree-item',
			item,
			depth,
			parentId,
		},
	});

	const {
		attributes,
		listeners,
		setNodeRef,
		transform,
		transition,
		isDragging,
		isOver,
		active,
		over,
	} = sortable;

	const style = {
		transform: CSS.Transform.toString(transform),
		transition,
		opacity: isDragging ? 0.5 : 1,
	};

	// === Compute drop indicator when this item is hovered during drag ===
	useEffect(() => {
		if (!isOver || !active || active.id === item.id) {
			setDropPosition(null);
			return;
		}

		// Check ancestor relationship (can't drop into own descendant)
		const draggedId = active.id;
		const draggedItem = active.data.current?.item;
		const isAncestor = isAncestorOf(items, draggedId, item.id);

		const targetDepth = depth;
		const valid = isValidDrop({
			draggedItem: draggedItem,
			targetItem: item,
			targetDepth,
			menuType,
			position: canHaveChildren ? DROP_POSITION.INSIDE : DROP_POSITION.BEFORE,
			isAncestor,
		});

		if (!valid) {
			setDropPosition(null);
			return;
		}

		// Determine precise position based on cursor
		if (rowRef.current) {
			const rect = rowRef.current.getBoundingClientRect();
			const cursorY = sortable.activatorEvent?.clientY;
			// If we can't get cursor, fall back to relative position from over rect
			const y = (cursorY !== undefined) ? cursorY : rect.top + rect.height / 2;
			const pos = canHaveChildren
				? computeDropPosition(y, rect, true)
				: (y < rect.top + rect.height / 2 ? DROP_POSITION.BEFORE : DROP_POSITION.AFTER);
			setDropPosition(pos);

			// Auto-expand collapsed containers on hover (500 ms delay — spec §6.3.2)
			if (pos === DROP_POSITION.INSIDE && canHaveChildren && !isExpanded) {
				if (hoverTimerRef.current) clearTimeout(hoverTimerRef.current);
				hoverTimerRef.current = setTimeout(() => setIsExpanded(true), 500);
			}
		}
	}, [isOver, active, item.id, canHaveChildren, isExpanded, items, depth, menuType, sortable.activatorEvent]);

	useEffect(() => {
		return () => {
			if (hoverTimerRef.current) clearTimeout(hoverTimerRef.current);
		};
	}, []);

	// === Handle drag end (dispatch moveItem) ===
	useEffect(() => {
		if (!over || !active || isDragging) return;
		// This effect fires on every render where over changes during drag;
		// the actual dispatch happens in the parent DndContext onDragEnd.
	}, [over, active, isDragging]);

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

	// === Keyboard reordering (spec §6.3.5) ===
	const handleKeyDown = (e) => {
		if (isEditing) return;

		// Ctrl+ArrowUp/Down = reorder among siblings
		// Ctrl+ArrowRight = indent (becomes child of preceding sibling)
		// Ctrl+ArrowLeft = outdent (move to parent's siblings)
		if (!e.ctrlKey && !e.metaKey) return;

		const arrow = e.key;
		if (!['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(arrow)) return;

		e.preventDefault();
		e.stopPropagation();

		// Find this item's location in the tree
		const loc = findLocation(items, item.id);
		if (!loc) return;

		if (arrow === 'ArrowUp' || arrow === 'ArrowDown') {
			const siblings = loc.parentId === null
				? items
				: (findItemInTree(items, loc.parentId)?.children || []);
			const newIdx = arrow === 'ArrowUp' ? loc.index - 1 : loc.index + 1;
			if (newIdx < 0 || newIdx >= siblings.length) return;
			moveItem(item.id, loc.parentId, newIdx);
			announceMove(item, loc.parentId, newIdx);
		} else if (arrow === 'ArrowRight') {
			// Indent: become child of preceding sibling
			const siblings = loc.parentId === null
				? items
				: (findItemInTree(items, loc.parentId)?.children || []);
			if (loc.index === 0) return;
			const prevSibling = siblings[loc.index - 1];
			if (!canContainChildren(prevSibling.type)) return;
			if (!isNestingAllowedForTypes(item.type, prevSibling.type, menuType)) return;
			moveItem(item.id, prevSibling.id, (prevSibling.children || []).length);
			announceMove(item, prevSibling.id, (prevSibling.children || []).length);
		} else if (arrow === 'ArrowLeft') {
			// Outdent: move to parent's siblings list, right after parent
			if (loc.parentId === null) return;
			const parent = findItemInTree(items, loc.parentId);
			if (!parent) return;
			const grandparentLoc = findLocation(items, loc.parentId);
			if (!grandparentLoc) return;
			moveItem(item.id, grandparentLoc.parentId, grandparentLoc.index + 1);
			announceMove(item, grandparentLoc.parentId, grandparentLoc.index + 1);
		}
	};

	const announceMove = (movedItem, newParentId, newIndex) => {
		const parentLabel = newParentId
			? (findItemInTree(items, newParentId)?.label || newParentId)
			: __('racine', 'woo-total-menu');
		const msg = sprintf(
			/* translators: 1: item label, 2: parent label, 3: position */
			__('« %1$s » déplacé en position %3$d dans %2$s.', 'woo-total-menu'),
			movedItem.label || movedItem.type,
			parentLabel,
			newIndex + 1
		);
		setAnnouncement(msg);
	};

	const displayLabel = item.label || item.widget_type || item.type;

	// Build drop indicator classes
	const dropClasses = ['wtm-tree__item'];
	if (isSelected) dropClasses.push('is-selected');
	if (isDragging) dropClasses.push('is-dragging');
	if (dropPosition === DROP_POSITION.BEFORE) dropClasses.push('drop-before');
	if (dropPosition === DROP_POSITION.AFTER) dropClasses.push('drop-after');
	if (dropPosition === DROP_POSITION.INSIDE) dropClasses.push('drop-inside');

	return (
		<li
			ref={setNodeRef}
			className={dropClasses.join(' ')}
			style={{
				...style,
				paddingLeft: `${12 + depth * 16}px`,
			}}
		>
			<div
				ref={rowRef}
				className="wtm-tree__item-row"
			>
				{/* Drag handle */}
				<button
					type="button"
					className="wtm-tree__drag-handle"
					aria-label={__('Déplacer cet élément', 'woo-total-menu')}
					aria-grabbed={isDragging ? 'true' : 'false'}
					{...attributes}
					{...listeners}
				>
					<span className="dashicons dashicons-screenoptions"></span>
				</button>

				{/* Expand/collapse toggle — separate button to avoid nested <button> (invalid HTML) */}
				{canHaveChildren && hasChildren && (
					<button
						type="button"
						className="wtm-tree__toggle"
						onClick={(e) => {
							e.stopPropagation();
							setIsExpanded(!isExpanded);
						}}
						aria-label={isExpanded ? __('Réduire', 'woo-total-menu') : __('Déplier', 'woo-total-menu')}
						aria-expanded={isExpanded}
					>
						<span className={`dashicons dashicons-${isExpanded ? 'arrow-down' : 'arrow-right'}`}></span>
					</button>
				)}

				{/* Main item button */}
				<button
					type="button"
					className="wtm-tree__item-button"
					onClick={handleSelect}
					onDoubleClick={handleDoubleClick}
					onKeyDown={handleKeyDown}
					aria-selected={isSelected}
				>
					<span className={`dashicons dashicons-${ITEM_ICON[item.type] || 'marker'}`}></span>
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

			{hasChildren && isExpanded && (
				<ul className="wtm-tree__sublist">
					{item.children.map((child) => (
						<SortableTreeItem
							key={child.id}
							item={child}
							depth={depth + 1}
							parentId={item.id}
							selectedItemId={selectedItemId}
							onSelect={onSelect}
							announcementRef={announcementRef}
						/>
					))}
				</ul>
			)}
		</li>
	);
}

// === Local tree helpers (cheap; we could import from store but avoid circular deps) ===

function findItemInTree(items, id) {
	for (const it of items) {
		if (it.id === id) return it;
		if (it.children) {
			const found = findItemInTree(it.children, id);
			if (found) return found;
		}
	}
	return null;
}

function findLocation(items, id, parentId = null) {
	for (let i = 0; i < items.length; i++) {
		if (items[i].id === id) return { parentId, index: i };
		if (items[i].children) {
			const found = findLocation(items[i].children, id, items[i].id);
			if (found) return found;
		}
	}
	return null;
}

function isNestingAllowedForTypes(childType, parentType, menuType) {
	if (parentType === null) {
		if (menuType === 'vertical') {
			return ['link', 'mega_container', 'title', 'separator', 'accordion_parent'].includes(childType);
		}
		return ['link', 'mega_container', 'title', 'separator'].includes(childType);
	}
	if (parentType === 'mega_container') return childType === 'column';
	if (parentType === 'column') {
		return ['link', 'title', 'widget', 'separator', 'accordion_parent'].includes(childType);
	}
	if (parentType === 'accordion_parent') return ['link', 'widget'].includes(childType);
	return false;
}
