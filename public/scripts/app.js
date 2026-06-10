function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

async function fetchJson(url, opts = {}) {
  const headers = Object.assign({
    'Accept': 'application/json',
    'X-CSRF-Token': csrfToken(),
    'X-Requested-With': 'XMLHttpRequest',
  }, opts.headers || {});
  const response = await fetch(url, Object.assign({}, opts, { headers, credentials: 'same-origin' }));
  let payload = null;
  try { payload = await response.json(); } catch (_) { /* non-JSON */ }
  if (!response.ok) {
    const msg = (payload && payload.error) || response.statusText || 'Request failed';
    const err = new Error(msg);
    err.status = response.status;
    throw err;
  }
  return payload || {};
}

function toast(message, type = 'success') {
  let host = document.getElementById('toastHost');
  if (!host) {
    host = document.createElement('div');
    host.id = 'toastHost';
    host.className = 'toast-host';
    document.body.appendChild(host);
  }
  const el = document.createElement('div');
  el.className = `toast toast-${type}`;
  el.textContent = message;
  host.appendChild(el);
  setTimeout(() => { el.classList.add('toast-out'); }, 2400);
  setTimeout(() => { el.remove(); }, 3000);
}

const sidebarToggle  = document.getElementById('sidebarToggle');
const adminSidebar   = document.getElementById('adminSidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');

function openSidebar() {
  adminSidebar?.classList.add('open');
  sidebarOverlay?.classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeSidebar() {
  adminSidebar?.classList.remove('open');
  sidebarOverlay?.classList.remove('open');
  document.body.style.overflow = '';
}

sidebarToggle?.addEventListener('click', function () {
  adminSidebar?.classList.contains('open') ? closeSidebar() : openSidebar();
});
sidebarOverlay?.addEventListener('click', closeSidebar);
document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeSidebar(); });

const filterToggle = document.getElementById('filterToggle');
const filterPanel  = document.getElementById('filterPanel');
const filterClose  = document.getElementById('filterClose');
filterToggle?.addEventListener('click', () => filterPanel?.classList.toggle('open'));
filterClose?.addEventListener('click',  () => filterPanel?.classList.remove('open'));

const currentPath = window.location.pathname.replace(/\/$/, '') || '/';
document.querySelectorAll('.sidebar-link').forEach(function (link) {
  const href = (link.getAttribute('href') || '').replace(/\/$/, '');
  if (href && currentPath === href) link.classList.add('active');
});
