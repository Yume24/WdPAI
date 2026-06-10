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
