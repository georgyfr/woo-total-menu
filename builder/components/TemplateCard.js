/**
 * TemplateCard — Carte individuelle d'un template.
 *
 * Affiche un mini-aperçu CSS du template (basé sur le champ `thumbnail`),
 * son nom, sa description, ses tags et un bouton "Appliquer".
 *
 * Le mini-aperçu est rendu par `ThumbnailPreview` qui choisit un layout
 * CSS synthétique selon le `thumbnail` (mapping défini ci-dessous).
 *
 * @package WooTotalMenu
 * @since 1.5.0
 */

import { Button, Tooltip } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Mapping thumbnail slug -> rendered structure (mini preview).
 *
 * Chaque entrée renvoie un JSX léger qui évoque visuellement le template
 * sans avoir à charger le vrai rendu frontend (beaucoup plus rapide).
 */
const THUMBNAIL_RENDERERS = {
	'menu-simple': () => (
		<div className="wtm-thumb wtm-thumb--menu-simple">
			<span className="wtm-thumb__bar" />
			<div className="wtm-thumb__row">
				<span className="wtm-thumb__link" />
				<span className="wtm-thumb__link" />
				<span className="wtm-thumb__link" />
				<span className="wtm-thumb__link" />
			</div>
		</div>
	),
	'menu-mega': () => (
		<div className="wtm-thumb wtm-thumb--menu-mega">
			<div className="wtm-thumb__row">
				<span className="wtm-thumb__link wtm-thumb__link--caret" />
				<span className="wtm-thumb__link wtm-thumb__link--caret" />
				<span className="wtm-thumb__link" />
			</div>
			<div className="wtm-thumb__mega">
				<span className="wtm-thumb__col" />
				<span className="wtm-thumb__col" />
				<span className="wtm-thumb__col wtm-thumb__col--wide" />
			</div>
		</div>
	),
	'menu-vertical': () => (
		<div className="wtm-thumb wtm-thumb--menu-vertical">
			<div className="wtm-thumb__sidebar">
				<span className="wtm-thumb__row-item" />
				<span className="wtm-thumb__row-item" />
				<span className="wtm-thumb__row-item" />
				<span className="wtm-thumb__row-item" />
			</div>
		</div>
	),
	'header-3cols': () => (
		<div className="wtm-thumb wtm-thumb--header-3cols">
			<div className="wtm-thumb__row">
				<span className="wtm-thumb__logo" />
				<div className="wtm-thumb__links">
					<span className="wtm-thumb__link" />
					<span className="wtm-thumb__link" />
					<span className="wtm-thumb__link" />
				</div>
				<div className="wtm-thumb__icons">
					<span className="wtm-thumb__icon" />
					<span className="wtm-thumb__icon" />
				</div>
			</div>
		</div>
	),
	'header-2cols': () => (
		<div className="wtm-thumb wtm-thumb--header-2cols">
			<div className="wtm-thumb__row">
				<span className="wtm-thumb__logo" />
				<div className="wtm-thumb__links">
					<span className="wtm-thumb__link" />
					<span className="wtm-thumb__link" />
					<span className="wtm-thumb__link" />
				</div>
			</div>
		</div>
	),
	'header-2rows': () => (
		<div className="wtm-thumb wtm-thumb--header-2rows">
			<div className="wtm-thumb__topbar" />
			<div className="wtm-thumb__row">
				<span className="wtm-thumb__logo" />
				<div className="wtm-thumb__links">
					<span className="wtm-thumb__link" />
					<span className="wtm-thumb__link" />
				</div>
				<div className="wtm-thumb__icons">
					<span className="wtm-thumb__icon" />
				</div>
			</div>
		</div>
	),
	'header-centered': () => (
		<div className="wtm-thumb wtm-thumb--header-centered">
			<div className="wtm-thumb__center">
				<span className="wtm-thumb__logo" />
			</div>
			<div className="wtm-thumb__center">
				<span className="wtm-thumb__link" />
				<span className="wtm-thumb__link" />
				<span className="wtm-thumb__link" />
			</div>
		</div>
	),
	'footer-4cols': () => (
		<div className="wtm-thumb wtm-thumb--footer-4cols">
			<div className="wtm-thumb__cols">
				<span className="wtm-thumb__col" />
				<span className="wtm-thumb__col" />
				<span className="wtm-thumb__col" />
				<span className="wtm-thumb__col" />
			</div>
		</div>
	),
	'footer-minimal': () => (
		<div className="wtm-thumb wtm-thumb--footer-minimal">
			<div className="wtm-thumb__center">
				<span className="wtm-thumb__text" />
			</div>
			<div className="wtm-thumb__center">
				<span className="wtm-thumb__icon" />
				<span className="wtm-thumb__icon" />
				<span className="wtm-thumb__icon" />
			</div>
		</div>
	),
	'footer-dark': () => (
		<div className="wtm-thumb wtm-thumb--footer-dark">
			<div className="wtm-thumb__cols">
				<span className="wtm-thumb__col" />
				<span className="wtm-thumb__col" />
				<span className="wtm-thumb__col" />
				<span className="wtm-thumb__col" />
			</div>
			<div className="wtm-thumb__bottom" />
		</div>
	),
};

