/**
 * Client-side validation for Customer Profile & Change Password forms (NovaPhone).
 */

document.addEventListener('DOMContentLoaded', () => {
    initProfilePasswordToggles();
    initProfileUpdateValidation();
    initPasswordChangeValidation();
});

function initProfilePasswordToggles() {
    document.querySelectorAll('[data-toggle-password]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-toggle-password');
            const input = targetId ? document.getElementById(targetId) : null;
            if (!input) return;

            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            btn.textContent = isHidden ? 'Hide' : 'Show';
            btn.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
            btn.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
        });
    });
}

function setProfileFieldError(input, msg) {
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
}

function initProfileUpdateValidation() {
    const form = document.getElementById('profile-form');
    if (!form) return;

    const nameInput = document.getElementById('prof-name');
    const phoneInput = document.getElementById('prof-phone');
    const addrInput = document.getElementById('prof-address');
    const submitBtn = document.getElementById('profile-submit-btn');

    const normalizePhone = (v) => {
        let c = v.trim().replace(/[\s.\-()]+/g, '');
        if (c.startsWith('+84')) c = '0' + c.slice(3);
        return c;
    };

    const fields = [
        [nameInput, () => {
            const v = nameInput.value.trim();
            if (!v) return 'Full name is required.';
            if (v.length < 2 || v.length > 100) return 'Full name must be between 2 and 100 characters.';
            return '';
        }],
        [phoneInput, () => {
            const v = phoneInput.value.trim();
            if (!v) return 'Phone number is required.';
            if (!/^0(3|5|7|8|9)[0-9]{8}$/.test(normalizePhone(v))) {
                return 'Enter a valid 10-digit Vietnamese mobile number (e.g. 0912345678).';
            }
            return '';
        }],
        [addrInput, () => {
            if (addrInput.value.trim().length > 255) return 'Address must not exceed 255 characters.';
            return '';
        }],
    ];

    fields.forEach(([input, fn]) => {
        if (!input) return;
        input.addEventListener('blur', () => setProfileFieldError(input, fn()));
        input.addEventListener('input', () => {
            if (input.classList.contains('is-invalid')) setProfileFieldError(input, fn());
        });
    });

    form.addEventListener('submit', (event) => {
        let firstBad = null;
        fields.forEach(([input, fn]) => {
            const err = fn();
            setProfileFieldError(input, err);
            if (err && !firstBad) firstBad = input;
        });

        if (firstBad) {
            event.preventDefault();
            firstBad.focus();
            return;
        }
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving...';
        }
    });
}

function initPasswordChangeValidation() {
    const form = document.getElementById('password-form');
    if (!form) return;

    const currentPwd = document.getElementById('pwd-current');
    const newPwd = document.getElementById('pwd-new');
    const confirmPwd = document.getElementById('pwd-confirm');
    const submitBtn = document.getElementById('password-submit-btn');
    const rulesList = document.getElementById('password-rules-list');

    const updateChecklist = (password) => {
        if (!rulesList) return;
        const checks = {
            length: password.length >= 8,
            upper: /[A-Z]/.test(password),
            lower: /[a-z]/.test(password),
            digit: /[0-9]/.test(password),
        };
        Object.entries(checks).forEach(([rule, passed]) => {
            const el = rulesList.querySelector(`[data-rule="${rule}"]`);
            if (el) el.classList.toggle('is-passed', passed);
        });
    };

    const fields = [
        [currentPwd, () => (!currentPwd.value ? 'Current password is required.' : '')],
        [newPwd, () => {
            const v = newPwd.value;
            if (!v) return 'New password is required.';
            if (v.length < 8) return 'Password must be at least 8 characters long.';
            if (!/[A-Z]/.test(v)) return 'Password must include at least one uppercase letter.';
            if (!/[a-z]/.test(v)) return 'Password must include at least one lowercase letter.';
            if (!/[0-9]/.test(v)) return 'Password must include at least one number.';
            if (currentPwd.value && v === currentPwd.value) return 'New password must be different from your current password.';
            return '';
        }],
        [confirmPwd, () => {
            if (!confirmPwd.value) return 'Please confirm your new password.';
            if (confirmPwd.value !== newPwd.value) return 'Password confirmation does not match.';
            return '';
        }],
    ];

    fields.forEach(([input, fn]) => {
        if (!input) return;
        input.addEventListener('blur', () => setProfileFieldError(input, fn()));
        input.addEventListener('input', () => {
            if (input === newPwd) {
                updateChecklist(newPwd.value);
                if (confirmPwd.value !== '') setProfileFieldError(confirmPwd, fields[2][1]());
            }
            if (input.classList.contains('is-invalid')) setProfileFieldError(input, fn());
        });
    });

    form.addEventListener('submit', (event) => {
        let firstBad = null;
        fields.forEach(([input, fn]) => {
            const err = fn();
            setProfileFieldError(input, err);
            if (err && !firstBad) firstBad = input;
        });

        if (firstBad) {
            event.preventDefault();
            firstBad.focus();
            return;
        }
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Updating...';
        }
    });
}

