'use strict';

const reviewsTarget = document.getElementById('reviews'); const reviewsMessage = document.getElementById('message'); let staffReviewToken = '';
async function moderate(id, status) {
  await apiRequest('avis_employe.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': staffReviewToken }, body: JSON.stringify({ avis_id: Number(id), statut: status }) });
  await loadReviews(); reviewsMessage.textContent = `Avis ${status}.`;
}
async function loadReviews() {
  const data = await apiRequest('avis_employe.php'); reviewsTarget.replaceChildren();
  if (!data.avis.length) { reviewsTarget.append(textElement('p', 'Aucun avis à modérer.')); return; }
  for (const review of data.avis) {
    const card = document.createElement('article'); card.className = 'card';
    card.append(textElement('p', `${'★'.repeat(Number(review.note))}${'☆'.repeat(5 - Number(review.note))}`, 'stars'), textElement('h2', `${review.prenom} ${review.nom}`), textElement('p', review.commentaire), textElement('p', `Commande n°${review.commande_id} — ${review.statut}`, 'muted'));
    if (review.statut === 'en attente') {
      const actions = document.createElement('div'); actions.className = 'actions';
      const accept = textElement('button', 'Valider'); const refuse = textElement('button', 'Refuser'); refuse.className = 'secondary';
      accept.addEventListener('click', () => moderate(review.avis_id, 'validé').catch(error => { reviewsMessage.textContent = error.message; reviewsMessage.className = 'error'; }));
      refuse.addEventListener('click', () => moderate(review.avis_id, 'refusé').catch(error => { reviewsMessage.textContent = error.message; reviewsMessage.className = 'error'; })); actions.append(accept, refuse); card.append(actions);
    }
    reviewsTarget.append(card);
  }
}
(async () => { try { const session = await apiRequest('session_courante.php'); if (!['employe','administrateur'].includes(session.utilisateur?.role)) throw new Error('Accès réservé au personnel.'); staffReviewToken = session.csrf_token; await loadReviews(); } catch (error) { reviewsTarget.replaceChildren(textElement('p', error.message, 'error')); } })();
