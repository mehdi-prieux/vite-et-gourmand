'use strict';

const target = document.getElementById('menu-detail');
const menuId = new URLSearchParams(location.search).get('id');

function paragraph(parent, label, value) {
  const row = document.createElement('p'); const strong = textElement('strong', `${label} : `); row.append(strong, document.createTextNode(value || 'Non précisé')); parent.append(row);
}

(async () => {
  if (!/^[1-9]\d*$/.test(menuId || '')) { target.replaceChildren(textElement('p', 'Menu invalide.', 'error')); return; }
  try {
    const menus = await apiRequest('menu.php');
    const menu = menus.find(item => String(item.menu_id) === menuId);
    if (!menu) throw new Error('Menu introuvable.');
    document.title = `${menu.titre} — Vite & Gourmand`;
    const gallery = document.createElement('div'); gallery.className = 'menu-gallery'; gallery.setAttribute('aria-label', `Galerie du menu ${menu.titre}`);
    for (const [index, image] of (menu.images || []).entries()) {
      const picture = document.createElement('img'); picture.src = image.chemin_image; picture.alt = `Présentation du menu ${menu.titre}, image ${index + 1}`; picture.loading = 'lazy'; gallery.append(picture);
    }
    const layout = document.createElement('div'); layout.className = 'menu-layout';
    const main = document.createElement('article'); main.className = 'panel';
    main.append(textElement('p', menu.theme || 'Menu', 'eyebrow'), textElement('h1', menu.titre), textElement('p', menu.description || 'Aucune description.', 'lead'));
    paragraph(main, 'Régime', menu.regime); paragraph(main, 'Minimum', `${menu.nombre_personnes_min} personnes`);
    paragraph(main, 'Prix pour le minimum', Number(menu.prix).toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' }));
    paragraph(main, 'Stock', `${menu.stock} commande(s)`);
    const conditions = document.createElement('div'); conditions.className = 'notice'; conditions.append(textElement('h2', 'Conditions à lire avant de commander'), textElement('p', menu.conditions_menu || 'Aucune condition particulière.')); main.append(conditions);
    const actions = document.createElement('div'); actions.className = 'actions';
    const order = textElement('a', 'Commander ce menu', 'button'); order.href = `client.html?menu_id=${encodeURIComponent(menu.menu_id)}`;
    actions.append(order); main.append(actions);
    const dishes = document.createElement('aside'); dishes.className = 'panel'; dishes.append(textElement('h2', 'Composition du menu'));
    if (!menu.plats.length) dishes.append(textElement('p', 'La composition sera précisée prochainement.', 'muted'));
    for (const dish of menu.plats) {
      const item = document.createElement('section'); item.className = 'dish'; item.append(textElement('p', dish.type_plat || 'Plat', 'eyebrow'), textElement('h3', dish.nom), textElement('p', dish.description || ''));
      const allergens = (dish.allergenes || []).map(value => value.nom).join(', '); paragraph(item, 'Allergènes', allergens || 'Aucun allergène renseigné'); dishes.append(item);
    }
    layout.append(main, dishes); target.replaceChildren(...(gallery.childElementCount ? [gallery] : []), layout);
  } catch (error) { target.replaceChildren(textElement('p', error.message, 'error')); }
})();
