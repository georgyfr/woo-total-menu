/**
 * Main App component — orchestrates the 3-column Builder layout.
 *
 * @package WooTotalMenu
 * @since 1.1.0
 */

import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';

import Header from './Header';
import TreePanel from './TreePanel';
import PreviewPanel from './PreviewPanel';
import PropertiesPanel from './PropertiesPanel';
import { WTM_STORE_NAME } from '../stores/menu';
import { UI_STORE_NAME } from '../stores/ui';

export default function App({ initialState }) {
	const { loadMenu, setError } = useDispatch(WTM_STORE_NAME);
	const { setRestConfig } = useDispatch(UI_STORE_NAME);

	const isLoading = useSelect((select) => select(WTM_STORE_NAME).isLoading(), []);
	const error = useSelect((select) => select(WTM_STORE_NAME).getError(), []);
	const menu = useSelect((select) => select(WTM_STORE_NAME).getMenu(), []);
	const isDirty = useSelect((select) => select(WTM_STORE_NAME).isDirty(), []);

	// On mount: configure REST and load the menu.
	useEffect(() => {
		setRestConfig({
			restUrl: initialState.restUrl,
			restNonce: initialState.restNonce,
		});
		if (initialState.menuId) {
			loadMenu(initialState.menuId);
		} else if (initialState.isNew) {
			// Initialize with empty config.
			loadMenu(null, {
				title: __('Nouveau menu', 'woo-total-menu'),
				menu_type: 'horizontal',
				location: 'primary',
				config: { version: 1, items: [] },
			});
		}
	}, []);

	return (
		<div className="wtm-builder">
			<Header
				title={menu?.title || __('Nouveau menu', 'woo-total-menu')}
				menuType={menu?.menu_type || 'horizontal'}
				isDirty={isDirty}
				isLoading={isLoading}
			/>

			{error && (
				<div className="wtm-builder__error">
					<span className="dashicons dashicons-warning"></span>
					{error}
				</div>
			)}

			<div className="wtm-builder__main">
				<div className="wtm-builder__col wtm-builder__col--tree">
					<TreePanel />
				</div>
				<div className="wtm-builder__col wtm-builder__col--preview">
					<PreviewPanel />
				</div>
				<div className="wtm-builder__col wtm-builder__col--properties">
					<PropertiesPanel />
				</div>
			</div>
		</div>
	);
}
