'use strict';

const contactForm = document.getElementById('contact-form');
const contactMessage = document.getElementById('message');
contactForm.addEventListener('submit', async event => {
  event.preventDefault();
  const button = contactForm.querySelector('button'); button.disabled = true;
  const values = new FormData(contactForm);
  try {
    const data = await apiRequest('contact.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: values.get('email'), titre: values.get('titre'), description: values.get('description') })
    });
    contactForm.reset(); contactMessage.className = ''; contactMessage.textContent = data.message;
  } catch (error) { contactMessage.className = 'error'; contactMessage.textContent = error.message; }
  finally { button.disabled = false; }
});
