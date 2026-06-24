/**
 * PropertiesPanel — right column showing properties of the selected item.
 *
 * @package WooTotalMenu
 * @since 1.1.0
 */

import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';

import { WTM_STORE_NAME } from '../stores/menu';
import { UI_STORE_NAME } from '../stores/ui';

export default function PropertiesPanel() {
	const selectedItem = useSelect((select) => select(WTM_STORE_NAME).getSelectedItem(), []);
	const menu = useSelect((select) => select(WTM_STORE_NAME).getMenu(), []);

	return (
		<div className="wtm-properties">
			<div className="wtm-properties__header">
				<h2>
					<span className="dashicons dashicons-admin-customizer"></span>
					{__('Propriétés', 'woo-total-menu')}
				</h2>
			</div>

			<div className="wtm-properties__body">
				{selectedItem ? (
					<div className="wtm-properties__item">
						<div className="wtm-properties__row">
							<label>{__('ID', 'woo-total-menu')}</label>
							<code>{selectedItem.id}</code>
						</div>
						<div className="wtm-properties__row">
							<label>{__('Type', 'woo-total-menu')}</label>
							<code>{selectedItem.type}</code>
						</div>
						{selectedItem.label && (
							<div className="wtm-properties__row">
								<label>{__('Libellé', 'woo-total-menu')}</label>
								<input type="text" defaultValue={selectedItem.label} disabled />
							</div>
						)}
						{selectedItem.url && (
							<div className="wtm-properties__row">
								<label>{__('URL', 'woo-total-menu')}</label>
								<input type="text" defaultValue={selectedItem.url} disabled />
							</div>
						)}
						{selectedItem.widget_type && (
							<div className="wtm-properties__row">
								<label>{__('Widget', 'woo-total-menu')}</label>
								<code>{selectedItem.widget_type}</code>
							</div>
						)}
						{selectedItem.badge && (
							<div className="wtm-properties__row">
								<label>{__('Badge', 'woo-total-menu')}</label>
								<span className="wtm-badge">{selectedItem.badge.text}</span>
							</div>
						)}
						<p className="wtm-properties__hint">
							{__('Édition complète des propriétés disponible en v1.1.2.', 'woo-total-menu')}
						</p>
					</div>
				) : menu ? (
					<div className="wtm-properties__menu">
						<h3>{__('Propriétés du menu', 'woo-total-menu')}</h3>
						<div className="wtm-properties__row">
							<label>{__('Titre', 'woo-total-menu')}</label>
							<input type="text" defaultValue={menu.title} disabled />
						</div>
						<div className="wtm-properties__row">
							<label>{__('Slug', 'woo-total-menu')}</label>
							<code>{menu.slug}</code>
						</div>
						<div className="wtm-properties__row">
							<label>{__('Statut', 'woo-total-menu')}</label>
							<code>{menu.status}</code>
						</div>
						<div className="wtm-properties__row">
							<label>{__('Type de menu', 'woo-total-menu')}</label>
							<code>{menu.menu_type}</code>
						</div>
						<div className="wtm-properties__row">
							<label>{__('Emplacement', 'woo-total-menu')}</label>
							<code>{menu.location}</code>
						</div>
						<div className="wtm-properties__row">
							<label>{__('Version du schéma', 'woo-total-menu')}</label>
							<code>{menu.version}</code>
						</div>
					</div>
				) : (
					<div className="wtm-properties__empty">
						<span className="dashicons dashicons-info"></span>
						<p>{__('Sélectionnez un élément dans l\'arborescence pour afficher ses propriétés.', 'woo-total-menu')}</p>
					</div>
				)}
			</div>
		</div>
	);
}
