/**
 * Gutenberg Blocks — Woo Total Menu.
 *
 * Enregistre 3 blocs dynamiques côté éditeur :
 *   - wtm/menu
 *   - wtm/header
 *   - wtm/footer
 *
 * Le rendu serveur est géré par PHP (Gutenberg_Blocks::render_*_block).
 * Ici on ne fait que déclarer les blocs + la sidebar de configuration
 * (sélecteur de menu).
 */

import './style.css';

( function ( wp ) {
        const { registerBlockType } = wp.blocks;
        const { SelectControl, Placeholder } = wp.components;
        const { __ } = wp.i18n;
        const el = wp.element.createElement;

        // Données injectées via wp_localize_script.
        const menus = ( window.wtmBlocksData && window.wtmBlocksData.menus ) || [];

        /**
         * Construit les options pour le SelectControl.
         *
         * @param {boolean} includeEmpty Inclure une option vide en première position.
         * @return {Array} Options.
         */
        function buildOptions( includeEmpty ) {
                const opts = includeEmpty
                        ? [ { value: 0, label: __( '— Sélectionner —', 'woo-total-menu' ) } ]
                        : [];
                menus.forEach( function ( m ) {
                        opts.push( { value: m.value, label: m.label } );
                } );
                return opts;
        }

        /**
         * Icône inline pour les blocs (évite la dépendance à dashicons-prefixed icons).
         */
        function menuIcon() {
                return el(
                        'span',
                        {
                                className: 'dashicons dashicons-menu',
                                style: { fontSize: '20px', width: '20px', height: '20px' },
                        }
                );
        }

        // 1. Bloc `wtm/menu`.
        registerBlockType( 'wtm/menu', {
                title: __( 'Woo Total Menu — Menu', 'woo-total-menu' ),
                description: __(
                        'Affiche un menu Woo Total Menu (par ID ou par emplacement).',
                        'woo-total-menu'
                ),
                icon: menuIcon(),
                category: 'widgets',
                keywords: [ 'menu', 'woo', 'wtm', 'mega', 'navigation' ],
                supports: {
                        html: false,
                        align: [ 'wide', 'full' ],
                },
                attributes: {
                        menuId: { type: 'number', default: 0 },
                        location: { type: 'string', default: '' },
                },
                edit: function ( props ) {
                        const { attributes, setAttributes } = props;

                        if ( menus.length === 0 ) {
                                return el(
                                        Placeholder,
                                        {
                                                icon: menuIcon(),
                                                label: __( 'Woo Total Menu', 'woo-total-menu' ),
                                        },
                                        __(
                                                'Aucun menu WTM publié. Créez-en un depuis le Builder.',
                                                'woo-total-menu'
                                        )
                                );
                        }

                        return el(
                                'div',
                                { className: 'wtm-block-edit' },
                                el( SelectControl, {
                                        label: __( 'Menu à afficher', 'woo-total-menu' ),
                                        value: attributes.menuId,
                                        options: buildOptions( true ),
                                        onChange: function ( val ) {
                                                setAttributes( { menuId: parseInt( val, 10 ) || 0 } );
                                        },
                                } )
                        );
                },
                save: function () {
                        // Rendu serveur.
                        return null;
                },
        } );

        // 2. Bloc `wtm/header`.
        registerBlockType( 'wtm/header', {
                title: __( 'Woo Total Menu — Header', 'woo-total-menu' ),
                description: __(
                        'Affiche un header Woo Total Menu (utilise la config header du menu sélectionné).',
                        'woo-total-menu'
                ),
                icon: el(
                        'span',
                        {
                                className: 'dashicons dashicons-align-center',
                                style: { fontSize: '20px', width: '20px', height: '20px' },
                        }
                ),
                category: 'widgets',
                keywords: [ 'header', 'woo', 'wtm', 'topbar' ],
                supports: {
                        html: false,
                        align: [ 'wide', 'full' ],
                },
                attributes: {
                        menuId: { type: 'number', default: 0 },
                },
                edit: function ( props ) {
                        const { attributes, setAttributes } = props;

                        if ( menus.length === 0 ) {
                                return el(
                                        Placeholder,
                                        {
                                                icon: menuIcon(),
                                                label: __( 'Woo Total Menu — Header', 'woo-total-menu' ),
                                        },
                                        __(
                                                'Aucun menu WTM publié. Créez-en un puis configurez son header.',
                                                'woo-total-menu'
                                        )
                                );
                        }

                        return el(
                                'div',
                                { className: 'wtm-block-edit wtm-block-edit-header' },
                                el( SelectControl, {
                                        label: __( 'Menu contenant la config header', 'woo-total-menu' ),
                                        value: attributes.menuId,
                                        options: buildOptions( true ),
                                        onChange: function ( val ) {
                                                setAttributes( { menuId: parseInt( val, 10 ) || 0 } );
                                        },
                                } )
                        );
                },
                save: function () {
                        return null;
                },
        } );

        // 3. Bloc `wtm/footer`.
        registerBlockType( 'wtm/footer', {
                title: __( 'Woo Total Menu — Footer', 'woo-total-menu' ),
                description: __(
                        'Affiche un footer Woo Total Menu (utilise la config footer du menu sélectionné).',
                        'woo-total-menu'
                ),
                icon: el(
                        'span',
                        {
                                className: 'dashicons dashicons-table-row-after',
                                style: { fontSize: '20px', width: '20px', height: '20px' },
                        }
                ),
                category: 'widgets',
                keywords: [ 'footer', 'woo', 'wtm', 'bottom' ],
                supports: {
                        html: false,
                        align: [ 'wide', 'full' ],
                },
                attributes: {
                        menuId: { type: 'number', default: 0 },
                },
                edit: function ( props ) {
                        const { attributes, setAttributes } = props;

                        if ( menus.length === 0 ) {
                                return el(
                                        Placeholder,
                                        {
                                                icon: menuIcon(),
                                                label: __( 'Woo Total Menu — Footer', 'woo-total-menu' ),
                                        },
                                        __(
                                                'Aucun menu WTM publié. Créez-en un puis configurez son footer.',
                                                'woo-total-menu'
                                        )
                                );
                        }

                        return el(
                                'div',
                                { className: 'wtm-block-edit wtm-block-edit-footer' },
                                el( SelectControl, {
                                        label: __( 'Menu contenant la config footer', 'woo-total-menu' ),
                                        value: attributes.menuId,
                                        options: buildOptions( true ),
                                        onChange: function ( val ) {
                                                setAttributes( { menuId: parseInt( val, 10 ) || 0 } );
                                        },
                                } )
                        );
                },
                save: function () {
                        return null;
                },
        } );
} )( window.wp );
