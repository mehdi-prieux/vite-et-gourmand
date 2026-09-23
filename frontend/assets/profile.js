'use strict';

const profileForm = document.getElementById('profile-form');
const profileMessage = document.getElementById('message');
let profileToken = '';
function profileNotify(text, error = false) { profileMessage.textContent = text; profileMessage.className = error ? 'error' : ''; }

(async () => {
  try {
    const session = await apiRequest('session_courante.php');
    if (session.utilisateur?.role !== 'utilisateur') throw new Error('Connexion client requise.');
    profileToken = session.csrf_token;
    const data = await apiRequest('profil.php');
    for (const [name, value] of Object.entries(data.profil)) if (profileForm.elements[name]) profileForm.elements[name].value = value || '';
    profileForm.hidden = false; profileNotify('Vous pouvez mettre à jour vos coordonnées.');
  } catch (error) { profileNotify(error.message, true); }
})();

profileForm.addEventListener('submit', async event => {
  event.preventDefault(); const button = profileForm.querySelector('button'); button.disabled = true;
  const values = new FormData(profileForm); const payload = Object.fromEntries(values.entries());
  try {
    const data = await apiRequest('profil.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': profileToken }, body: JSON.stringify(payload) });
    profileNotify(data.message);
  } catch (error) { profileNotify(error.message, true); }
  finally { button.disabled = false; }
});
