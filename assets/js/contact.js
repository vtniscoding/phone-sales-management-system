/**
 * Client-side validation for Contact & Support Form (NovaPhone).
 */

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('contact-form');
    if (!form) return;

    const nameInput = document.getElementById('cnt-name');
    const phoneInput = document.getElementById('cnt-phone');
    const emailInput = document.getElementById('cnt-email');
    const subjectInput = document.getElementById('cnt-subject');
    const messageInput = document.getElementById('cnt-message');
    const submitBtn = document.getElementById('contact-submit-btn');

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
            else input.classList.remove('is-valid');
            input.setAttribute('aria-invalid', 'false');
            if (errEl) errEl.textContent = '';
        }
    };

    const normalizePhone = (v) => {
        let c = v.trim().replace(/[\s.\-()]+/g, '');
        if (c.startsWith('+84')) c = '0' + c.slice(3);
        return c;
    };

    const validators = [
        [nameInput, () => {
            const v = nameInput.value.trim();
            if (!v) return 'Full name is required.';
            if (v.length < 2 || v.length > 100) return 'Full name must be between 2 and 100 characters.';
            return '';
        }],
        [phoneInput, () => {
            const v = phoneInput.value.trim();
            if (v !== '' && !/^0(3|5|7|8|9)[0-9]{8}$/.test(normalizePhone(v))) {
                return 'Enter a valid 10-digit Vietnamese mobile number (e.g. 0909112233).';
            }
            return '';
        }],
        [emailInput, () => {
            const v = emailInput.value.trim();
            if (!v) return 'Email address is required.';
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v) || v.length > 150) {
                return 'Please enter a valid email address.';
            }
            return '';
        }],
        [subjectInput, () => {
            const v = subjectInput.value.trim();
            if (!v) return 'Subject is required.';
            if (v.length < 3 || v.length > 150) return 'Subject must be between 3 and 150 characters.';
            return '';
        }],
        [messageInput, () => {
            const v = messageInput.value.trim();
            if (!v) return 'Message content is required.';
            if (v.length < 10 || v.length > 2000) return 'Message must be between 10 and 2000 characters.';
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
        let firstBad = null;
        validators.forEach(([input, fn]) => {
            const err = fn();
            setErr(input, err);
            if (err && !firstBad) firstBad = input;
        });

        if (firstBad) {
            event.preventDefault();
            firstBad.focus();
            return;
        }
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Sending...';
        }
    });
});

