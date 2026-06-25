/**
 * LayoutCanvas — visual grid of rows/columns/modules for the Header/Footer builder.
 *
 * Spec reference: §3.6 (Header), §3.7 (Footer), §4.6.5, §6.3 (DnD).
 *
 * Supports:
 *   - Add / remove / select rows
 *   - Add / remove / resize columns (width 1..12)
 *   - Drop modules from the palette (HTML5 DnD, reads 'wtm/module-type')
 *   - Move modules between columns
 *   - Click to select (row | column | module)
 *
 * @package WooTotalMenu
 * @since 1.4.0
 */

import { __, sprintf } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';
import { LAYOUT_STORE_NAME } from '../stores/layout';
import { UI_STORE_NAME } from '../stores/ui';

const MODULE_LABELS = {
	logo: __('Logo', 'woo-total-menu'),
	menu: __('Menu', 'woo-total-menu'),
	search: __('Recherche', 'woo-total-menu'),
	cart: __('Panier', 'woo-total-menu'),
	button: __('Bouton', 'woo-total-menu'),
	html: __('HTML', 'woo-total-menu'),
	social: __('Réseaux', 'woo-total-menu'),
	newsletter: __('Newsletter', 'woo-total-menu'),
	text: __('Texte', 'woo-total-menu'),
};

const MODULE_ICONS = {
	logo: 'format-image',
	menu: 'menu',
	search: 'search',
	cart: 'cart',
	button: 'button',
	html: 'editor-code',
	social: 'share',
	newsletter: 'email',
	text: 'text',
};

/**
 * Render a summary of a module's settings inside the canvas card.
 *
 * @param {Object} module Module.
 * @return {string} Short text description.
 */
function moduleSummary(module) {
	const s = module.settings || {};
	switch (module.type) {
		case 'logo':
			return s.image_id ? sprintf(__('Image #%d', 'woo-total-menu'), s.image_id) : __('Logo texte', 'woo-total-menu');
		case 'menu':
			return s.menu_id ? sprintf(__('Menu #%d', 'woo-total-menu'), s.menu_id) : __('(non assigné)', 'woo-total-menu');
		case 'search':
			return s.placeholder || __('Rechercher…', 'woo-total-menu');
		case 'cart':
			return s.behavior === 'dropdown' ? __('Dropdown', 'woo-total-menu') : __('Drawer', 'woo-total-menu');
		case 'button':
			return s.text || __('(vide)', 'woo-total-menu');
		case 'html':
			return s.content ? s.content.substring(0, 40) : __('(vide)', 'woo-total-menu');
		case 'social':
			return sprintf(__('%d réseau(x)', 'woo-total-menu'), (s.links || []).length);
		case 'newsletter':
			return s.button_text || __('S\'abonner', 'woo-total-menu');
		case 'text':
			return s.content ? s.content.substring(0, 40) : __('(vide)', 'woo-total-menu');
		default:
			return '';
	}
}

