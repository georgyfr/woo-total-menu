/**
 * Entry point of the Woo Total Menu Builder React app.
 *
 * @package WooTotalMenu
 * @since 1.1.0
 */

import { render } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import App from './components/App';
import './stores/menu';
import './stores/ui';
import './style.css';

// Render the Builder app in the #wtm-builder-root container that the
// PHP page (Pages/Builder.php) outputs.
const container = document.getElementById('wtm-builder-root');

if (container) {
        // Read the initial state passed from PHP via wp_localize_script (set by Admin_Menu::enqueue_builder_assets).
        const initialState = window.wtmBuilderData || {
                menuId: 0,
                menu: null,
                restNonce: '',
                restUrl: '',
                isNew: false,
        };

        // Also read the menu_id / new from data attributes on the container
        // (set by Builder::render) as a fallback.
        if (!initialState.menuId) {
                initialState.menuId = parseInt(container.dataset.menuId || '0', 10);
        }
        if (!initialState.isNew) {
                initialState.isNew = container.dataset.isNew === '1';
        }

        render(<App initialState={initialState} />, container);
}
