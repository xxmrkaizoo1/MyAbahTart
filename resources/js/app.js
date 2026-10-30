/**
 * Small progressive enhancements. Every page works without JavaScript.
 */

const formatRinggit = (value) => `RM ${Number(value).toFixed(2)}`;

// Flash messages: dismiss on click, fade out after a few seconds.
document.querySelectorAll('[data-flash]').forEach((flash) => {
    const hide = () => {
        flash.style.opacity = '0';
        flash.style.transform = 'translateY(-8px)';
        setTimeout(() => flash.remove(), 300);
    };

    flash.querySelector('[data-dismiss]')?.addEventListener('click', hide);
    setTimeout(hide, 4500);
});

const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// Slide-in drawers: [data-drawer-open="id"] opens, [data-drawer-close] or Escape closes.
const closeDrawers = () => {
    document.querySelectorAll('[data-drawer]:not(.hidden)').forEach((drawer) => {
        if (prefersReducedMotion) {
            drawer.classList.add('hidden');

            return;
        }

        drawer.dataset.closing = '';
        setTimeout(() => {
            if (drawer.hasAttribute('data-closing')) {
                drawer.classList.add('hidden');
                drawer.removeAttribute('data-closing');
            }
        }, 220);
    });
    document.documentElement.classList.remove('overflow-hidden');
};

document.querySelectorAll('[data-drawer-open]').forEach((button) => {
    button.addEventListener('click', () => {
        const drawer = document.getElementById(button.dataset.drawerOpen);

        drawer?.removeAttribute('data-closing');
        drawer?.classList.remove('hidden');
        document.documentElement.classList.add('overflow-hidden');
    });
});

document.querySelectorAll('[data-drawer-close]').forEach((element) => element.addEventListener('click', closeDrawers));
document.addEventListener('keydown', (event) => event.key === 'Escape' && closeDrawers());

// Colour theme picker: swap the theme instantly and remember it for a year.
document.querySelectorAll('[data-theme-choice]').forEach((button) => {
    button.addEventListener('click', () => {
        const theme = button.dataset.themeChoice;
        const apply = () => {
            document.documentElement.dataset.theme = theme;
            document.querySelectorAll('[data-theme-choice]').forEach((choice) => {
                choice.setAttribute('aria-pressed', String(choice.dataset.themeChoice === theme));
            });
        };

        document.cookie = `theme=${theme}; path=/; max-age=31536000; SameSite=Lax`;

        // Crossfade the whole page into the new theme where the browser supports it.
        if (document.startViewTransition && !prefersReducedMotion) {
            document.startViewTransition(apply);
        } else {
            apply();
        }
    });
});

// Main navigation: the highlighted pill glides to whichever link is hovered or focused.
document.querySelectorAll('[data-nav-track]').forEach((track) => {
    const pill = track.querySelector('[data-nav-pill]');
    const home = pill?.parentElement;

    if (!pill) {
        return;
    }

    const light = (link) => {
        track.querySelectorAll('a').forEach((item) => item.toggleAttribute('data-lit', item === link));
        pill.style.translate = `${link.offsetLeft - home.offsetLeft}px 0`;
        pill.style.width = `${link.offsetWidth}px`;
    };

    track.querySelectorAll('a').forEach((link) => {
        link.addEventListener('mouseenter', () => light(link));
        link.addEventListener('focus', () => light(link));
    });

    const reset = () => {
        light(home);
        pill.style.translate = '';
        pill.style.width = '';
    };

    track.addEventListener('mouseleave', reset);
    track.addEventListener('focusout', (event) => !track.contains(event.relatedTarget) && reset());
});

// The sticky header gains a shadow once the page has scrolled.
document.querySelectorAll('[data-site-header]').forEach((header) => {
    const update = () => header.toggleAttribute('data-scrolled', window.scrollY > 8);

    window.addEventListener('scroll', update, { passive: true });
    update();
});

