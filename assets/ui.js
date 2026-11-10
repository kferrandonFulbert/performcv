document.addEventListener('click', (event) => {
    if (event.target.closest('[data-print]')) window.print();
});
document.addEventListener('submit', (event) => {
    const message = event.target.dataset.confirm;
    if (message && !window.confirm(message)) event.preventDefault();
});
