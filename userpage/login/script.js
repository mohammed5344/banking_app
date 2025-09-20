const form = document.getElementById('loginForm');
if (form) {
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const username = document.getElementById('username')?.value?.trim() || '';
    const password = document.getElementById('password')?.value || '';

    try {
      const res = await fetch('login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username, password })
      });
      const data = await res.json();

      if (!data.ok) {
        const msg = data.error || 'Login failed';
        if (typeof data.remaining === 'number') {
          alert(`${msg}. Attempts left: ${data.remaining}`);
        } else {
          alert(msg);
        }
        return;
      }

      localStorage.setItem('user', JSON.stringify(data.user));

      window.location.href = data.redirect || '/dashboard/dashboard.html';
    } catch (err) {
      console.error(err);
      alert('Network error. Is PHP running and is login.php reachable?');
    }
  });
}
