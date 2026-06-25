/**
 * TemplateGallery — Modal galerie de templates intégrés.
 *
 * Affiché au-dessus du Builder quand `isTemplatesOpen` est true dans le
 * store `wtm/ui`. Permet de :
 *  - Filtrer par type (menu | header | footer) et par catégorie.
 *  - Rechercher par mot-clé (sur name, description, preview, tags).
 *  - Prévisualiser chaque template via une mini-rendu CSS (TemplateCard).
 *  - Appliquer un template au menu courant via `applyTemplate(id)`.
 *
 * @package WooTotalMenu
 * @since 1.5.0
 */

import { useEffect, useState } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { Button, Spinner, Modal, Notice, TextControl } from '@wordpress/components';

import { UI_STORE_NAME } from '../stores/ui';
import { TEMPLATES_STORE_NAME } from '../stores/templates';
import { TemplateCard } from './TemplateCard';

/**
 * Gallery modal component.
 *
 * @return {JSX.Element|null} The gallery modal, or null when closed.
 */
export function TemplateGallery() {
	const isOpen = useSelect((select) => select(UI_STORE_NAME).isTemplatesOpen(), []);
	const activeMode = useSelect((select) => select(UI_STORE_NAME).getActiveMode(), []);
	const templates = useSelect((select) => select(TEMPLATES_STORE_NAME).getFilteredTemplates(), []);
	const categories = useSelect((select) => select(TEMPLATES_STORE_NAME).getCategories(), []);
	const isLoading = useSelect((select) => select(TEMPLATES_STORE_NAME).isLoading(), []);
	const error = useSelect((select) => select(TEMPLATES_STORE_NAME).getError(), []);
	const isApplying = useSelect((select) => select(TEMPLATES_STORE_NAME).isApplying(), []);
	const applyError = useSelect((select) => select(TEMPLATES_STORE_NAME).getApplyError(), []);
	const filterType = useSelect((select) => select(TEMPLATES_STORE_NAME).getFilterType(), []);
	const filterCategory = useSelect((select) => select(TEMPLATES_STORE_NAME).getFilterCategory(), []);
	const filterSearch = useSelect((select) => select(TEMPLATES_STORE_NAME).getFilterSearch(), []);

	const { closeTemplates } = useDispatch(UI_STORE_NAME);
	const { fetchTemplates, setFilterType, setFilterCategory, setFilterSearch, resetFilters, applyTemplate } = useDispatch(TEMPLATES_STORE_NAME);

	// When the modal opens, ensure the catalog is loaded and align the
	// `filterType` with the current Builder mode (so the user sees the
	// most relevant templates first).
	useEffect(() => {
		if (!isOpen) return;
		fetchTemplates();
		// If the user is in Header/Footer mode, pre-filter on that type.
		if (activeMode && (activeMode === 'header' || activeMode === 'footer' || activeMode === 'menu')) {
			setFilterType(activeMode);
		}
	}, [isOpen]); // eslint-disable-line react-hooks/exhaustive-deps

	// Escape key closes the modal.
	useEffect(() => {
		if (!isOpen) return;
		const onKey = (e) => {
			if (e.key === 'Escape' && !isApplying) {
				closeTemplates();
			}
		};
		window.addEventListener('keydown', onKey);
		return () => window.removeEventListener('keydown', onKey);
	}, [isOpen, isApplying, closeTemplates]);

	if (!isOpen) return null;

	// Tabs type.
	const typeTabs = [
		{ slug: '', label: __('Tous', 'woo-total-menu') },
		{ slug: 'menu', label: __('Menus', 'woo-total-menu') },
		{ slug: 'header', label: __('Headers', 'woo-total-menu') },
		{ slug: 'footer', label: __('Footers', 'woo-total-menu') },
	];

	return (
		<Modal
			className="wtm-template-gallery-modal"
			title={__('Galerie de templates', 'woo-total-menu')}
			onRequestClose={() => !isApplying && closeTemplates()}
			shouldCloseOnClickOutside={false}
			isDismissable={!isApplying}
		>
			<div className="wtm-template-gallery">
				{error && (
					<Notice status="error" isDismissible={false}>
						{error}
					</Notice>
				)}
				{applyError && (
					<Notice status="error" isDismissible={false}>
						{applyError}
					</Notice>
				)}

				<div className="wtm-template-gallery__toolbar">
					<div className="wtm-template-gallery__tabs" role="tablist">
						{typeTabs.map((tab) => (
							<Button
								key={tab.slug || 'all'}
								isPrimary={filterType === tab.slug}
								isSecondary={filterType !== tab.slug}
								isSmall
								onClick={() => setFilterType(tab.slug)}
								role="tab"
								aria-selected={filterType === tab.slug}
							>
								{tab.label}
							</Button>
						))}
					</div>
					<TextControl
						className="wtm-template-gallery__search"
						placeholder={__('Rechercher un template…', 'woo-total-menu')}
						value={filterSearch}
						onChange={(val) => setFilterSearch(val)}
					/>
				</div>

				{categories.length > 0 && (
					<div className="wtm-template-gallery__categories">
						<Button
							isLink={!filterCategory}
							isPrimary={!!filterCategory && filterCategory === ''}
							onClick={() => setFilterCategory('')}
							isSmall
						>
							{__('Toutes catégories', 'woo-total-menu')}
						</Button>
						{categories.map((cat) => (
							<Button
								key={cat.slug}
								isPrimary={filterCategory === cat.slug}
								isSecondary={filterCategory !== cat.slug}
								isSmall
								onClick={() => setFilterCategory(cat.slug === filterCategory ? '' : cat.slug)}
							>
								{cat.label} ({cat.count})
							</Button>
						))}
						{(filterType || filterCategory || filterSearch) && (
							<Button
								className="wtm-template-gallery__reset"
								isDestructive
								isLink
								onClick={() => resetFilters()}
							>
								{__('Réinitialiser les filtres', 'woo-total-menu')}
							</Button>
						)}
					</div>
				)}

				<div className="wtm-template-gallery__grid">
					{isLoading && (
						<div className="wtm-template-gallery__loading">
							<Spinner />
							<p>{__('Chargement des templates…', 'woo-total-menu')}</p>
						</div>
					)}
					{!isLoading && templates.length === 0 && (
						<div className="wtm-template-gallery__empty">
							<p>{__('Aucun template ne correspond à votre recherche.', 'woo-total-menu')}</p>
						</div>
					)}
					{!isLoading && templates.length > 0 && (
						templates.map((tpl) => (
							<TemplateCard
								key={tpl.id}
								template={tpl}
								isApplying={isApplying}
								onApply={() => applyTemplate(tpl.id)}
							/>
						))
					)}
				</div>

				{isApplying && (
					<div className="wtm-template-gallery__applying">
						<Spinner />
						<span>{__('Application du template en cours…', 'woo-total-menu')}</span>
					</div>
				)}
			</div>
		</Modal>
	);
}
