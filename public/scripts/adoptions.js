document.querySelectorAll('.js-adoption-action').forEach(form => {
  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    const action = this.dataset.action;
    const row = this.closest('tr');
    try {
      await fetchJson(this.action, {
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
