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
