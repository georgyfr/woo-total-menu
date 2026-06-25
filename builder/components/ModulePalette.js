/**
 * ModulePalette — list of available modules draggable onto the canvas.
 *
 * Spec reference: §3.6.2 (Header modules), §3.7.1 (Footer modules), §4.6.5.
 *
 * @package WooTotalMenu
 * @since 1.4.0
 */

import { __ } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';
import { LAYOUT_STORE_NAME } from '../stores/layout';
import { UI_STORE_NAME } from '../stores/ui';

const MODULES = [
	{ type: 'logo', label: __('Logo', 'woo-total-menu'), icon: 'format-image', desc: __('Image + lien accueil', 'woo-total-menu') },
	{ type: 'menu', label: __('Menu', 'woo-total-menu'), icon: 'menu', desc: __('Menu Woo Total Menu', 'woo-total-menu') },
	{ type: 'search', label: __('Recherche', 'woo-total-menu'), icon: 'search', desc: __('Barre de recherche produits', 'woo-total-menu') },
	{ type: 'cart', label: __('Panier', 'woo-total-menu'), icon: 'cart', desc: __('Mini-panier WooCommerce', 'woo-total-menu') },
	{ type: 'button', label: __('Bouton', 'woo-total-menu'), icon: 'button', desc: __('Bouton CTA', 'woo-total-menu') },
	{ type: 'html', label: __('HTML', 'woo-total-menu'), icon: 'editor-code', desc: __('HTML libre', 'woo-total-menu') },
	{ type: 'social', label: __('Réseaux', 'woo-total-menu'), icon: 'share', desc: __('Icônes réseaux sociaux', 'woo-total-menu') },
	{ type: 'newsletter', label: __('Newsletter', 'woo-total-menu'), icon: 'email', desc: __('Formulaire email', 'woo-total-menu') },
	{ type: 'text', label: __('Texte', 'woo-total-menu'), icon: 'text', desc: __('Texte / copyright', 'woo-total-menu') },
];

export default function ModulePalette() {
	const activeMode = useSelect((select) => select(UI_STORE_NAME).getActiveMode(), []);
	const selectedElementId = useSelect((select) => select(LAYOUT_STORE_NAME).getSelectedElementId(), []);
	const { addModule } = useDispatch(LAYOUT_STORE_NAME);

	const handleDragStart = (e, moduleType) => {
		e.dataTransfer.setData('wtm/module-type', moduleType);
		e.dataTransfer.effectAllowed = 'copy';
	};

	const handleAdd = (moduleType) => {
		// Click to add: drops into the currently selected column if any.
		// Otherwise the user must drag-and-drop.
		if (!selectedElementId) return;
		// We can't tell easily if the selected element is a column — but the
		// LayoutCanvas handles the click-to-add via its own toolbar.
		addModule(activeMode, selectedElementId, moduleType);
	};

	return (
		<aside className="wtm-builder__palette">
			<div className="wtm-builder__palette-header">
				<h2>
					<span className="dashicons dashicons-screenoptions"></span>
					{__('Modules', 'woo-total-menu')}
				</h2>
				<p className="wtm-builder__palette-help">
					{__('Glissez-déposez une tuile dans une colonne du canevas.', 'woo-total-menu')}
				</p>
			</div>
			<div className="wtm-builder__palette-list">
				{MODULES.map((m) => (
					<div
						key={m.type}
						className="wtm-builder__palette-item"
						draggable
						onDragStart={(e) => handleDragStart(e, m.type)}
						onClick={() => handleAdd(m.type)}
						title={m.desc}
					>
						<span className={`dashicons dashicons-${m.icon} wtm-builder__palette-icon`}></span>
						<div className="wtm-builder__palette-label">
							<span className="wtm-builder__palette-name">{m.label}</span>
							<span className="wtm-builder__palette-desc">{m.desc}</span>
						</div>
					</div>
				))}
			</div>
		</aside>
	);
}
