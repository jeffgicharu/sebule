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

const stillness = window.matchMedia('(prefers-reduced-motion: reduce)');

// Further down a page, things rise into place as they come into view, those arriving together a beat
// apart. Only what is still below the screen is held back, so without this script, or for a visitor
// who wants less motion, every page is simply all there.
const rising = [...document.querySelectorAll('[data-reveal]')];

if (rising.length && 'IntersectionObserver' in window && !stillness.matches) {
    const watcher = new IntersectionObserver(
        (entries) => {
            let order = 0;

            for (const entry of entries) {
                if (entry.isIntersecting) {
                    entry.target.style.setProperty('--rise-delay', `${Math.min(order++, 5) * 90}ms`);
                    entry.target.classList.remove('is-waiting');
                    watcher.unobserve(entry.target);
                }
            }
        },
        { rootMargin: '0px 0px -8% 0px' },
    );

    for (const element of rising) {
        if (element.getBoundingClientRect().top > window.innerHeight) {
            element.classList.add('is-waiting');
            watcher.observe(element);
        }
    }
}

// With a mouse, a product photo comes closer at the spot the pointer is over. The card's link lies
// over the whole card, so the photo is found from the card rather than from what is under the pointer.
document.addEventListener('pointermove', (event) => {
    const tile = event.pointerType === 'mouse' && event.target.closest?.('article')?.querySelector('[data-inspect]');

    if (tile) {
        const box = tile.getBoundingClientRect();
        const share = (from, size, at) => `${Math.min(Math.max(((at - from) / size) * 100, 0), 100).toFixed(1)}%`;

        tile.style.setProperty('--look-x', share(box.left, box.width, event.clientX));
        tile.style.setProperty('--look-y', share(box.top, box.height, event.clientY));
    }
});

// Pointing at a piece or at a colour starts loading the large photo its page opens with, at the size
// that page shows it (the main photo in shop/product.blade.php), so it is there when the photo lands.
const looked = new Set();

const lookAhead = (event) => {
    const srcset = event.target.closest?.('[data-look]')?.dataset.look ?? event.target.closest?.('article')?.querySelector('[data-inspect] img')?.srcset;

    if (srcset && !looked.has(srcset)) {
        looked.add(srcset);

        const ahead = new Image();
        ahead.sizes = '(min-width: 64rem) 58vw, 100vw';
        ahead.srcset = srcset;
    }
};

document.addEventListener('pointerover', lookAhead);
document.addEventListener('focusin', lookAhead);

// Adding to the cart: the photo of the piece flies up into the cart, and then the cart page opens.
// The form is sent as it would be without script, only a moment later.
document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-to-cart') || event.defaultPrevented) {
        return;
    }

    if (form.dataset.sent) {
        event.preventDefault();

        return;
    }

    const photo = document.querySelector('[data-piece-main] img');
    const cart = document.querySelector('[data-cart] svg');
    const button = form.querySelector('button');

    if (!photo?.complete || !cart || !button || stillness.matches || typeof photo.animate !== 'function') {
        return;
    }

    event.preventDefault();
    form.dataset.sent = 'yes';
    button.setAttribute('aria-busy', 'true');

    let sent = false;
    const send = () => {
        if (!sent) {
            sent = true;
            HTMLFormElement.prototype.submit.call(form);
        }
    };

    // From the photo if at least half of it is in view; otherwise a small copy of it rises from the button.
    let from = photo.getBoundingClientRect();
    const showing = Math.min(from.bottom, window.innerHeight) - Math.max(from.top, 0);

    if (showing < from.height / 2) {
        const pressed = button.getBoundingClientRect();
        from = { left: pressed.left + pressed.width / 2 - 44, top: pressed.top + pressed.height / 2 - 44, width: 88, height: 88 };
    }

    const to = cart.getBoundingClientRect();
    const across = to.left + to.width / 2 - (from.left + from.width / 2);
    const up = to.top + to.height / 2 - (from.top + from.height / 2);
    const time = 850;

    const flyer = document.createElement('div');
    flyer.className = 'flyer';
    flyer.setAttribute('aria-hidden', 'true');
    Object.assign(flyer.style, { left: `${from.left}px`, top: `${from.top}px`, width: `${from.width}px`, height: `${from.height}px` });

    const copy = new Image();
    copy.src = photo.currentSrc || photo.src;
    copy.alt = '';
    flyer.append(copy);
    document.body.append(flyer);

    // Across slowly at first and up quickly, so the photo travels in an arc.
    flyer.animate([{ translate: '0 0' }, { translate: `${across}px 0` }], { duration: time, easing: 'cubic-bezier(0.5, 0, 0.75, 0.5)', fill: 'forwards' });
    const flight = copy.animate(
        [
            { translate: '0 0', scale: 1, opacity: 1 },
            { translate: '0 -1.5rem', scale: 0.9, opacity: 1, offset: 0.18 },
            { translate: `0 ${up}px`, scale: Math.max(to.width / from.width, 0.05), opacity: 0.25, borderRadius: '50%' },
        ],
        { duration: time, easing: 'cubic-bezier(0.3, 0.1, 0.25, 1)', fill: 'forwards' },
    );

    flight.finished
        .then(() => {
            cart.animate([{ rotate: '0deg' }, { rotate: '-14deg' }, { rotate: '10deg' }, { rotate: '-5deg' }, { rotate: '0deg' }], { duration: 450, easing: 'ease-out' });
            send();
        })
        .catch(send);

    // Whatever happens to the animation, the form goes.
    window.setTimeout(send, time + 400);
});

// Coming back to a page kept in the browser's memory: put the cart button back as it was.
window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        document.querySelectorAll('.flyer').forEach((flyer) => flyer.remove());
        document.querySelectorAll('form[data-to-cart]').forEach((form) => {
            delete form.dataset.sent;
            form.querySelector('button')?.removeAttribute('aria-busy');
        });
    }
});
