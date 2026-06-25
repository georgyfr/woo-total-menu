/**
 * Conditions Panel — modal editor for v1.7.0 menu visibility rules.
 *
 * Lets the user define a list of rules (page_type, user_state, device, etc.)
 * combined with AND (all) or OR (any) logic. Rules are stored on the menu
 * post via the REST endpoint /wtm/v1/menus/{id}/conditions and consulted
 * at render time by Condition_Evaluator (server-side).
 *
 * The panel also exposes a "Test" button that calls the /conditions/test
 * endpoint to evaluate the current ruleset against the live request —
 * useful for previewing which rules will pass on the current page.
 *
 * @package WooTotalMenu
 * @since 1.7.0
 */

import { useEffect, useState, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';

import { WTM_STORE_NAME } from '../stores/menu';
import { UI_STORE_NAME } from '../stores/ui';

const RULE_TYPES = [
	{ value: 'page_type',  label: __('Type de page', 'woo-total-menu'), placeholder: 'shop | product | cart | checkout | front_page | …' },
	{ value: 'post_id',    label: __('ID du contenu', 'woo-total-menu'), placeholder: '42, 17 (IDs séparés par virgule)' },
	{ value: 'post_type',  label: __('Type de contenu', 'woo-total-menu'), placeholder: 'post | page | product' },
	{ value: 'taxonomy',   label: __('Taxonomie', 'woo-total-menu'), placeholder: 'category:news | product_cat:t-shirts' },
	{ value: 'user_state', label: __('Utilisateur', 'woo-total-menu'), placeholder: 'logged_in | logged_out' },
	{ value: 'user_role',  label: __('Rôle utilisateur', 'woo-total-menu'), placeholder: 'administrator | customer | …' },
	{ value: 'device',     label: __('Appareil', 'woo-total-menu'), placeholder: 'mobile | tablet | desktop' },
	{ value: 'date_range', label: __('Plage de dates', 'woo-total-menu'), placeholder: '2026-01-01..2026-12-31' },
	{ value: 'url_param',  label: __('Paramètre URL', 'woo-total-menu'), placeholder: 'utm_source=newsletter | key=*' },
	{ value: 'language',   label: __('Langue', 'woo-total-menu'), placeholder: 'en | fr (WPML/Polylang)' },
];

const PAGE_TYPE_VALUES = [
	'front_page', 'home', 'single', 'page', 'archive', 'search', '404',
	'shop', 'product', 'cart', 'checkout', 'account', 'product_category', 'product_tag',
];

const USER_STATE_VALUES = ['logged_in', 'logged_out'];
const DEVICE_VALUES = ['mobile', 'tablet', 'desktop'];

const ENUM_VALUES = {
	page_type: PAGE_TYPE_VALUES,
	user_state: USER_STATE_VALUES,
	device: DEVICE_VALUES,
};

export default function ConditionsPanel() {
	const isOpen = useSelect((select) => select(UI_STORE_NAME).isConditionsOpen(), []);
	const menu = useSelect((select) => select(WTM_STORE_NAME).getMenu(), []);
	const { closeConditions, setAnnouncement } = useDispatch(UI_STORE_NAME);

	const [logic, setLogic] = useState('all');
	const [rules, setRules] = useState([]);
	const [testResult, setTestResult] = useState(null);
	const [isSaving, setIsSaving] = useState(false);
	const [isLoading, setIsLoading] = useState(false);
	const [error, setError] = useState('');

	const menuId = menu?.id;

	// Load conditions when the panel opens or menu changes.
	useEffect(() => {
		if (!isOpen || !menuId) {
			return;
		}
		setIsLoading(true);
		setError('');
		apiFetch({
			path: `/wtm/v1/menus/${menuId}/conditions`,
			method: 'GET',
		})
			.then((data) => {
				setLogic(data.logic || 'all');
				setRules(Array.isArray(data.rules) ? data.rules : []);
				setTestResult(null);
			})
			.catch((err) => {
				setError(err?.message || __('Erreur de chargement.', 'woo-total-menu'));
			})
			.finally(() => setIsLoading(false));
	}, [isOpen, menuId]);

	const addRule = useCallback(() => {
		setRules([...rules, { type: 'page_type', value: '' }]);
		setTestResult(null);
	}, [rules]);

	const removeRule = useCallback((idx) => {
		setRules(rules.filter((_, i) => i !== idx));
		setTestResult(null);
	}, [rules]);

	const updateRule = useCallback((idx, field, value) => {
		const next = rules.map((r, i) => i === idx ? { ...r, [field]: value } : r);
		// When type changes, reset the value so the user picks a fresh one.
		if (field === 'type') {
			next[idx].value = '';
		}
		setRules(next);
		setTestResult(null);
	}, [rules]);

	const handleSave = useCallback(() => {
		if (!menuId) return;
		setIsSaving(true);
		setError('');
		apiFetch({
			path: `/wtm/v1/menus/${menuId}/conditions`,
			method: 'PUT',
			data: { logic, rules },
		})
			.then(() => {
				setAnnouncement(__('Conditions enregistrées.', 'woo-total-menu'));
				closeConditions();
			})
			.catch((err) => {
				setError(err?.message || __('Erreur lors de la sauvegarde.', 'woo-total-menu'));
			})
			.finally(() => setIsSaving(false));
	}, [menuId, logic, rules, closeConditions, setAnnouncement]);

	const handleTest = useCallback(() => {
		if (!menuId) return;
		setError('');
		apiFetch({
			path: `/wtm/v1/menus/${menuId}/conditions/test`,
			method: 'POST',
			data: { logic, rules },
		})
			.then((result) => {
				setTestResult(result);
				setAnnouncement(
					result.overall_match
						? __('Test : le menu s\'afficherait sur cette page.', 'woo-total-menu')
						: __('Test : le menu ne s\'afficherait pas sur cette page.', 'woo-total-menu')
				);
			})
			.catch((err) => {
				setError(err?.message || __('Erreur lors du test.', 'woo-total-menu'));
			});
	}, [menuId, logic, rules, setAnnouncement]);

	const handleClear = useCallback(() => {
		if (!menuId) return;
		setIsSaving(true);
		apiFetch({
			path: `/wtm/v1/menus/${menuId}/conditions`,
			method: 'DELETE',
		})
			.then(() => {
				setLogic('all');
				setRules([]);
				setTestResult(null);
				setAnnouncement(__('Conditions effacées.', 'woo-total-menu'));
			})
			.catch((err) => {
				setError(err?.message || __('Erreur lors de l\'effacement.', 'woo-total-menu'));
			})
			.finally(() => setIsSaving(false));
	}, [menuId, setAnnouncement]);

	if (!isOpen) {
		return null;
	}

	return (
		<div className="wtm-modal-overlay" role="dialog" aria-modal="true" aria-label={__('Conditions d\'affichage', 'woo-total-menu')}>
			<div className="wtm-modal wtm-conditions-modal">
				<div className="wtm-modal__header">
					<h2>
						<span className="dashicons dashicons-shield-alt"></span>
						{__('Conditions d\'affichage', 'woo-total-menu')}
					</h2>
					<button
						type="button"
						className="wtm-modal__close"
						onClick={closeConditions}
						aria-label={__('Fermer', 'woo-total-menu')}
					>
						<span className="dashicons dashicons-no"></span>
					</button>
				</div>

				<div className="wtm-modal__body">
					<p className="wtm-conditions__intro">
						{__('Définissez quand ce menu (et son header/footer lié) doit s\'afficher. Sans conditions, le menu s\'affiche toujours.', 'woo-total-menu')}
					</p>

					<div className="wtm-conditions__logic">
						<label>
							<input
								type="radio"
								name="wtm_logic"
								value="all"
								checked={logic === 'all'}
								onChange={() => setLogic('all')}
							/>
							{__('Toutes les règles doivent être vraies (ET)', 'woo-total-menu')}
						</label>
						<label>
							<input
								type="radio"
								name="wtm_logic"
								value="any"
								checked={logic === 'any'}
								onChange={() => setLogic('any')}
							/>
							{__('Au moins une règle doit être vraie (OU)', 'woo-total-menu')}
						</label>
					</div>

					{isLoading ? (
						<p>{__('Chargement…', 'woo-total-menu')}</p>
					) : (
						<table className="wtm-conditions__table">
							<thead>
								<tr>
									<th>{__('Type', 'woo-total-menu')}</th>
									<th>{__('Valeur', 'woo-total-menu')}</th>
									<th style={{ width: '40px' }}></th>
								</tr>
							</thead>
							<tbody>
								{rules.length === 0 && (
									<tr>
										<td colSpan={3} className="wtm-conditions__empty">
											{__('Aucune règle. Cliquez sur « Ajouter une règle » pour commencer.', 'woo-total-menu')}
										</td>
									</tr>
								)}
								{rules.map((rule, idx) => {
									const enumValues = ENUM_VALUES[rule.type];
									const ruleTypeDef = RULE_TYPES.find((r) => r.value === rule.type);
									return (
										<tr key={idx}>
											<td>
												<select
													value={rule.type}
													onChange={(e) => updateRule(idx, 'type', e.target.value)}
												>
													{RULE_TYPES.map((rt) => (
														<option key={rt.value} value={rt.value}>{rt.label}</option>
													))}
												</select>
											</td>
											<td>
												{enumValues ? (
													<select
														value={rule.value}
														onChange={(e) => updateRule(idx, 'value', e.target.value)}
													>
														<option value="">— Sélectionner —</option>
														{enumValues.map((v) => (
															<option key={v} value={v}>{v}</option>
														))}
													</select>
												) : (
													<input
														type="text"
														value={rule.value}
														placeholder={ruleTypeDef?.placeholder || ''}
														onChange={(e) => updateRule(idx, 'value', e.target.value)}
													/>
												)}
											</td>
											<td>
												<button
													type="button"
													className="wtm-conditions__remove"
													onClick={() => removeRule(idx)}
													aria-label={__('Supprimer cette règle', 'woo-total-menu')}
													title={__('Supprimer', 'woo-total-menu')}
												>
													<span className="dashicons dashicons-trash"></span>
												</button>
											</td>
										</tr>
									);
								})}
							</tbody>
						</table>
					)}

					<div className="wtm-conditions__actions-row">
						<button type="button" className="wtm-btn is-secondary" onClick={addRule} disabled={isLoading}>
							<span className="dashicons dashicons-plus"></span>
							{__('Ajouter une règle', 'woo-total-menu')}
						</button>
						<button type="button" className="wtm-btn is-secondary" onClick={handleTest} disabled={isLoading || rules.length === 0}>
							<span className="dashicons dashicons-search"></span>
							{__('Tester sur la page courante', 'woo-total-menu')}
						</button>
					</div>

					{testResult && (
						<div className={`wtm-conditions__test-result ${testResult.overall_match ? 'is-match' : 'is-no-match'}`}>
							<p>
								<span className={`dashicons dashicons-${testResult.overall_match ? 'yes-alt' : 'warning'}`}></span>
								{testResult.overall_match
									? __('Le menu s\'afficherait sur la page courante.', 'woo-total-menu')
									: __('Le menu ne s\'afficherait PAS sur la page courante.', 'woo-total-menu')}
							</p>
							<ul className="wtm-conditions__test-rules">
								{testResult.rules.map((r, i) => (
									<li key={i} className={r.passed ? 'is-pass' : 'is-fail'}>
										<span className={`dashicons dashicons-${r.passed ? 'yes' : 'no'}`}></span>
										<code>{r.type}={r.value}</code>
									</li>
								))}
							</ul>
						</div>
					)}

					{error && (
						<div className="wtm-conditions__error">
							<span className="dashicons dashicons-warning"></span>
							{error}
						</div>
					)}
				</div>

				<div className="wtm-modal__footer">
					<button type="button" className="wtm-btn is-danger is-tertiary" onClick={handleClear} disabled={isLoading || isSaving || rules.length === 0}>
						{__('Effacer toutes les conditions', 'woo-total-menu')}
					</button>
					<div className="wtm-modal__footer-right">
						<button type="button" className="wtm-btn is-secondary" onClick={closeConditions} disabled={isSaving}>
							{__('Annuler', 'woo-total-menu')}
						</button>
						<button type="button" className="wtm-btn" onClick={handleSave} disabled={isLoading || isSaving}>
							<span className="dashicons dashicons-saved"></span>
							{isSaving ? __('Enregistrement…', 'woo-total-menu') : __('Enregistrer', 'woo-total-menu')}
						</button>
					</div>
				</div>
			</div>
		</div>
	);
}
