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
