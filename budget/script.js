const cards = document.querySelectorAll('.card');

cards.forEach(card => {
  const header = card.querySelector('h3'); // only header toggles
  header.addEventListener('click', () => {
    card.classList.toggle('active');
  });
});