// Scroll reveal: page sections and cards rise into view, staggered when several appear together.
if (!prefersReducedMotion && 'IntersectionObserver' in window) {
    const observer = new IntersectionObserver(
        (entries) => {
            entries
                .filter((entry) => entry.isIntersecting)
                .forEach((entry, index) => {
                    entry.target.style.setProperty('--reveal-delay', `${Math.min(index, 8) * 70}ms`);
                    entry.target.classList.add('is-revealed');
                    observer.unobserve(entry.target);
                });
        },
        { rootMargin: '0px 0px -8% 0px' },
    );

    document.querySelectorAll('main section, main article, main [data-reveal]').forEach((element) => {
        if (element.parentElement.closest('.reveal')?.matches('article')) {
            return;
        }

        element.classList.add('reveal');
        observer.observe(element);
    });
}

// Ask before destructive actions, in the shop's own pop-up (falls back to the browser's box when the page has none).
document.addEventListener('submit', (event) => {
    const form = event.target;
    const message = event.submitter?.dataset.confirm ?? form.dataset.confirm;

    if (!message || form.dataset.confirmed === '1') {
        delete form.dataset.confirmed;

        return;
    }

    const dialog = document.getElementById('confirm-dialog');

    if (!dialog) {
        if (!window.confirm(message)) {
            event.preventDefault();
        }

        return;
    }

    event.preventDefault();

    const isDelete = /^(delete|remove)\b/i.test(message);
    const [action] = message.match(/^\w+/) ?? ['Yes'];
    dialog.querySelector('[data-confirm-message]').textContent = message;
    dialog.querySelector('[data-confirm-icon="danger"]').classList.toggle('hidden', !isDelete);
    dialog.querySelector('[data-confirm-icon="caution"]').classList.toggle('hidden', isDelete);

    const accept = dialog.querySelector('[data-confirm-accept]');
    accept.textContent = isDelete ? action.charAt(0).toUpperCase() + action.slice(1).toLowerCase() : 'Yes, continue';

    const submitter = event.submitter;
    accept.onclick = () => {
        dialog.close();
        form.dataset.confirmed = '1';
        form.requestSubmit(submitter ?? undefined);
    };

    dialog.showModal();
    accept.focus();
});

// Submit a form as soon as one of its controls changes.
document.querySelectorAll('[data-autosubmit]').forEach((control) => {
    control.addEventListener('change', () => control.form?.requestSubmit());
});

