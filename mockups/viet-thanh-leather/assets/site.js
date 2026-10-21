/** Shared navigation and subtle reveal motion for the Việt Thành concept site. */
const menuButton = document.querySelector('.menu-button');
const nav = document.querySelector('.nav');
const collectionItem = document.querySelector('.nav-item');
const collectionButton = document.querySelector('.collection-trigger');

if (menuButton && nav && collectionItem && collectionButton) {
  menuButton.addEventListener('click', () => {
    const expanded = menuButton.getAttribute('aria-expanded') === 'true';
    menuButton.setAttribute('aria-expanded', String(!expanded));
    nav.classList.toggle('open', !expanded);
  });
  collectionButton.addEventListener('click', () => {
    const expanded = collectionButton.getAttribute('aria-expanded') === 'true';
    collectionButton.setAttribute('aria-expanded', String(!expanded));
    collectionItem.classList.toggle('open', !expanded);
  });
  nav.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => {
    nav.classList.remove('open');
    collectionItem.classList.remove('open');
    menuButton.setAttribute('aria-expanded', 'false');
    collectionButton.setAttribute('aria-expanded', 'false');
  }));
}

if ('IntersectionObserver' in window) {
  const observer = new IntersectionObserver((entries) => entries.forEach((entry) => {
    if (entry.isIntersecting) {
      entry.target.classList.add('visible');
      observer.unobserve(entry.target);
    }
  }), { threshold: 0.12 });
  document.querySelectorAll('.reveal').forEach((element) => observer.observe(element));
} else {
  document.querySelectorAll('.reveal').forEach((element) => element.classList.add('visible'));
}
