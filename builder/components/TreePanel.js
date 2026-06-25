/**
 * TreePanel — left column showing the menu items hierarchy with drag & drop.
 *
 * v1.1.2 changes:
 * - Drag & drop arborescent via @dnd-kit
 * - Drop indicators (before/after = blue line, inside = dashed border)
 * - Keyboard reordering (Ctrl+Arrow keys)
 * - aria-live announcements after each move
 * - Auto-expand collapsed containers on hover
 * - Undo/Redo handled at the store level (each moveItem pushes history)
 *
 * @package WooTotalMenu
 * @since 1.1.0
 */

import { useState, useRef, useEffect } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { useSelect, useDispatch } from '@wordpress/data';
import {
	DndContext,
	DragOverlay,
	PointerSensor,
	KeyboardSensor,
	useSensor,
	useSensors,
	closestCenter,
} from '@dnd-kit/core';
import { sortableKeyboardCoordinates } from '@dnd-kit/sortable';

import { WTM_STORE_NAME } from '../stores/menu';
import { UI_STORE_NAME } from '../stores/ui';
import AddItemButton from './AddItemButton';
import SortableTreeItem from './SortableTreeItem';
import {
	DROP_POSITION,
	computeDropPosition,
	isValidDrop,
	isAncestorOf,
	computeMoveTarget,
} from './dnd-helpers';

export default function TreePanel() {
	const items = useSelect((select) => select(WTM_STORE_NAME).getItems(), []);
	const menu = useSelect((select) => select(WTM_STORE_NAME).getMenu(), []);
	const selectedItemId = useSelect((select) => select(UI_STORE_NAME).getSelectedItemId(), []);
	const { selectItem, setAnnouncement } = useDispatch(UI_STORE_NAME);
	const { moveItem } = useDispatch(WTM_STORE_NAME);

	const [activeItem, setActiveItem] = useState(null);
	const announcementRef = useRef(null);

	// Sensors — require 5px movement to start drag (avoid accidental clicks)
	const sensors = useSensors(
		useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
		useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates })
	);

	const handleDragStart = (event) => {
		const { active } = event;
		const item = active.data.current?.item;
		setActiveItem(item);
	};

	const handleDragOver = (event) => {
		// Visual feedback handled by SortableTreeItem via useSortable.
		// We could implement cross-parent reordering during drag here,
		// but for simplicity we only update on drop.
	};

	const handleDragEnd = (event) => {
		const { active, over } = event;
		setActiveItem(null);

		if (!over || active.id === over.id) return;

		const draggedItem = active.data.current?.item;
		const targetItem = over.data.current?.item;
		const targetDepth = over.data.current?.depth ?? 0;
		const targetParentId = over.data.current?.parentId ?? null;

		if (!draggedItem || !targetItem) return;

		// Determine drop position (we'll re-derive it from the last known cursor pos)
		// @dnd-kit doesn't expose cursor in onDragEnd, so we use over.rect + tolerance
		const targetRect = over.rect;
		const cursorY = active.rect.current.translated?.top ?? targetRect.top + targetRect.height / 2;
		const canHaveChildren = ['mega_container', 'column', 'accordion_parent'].includes(targetItem.type);

		const position = canHaveChildren
			? computeDropPosition(cursorY, targetRect, true)
			: (cursorY < targetRect.top + targetRect.height / 2 ? DROP_POSITION.BEFORE : DROP_POSITION.AFTER);

		// Validate drop
		const isAncestor = isAncestorOf(items, draggedItem.id, targetItem.id);
		const valid = isValidDrop({
			draggedItem,
			targetItem,
			targetDepth,
			menuType: menu?.menu_type || 'horizontal',
			position,
			isAncestor,
		});

		if (!valid) {
			setAnnouncement(__('Déplacement invalide : la cible ne peut pas contenir cet élément.', 'woo-total-menu'));
			return;
		}

		const moveTarget = computeMoveTarget({
			draggedItemId: draggedItem.id,
			targetItem,
			targetParentId,
			targetDepth,
			position,
			items,
		});

		if (!moveTarget) return;

		moveItem(draggedItem.id, moveTarget.parentId, moveTarget.index);

		// Announce the move for screen readers (spec §6.7)
		const parentLabel = moveTarget.parentId
			? (findItemLabel(items, moveTarget.parentId) || moveTarget.parentId)
			: __('racine', 'woo-total-menu');
		setAnnouncement(sprintf(
			/* translators: 1: item label, 2: parent label, 3: position */
			__('« %1$s » déplacé en position %3$d dans %2$s.', 'woo-total-menu'),
			draggedItem.label || draggedItem.type,
			parentLabel,
			moveTarget.index + 1
		));
	};

	const handleDragCancel = () => {
		setActiveItem(null);
	};

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
					<DndContext
						sensors={sensors}
						collisionDetection={closestCenter}
						onDragStart={handleDragStart}
						onDragOver={handleDragOver}
						onDragEnd={handleDragEnd}
						onDragCancel={handleDragCancel}
					>
						<ul className="wtm-tree__list">
							{items.map((item) => (
								<SortableTreeItem
									key={item.id}
									item={item}
									selectedItemId={selectedItemId}
									onSelect={selectItem}
									depth={0}
									parentId={null}
									announcementRef={announcementRef}
								/>
							))}
						</ul>

						<DragOverlay dropAnimation={{ duration: 200, easing: 'cubic-bezier(0.18, 0.67, 0.6, 1.22)' }}>
							{activeItem ? (
								<div className="wtm-tree__drag-overlay">
									<span className={`dashicons dashicons-${
										{
											link: 'admin-links',
											mega_container: 'screenoptions',
											column: 'columns',
											widget: 'admin-generic',
											title: 'heading',
											separator: 'minus',
										}[activeItem.type] || 'marker'
									}`}></span>
									{activeItem.label || activeItem.widget_type || activeItem.type}
								</div>
							) : null}
						</DragOverlay>
					</DndContext>
				)}
			</div>

			{items.length > 0 && (
				<div className="wtm-tree__footer">
					<AddItemButton parentId={null} label={__('Ajouter un élément', 'woo-total-menu')} />
				</div>
			)}

			{/* Screen reader live region (spec §6.7) */}
			<div
				ref={announcementRef}
				className="wtm-tree__sr-announcement"
				aria-live="polite"
				aria-atomic="true"
			>
				<SRAnnouncement />
			</div>
		</div>
	);
}

/**
 * Component that reads the announcement from the UI store and renders it.
 */
function SRAnnouncement() {
	const announcement = useSelect((select) => select(UI_STORE_NAME).getAnnouncement?.() || '', []);
	return <span>{announcement}</span>;
}

function findItemLabel(items, id) {
	for (const it of items) {
		if (it.id === id) return it.label || it.widget_type || it.type;
		if (it.children) {
			const found = findItemLabel(it.children, id);
			if (found) return found;
		}
	}
	return null;
}
