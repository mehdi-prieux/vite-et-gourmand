'use strict';

const API_ROOT = '../backend/api/';

async function apiRequest(endpoint, options = {}) {
  const response = await fetch(API_ROOT + endpoint, { cache: 'no-store', credentials: 'same-origin', ...options });
  let data;
  try { data = await response.json(); } catch { throw new Error('Réponse serveur illisible.'); }
  if (!response.ok) {
    const error = new Error(data.erreur || data.error || 'Une erreur est survenue.');
    error.status = response.status;
    throw error;
  }
  return data;
}

function textElement(tag, text, className = '') {
  const element = document.createElement(tag);
  element.textContent = text;
  if (className) element.className = className;
  return element;
}

function renderHours(target, hours) {
  target.replaceChildren();
  const days = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
  for (const day of days) {
    const item = hours.find(value => value.jour?.toLocaleLowerCase('fr') === day.toLocaleLowerCase('fr'));
    const row = document.createElement('li');
    const schedule = item && item.heure_ouverture && item.heure_fermeture
      ? `${item.heure_ouverture}–${item.heure_fermeture}` : 'Fermé';
    row.append(textElement('span', day), textElement('span', schedule));
    target.append(row);
  }
}

async function loadFooterHours() {
  const target = document.querySelector('[data-hours]');
  if (!target) return;
  try {
    const data = await apiRequest('horaires.php');
    renderHours(target, Array.isArray(data.horaires) ? data.horaires : []);
  } catch {
    target.replaceChildren(textElement('li', 'Horaires temporairement indisponibles'));
  }
}

document.addEventListener('DOMContentLoaded', loadFooterHours);
