// Dashboard live refresh (only runs on pages that have the tables).
(function () {
  const base = window.ATT_BASE || '';
  const presentBody = document.querySelector('#present-tbl tbody');
  const feedBody = document.querySelector('#feed-tbl tbody');
  if (!presentBody && !feedBody) return;

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"]/g, c =>
      ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  }

  async function refresh() {
    try {
      const r = await fetch(base + '/api/live.php', { credentials: 'same-origin' });
      if (!r.ok) return;
      const d = await r.json();

      if (presentBody) {
        presentBody.innerHTML = d.present.length
          ? d.present.map(p => `<tr><td>${esc(p.emp_no)}</td><td>${esc(p.name)}</td>
              <td>${esc(p.check_in)}</td><td>${esc(p.check_out)}</td>
              <td><span class="pill ${p.status === 'late' ? 'warn' : 'ok'}">${esc(p.status)}</span></td></tr>`).join('')
          : '<tr><td colspan="5" class="muted">Nobody checked in yet today.</td></tr>';
      }
      if (feedBody) {
        feedBody.innerHTML = d.feed.length
          ? d.feed.map(f => `<tr><td>${esc(f.time)}</td><td>${esc(f.emp_no)}</td><td>${esc(f.name)}</td></tr>`).join('')
          : '<tr><td colspan="3" class="muted">No scans yet.</td></tr>';
      }
    } catch (e) { /* network hiccup; try again next tick */ }
  }

  refresh();
  setInterval(refresh, 5000);
})();