// Quantity steppers: <div data-stepper> with [data-step="-1|1"] buttons and an input.
document.querySelectorAll('[data-stepper]').forEach((stepper) => {
    const input = stepper.querySelector('input');
    const min = Number(input.min || 0);
    const max = Number(input.max || 99);

    stepper.querySelectorAll('[data-step]').forEach((button) => {
        button.addEventListener('click', () => {
            const next = Math.min(max, Math.max(min, Number(input.value || 0) + Number(button.dataset.step)));

            if (next === Number(input.value)) {
                return;
            }

            input.value = next;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });
});

// Checkout: update the delivery fee and total as the delivery method changes.
const checkout = document.querySelector('[data-checkout]');

if (checkout) {
    const subtotal = Number(checkout.dataset.subtotal);
    const discount = Number(checkout.dataset.discount);
    const feeOutput = checkout.querySelector('[data-summary="fee"]');
    const totalOutputs = document.querySelectorAll('[data-summary="total"]');
    const addressField = checkout.querySelector('[data-address-field]');

    const update = () => {
        const selected = checkout.querySelector('input[name="delivery_method"]:checked');

        if (!selected) {
            return;
        }

        const isPending = selected.dataset.fee === '';
        const fee = isPending ? 0 : Number(selected.dataset.fee);
        feeOutput.textContent = isPending ? 'By distance' : fee === 0 ? 'FREE' : formatRinggit(fee);
        totalOutputs.forEach((output) => (output.textContent = formatRinggit(Math.max(0, subtotal - discount + fee))));
        addressField?.classList.toggle('hidden', selected.dataset.needsAddress !== '1');
    };

    // Lalamove: ask the server for a live price whenever the address changes.
    const liveRadio = checkout.querySelector('input[data-live-quote]');
    const addressInput = document.getElementById('address');
    const quoteStatus = checkout.querySelector('[data-quote-status]');
    let quoteTimer;
    let latestQuote = 0;

    const showQuote = ({ price = null, label, note, status = '', tone = 'soft' }) => {
        const card = liveRadio.closest('label').querySelector('[data-quote-price]');
        liveRadio.dataset.fee = price === null ? '' : String(price);
        card.firstElementChild.textContent = label;
        card.querySelector('[data-quote-note]').textContent = note;
        quoteStatus.textContent = status;
        quoteStatus.className = `mt-1 text-sm ${tone === 'error' ? 'text-rose-700' : 'text-ink-soft'} ${status && liveRadio.checked ? '' : 'hidden'}`;
        update();
    };

    const fetchQuote = async () => {
        const address = addressInput?.value.trim() ?? '';

        if (address.length < 8) {
            showQuote({ label: 'By distance', note: 'Live price from Lalamove', status: 'Enter your full address to see the Lalamove price.' });

            return;
        }

        const request = ++latestQuote;
        showQuote({ label: 'Checking…', note: 'Asking Lalamove', status: 'Getting the Lalamove price for this address…' });

        try {
            const response = await fetch(liveRadio.dataset.liveQuote, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('#checkout-form input[name="_token"]')?.value ?? '',
                },
                body: JSON.stringify({ address }),
            });
            const data = await response.json().catch(() => ({}));

            if (request !== latestQuote) {
                return;
            }

            if (!response.ok) {
                showQuote({ label: 'Unavailable', note: 'Try another option', status: data.message ?? 'Couldn’t get a Lalamove price.', tone: 'error' });

                return;
            }

            const distance = data.distance_km ? `${data.distance_km} km` : 'Live price';
            showQuote({ price: data.price, label: formatRinggit(data.price), note: `${distance} · Lalamove`, status: `Lalamove price for this address (${distance}).` });
        } catch {
            if (request === latestQuote) {
                showQuote({ label: 'Unavailable', note: 'Try another option', status: 'Couldn’t reach Lalamove. Please try again.', tone: 'error' });
            }
        }
    };

    if (liveRadio) {
        addressInput?.addEventListener('input', () => {
            clearTimeout(quoteTimer);
            quoteTimer = setTimeout(fetchQuote, 800);
        });

        fetchQuote();
    }

    checkout.querySelectorAll('input[name="delivery_method"]').forEach((radio) =>
        radio.addEventListener('change', () => {
            update();

            if (liveRadio) {
                quoteStatus.classList.toggle('hidden', !liveRadio.checked || quoteStatus.textContent === '');
            }
        }),
    );
    update();
}

// Product sizes: show the price of the size that's picked.
document.querySelectorAll('input[name="size"][data-price]').forEach((radio) => {
    radio.addEventListener('change', () => {
        const price = document.querySelector('[data-size-price]');
        const quantity = radio.form?.querySelector('input[name="quantity"]');

        if (price && radio.checked) {
            price.textContent = radio.dataset.price;
        }

        if (quantity && radio.checked && radio.dataset.stock) {
            quantity.max = Math.max(1, Math.min(99, Number(radio.dataset.stock)));
            quantity.value = Math.min(Number(quantity.value) || 1, Number(quantity.max));
        }
    });

    // The main photo shows first; a size's own photo only appears once the customer taps that size.
    radio.addEventListener('click', () => {
        document.querySelectorAll('[data-size-photo]').forEach((photo) => {
            photo.classList.toggle('opacity-0', photo.dataset.sizePhoto !== radio.value);
        });
    });
});

// Pop-up dialogs: [data-dialog-open="id"] opens <dialog id>, [data-dialog-close] or a click on the backdrop closes it.
document.querySelectorAll('[data-dialog-open]').forEach((button) => {
    button.addEventListener('click', () => document.getElementById(button.dataset.dialogOpen)?.showModal());
});

document.querySelectorAll('dialog[data-dialog]').forEach((dialog) => {
    dialog.querySelectorAll('[data-dialog-close]').forEach((button) => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            dialog.close();
        }
    });

    if (dialog.hasAttribute('data-dialog-autoshow')) {
        dialog.showModal();
    }
});

