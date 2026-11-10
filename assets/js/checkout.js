/**
 * Client-side Checkout Validation & Payment Method Preview (NovaPhone).
 */

document.addEventListener('DOMContentLoaded', () => {
    initPaymentPreview();
    initCheckoutValidation();
});

function initPaymentPreview() {
    const radios = document.querySelectorAll('input[name="payment_method"]');
    const preview = document.getElementById('bank-transfer-preview');
    if (radios.length === 0 || !preview) return;

    const syncVisibility = () => {
        const selected = document.querySelector('input[name="payment_method"]:checked');
        const isTransfer = selected && selected.value === 'bank_transfer';
        preview.classList.toggle('is-hidden', !isTransfer);
    };

    radios.forEach((radio) => radio.addEventListener('change', syncVisibility));
    syncVisibility();
}

function initCheckoutValidation() {
    const form = document.getElementById('checkout-form');
    if (!form) return;

    const nameInput = document.getElementById('chk-name');
    const phoneInput = document.getElementById('chk-phone');
    const addressInput = document.getElementById('chk-address');
    const noteInput = document.getElementById('chk-note');
    const submitBtn = document.getElementById('checkout-submit-btn');

    const setErr = (input, msg) => {
        if (!input) return;
        const errEl = document.getElementById(`${input.id}-error`);
        if (msg) {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            input.setAttribute('aria-invalid', 'true');
            if (errEl) errEl.textContent = msg;
        } else {
            input.classList.remove('is-invalid');
            if (input.value.trim() !== '') input.classList.add('is-valid');
            input.setAttribute('aria-invalid', 'false');
            if (errEl) errEl.textContent = '';
        }
    };

    const normalizePhone = (value) => {
        let cleaned = value.trim().replace(/[\s.\-()]+/g, '');
        if (cleaned.startsWith('+84')) cleaned = '0' + cleaned.slice(3);
        return cleaned;
    };

    const validators = [
        [nameInput, () => {
            const v = nameInput.value.trim();
            if (!v) return 'Recipient full name is required.';
            if (v.length < 2 || v.length > 100) return 'Recipient full name must be between 2 and 100 characters.';
            return '';
        }],
        [phoneInput, () => {
            const v = phoneInput.value.trim();
            if (!v) return 'Contact phone number is required.';
            if (!/^0(3|5|7|8|9)[0-9]{8}$/.test(normalizePhone(v))) {
                return 'Enter a valid 10-digit Vietnamese mobile number (e.g. 0912345678).';
            }
            return '';
        }],
        [addressInput, () => {
            const v = addressInput.value.trim();
            if (!v) return 'Shipping address is required.';
            if (v.length < 5 || v.length > 255) return 'Shipping address must be between 5 and 255 characters.';
            return '';
        }],
        [noteInput, () => {
            if (noteInput.value.trim().length > 255) return 'Order note must not exceed 255 characters.';
            return '';
        }],
    ];

    validators.forEach(([input, fn]) => {
        if (!input) return;
        input.addEventListener('blur', () => setErr(input, fn()));
        input.addEventListener('input', () => {
            if (input.classList.contains('is-invalid')) setErr(input, fn());
        });
    });

    form.addEventListener('submit', (event) => {
        let firstInvalid = null;
        validators.forEach(([input, fn]) => {
            if (!input) return;
            const err = fn();
            setErr(input, err);
            if (err && !firstInvalid) firstInvalid = input;
        });

        if (firstInvalid) {
            event.preventDefault();
            firstInvalid.focus();
            return;
        }

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Placing Order...';
        }
    });
}

