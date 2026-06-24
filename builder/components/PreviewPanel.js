/**
 * PreviewPanel — center column showing a live preview of the menu.
 *
 * In v1.1.0 this is a stub that displays a placeholder.
 * The actual iframe-based live preview will come in v1.1.4.
 *
 * @package WooTotalMenu
 * @since 1.1.0
 */

import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';

import { WTM_STORE_NAME } from '../stores/menu';
import { UI_STORE_NAME } from '../stores/ui';

export default function PreviewPanel() {
	const menu = useSelect((select) => select(WTM_STORE_NAME).getMenu(), []);
	const device = useSelect((select) => select(UI_STORE_NAME).getDevice(), []);
	const isLoading = useSelect((select) => select(WTM_STORE_NAME).isLoading(), []);

	const deviceWidth = {
		desktop: '100%',
		tablet: '768px',
		mobile: '375px',
	};

	return (
		<div className="wtm-preview">
			<div className="wtm-preview__header">
				<h2>
					<span className="dashicons dashicons-visibility"></span>
					{__('Aperçu', 'woo-total-menu')}
				</h2>
				<span className="wtm-preview__device">{device}</span>
			</div>

			<div className="wtm-preview__body">
				<div
					className={`wtm-preview__frame wtm-preview__frame--${device}`}
					style={{ maxWidth: deviceWidth[device] }}
				>
					{isLoading ? (
						<div className="wtm-preview__loading">
							<span className="dashicons dashicons-update spin"></span>
							<p>{__('Chargement du menu…', 'woo-total-menu')}</p>
						</div>
					) : menu ? (
						<div className="wtm-preview__placeholder">
							<div className="wtm-preview__menu-bar">
								<span className="wtm-preview__logo">LOGO</span>
								<nav className="wtm-preview__nav">
									{(menu.config?.items || []).slice(0, 5).map((item) => (
										<span key={item.id} className="wtm-preview__nav-item">
											{item.label || item.type}
										</span>
									))}
									{(!menu.config?.items || menu.config.items.length === 0) && (
										<span className="wtm-preview__empty-nav">
											{__('Menu vide — ajoutez des éléments dans l\'arborescence', 'woo-total-menu')}
										</span>
									)}
								</nav>
								<span className="wtm-preview__cart dashicons dashicons-cart"></span>
							</div>
							<div className="wtm-preview__content">
								<p>
									{__('Aperçu live via iframe + postMessage — disponible en v1.1.4.', 'woo-total-menu')}
								</p>
								<p className="wtm-preview__hint">
									{__('Pour l\'instant, voici un placeholder statique du menu.', 'woo-total-menu')}
								</p>
							</div>
						</div>
					) : (
						<div className="wtm-preview__empty">
							<span className="dashicons dashicons-menu"></span>
							<p>{__('Aucun menu chargé.', 'woo-total-menu')}</p>
						</div>
					)}
				</div>
			</div>
		</div>
	);
}