// Checkbox groups: [data-check-all="on|off"] ticks or clears every box and [data-check-count] shows how many are ticked.
document.querySelectorAll('[data-check-group]').forEach((group) => {
    const boxes = group.querySelectorAll('input[type="checkbox"]');
    const count = group.querySelector('[data-check-count]');
    const update = () => count && (count.textContent = [...boxes].filter((box) => box.checked).length);

    group.querySelectorAll('[data-check-all]').forEach((button) => {
        button.addEventListener('click', () => {
            boxes.forEach((box) => (box.checked = button.dataset.checkAll === 'on'));
            update();
        });
    });
    boxes.forEach((box) => box.addEventListener('change', update));

    // Typing a quantity for an item ticks it.
    group.querySelectorAll('[data-quantity-for]').forEach((input) => {
        input.addEventListener('input', () => {
            const box = group.querySelector(`[data-quantity-box="${input.dataset.quantityFor}"]`);

            if (box && input.value !== '' && !box.checked) {
                box.checked = true;
                update();
            }
        });
    });
});

// Point of sale: build the ticket in the browser, then post it as a regular form.
const pos = document.querySelector('[data-pos]');
const POS_STORAGE_KEY = 'pos-ticket';

if (document.querySelector('[data-pos-sale-complete]')) {
    try {
        sessionStorage.removeItem(POS_STORAGE_KEY);
    } catch {
        // Storage can be unavailable in private windows; the ticket simply isn't remembered.
    }
}

