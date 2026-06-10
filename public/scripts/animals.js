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
