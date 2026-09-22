'use strict';

const managementMessage = document.getElementById('message');
const managementContent = document.getElementById('content');
const menuForm = document.getElementById('menu-form');
const dishForm = document.getElementById('dish-form');
const hoursForm = document.getElementById('hours-form');
let managementToken = '';
let currentDishes = [];
let currentAllergens = [];

function managementNotify(text, error = false) {
  managementMessage.textContent = text;
  managementMessage.className = error ? 'error' : '';
}

async function postCatalog(payload) {
  return apiRequest('catalogue_employe.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': managementToken },
    body: JSON.stringify(payload)
  });
}

async function postHours(payload) {
  return apiRequest('horaires_employe.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': managementToken },
    body: JSON.stringify(payload)
  });
}

function actionButton(label, callback, className = 'button secondary') {
  const button = textElement('button', label, className);
  button.type = 'button';
  button.addEventListener('click', callback);
  return button;
}

function renderChoices(target, items, name, idKey, labelKey) {
  target.replaceChildren();
  if (!items.length) { target.append(textElement('p', 'Aucun élément disponible.')); return; }
  for (const item of items) {
    const label = document.createElement('label');
    const input = document.createElement('input'); input.type = 'checkbox'; input.name = name; input.value = String(item[idKey]);
    label.append(input, document.createTextNode(` ${item[labelKey]}`)); target.append(label);
  }
}

function resetMenuForm() {
  menuForm.reset();
  menuForm.elements.menu_id.value = '';
  menuForm.elements.disponible.checked = true;
  document.getElementById('menu-form-title').textContent = 'Ajouter un menu';
  document.getElementById('cancel-menu-edit').hidden = true;
}

function resetDishForm() {
  dishForm.reset();
  dishForm.elements.plat_id.value = '';
  document.getElementById('dish-form-title').textContent = 'Ajouter un plat';
  document.getElementById('cancel-dish-edit').hidden = true;
}

function resetHoursForm() {
  hoursForm.reset();
  hoursForm.elements.horaire_id.value = '';
  document.getElementById('hours-form-title').textContent = 'Ajouter un horaire';
  document.getElementById('cancel-hours-edit').hidden = true;
}