if (pos) {
    const form = pos.querySelector('[data-pos-form]');
    const list = form.querySelector('[data-pos-lines]');
    const empty = form.querySelector('[data-pos-empty]');
    const inputs = form.querySelector('[data-pos-inputs]');
    const discountInput = form.querySelector('[data-pos-discount]');
    const tendered = form.querySelector('[data-pos-tendered]');
    const cashPanel = form.querySelector('[data-pos-cash]');
    const charge = form.querySelector('[data-pos-charge]');
    const products = new Map(
        [...pos.querySelectorAll('[data-pos-product]')].map((card) => [
            card.dataset.id,
            {
                name: card.dataset.name,
                stock: Number(card.dataset.stock),
                sizes: Object.fromEntries([...card.querySelectorAll('[data-size-stock]')].map((button) => [button.dataset.size, Number(button.dataset.sizeStock)])),
            },
        ]),
    );

    let lines = [];

    try {
        lines = JSON.parse(sessionStorage.getItem(POS_STORAGE_KEY) || '[]').filter((line) => products.has(line.id));
    } catch {
        lines = [];
    }

    const totals = () => {
        const subtotal = lines.reduce((sum, line) => sum + line.price * line.quantity, 0);
        const discount = Math.min(Math.max(Number(discountInput.value) || 0, 0), subtotal);

        return { subtotal, discount, total: Math.max(subtotal - discount, 0) };
    };

    const unitsOf = (id) => lines.filter((line) => line.id === id).reduce((sum, line) => sum + line.quantity, 0);
    const unitsOfSize = (id, size) => lines.filter((line) => line.id === id && line.size === size).reduce((sum, line) => sum + line.quantity, 0);
    const atLimit = (id, size) => {
        const sizeStock = products.get(id).sizes[size];

        return unitsOf(id) >= products.get(id).stock || (size && sizeStock !== undefined && unitsOfSize(id, size) >= sizeStock);
    };
    const isCash = () => form.querySelector('[data-pos-method]:checked')?.value === 'cash';

    const element = (tag, className, text) => {
        const node = document.createElement(tag);
        node.className = className;

        if (text !== undefined) {
            node.textContent = text;
        }

        return node;
    };

    let render;

    const changeQuantity = (index, step) => {
        const line = lines[index];

        if (step > 0 && atLimit(line.id, line.size)) {
            return;
        }

        line.quantity += step;

        if (line.quantity < 1) {
            lines.splice(index, 1);
        }

        render();
    };

    render = () => {
        list.querySelectorAll('[data-pos-line]').forEach((node) => node.remove());
        inputs.innerHTML = '';
        empty.hidden = lines.length > 0;

        lines.forEach((line, index) => {
            const item = element('li', 'flex items-center gap-3 px-5 py-3');
            item.dataset.posLine = '';

            const details = element('div', 'min-w-0 flex-1');
            details.append(
                element('p', 'truncate text-sm font-bold', line.name),
                element('p', 'text-xs text-ink-soft', line.size ? `Size ${line.size} · ${formatRinggit(line.price)}` : formatRinggit(line.price)),
            );

            const stepper = element('div', 'flex items-center rounded-full bg-paper ring-1 ring-ink/10');
            const stepButton = (label, step, action) => {
                const button = element('button', 'grid size-8 place-items-center rounded-full text-ink-soft hover:text-ink', label);
                button.type = 'button';
                button.setAttribute('aria-label', `${action} ${line.name}`);
                button.addEventListener('click', () => changeQuantity(index, step));

                return button;
            };
            stepper.append(
                stepButton('−', -1, 'Decrease'),
                element('span', 'w-7 text-center text-sm font-bold', String(line.quantity)),
                stepButton('+', 1, 'Increase'),
            );

            item.append(details, stepper, element('p', 'w-20 text-right text-sm font-bold', formatRinggit(line.price * line.quantity)));
            list.append(item);

            Object.entries({ product_id: line.id, size: line.size, quantity: line.quantity }).forEach(([field, value]) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `items[${index}][${field}]`;
                input.value = value;
                inputs.append(input);
            });
        });

        const { subtotal, discount, total } = totals();
        form.querySelector('[data-pos-subtotal]').textContent = formatRinggit(subtotal);
        form.querySelector('[data-pos-discount-total]').textContent = `−${formatRinggit(discount)}`;
        form.querySelector('[data-pos-total]').textContent = formatRinggit(total);
        form.querySelector('[data-pos-charge-total]').textContent = formatRinggit(total);

        cashPanel.hidden = !isCash();
        const cash = Number(tendered.value) || 0;
        form.querySelector('[data-pos-change]').textContent = formatRinggit(Math.max(cash - total, 0));
        charge.disabled = lines.length === 0 || (isCash() && cash < total);

        try {
            sessionStorage.setItem(POS_STORAGE_KEY, JSON.stringify(lines));
        } catch {
            // Not remembering the ticket is fine.
        }
    };

    pos.querySelectorAll('[data-pos-add]').forEach((button) => {
        button.addEventListener('click', () => {
            const id = button.closest('[data-pos-product]').dataset.id;
            const size = button.dataset.size;
            const existing = lines.findIndex((line) => line.id === id && line.size === size);

            if (atLimit(id, size)) {
                return;
            }

            if (existing === -1) {
                lines.push({ id, size, name: products.get(id).name, price: Number(button.dataset.price), quantity: 1 });
            } else {
                lines[existing].quantity += 1;
            }

            render();
        });
    });

    form.querySelector('[data-pos-clear]').addEventListener('click', () => {
        lines = [];
        discountInput.value = '';
        tendered.value = '';
        render();
    });

    form.querySelectorAll('[data-pos-quick]').forEach((button) => {
        button.addEventListener('click', () => {
            const { total } = totals();
            tendered.value = (button.dataset.posQuick === 'exact' ? total : Number(button.dataset.posQuick)).toFixed(2);
            render();
        });
    });

    [discountInput, tendered].forEach((input) => input.addEventListener('input', render));
    form.querySelectorAll('[data-pos-method]').forEach((radio) => radio.addEventListener('change', render));

    // Search and collection filters.
    const search = pos.querySelector('[data-pos-search]');
    let category = '';

    const filter = () => {
        const term = search.value.trim().toLowerCase();
        let shown = 0;

        pos.querySelectorAll('[data-pos-product]').forEach((card) => {
            const visible = card.dataset.name.toLowerCase().includes(term) && (category === '' || card.dataset.category === category);
            card.hidden = !visible;
            shown += visible ? 1 : 0;
        });

        pos.querySelector('[data-pos-no-results]')?.classList.toggle('hidden', shown > 0);
    };

    search?.addEventListener('input', filter);
    pos.querySelectorAll('[data-pos-category]').forEach((button) => {
        button.addEventListener('click', () => {
            category = button.dataset.posCategory;
            pos.querySelectorAll('[data-pos-category]').forEach((other) => other.setAttribute('aria-pressed', String(other === button)));
            filter();
        });
    });

    render();
}

