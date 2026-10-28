/* Light by default. Dark only when the visitor picks it; the choice is remembered in this browser. */
(function () {
  var root = document.documentElement;
  function current() { return root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light'; }
  function paint() {
    var dark = current() === 'dark';
    document.querySelectorAll('[data-theme-toggle]').forEach(function (b) {
      b.textContent = dark ? '☀️' : '🌙';
      b.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
      b.setAttribute('title', dark ? 'Light mode' : 'Dark mode');
      b.setAttribute('aria-pressed', dark ? 'true' : 'false');
    });
  }
  document.addEventListener('click', function (e) {
    if (!e.target.closest('[data-theme-toggle]')) return;
    var next = current() === 'dark' ? 'light' : 'dark';
    if (next === 'dark') root.setAttribute('data-theme', 'dark'); else root.removeAttribute('data-theme');
    try { localStorage.setItem('theme', next); } catch (err) {}
    paint();
  });
  paint();
})();
