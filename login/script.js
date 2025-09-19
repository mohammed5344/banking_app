// --- Login form submit -> call backend ---
const form = document.getElementById('loginForm');
if (form) {
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const username = document.getElementById('username')?.value || '';
    const password = document.getElementById('password')?.value || '';

    try {
      const res = await fetch('http://localhost:8000/api/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username, password }),
      });

      const data = await res.json();

      if (!data.ok) {
        alert(data.error || 'Login failed');
        return;
      }

      // Store minimal session info (hackathon-simple)
      localStorage.setItem('user', JSON.stringify(data.user));

      // TODO: redirect to your dashboard page if you have one
      // window.location.href = 'dashboard.html';
      alert(`Welcome, ${data.user.first_name}!`);
    } catch (err) {
      console.error(err);
      alert('Network error. Is the backend running on :8000?');
    }
  });
}
