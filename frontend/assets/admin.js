'use strict';

const adminMessage = document.getElementById('message');
const adminLogin = document.getElementById('login');
const adminDashboard = document.getElementById('dashboard');
const employeesTarget = document.getElementById('employees');
const statsForm = document.getElementById('stats-form');
let adminToken = '';

function adminNotify(text, error = false) { adminMessage.textContent = text; adminMessage.className = error ? 'error' : ''; }

async function loadEmployees() {
  const data = await apiRequest('employes_admin.php'); employeesTarget.replaceChildren();
  for (const employee of data.employes) {
    const card = document.createElement('article'); card.className = 'card';
    card.append(textElement('h2', `${employee.prenom} ${employee.nom}`), textElement('p', employee.email), textElement('p', employee.actif ? 'Compte actif' : 'Compte désactivé', 'muted'));
    const button = textElement('button', employee.actif ? 'Désactiver' : 'Activer');
    button.addEventListener('click', async () => {
      button.disabled = true;
      try { await apiRequest('employes_admin.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': adminToken }, body: JSON.stringify({ action: 'activer', utilisateur_id: Number(employee.utilisateur_id), actif: !Number(employee.actif) }) }); await loadEmployees(); adminNotify('État du compte mis à jour.'); }
      catch (error) { adminNotify(error.message, true); button.disabled = false; }
    });
    card.append(button); employeesTarget.append(card);
  }
  if (!data.employes.length) employeesTarget.append(textElement('p', 'Aucun compte employé.'));
}

async function prepareStatsFilters() {
  const menus = await apiRequest('menu.php'); const select = statsForm.elements.menu_id;
  for (const menu of menus) { const option = document.createElement('option'); option.value = String(menu.menu_id); option.textContent = menu.titre; select.append(option); }
}

async function loadStats() {
  const params = new URLSearchParams(); const values = new FormData(statsForm);
  for (const name of ['date_debut', 'date_fin', 'menu_id']) if (values.get(name)) params.set(name, values.get(name));
  const data = await apiRequest('statistiques_admin.php' + (params.size ? `?${params}` : ''));
  document.getElementById('stats-summary').textContent = `${data.nombre_commandes} commande(s) · ${Number(data.chiffre_affaires).toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' })} de chiffre d’affaires`;
  const chart = document.getElementById('stats-chart'); chart.replaceChildren();
  const max = Math.max(1, ...data.par_menu.map(row => Number(row.nombre_commandes)));
  for (const row of data.par_menu) {
    const line = document.createElement('div'); line.className = 'stats-row';
    const bar = document.createElement('div'); bar.className = 'stats-bar'; bar.setAttribute('aria-hidden', 'true');
    const fill = document.createElement('span'); fill.style.width = `${Math.round(Number(row.nombre_commandes) / max * 100)}%`; bar.append(fill);
    line.append(textElement('span', row.titre), bar, textElement('strong', String(row.nombre_commandes))); chart.append(line);
  }
}

async function enterAdmin(data) {
  if (data.utilisateur?.role !== 'administrateur') throw new Error('Accès administrateur requis.');
  adminToken = data.csrf_token; adminLogin.hidden = true; adminDashboard.hidden = false;
  if (statsForm.elements.menu_id.options.length === 1) await prepareStatsFilters();
  await loadEmployees();
  try { await loadStats(); } catch (error) { document.getElementById('stats-summary').textContent = error.message; }
}

document.getElementById('login-form').addEventListener('submit', async event => {
  event.preventDefault(); const values = new FormData(event.currentTarget);
  try { const data = await apiRequest('connexion.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ email: values.get('email'), mot_de_passe: values.get('mot_de_passe') }) }); await enterAdmin(data); adminNotify('Connexion réussie.'); }
  catch (error) { adminNotify(error.message, true); }
});

document.getElementById('employee-form').addEventListener('submit', async event => {
  event.preventDefault(); const form = event.currentTarget; const values = new FormData(form); const button = form.querySelector('button'); button.disabled = true;
  try { await apiRequest('employes_admin.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': adminToken }, body: JSON.stringify({ action: 'creer', ...Object.fromEntries(values.entries()) }) }); form.reset(); await loadEmployees(); adminNotify('Compte employé créé.'); }
  catch (error) { adminNotify(error.message, true); } finally { button.disabled = false; }
});

statsForm.addEventListener('submit', async event => { event.preventDefault(); try { await loadStats(); adminNotify('Statistiques mises à jour.'); } catch (error) { adminNotify(error.message, true); } });
document.getElementById('reset-stats').addEventListener('click', async () => { statsForm.reset(); try { await loadStats(); adminNotify('Filtres réinitialisés.'); } catch (error) { adminNotify(error.message, true); } });

(async () => {
  try { await enterAdmin(await apiRequest('session_courante.php')); }
  catch (error) { adminLogin.hidden = false; adminDashboard.hidden = true; if (error.status !== 401) adminNotify(error.message, true); }
})();
