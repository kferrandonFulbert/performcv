document.addEventListener('click', (event) => {
    if (event.target.closest('[data-print]')) window.print();
});
document.addEventListener('submit', (event) => {
    const message = event.target.dataset.confirm;
    if (message && !window.confirm(message)) event.preventDefault();
});

// Preview the submitted form without saving it; keep the ordinary POST fallback.
document.querySelectorAll('[data-cv-editor]').forEach((form) => {
    const status = document.querySelector('[data-preview-status]');
    const target = document.querySelector('[data-preview-target]');
    const select = form.querySelector('[name="cv[templateId]"]');
    let timer;
    let pending;
    let generation = 0;
    const preview = async () => {
        const current = ++generation;
        pending?.abort();
        pending = new AbortController();
        const body = new FormData(form);
        body.set('_preview', '1');
        status.textContent = 'Actualisation de votre aperçu…';
        try {
            const response = await fetch(form.action || window.location.href, {method: 'POST', body, signal: pending.signal, headers: {'X-Requested-With': 'XMLHttpRequest'}});
            if (current !== generation) return;
            if (!response.ok || response.redirected) throw new Error('preview');
            const html = await response.text();
            if (current !== generation) return;
            // HTML is rendered by the authenticated same-origin Twig endpoint.
            target.innerHTML = html;
            status.textContent = 'Aperçu à jour. Aucun changement enregistré.';
        } catch (error) {
            if (error.name !== 'AbortError' && current === generation) status.textContent = 'Aperçu indisponible. Utilisez le bouton Prévisualiser pour réessayer.';
        }
    };
    const syncCards = () => {
        form.querySelectorAll('[data-template-id]').forEach((card) => {
            const selected = card.dataset.templateId === select.value;
            card.setAttribute('aria-pressed', String(selected));
            if (selected) form.querySelector('[data-save-cv]').disabled = card.dataset.templateOwned !== 'true';
        });
        document.querySelectorAll('[data-template-purchase]').forEach((purchase) => {
            purchase.hidden = purchase.dataset.templatePurchase !== select.value;
        });
    };
    form.querySelectorAll('[data-template-id]').forEach((card) => card.addEventListener('click', () => {
        select.value = card.dataset.templateId;
        select.dispatchEvent(new Event('change', {bubbles: true}));
    }));
    form.addEventListener('change', () => {
        syncCards();
        clearTimeout(timer);
        timer = setTimeout(preview, 200);
    });
    form.addEventListener('submit', (event) => {
        if (event.submitter?.name === '_preview') {
            event.preventDefault();
            clearTimeout(timer);
            preview();
        }
    });
    syncCards();
});
