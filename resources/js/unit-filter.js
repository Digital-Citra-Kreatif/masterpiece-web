// Filter unit
for (const host of document.querySelectorAll('[data-unit-filter]')) {
  const buttons = [...host.querySelectorAll('[data-filter]')];
  const dots = [...host.querySelectorAll('.units__dots [data-status]')];

  const show = (status) => {
    host.classList.toggle('is-filtered', status !== null);
    for (const button of buttons) {
      button.setAttribute('aria-pressed', String(button.dataset.filter === status));
    }
    for (const dot of dots) {
      dot.hidden = status !== null && dot.dataset.status !== status;
    }
  };

  for (const button of buttons) {
    button.addEventListener('click', () => show(button.getAttribute('aria-pressed') === 'true' ? null : button.dataset.filter));
  }
}