export default function LayoutCanvas() {
	const activeMode = useSelect((select) => select(UI_STORE_NAME).getActiveMode(), []);
	const layout = useSelect((select) => select(LAYOUT_STORE_NAME).getLayout(activeMode), [activeMode]);
	const selectedElementId = useSelect((select) => select(LAYOUT_STORE_NAME).getSelectedElementId(), []);
	const selectedElementType = useSelect((select) => select(LAYOUT_STORE_NAME).getSelectedElementType(), []);

	const {
		addRow, updateRow, removeRow, moveRow,
		addColumn, updateColumn, removeColumn,
		addModule, updateModule, removeModule, moveModule,
		selectElement,
	} = useDispatch(LAYOUT_STORE_NAME);

	if (!layout) {
		return (
			<div className="wtm-canvas wtm-canvas--empty">
				<p>{__('Aucun layout chargé.', 'woo-total-menu')}</p>
			</div>
		);
	}

	const handleDropOnColumn = (e, colId) => {
		e.preventDefault();
		const moduleType = e.dataTransfer.getData('wtm/module-type');
		if (moduleType) {
			addModule(activeMode, colId, moduleType);
		}
	};

	const handleDropOnModule = (e, targetColId, targetIndex) => {
		e.preventDefault();
		const draggedModuleId = e.dataTransfer.getData('wtm/module-id');
		if (draggedModuleId) {
			moveModule(activeMode, draggedModuleId, targetColId, targetIndex);
		}
	};

	const handleModuleDragStart = (e, module) => {
		e.dataTransfer.setData('wtm/module-id', module.id);
		e.dataTransfer.effectAllowed = 'move';
	};

	return (
		<div className={`wtm-canvas wtm-canvas--${activeMode}`}>
			<div className="wtm-canvas__header">
				<h2>
					<span className="dashicons dashicons-layout"></span>
					{activeMode === 'header' ? __('Canevas Header', 'woo-total-menu') : __('Canevas Footer', 'woo-total-menu')}
				</h2>
				<button
					type="button"
					className="button button-secondary wtm-canvas__add-row"
					onClick={() => addRow(activeMode)}
				>
					<span className="dashicons dashicons-plus-alt"></span>
					{__('Ajouter une ligne', 'woo-total-menu')}
				</button>
			</div>

			{layout.rows.length === 0 ? (
				<div className="wtm-canvas__empty">
					<span className="dashicons dashicons-layout"></span>
					<p>{__('Aucune ligne. Cliquez sur « Ajouter une ligne » pour commencer.', 'woo-total-menu')}</p>
				</div>
			) : (
				<div className="wtm-canvas__rows">
					{layout.rows.map((row, rowIndex) => {
						const isSelectedRow = selectedElementType === 'row' && selectedElementId === row.id;
						return (
							<div
								key={row.id}
								className={`wtm-canvas__row ${isSelectedRow ? 'is-selected' : ''}`}
								style={{
									background: row.settings.background || 'transparent',
									minHeight: row.settings.height ? `${row.settings.height}px` : '60px',
									paddingTop: row.settings.padding_y ? `${row.settings.padding_y}px` : '12px',
									paddingBottom: row.settings.padding_y ? `${row.settings.padding_y}px` : '12px',
								}}
								onClick={(e) => {
									if (e.target === e.currentTarget) {
										selectElement(row.id, 'row');
									}
								}}
							>
								<div className="wtm-canvas__row-toolbar">
									<button
										type="button"
										className="wtm-canvas__row-handle"
										draggable
										onDragStart={(e) => e.dataTransfer.setData('wtm/row-id', row.id)}
										title={__('Glisser pour réordonner', 'woo-total-menu')}
									>
										<span className="dashicons dashicons-move"></span>
									</button>
									<span className="wtm-canvas__row-label">
										{sprintf(__('Ligne %d', 'woo-total-menu'), rowIndex + 1)}
									</span>
									<button
										type="button"
										className="wtm-canvas__row-delete"
										onClick={(e) => { e.stopPropagation(); removeRow(activeMode, row.id); }}
										title={__('Supprimer la ligne', 'woo-total-menu')}
									>
										<span className="dashicons dashicons-trash"></span>
									</button>
								</div>
								<div className="wtm-canvas__cols">
									{row.columns.map((col) => {
										const isSelectedCol = selectedElementType === 'column' && selectedElementId === col.id;
										return (
											<div
												key={col.id}
												className={`wtm-canvas__col ${isSelectedCol ? 'is-selected' : ''}`}
												style={{ flex: `0 0 ${(col.width / 12) * 100}%` }}
												onDragOver={(e) => e.preventDefault()}
												onDrop={(e) => handleDropOnColumn(e, col.id)}
												onClick={(e) => {
													if (e.target === e.currentTarget) {
														selectElement(col.id, 'column');
													}
												}}
											>
												<div className="wtm-canvas__col-header">
													<label className="wtm-canvas__col-width">
														{__('Largeur', 'woo-total-menu')}
														<input
															type="number"
															min="1"
															max="12"
															value={col.width}
															onChange={(e) => updateColumn(activeMode, col.id, { width: parseInt(e.target.value, 10) || 1 })}
															onClick={(e) => e.stopPropagation()}
														/>
														<span>/12</span>
													</label>
													<button
														type="button"
														className="wtm-canvas__col-delete"
														onClick={(e) => { e.stopPropagation(); removeColumn(activeMode, col.id); }}
														title={__('Supprimer la colonne', 'woo-total-menu')}
													>
														<span className="dashicons dashicons-trash"></span>
													</button>
												</div>
												<div className="wtm-canvas__modules">
													{col.modules.map((module, modIndex) => {
														const isSelectedMod = selectedElementType === 'module' && selectedElementId === module.id;
														return (
															<div
																key={module.id}
																className={`wtm-canvas__module ${isSelectedMod ? 'is-selected' : ''}`}
																draggable
																onDragStart={(e) => handleModuleDragStart(e, module)}
																onDragOver={(e) => e.preventDefault()}
																onDrop={(e) => { e.preventDefault(); e.stopPropagation(); handleDropOnModule(e, col.id, modIndex); }}
																onClick={(e) => { e.stopPropagation(); selectElement(module.id, 'module'); }}
															>
																<span className={`dashicons dashicons-${MODULE_ICONS[module.type] || 'block-default'} wtm-canvas__module-icon`}></span>
																<div className="wtm-canvas__module-body">
																	<span className="wtm-canvas__module-type">{MODULE_LABELS[module.type] || module.type}</span>
																	<span className="wtm-canvas__module-summary">{moduleSummary(module)}</span>
																</div>
																<button
																	type="button"
																	className="wtm-canvas__module-delete"
																	onClick={(e) => { e.stopPropagation(); removeModule(activeMode, module.id); }}
																	title={__('Supprimer le module', 'woo-total-menu')}
																>
																	<span className="dashicons dashicons-no-alt"></span>
																</button>
															</div>
														);
													})}
													{col.modules.length === 0 && (
														<div className="wtm-canvas__col-empty">
															{__('Glissez un module ici', 'woo-total-menu')}
														</div>
													)}
												</div>
												<button
													type="button"
													className="button button-link wtm-canvas__col-add"
													onClick={(e) => { e.stopPropagation(); addColumn(activeMode, row.id); }}
												>
													<span className="dashicons dashicons-plus"></span>
													{__('Colonne', 'woo-total-menu')}
												</button>
											</div>
										);
									})}
								</div>
							</div>
						);
					})}
				</div>
			)}
		</div>
	);
}
