// A filter form puts every field in the address, chosen or not. This sends only the
// choices that were made, so a filtered page has a short address worth sharing.
document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-tidy-address')) {
        return;
    }

    event.preventDefault();

    const query = new URLSearchParams();

    for (const [name, value] of new FormData(form)) {
        if (value !== '' && value !== form.elements[name]?.dataset.default) {
            query.append(name, value);
        }
    }

    const address = query.toString();

    window.location.assign(form.action + (address ? '?' + address : ''));
});

// Checkout: say what delivery to the chosen county costs as soon as it is chosen.
document.addEventListener('change', (event) => {
    const select = event.target.closest?.('[data-county]');
    const note = document.querySelector('[data-county-note]');

    if (select && note) {
        note.textContent = select.selectedOptions[0]?.dataset.note ?? '';
    }
});

// A form that must not be sent twice lets go of its button once it is on its way.
// The server is safe against a second send as well; this spares the customer the doubt.
document.addEventListener('submit', (event) => {
    const form = event.target;

    if (form instanceof HTMLFormElement && form.hasAttribute('data-once') && !event.defaultPrevented) {
        if (form.dataset.sent) {
            event.preventDefault();

            return;
        }

        form.dataset.sent = 'yes';
        form.querySelector('button')?.setAttribute('aria-busy', 'true');
    }
});

// Payment page: while a prompt is on the customer's phone, ask every few seconds
// whether it has been answered, and load the page afresh the moment it has.
const watch = document.querySelector('[data-payment-watch]');

if (watch) {
    const ask = async () => {
        try {
            const response = await fetch(watch.dataset.paymentWatch, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            const state = await response.json();

            if (state.order !== 'pending_payment' || state.payment !== 'pending') {
                window.location.reload();

                return;
            }
        } catch {
            // A dropped connection is not news. Ask again next time.
        }

        window.setTimeout(ask, 3000);
    };

    window.setTimeout(ask, 3000);
}

// Product photos: a photo opens over the page to be looked at closely, and a click or tap on it
// there shows it at its full size. Without this script, or in a browser with no dialogs, the
// link opens the photo by itself.
document.addEventListener('click', (event) => {
    const link = event.target.closest?.('[data-zoom]');
    const dialog = document.querySelector('[data-zoom-dialog]');

    if (link && typeof dialog?.showModal === 'function') {
        event.preventDefault();

        const photo = dialog.querySelector('img');
        photo.src = link.href;
        photo.alt = link.querySelector('img')?.alt ?? '';

        dialog.classList.remove('is-closer');
        dialog.showModal();

        return;
    }

    if (event.target.matches?.('[data-zoom-dialog] img')) {
        const stage = event.target.parentElement;
        const closer = event.target.closest('[data-zoom-dialog]').classList.toggle('is-closer');

        // Coming closer keeps the spot that was clicked in view.
        if (closer) {
            const box = event.target.getBoundingClientRect();
            const across = (event.clientX - box.left) / box.width;
            const down = (event.clientY - box.top) / box.height;

            requestAnimationFrame(() => {
                stage.scrollLeft = across * stage.scrollWidth - stage.clientWidth / 2;
                stage.scrollTop = down * stage.scrollHeight - stage.clientHeight / 2;
            });
        }
    }
});
