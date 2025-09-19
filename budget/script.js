const cards = document.querySelectorAll('.card');

cards.forEach(card => {
  const header = card.querySelector('h3');
  
  // Toggle only when clicking the header
  header.addEventListener('click', () => {
    card.classList.toggle('active');
  });
});