// Channel chart: crosshair, markers and a tooltip for the day under the pointer.
document.querySelectorAll('[data-channel-chart]').forEach((chart) => {
    const points = JSON.parse(chart.dataset.points || '[]');
    const plot = chart.querySelector('[data-chart-plot]');
    const crosshair = plot.querySelector('[data-chart-crosshair]');
    const tooltip = plot.querySelector('[data-chart-tooltip]');
    const markers = [...plot.querySelectorAll('[data-chart-marker]')];
    const peak = Math.max(1, ...points.flatMap((point) => [point.online, point.counter]));
    const magnitude = 10 ** Math.floor(Math.log10(peak));
    const max = [1, 2, 2.5, 5, 10].map((step) => step * magnitude).find((candidate) => candidate >= peak);

    if (points.length === 0) {
        return;
    }

    const show = (index) => {
        const point = points[index];
        const left = points.length > 1 ? (index / (points.length - 1)) * 100 : 50;

        crosshair.style.left = `${left}%`;
        crosshair.classList.remove('hidden');
        markers.forEach((marker) => {
            marker.style.left = `${left}%`;
            marker.style.top = `${100 - (point[marker.dataset.chartMarker] / max) * 100}%`;
            marker.classList.remove('hidden');
        });

        tooltip.querySelector('[data-chart-tooltip-date]').textContent = point.date;
        tooltip.querySelectorAll('[data-chart-tooltip-value]').forEach((value) => {
            value.textContent = formatRinggit(point[value.dataset.chartTooltipValue]);
        });
        tooltip.classList.remove('hidden');
        tooltip.style.left = left > 60 ? '' : `calc(${left}% + 12px)`;
        tooltip.style.right = left > 60 ? `calc(${100 - left}% + 12px)` : '';
    };

    const hide = () => [crosshair, tooltip, ...markers].forEach((element) => element.classList.add('hidden'));

    plot.addEventListener('pointermove', (event) => {
        const box = plot.getBoundingClientRect();
        const ratio = Math.min(Math.max((event.clientX - box.left) / box.width, 0), 1);
        show(Math.round(ratio * (points.length - 1)));
    });
    plot.addEventListener('pointerleave', hide);
});

// Product form: when any size has a price, each size keeps its own stock and the total stock is their sum.
document.querySelectorAll('[data-size-stock-total]').forEach((total) => {
    const section = total.closest('section');
    const note = section.querySelector('[data-size-stock-note]');
    const sizes = [...section.querySelectorAll('[data-size-price-input]')].map((price) => ({
        price,
        count: price.parentElement.querySelector('[data-size-count]'),
        extras: price.parentElement.querySelectorAll('[data-size-extra]'),
    }));

    const sync = () => {
        const offered = sizes.filter(({ price }) => price.value !== '');

        sizes.forEach(({ price, count, extras }) => {
            count.disabled = price.value === '';
            extras.forEach((extra) => {
                extra.disabled = price.value === '';
            });
        });
        total.readOnly = offered.length > 0;
        note.classList.toggle('hidden', offered.length === 0);

        if (offered.length > 0) {
            total.value = offered.reduce((sum, { count }) => sum + (parseInt(count.value, 10) || 0), 0);
        }
    };

    sizes.forEach(({ price, count }) => {
        price.addEventListener('input', sync);
        count.addEventListener('input', sync);
    });
    sync();
});

// Preview an uploaded product photo before saving.
document.querySelectorAll('[data-image-input]').forEach((input) => {
    input.addEventListener('change', () => {
        const preview = document.querySelector(input.dataset.imageInput);
        const [file] = input.files;

        if (preview && file) {
            preview.innerHTML = '';
            const image = document.createElement('img');
            image.src = URL.createObjectURL(file);
            image.className = 'size-full object-cover';
            preview.append(image);
        }
    });
});

// Toggle password visibility.
document.querySelectorAll('[data-toggle-password]').forEach((button) => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.togglePassword);
        input.type = input.type === 'password' ? 'text' : 'password';
    });
});
