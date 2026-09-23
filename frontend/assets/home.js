'use strict';

function menuCard(menu) {
  const article = document.createElement('article'); article.className = 'card';
  article.append(textElement('p', menu.theme || 'Menu', 'eyebrow'), textElement('h3', menu.titre));
  article.append(textElement('p', menu.description || 'Découvrez ce menu imaginé pour vos événements.'));
  const meta = document.createElement('div'); meta.className = 'meta';
  meta.append(textElement('span', menu.regime || 'Non précisé', 'pill'), textElement('span', `${menu.nombre_personnes_min} personnes minimum`, 'pill'));
  article.append(meta, textElement('p', `${Number(menu.prix).toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' })}`, 'price'));
  const link = textElement('a', 'Voir le détail', 'button secondary'); link.href = `menu.html?id=${encodeURIComponent(menu.menu_id)}`; link.setAttribute('aria-label', `Voir le détail : ${menu.titre}`); article.append(link);
  return article;
}

(async () => {
  const menusTarget = document.getElementById('featured-menus');
  const reviewsTarget = document.getElementById('reviews');
  try {
    const menus = await apiRequest('menu.php');
    menusTarget.replaceChildren(...menus.filter(menu => Boolean(Number(menu.disponible))).slice(0, 3).map(menuCard));
    if (!menusTarget.children.length) menusTarget.append(textElement('p', 'Aucun menu disponible actuellement.'));
  } catch (error) { menusTarget.replaceChildren(textElement('p', error.message, 'error')); }
  try {
    const data = await apiRequest('avis_publics.php');
    const reviews = Array.isArray(data.avis) ? data.avis : [];
    reviewsTarget.replaceChildren();
    if (!reviews.length) { reviewsTarget.append(textElement('p', 'Les prochains avis validés seront publiés ici.', 'muted')); return; }
    for (const review of reviews) {
      const article = document.createElement('article'); article.className = 'card';
      article.append(textElement('p', `${'★'.repeat(Number(review.note))}${'☆'.repeat(5 - Number(review.note))}`, 'stars'));
      article.append(textElement('blockquote', `« ${review.commentaire} »`), textElement('p', `${review.prenom} ${String(review.nom).slice(0, 1)}.`, 'muted'));
      reviewsTarget.append(article);
    }
  } catch (error) { reviewsTarget.replaceChildren(textElement('p', error.message, 'error')); }
})();
