'use strict';

const currentPage = document.body.dataset.page || '';
const navItems = [
  ['index.html', 'Accueil', 'home'],
  ['menus.html', 'Menus', 'menus'],
  ['espace.html', 'Mon espace', 'account'],
  ['contact.html', 'Contact', 'contact'],
];

const header = document.querySelector('[data-site-header]');
if (header) {
  const nav = document.createElement('nav');
  nav.className = 'nav container';
  nav.setAttribute('aria-label', 'Navigation principale');
  const brand = document.createElement('a');
  brand.className = 'brand'; brand.href = 'index.html'; brand.innerHTML = 'Vite <span>&amp;</span> Gourmand';
  const list = document.createElement('ul');
  for (const [href, label, key] of navItems) {
    const item = document.createElement('li');
    const link = document.createElement('a'); link.href = href; link.textContent = label;
    if (key === currentPage) link.setAttribute('aria-current', 'page');
    item.append(link); list.append(item);
  }
  nav.append(brand, list); header.append(nav);
}

const footer = document.querySelector('[data-site-footer]');
if (footer) {
  footer.innerHTML = `
    <div class="container footer-grid">
      <div><h2>Vite &amp; Gourmand</h2><p>Traiteur événementiel à Bordeaux depuis 25 ans. Des menus de saison, préparés avec soin pour vos moments à partager.</p></div>
      <div><h2>Horaires</h2><ul class="hours" data-hours><li>Chargement…</li></ul></div>
      <div><h2>Informations</h2><p><a href="contact.html">Nous contacter</a><br><a href="mentions-legales.html">Mentions légales</a><br><a href="cgv.html">Conditions générales de vente</a></p></div>
    </div>
    <div class="container copyright">© 2026 Vite &amp; Gourmand — Projet pédagogique Studi.</div>`;
}
