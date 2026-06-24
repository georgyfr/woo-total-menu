/**
 * Header component — top toolbar with title, device switcher, save button.
 *
 * @package WooTotalMenu
 * @since 1.1.0
 */

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
	const { saveMenu } = useDispatch(WTM_STORE_NAME);
	const device = useSelect((select) => select(UI_STORE_NAME).getDevice(), []);
	const { setDevice } = useDispatch(UI_STORE_NAME);

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
