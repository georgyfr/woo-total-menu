/**
 * Tree DnD logic — helpers for drag and drop in the menu tree.
 *
 * Implements spec §6.3:
 * - Reorder items at same level
 * - Move items between levels
 * - Nest items inside authorized containers (mega_container, column, accordion_parent)
 *
 * Drop zones:
 * - "before" = thick 2px blue line above
 * - "after" = same line below
 * - "inside" = dashed blue border + light bg
 *
 * Nesting rules (spec §3.4.2):
 * - root → link, mega_container, title, separator (+ accordion_parent in vertical menu)
 * - mega_container → column ONLY
 * - column → link, title, widget, separator, accordion_parent
 * - accordion_parent (vertical only) → link, widget
 * - link, widget, title, separator → terminal (no children)
 *
 * @package WooTotalMenu
 * @since 1.1.2
 */

import { isNestingAllowed, findItem, getItemDepth } from '../stores/menu';

/**
 * Drop position relative to a target item.
 *
 * @type {{BEFORE: string, AFTER: string, INSIDE: string}}
 */
export const DROP_POSITION = {
	BEFORE: 'before',
	AFTER: 'after',
	INSIDE: 'inside',
};

/**
 * Vertical tolerance (px) to determine "before/after/inside" zones.
 * - Top 30% = before, bottom 30% = after, middle 40% = inside.
 *
 * @type {{TOP: number, MIDDLE_TOP: number, MIDDLE_BOTTOM: number}}
 */
export const DROP_TOLERANCE = {
	TOP: 0.3,
	MIDDLE_TOP: 0.3,
	MIDDLE_BOTTOM: 0.7,
};

/**
 * Determine the drop position based on cursor Y vs target rect.
 * If target can't have children, INSIDE is never returned.
 *
 * @param {number} cursorY  Cursor Y relative to viewport.
 * @param {DOMRect} rect    Target item bounding rect.
 * @param {boolean} canContainChildren Whether the target accepts children.
 * @return {string} DROP_POSITION value.
 */
export function computeDropPosition(cursorY, rect, canContainChildren) {
	const relative = (cursorY - rect.top) / rect.height;
	if (canContainChildren && relative > DROP_TOLERANCE.MIDDLE_TOP && relative < DROP_TOLERANCE.MIDDLE_BOTTOM) {
		return DROP_POSITION.INSIDE;
	}
	return relative < 0.5 ? DROP_POSITION.BEFORE : DROP_POSITION.AFTER;
}

/**
 * Validate whether a drop is allowed given:
 *  - the dragged item type
 *  - the target item type
 *  - the target item depth
 *  - the menu type
 *  - the drop position
 *  - whether the dragged item is itself an ancestor of the target (forbidden)
 *
 * @param {Object}  params              { draggedItem, targetItem, targetDepth, menuType, position, isAncestor }
 * @return {boolean} True if the drop is allowed.
 */
export function isValidDrop({
	draggedItem,
	targetItem,
	targetDepth,
	menuType,
	position,
	isAncestor,
}) {
	// Can't drop an item onto itself.
	if (draggedItem.id === targetItem.id) return false;

	// Can't drop an item into one of its own descendants.
	if (isAncestor) return false;

	// Max depth = 3 (root → mega_container → column → widget/link)
	const MAX_DEPTH = 3;

	if (position === DROP_POSITION.INSIDE) {
		// Dropping inside the target: the target becomes the parent.
		// Check: parent accepts this child type, and depth doesn't exceed MAX.
		const newDepth = targetDepth + 1;
		if (newDepth >= MAX_DEPTH && draggedItem.children?.length > 0) {
			// An item with children can't be moved to a level where its children would exceed MAX_DEPTH.
			// For now we accept only if the dragged item has no children.
			return false;
		}
		return isNestingAllowed(draggedItem.type, targetItem.type, menuType);
	}

	// BEFORE / AFTER: parent is the target's parent (or root if target is root).
	// The dragged item takes the target's depth.
	if (targetDepth >= MAX_DEPTH) return false;
	return true;
}

/**
 * Build the data payload to dispatch to the moveItem action.
 *
 * @param {Object} params { draggedItemId, targetItem, targetParentId, targetIndex, position, targetDepth, items }
 * @return {{parentId: string|null, index: number}|null} Move data or null if invalid.
 */
export function computeMoveTarget({
	draggedItemId,
	targetItem,
	targetParentId,
	targetDepth,
	position,
	items,
}) {
	if (position === DROP_POSITION.INSIDE) {
		// Insert as first child of target.
		return { parentId: targetItem.id, index: 0 };
	}

	// BEFORE: same parent as target, index = target index
	// AFTER: same parent as target, index = target index + 1
	// But we need to find the target's index in its parent's children.
	const targetParentItems = targetParentId === null
		? items
		: (findItem(items, targetParentId)?.children || []);

	const targetIndex = targetParentItems.findIndex((it) => it.id === targetItem.id);
	if (targetIndex === -1) return null;

	const finalIndex = position === DROP_POSITION.BEFORE ? targetIndex : targetIndex + 1;

	// Adjust: if the dragged item is currently BEFORE the target in the same parent,
	// removing it shifts the target index by -1, so we need to compensate.
	// We'll let the reducer handle this — but we adjust here for UX accuracy.
	const draggedLoc = findItemLocationInTree(items, draggedItemId);
	if (
		draggedLoc &&
		draggedLoc.parentId === targetParentId &&
		draggedLoc.index < targetIndex
	) {
		return { parentId: targetParentId, index: finalIndex - 1 };
	}

	return { parentId: targetParentId, index: finalIndex };
}

/**
 * Find the parent ID and index of an item in the tree.
 *
 * @param {Array}  items Items tree.
 * @param {string} id    Item ID.
 * @return {{parentId: string|null, index: number}|null}
 */
function findItemLocationInTree(items, id) {
	function walk(arr, parentId) {
		for (let i = 0; i < arr.length; i++) {
			if (arr[i].id === id) return { parentId, index: i };
			if (arr[i].children) {
				const found = walk(arr[i].children, arr[i].id);
				if (found) return found;
			}
		}
		return null;
	}
	return walk(items, null);
}

/**
 * Check if `ancestorId` is an ancestor of `descendantId`.
 *
 * @param {Array}  items       Items tree.
 * @param {string} ancestorId  Potential ancestor ID.
 * @param {string} descendantId Descendant ID.
 * @return {boolean} True if ancestor.
 */
export function isAncestorOf(items, ancestorId, descendantId) {
	const ancestor = findItem(items, ancestorId);
	if (!ancestor || !ancestor.children) return false;
	function walk(arr) {
		for (const it of arr) {
			if (it.id === descendantId) return true;
			if (it.children && walk(it.children)) return true;
		}
		return false;
	}
	return walk(ancestor.children);
}
