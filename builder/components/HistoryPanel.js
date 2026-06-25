/**
 * HistoryPanel — modal showing the WordPress revisions of the current menu.
 *
 * v1.1.5 — Spec §6.6, §7.6, §9.9.
 *
 * Behavior:
 *   - Lists past revisions (date, author, items count)
 *   - Click a revision to preview it live in the preview iframe
 *     (via setPreviewRevision)
 *   - "Restore this revision" button → restoreRevision(menuId, revId)
 *   - Modal closes on Escape, backdrop click, or Close button
 *   - Auto-loads the revisions list on mount if empty
 *
 * @package WooTotalMenu
 * @since 1.1.5
 */

import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';

import { WTM_STORE_NAME } from '../stores/menu';
import { UI_STORE_NAME } from '../stores/ui';

export default function HistoryPanel() {
	const { closeHistory, setPreviewRevision, setAnnouncement } = useDispatch(UI_STORE_NAME);
	const { loadRevisions, restoreRevision } = useDispatch(WTM_STORE_NAME);

	const isHistoryOpen = useSelect((select) => select(UI_STORE_NAME).isHistoryOpen(), []);
	const menu = useSelect((select) => select(WTM_STORE_NAME).getMenu(), []);
	const revisions = useSelect((select) => select(WTM_STORE_NAME).getRevisions(), []);
	const isLoading = useSelect((select) => select(WTM_STORE_NAME).isLoadingRevisions(), []);
	const isRestoring = useSelect((select) => select(WTM_STORE_NAME).isRestoring(), []);
	const previewRevisionId = useSelect((select) => select(UI_STORE_NAME).getPreviewRevisionId(), []);

	const [confirmRevId, setConfirmRevId] = useState(null);

	// Load revisions when the modal opens (if not already loaded).
	useEffect(() => {
		if (isHistoryOpen && menu?.id && revisions.length === 0 && !isLoading) {
			loadRevisions(menu.id);
		}
	}, [isHistoryOpen, menu?.id]);

	// Close on Escape.
	useEffect(() => {
		if (!isHistoryOpen) return;
		const onKey = (e) => {
			if (e.key === 'Escape') {
				handleClose();
			}
		};
		window.addEventListener('keydown', onKey);
		return () => window.removeEventListener('keydown', onKey);
	}, [isHistoryOpen]);

	// Clear the preview when the modal closes.
	useEffect(() => {
		if (!isHistoryOpen && previewRevisionId !== null) {
			setPreviewRevision(null);
		}
	}, [isHistoryOpen]);

	if (!isHistoryOpen) return null;

	const handleClose = () => {
		setPreviewRevision(null);
		closeHistory();
	};

	const handlePreview = (revisionId) => {
		if (previewRevisionId === revisionId) {
			// Toggle off — back to live.
			setPreviewRevision(null);
			setAnnouncement(__('Aperçu live restauré.', 'woo-total-menu'));
		} else {
			setPreviewRevision(revisionId);
			setAnnouncement(
				sprintf(
					/* translators: %d revision ID */
					__('Aperçu de la révision #%d.', 'woo-total-menu'),
					revisionId
				)
			);
		}
	};

	const handleRestore = (revisionId) => {
		if (!menu?.id) return;
		restoreRevision(menu.id, revisionId).then(() => {
			setConfirmRevId(null);
			setPreviewRevision(null);
			setAnnouncement(
				__('Révision restaurée. L\'historique local a été réinitialisé.', 'woo-total-menu')
			);
			// Close modal after a short delay so the user sees the success state.
			setTimeout(() => {
				closeHistory();
			}, 600);
		});
	};

	const formatAbsolute = (iso) => {
		try {
			const d = new Date(iso);
			return d.toLocaleString(undefined, {
				year: 'numeric',
				month: 'short',
				day: '2-digit',
				hour: '2-digit',
				minute: '2-digit',
			});
		} catch (e) {
			return iso;
		}
	};

	return (
		<div
			className="wtm-history-modal__overlay"
			onClick={(e) => {
				if (e.target === e.currentTarget) handleClose();
			}}
			role="dialog"
			aria-modal="true"
			aria-labelledby="wtm-history-title"
		>
			<div className="wtm-history-modal">
				<div className="wtm-history-modal__header">
					<h2 id="wtm-history-title" className="wtm-history-modal__title">
						<span className="dashicons dashicons-backup"></span>
						{__('Historique des révisions', 'woo-total-menu')}
					</h2>
					<button
						type="button"
						className="wtm-history-modal__close"
						onClick={handleClose}
						aria-label={__('Fermer', 'woo-total-menu')}
					>
						<span className="dashicons dashicons-no-alt"></span>
					</button>
				</div>

				<div className="wtm-history-modal__body">
					<p className="wtm-history-modal__intro">
						{__(
							'Chaque sauvegarde crée une révision WordPress. Cliquez sur une ligne pour prévisualiser, puis « Restaurer » pour revenir à cet état.',
							'woo-total-menu'
						)}
					</p>

					{isLoading && (
						<div className="wtm-history-modal__loading">
							<span className="dashicons dashicons-update spin"></span>
							{__('Chargement des révisions…', 'woo-total-menu')}
						</div>
					)}

					{!isLoading && revisions.length === 0 && (
						<div className="wtm-history-modal__empty">
							<span className="dashicons dashicons-info"></span>
							{__(
								'Aucune révision enregistrée. Sauvegardez votre menu pour créer la première révision.',
								'woo-total-menu'
							)}
						</div>
					)}

					{!isLoading && revisions.length > 0 && (
						<ul className="wtm-history-modal__list">
							{revisions.map((rev) => {
								const isPreviewing = previewRevisionId === rev.id;
								const isConfirming = confirmRevId === rev.id;
								return (
									<li
										key={rev.id}
										className={`wtm-history-item ${isPreviewing ? 'is-previewing' : ''}`}
									>
										<button
											type="button"
											className="wtm-history-item__main"
											onClick={() => handlePreview(rev.id)}
											aria-pressed={isPreviewing}
										>
											<span className="wtm-history-item__avatar">
												{rev.author_avatar ? (
													<img src={rev.author_avatar} alt="" width="32" height="32" />
												) : (
													<span className="dashicons dashicons-admin-users"></span>
												)}
											</span>
											<span className="wtm-history-item__info">
												<span className="wtm-history-item__author">
													{rev.author_name || __('Inconnu', 'woo-total-menu')}
												</span>
												<span className="wtm-history-item__date" title={formatAbsolute(rev.date_modified)}>
													{sprintf(
														/* translators: %s relative time */
														__('il y a %s', 'woo-total-menu'),
														rev.relative_date
													)}
													<span className="wtm-history-item__sep"> · </span>
													{sprintf(
														/* translators: %d items count */
														__('%d items', 'woo-total-menu'),
														rev.items_count
													)}
												</span>
											</span>
											{isPreviewing && (
												<span className="wtm-history-item__badge">
													<span className="dashicons dashicons-visibility"></span>
													{__('Aperçu', 'woo-total-menu')}
												</span>
											)}
										</button>

										{isConfirming ? (
											<div className="wtm-history-item__confirm">
												<span>
													{__('Restaurer cette révision ?', 'woo-total-menu')}
												</span>
												<button
													type="button"
													className="wtm-btn wtm-btn--sm wtm-btn--danger"
													onClick={() => handleRestore(rev.id)}
													disabled={isRestoring}
												>
													{isRestoring
														? __('Restauration…', 'woo-total-menu')
														: __('Confirmer', 'woo-total-menu')}
												</button>
												<button
													type="button"
													className="wtm-btn wtm-btn--sm wtm-btn--secondary"
													onClick={() => setConfirmRevId(null)}
													disabled={isRestoring}
												>
													{__('Annuler', 'woo-total-menu')}
												</button>
											</div>
										) : (
											<button
												type="button"
												className="wtm-btn wtm-btn--sm wtm-btn--secondary wtm-history-item__restore"
												onClick={() => setConfirmRevId(rev.id)}
												disabled={isRestoring}
												title={__('Restaurer cette révision', 'woo-total-menu')}
											>
												<span className="dashicons dashicons-backup"></span>
												{__('Restaurer', 'woo-total-menu')}
											</button>
										)}
									</li>
								);
							})}
						</ul>
					)}
				</div>

				<div className="wtm-history-modal__footer">
					<span className="wtm-history-modal__count">
						{sprintf(
							/* translators: %d revisions count */
							__('%d révision(s) conservée(s)', 'woo-total-menu'),
							revisions.length
						)}
					</span>
					<button
						type="button"
						className="wtm-btn wtm-btn--secondary"
						onClick={handleClose}
					>
						{__('Fermer', 'woo-total-menu')}
					</button>
				</div>
			</div>
		</div>
	);
}