/**
 * Render the thumbnail preview for a template.
 *
 * @param {string} slug Thumbnail slug.
 * @return {JSX.Element} Rendered thumbnail.
 */
function ThumbnailPreview({ slug }) {
	const renderer = THUMBNAIL_RENDERERS[slug];
	if (renderer) return renderer();
	// Default generic preview.
	return (
		<div className="wtm-thumb wtm-thumb--default">
			<div className="wtm-thumb__row">
				<span className="wtm-thumb__logo" />
				<div className="wtm-thumb__links">
					<span className="wtm-thumb__link" />
					<span className="wtm-thumb__link" />
				</div>
			</div>
		</div>
	);
}

/**
 * Get the icon dashicon for a template type.
 *
 * @param {string} type Template type.
 * @return {string} Dashicon name.
 */
function typeIcon(type) {
	switch (type) {
		case 'menu': return 'menu';
		case 'header': return 'layout';
		case 'footer': return 'feedback';
		default: return 'layout';
	}
}

/**
 * Get the human-readable label for a template type.
 *
 * @param {string} type Template type.
 * @return {string} Translated label.
 */
function typeLabel(type) {
	switch (type) {
		case 'menu': return __('Menu', 'woo-total-menu');
		case 'header': return __('Header', 'woo-total-menu');
		case 'footer': return __('Footer', 'woo-total-menu');
		default: return type;
	}
}

/**
 * TemplateCard component.
 *
 * @param {Object}    props        Component props.
 * @param {Object}    props.template The template data.
 * @param {boolean}   props.isApplying Whether a template is being applied.
 * @param {Function}  props.onApply   Callback when "Apply" is clicked.
 * @return {JSX.Element} The rendered card.
 */
export function TemplateCard({ template, isApplying, onApply }) {
	const hasConfirm = typeof window !== 'undefined' && window.confirm;

	const handleApply = () => {
		// Confirm because applying overwrites the current configuration.
		const msg = __(
			'Appliquer ce template remplacera la configuration actuelle de ce menu. Voulez-vous continuer ?',
			'woo-total-menu'
		);
		if (hasConfirm && !window.confirm(msg)) {
			return;
		}
		onApply();
	};

	return (
		<div className="wtm-template-card" data-template-id={template.id} data-type={template.type}>
			<div className="wtm-template-card__preview">
				<ThumbnailPreview slug={template.thumbnail} />
				<span className="wtm-template-card__type-badge">
					<span className={`dashicons dashicons-${typeIcon(template.type)}`} />
					{typeLabel(template.type)}
				</span>
			</div>
			<div className="wtm-template-card__body">
				<h3 className="wtm-template-card__title">{template.name}</h3>
				<p className="wtm-template-card__desc">{template.description}</p>
				{template.preview && (
					<Tooltip text={template.preview}>
						<div className="wtm-template-card__preview-text">{template.preview}</div>
					</Tooltip>
				)}
				{template.tags && template.tags.length > 0 && (
					<div className="wtm-template-card__tags">
						{template.tags.slice(0, 4).map((tag) => (
							<span key={tag} className="wtm-template-card__tag">#{tag}</span>
						))}
					</div>
				)}
			</div>
			<div className="wtm-template-card__actions">
				<Button
					isPrimary
					isSmall
					disabled={isApplying}
					onClick={handleApply}
					className="wtm-template-card__apply"
				>
					<span className="dashicons dashicons-migrate" />
					{__('Appliquer', 'woo-total-menu')}
				</Button>
			</div>
		</div>
	);
}
