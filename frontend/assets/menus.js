'use strict';

let allMenus = [];
const form = document.getElementById('filters');
const target = document.getElementById('menus');
const count = document.getElementById('count');

function card(menu) {
  const article = document.createElement('article'); article.className = 'card';
  const firstImage = menu.images?.[0];
  if (firstImage) { const image = document.createElement('img'); image.src = firstImage.chemin_image; image.alt = `Menu ${menu.titre}`; image.loading = 'lazy'; article.append(image); }
  article.append(textElement('p', menu.theme || 'Sans thème', 'eyebrow'), textElement('h2', menu.titre), textElement('p', menu.description || 'Aucune description.'));
  const meta = document.createElement('div'); meta.className = 'meta';
  meta.append(textElement('span', menu.regime || 'Régime non précisé', 'pill'), textElement('span', `${menu.nombre_personnes_min} personnes minimum`, 'pill'), textElement('span', `${menu.stock} commande(s) disponible(s)`, 'pill'));
  const link = textElement('a', 'Voir le détail', 'button secondary'); link.href = `menu.html?id=${encodeURIComponent(menu.menu_id)}`; link.setAttribute('aria-label', `Voir le détail : ${menu.titre}`);
  article.append(meta, textElement('p', Number(menu.prix).toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' }), 'price'), link);
  return article;
}

function render() {
  const values = new FormData(form);
  const min = Number(values.get('prix_min'));
  const max = Number(values.get('prix_max'));
  const people = Number(values.get('personnes'));
  const theme = String(values.get('theme'));
  const regime = String(values.get('regime'));
  const menus = allMenus.filter(menu => {
    const price = Number(menu.prix);
    return (!min || price >= min) && (!max || price <= max) && (!people || Number(menu.nombre_personnes_min) <= people)
      && (!theme || menu.theme === theme) && (!regime || menu.regime === regime);
  });
  target.replaceChildren(...menus.map(card));
  if (!menus.length) target.append(textElement('p', 'Aucun menu ne correspond à ces critères.'));
  count.textContent = `${menus.length} menu(s) affiché(s).`;
}

function addOptions(name, values) {
  const select = form.elements[name];
  for (const value of [...new Set(values.filter(Boolean))].sort((a, b) => a.localeCompare(b, 'fr'))) {
    const option = document.createElement('option'); option.value = value; option.textContent = value; select.append(option);
  }
}

form.addEventListener('input', render);
form.addEventListener('reset', () => setTimeout(render));
(async () => {
  try {
    allMenus = (await apiRequest('menu.php')).filter(menu => Boolean(Number(menu.disponible)));
    addOptions('theme', allMenus.map(menu => menu.theme)); addOptions('regime', allMenus.map(menu => menu.regime)); render();
  } catch (error) { target.replaceChildren(textElement('p', error.message, 'error')); }
})();
