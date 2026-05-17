document.addEventListener('DOMContentLoaded', function () {

  /* ── Admin sidebar toggle (mobile) ── */
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

  /* ── Filter panel toggle (animals page, mobile) ── */
  const filterToggle = document.getElementById('filterToggle');
  const filterPanel  = document.getElementById('filterPanel');
  const filterClose  = document.getElementById('filterClose');
  filterToggle?.addEventListener('click', () => filterPanel?.classList.toggle('open'));
  filterClose?.addEventListener('click',  () => filterPanel?.classList.remove('open'));

  /* ── Active nav link highlight (JS fallback) ── */
  const currentPath = window.location.pathname.replace(/\/$/, '') || '/';
  document.querySelectorAll('.sidebar-link').forEach(function (link) {
    const href = (link.getAttribute('href') || '').replace(/\/$/, '');
    if (href && currentPath === href) link.classList.add('active');
  });

  /* ───────── Fetch helpers ───────── */
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

  /* ───────── Live filter on /animals ───────── */
  const grid       = document.getElementById('animalsGrid');
  const filterForm = document.getElementById('animalsFilter');
  const searchForm = document.getElementById('animalsSearch');
  const countEl    = document.getElementById('animalCount');

  function buildAnimalsQuery() {
    const params = new URLSearchParams();
    if (filterForm) {
      const fd = new FormData(filterForm);
      for (const [k, v] of fd.entries()) if (v) params.set(k, v);
    }
    if (searchForm) {
      const fd = new FormData(searchForm);
      const q = fd.get('q');
      if (q) params.set('q', q);
    }
    return params.toString();
  }

  function badgeFor(status) {
    if (status === 'available')    return 'badge-available';
    if (status === 'pending')      return 'badge-pending';
    if (status === 'adopted')      return 'badge-adopted';
    if (status === 'medical_hold') return 'badge-rejected';
    return 'badge-pending';
  }

  function renderAnimals(animals) {
    if (!grid) return;
    if (!animals.length) {
      grid.innerHTML = '<p class="text-muted">No animals match your filters.</p>';
    } else {
      grid.innerHTML = animals.map(a => `
        <a href="/animal?id=${a.id}" class="card animal-card">
          <div class="animal-card-photo">
            ${a.photo_path
              ? `<img src="/public/${a.photo_path}" alt="${a.name}" style="width:100%;height:100%;object-fit:cover">`
              : `<div class="animal-card-photo-placeholder"><i class="fa-solid ${a.species_icon || 'fa-paw'}"></i></div>`}
            <span class="animal-card-badge badge ${badgeFor(a.status)}">${a.status_label}</span>
          </div>
          <div class="animal-card-body">
            <h3 class="animal-card-name">${a.name}</h3>
            <p class="animal-card-meta">${a.breed || '—'} · ${a.gender}</p>
            <div class="animal-card-footer">
              <span class="text-xs text-muted"><i class="fa-solid ${a.species_icon || 'fa-paw'}"></i> ${a.species}</span>
              <span class="btn btn-outline btn-sm">View profile</span>
            </div>
          </div>
        </a>`).join('');
    }
    if (countEl) countEl.textContent = animals.length;
  }

  let filterDebounce = null;
  function refreshAnimals() {
    if (!grid) return;
    const qs = buildAnimalsQuery();
    fetchJson('/api/animals?' + qs)
      .then(payload => renderAnimals(payload.animals || []))
      .catch(err => toast(err.message, 'error'));
  }

  if (filterForm) {
    filterForm.addEventListener('change', () => {
      // Mirror radio choices into the search form so a manual GET also works.
      ['species_id','status','gender'].forEach(name => {
        const v = filterForm.querySelector(`input[name="${name}"]:checked`)?.value || '';
        const hidden = searchForm?.querySelector(`input[name="${name}"]`);
        if (hidden) hidden.value = v;
      });
      refreshAnimals();
    });
    filterForm.addEventListener('submit', e => { e.preventDefault(); refreshAnimals(); });
  }
  if (searchForm) {
    searchForm.addEventListener('submit', e => { e.preventDefault(); refreshAnimals(); });
    searchForm.querySelector('input[name="q"]')?.addEventListener('input', () => {
      clearTimeout(filterDebounce);
      filterDebounce = setTimeout(refreshAnimals, 300);
    });
  }

  /* ───────── Approve / reject adoption requests ───────── */
  document.querySelectorAll('.js-adoption-action').forEach(form => {
    form.addEventListener('submit', async function (e) {
      e.preventDefault();
      const action = this.dataset.action;
      const row = this.closest('tr');
      try {
        const data = await fetchJson(this.action, {
          method: 'POST',
          body: new FormData(this),
        });
        if (row) {
          const badge = row.querySelector('.js-status-badge');
          if (badge) {
            badge.className = 'badge js-status-badge ' + (action === 'approve' ? 'badge-adopted' : 'badge-rejected');
            badge.textContent = action === 'approve' ? 'Approved' : 'Rejected';
          }
          row.querySelector('.table-actions').innerHTML = '<span class="text-muted text-xs">Decided</span>';
        }
        toast('Request ' + (action === 'approve' ? 'approved' : 'rejected') + '.', 'success');
      } catch (err) {
        toast(err.message, 'error');
      }
    });
  });

  /* ───────── Volunteer shift sign-up / drop ───────── */
  function bindShiftForms(selector, label, onSuccessUpdate) {
    document.querySelectorAll(selector).forEach(form => {
      form.addEventListener('submit', async function (e) {
        e.preventDefault();
        try {
          await fetchJson(this.action, { method: 'POST', body: new FormData(this) });
          toast(label, 'success');
          onSuccessUpdate(this);
        } catch (err) {
          toast(err.message, 'error');
        }
      });
    });
  }

  bindShiftForms('.js-shift-signup', 'Signed up.', form => {
    const btn = form.querySelector('button');
    if (btn) {
      btn.disabled = true;
      btn.textContent = 'Joined';
      btn.classList.remove('btn-primary');
      btn.classList.add('btn-ghost');
    }
  });
  bindShiftForms('.js-shift-drop', 'Removed from shift.', form => {
    const item = form.closest('.activity-item');
    if (item) item.remove();
  });

});