async function reloadManagement() {
  const [menus, dishes, hours, allergensData] = await Promise.all([apiRequest('menu.php'), apiRequest('plats.php'), apiRequest('horaires.php'), apiRequest('allergenes.php')]);
  currentDishes = dishes;
  currentAllergens = allergensData.allergenes || [];
  renderChoices(document.getElementById('menu-dishes'), currentDishes, 'menu_plats', 'plat_id', 'nom');
  renderChoices(document.getElementById('dish-allergens'), currentAllergens, 'dish_allergenes', 'allergene_id', 'nom');
  const menusTarget = document.getElementById('menus');
  menusTarget.replaceChildren();
  for (const menu of menus) {
    const card = document.createElement('article'); card.className = 'card';
    card.append(textElement('h3', menu.titre), textElement('p', `${menu.prix} € · stock ${menu.stock} · ${menu.disponible ? 'disponible' : 'indisponible'}`));
    const edit = actionButton('Modifier', () => {
      for (const name of ['menu_id', 'titre', 'description', 'theme', 'regime', 'nombre_personnes_min', 'prix', 'conditions_menu', 'stock']) menuForm.elements[name].value = menu[name] ?? '';
      menuForm.elements.disponible.checked = Boolean(Number(menu.disponible));
      const selectedDishes = new Set((menu.plats || []).map(dish => String(dish.plat_id)));
      for (const input of menuForm.querySelectorAll('[name="menu_plats"]')) input.checked = selectedDishes.has(input.value);
      menuForm.elements.images.value = (menu.images || []).map(image => image.chemin_image).join('\n');
      document.getElementById('menu-form-title').textContent = `Modifier « ${menu.titre} »`;
      document.getElementById('cancel-menu-edit').hidden = false;
      menuForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    const remove = actionButton('Supprimer', async () => {
      if (!confirm(`Supprimer « ${menu.titre} » ?`)) return;
      try { await postCatalog({ action: 'supprimer_menu', menu_id: Number(menu.menu_id) }); await reloadManagement(); managementNotify('Menu supprimé.'); }
      catch (error) { managementNotify(error.message, true); }
    });
    card.append(edit, remove); menusTarget.append(card);
  }

  const dishesTarget = document.getElementById('dishes');
  dishesTarget.replaceChildren();
  for (const dish of dishes) {
    const card = document.createElement('article'); card.className = 'card';
    card.append(textElement('h3', dish.nom), textElement('p', dish.type_plat));
    const edit = actionButton('Modifier', () => {
      for (const name of ['plat_id', 'nom', 'type_plat', 'description']) dishForm.elements[name].value = dish[name] ?? '';
      const selectedAllergens = new Set((dish.allergenes || []).map(allergen => String(allergen.allergene_id)));
      for (const input of dishForm.querySelectorAll('[name="dish_allergenes"]')) input.checked = selectedAllergens.has(input.value);
      document.getElementById('dish-form-title').textContent = `Modifier « ${dish.nom} »`;
      document.getElementById('cancel-dish-edit').hidden = false;
      dishForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    const remove = actionButton('Supprimer', async () => {
      if (!confirm(`Supprimer « ${dish.nom} » ?`)) return;
      try { await postCatalog({ action: 'supprimer_plat', plat_id: Number(dish.plat_id) }); await reloadManagement(); managementNotify('Plat supprimé.'); }
      catch (error) { managementNotify(error.message, true); }
    });
    card.append(edit, remove); dishesTarget.append(card);
  }

  const hoursTarget = document.getElementById('hours');
  hoursTarget.replaceChildren();
  for (const hour of hours.horaires) {
    const card = document.createElement('article'); card.className = 'card';
    card.append(textElement('h3', hour.jour), textElement('p', `${hour.heure_ouverture}–${hour.heure_fermeture}`));
    const edit = actionButton('Modifier', () => {
      for (const name of ['horaire_id', 'jour', 'heure_ouverture', 'heure_fermeture']) hoursForm.elements[name].value = hour[name] ?? '';
      document.getElementById('hours-form-title').textContent = `Modifier ${hour.jour}`;
      document.getElementById('cancel-hours-edit').hidden = false;
      hoursForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    const remove = actionButton('Supprimer', async () => {
      if (!confirm(`Supprimer l’horaire « ${hour.jour} » ?`)) return;
      try { await postHours({ action: 'supprimer', horaire_id: Number(hour.horaire_id) }); await reloadManagement(); managementNotify('Horaire supprimé.'); }
      catch (error) { managementNotify(error.message, true); }
    });
    card.append(edit, remove); hoursTarget.append(card);
  }
}

menuForm.addEventListener('submit', async event => {
  event.preventDefault(); const form = event.currentTarget; const values = new FormData(form); const id = values.get('menu_id');
  try {
    const result = await postCatalog({ action: 'sauvegarder_menu', ...(id ? { menu_id: Number(id) } : {}), titre: values.get('titre'), description: values.get('description'), theme: values.get('theme'), regime: values.get('regime'), nombre_personnes_min: Number(values.get('nombre_personnes_min')), prix: Number(values.get('prix')), conditions_menu: values.get('conditions_menu'), stock: Number(values.get('stock')), disponible: values.get('disponible') === 'on' });
    const menuId = Number(result.menu_id);
    const dishIds = values.getAll('menu_plats').map(Number);
    const paths = String(values.get('images') || '').split(/\r?\n/).map(value => value.trim()).filter(Boolean);
    await postCatalog({ action: 'associer_plats', menu_id: menuId, plat_ids: dishIds });
    await postCatalog({ action: 'sauvegarder_images', menu_id: menuId, chemins: paths });
    resetMenuForm(); await reloadManagement(); managementNotify(id ? 'Menu modifié.' : 'Menu créé.');
  } catch (error) { managementNotify(error.message, true); }
});

dishForm.addEventListener('submit', async event => {
  event.preventDefault(); const form = event.currentTarget; const values = Object.fromEntries(new FormData(form).entries()); const id = values.plat_id; delete values.plat_id;
  const allergenIds = new FormData(form).getAll('dish_allergenes').map(Number);
  delete values.dish_allergenes;
  try { await postCatalog({ action: 'sauvegarder_plat', ...(id ? { plat_id: Number(id) } : {}), ...values, allergene_ids: allergenIds }); resetDishForm(); await reloadManagement(); managementNotify(id ? 'Plat modifié.' : 'Plat créé.'); }
  catch (error) { managementNotify(error.message, true); }
});

hoursForm.addEventListener('submit', async event => {
  event.preventDefault(); const form = event.currentTarget; const values = Object.fromEntries(new FormData(form).entries()); const id = values.horaire_id; delete values.horaire_id;
  try { await postHours({ ...(id ? { horaire_id: Number(id) } : {}), ...values }); resetHoursForm(); await reloadManagement(); managementNotify(id ? 'Horaire modifié.' : 'Horaire créé.'); }
  catch (error) { managementNotify(error.message, true); }
});

document.getElementById('cancel-menu-edit').addEventListener('click', resetMenuForm);
document.getElementById('cancel-dish-edit').addEventListener('click', resetDishForm);
document.getElementById('cancel-hours-edit').addEventListener('click', resetHoursForm);

(async () => {
  try {
    const session = await apiRequest('session_courante.php');
    if (!['employe', 'administrateur'].includes(session.utilisateur?.role)) throw new Error('Accès réservé au personnel.');
    managementToken = session.csrf_token; managementContent.hidden = false; await reloadManagement();
  } catch (error) { managementNotify(error.message, true); }
})();
