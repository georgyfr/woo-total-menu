/**
 * LayoutBuilder — central layout for the Header/Footer builder mode.
 *
 * Replaces the 3-column TreePanel/PreviewPanel/PropertiesPanel used in menu
 * mode with a ModulePalette/LayoutCanvas/ModuleProperties trio.
 *
 * Spec reference: §4.6.5, §9.5.2 (store wtm/header et wtm/footer).
 *
 * @package WooTotalMenu
 * @since 1.4.0
 */

import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';
import { LAYOUT_STORE_NAME } from '../stores/layout';
import { UI_STORE_NAME } from '../stores/ui';
import { WTM_STORE_NAME } from '../stores/menu';

import ModulePalette from './ModulePalette';
import LayoutCanvas from './LayoutCanvas';
import ModuleProperties from './ModuleProperties';

export default function LayoutBuilder() {
        const activeMode = useSelect((select) => select(UI_STORE_NAME).getActiveMode(), []);
        const menu = useSelect((select) => select(WTM_STORE_NAME).getMenu(), []);
        const layout = useSelect((select) => select(LAYOUT_STORE_NAME).getLayout(activeMode), [activeMode]);
        const { loadFromMenu, clearSelection } = useDispatch(LAYOUT_STORE_NAME);

        // Load the layout from the menu payload whenever the menu changes
        // (initial load) or when switching mode (header ↔ footer).
        useEffect(() => {
                if (menu) {
                        loadFromMenu(activeMode, menu);
                }
        // eslint-disable-next-line react-hooks/exhaustive-deps
        }, [menu?.id, activeMode]);

        // Clear selection when switching modes.
        useEffect(() => {
                clearSelection();
        // eslint-disable-next-line react-hooks/exhaustive-deps
        }, [activeMode]);

        if (!menu) {
                return (
                        <div className="wtm-layout-builder wtm-layout-builder--empty">
                                <p>{__('Aucun menu chargé.', 'woo-total-menu')}</p>
                        </div>
                );
        }

        if (!layout) {
                return (
                        <div className="wtm-layout-builder wtm-layout-builder--loading">
                                <span className="dashicons dashicons-update spin"></span>
                                <p>{__('Chargement du layout…', 'woo-total-menu')}</p>
                        </div>
                );
        }

        return (
                <div className="wtm-layout-builder">
                        <div className="wtm-builder__col wtm-builder__col--palette">
                                <ModulePalette />
                        </div>
                        <div className="wtm-builder__col wtm-builder__col--canvas">
                                <LayoutCanvas />
                        </div>
                        <div className="wtm-builder__col wtm-builder__col--module-properties">
                                <ModuleProperties />
                        </div>
                </div>
        );
}
