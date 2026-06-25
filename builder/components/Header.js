/**
 * Header component — top toolbar with title, device switcher, undo/redo, save.
 *
 * v1.1.2 additions:
 * - Undo/Redo buttons (spec §9.9) — wired to the new past/future stacks in wtm/menu
 * - Global keyboard shortcuts Ctrl+Z (undo) / Ctrl+Shift+Z or Ctrl+Y (redo)
 * - Buttons disabled when history stack is empty
 *
 * @package WooTotalMenu
 * @since 1.1.0
 */

import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';

import { WTM_STORE_NAME } from '../stores/menu';
import { UI_STORE_NAME } from '../stores/ui';

const DEVICE_OPTIONS = [
	{ value: 'desktop', label: __('Bureau', 'woo-total-menu'), icon: 'desktop' },
	{ value: 'tablet', label: __('Tablette', 'woo-total-menu'), icon: 'tablet' },
	{ value: 'mobile', label: __('Mobile', 'woo-total-menu'), icon: 'smartphone' },
];

export default function Header({ title, menuType, isDirty, isLoading }) {
	const { saveMenu, undo, redo } = useDispatch(WTM_STORE_NAME);
	const device = useSelect((select) => select(UI_STORE_NAME).getDevice(), []);
	const canUndo = useSelect((select) => select(WTM_STORE_NAME).canUndo(), []);
	const canRedo = useSelect((select) => select(WTM_STORE_NAME).canRedo(), []);
	const { setDevice, setAnnouncement } = useDispatch(UI_STORE_NAME);

	// === Global keyboard shortcuts (spec §9.9) ===
	useEffect(() => {
		const handleKeyDown = (e) => {
			// Skip if user is typing in an input/textarea/contenteditable
			const target = e.target;
			const isEditingField =
				target?.tagName === 'INPUT' ||
				target?.tagName === 'TEXTAREA' ||
				target?.isContentEditable;
			if (isEditingField) return;

			// Ctrl+Z or Cmd+Z = Undo
			// Ctrl+Shift+Z or Cmd+Shift+Z or Ctrl+Y = Redo
			const isUndo = (e.ctrlKey || e.metaKey) && !e.shiftKey && e.key.toLowerCase() === 'z';
			const isRedo = ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key.toLowerCase() === 'z')
				|| ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'y');

			if (isUndo && canUndo) {
				e.preventDefault();
				undo();
				setAnnouncement(__('Action annulée.', 'woo-total-menu'));
			} else if (isRedo && canRedo) {
				e.preventDefault();
				redo();
				setAnnouncement(__('Action rétablie.', 'woo-total-menu'));
			}
		};

		window.addEventListener('keydown', handleKeyDown);
		return () => window.removeEventListener('keydown', handleKeyDown);
	}, [canUndo, canRedo, undo, redo, setAnnouncement]);

	const handleUndo = () => {
		undo();
		setAnnouncement(__('Action annulée.', 'woo-total-menu'));
	};

	const handleRedo = () => {
		redo();
		setAnnouncement(__('Action rétablie.', 'woo-total-menu'));
	};

	return (
		<header className="wtm-builder__header">
			<div className="wtm-builder__header-left">
				<span className="dashicons dashicons-menu wtm-builder__logo"></span>
				<h1 className="wtm-builder__title">
					{title}
					{isDirty && <span className="wtm-builder__dirty">●</span>}
				</h1>
				<span className="wtm-builder__menutype">{menuType}</span>
			</div>

			<div className="wtm-builder__header-center">
				<div className="wtm-builder__history">
					<button
						type="button"
						className="wtm-builder__history-btn"
						onClick={handleUndo}
						disabled={!canUndo}
						title={__('Annuler (Ctrl+Z)', 'woo-total-menu')}
						aria-label={__('Annuler', 'woo-total-menu')}
					>
						<span className="dashicons dashicons-undo"></span>
					</button>
					<button
						type="button"
						className="wtm-builder__history-btn"
						onClick={handleRedo}
						disabled={!canRedo}
						title={__('Rétablir (Ctrl+Shift+Z)', 'woo-total-menu')}
						aria-label={__('Rétablir', 'woo-total-menu')}
					>
						<span className="dashicons dashicons-redo"></span>
					</button>
				</div>

				<div className="wtm-builder__devices">
					{DEVICE_OPTIONS.map((opt) => (
						<button
							key={opt.value}
							type="button"
							className={`wtm-builder__device ${device === opt.value ? 'is-active' : ''}`}
							onClick={() => setDevice(opt.value)}
							title={opt.label}
						>
							<span className={`dashicons dashicons-${opt.icon}`}></span>
						</button>
					))}
				</div>
			</div>

			<div className="wtm-builder__header-right">
				<button
					type="button"
					className="wtm-builder__save"
					onClick={() => saveMenu()}
					disabled={!isDirty || isLoading}
				>
					<span className="dashicons dashicons-saved"></span>
					{__('Enregistrer', 'woo-total-menu')}
				</button>
			</div>
		</header>
	);
}
