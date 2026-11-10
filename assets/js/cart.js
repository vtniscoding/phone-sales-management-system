/**
 * Shopping Cart Quantity Stepper & Confirmation Controls (NovaPhone).
 */

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.cart-qty-form').forEach((form) => {
        const input = form.querySelector('input[name="quantity"]');
        const stepBtns = form.querySelectorAll('[data-qty-step]');
        if (!input || stepBtns.length === 0) return;

        const min = Number(input.min || '1');
        const max = Number(input.max || '99');

        stepBtns.forEach((btn) => {
            btn.addEventListener('click', () => {
                const delta = Number(btn.getAttribute('data-qty-step') || '0');
                const current = parseInt(input.value || '1', 10);
                const next = Math.min(max, Math.max(min, (Number.isNaN(current) ? 1 : current) + delta));
                if (next !== current) {
                    input.value = String(next);
                    form.submit();
                }
            });
        });

        input.addEventListener('change', () => {
            let val = parseInt(input.value || '1', 10);
            if (Number.isNaN(val) || val < min) val = min;
            if (val > max) val = max;
            input.value = String(val);
        });
    });

    document.querySelectorAll('[data-confirm-clear]').forEach((btn) => {
        btn.addEventListener('click', (event) => {
            if (!window.confirm('Are you sure you want to remove all items from your shopping cart?')) {
                event.preventDefault();
            }
        });
    });
});

