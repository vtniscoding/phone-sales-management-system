/**
 * Client-side authentication form validation and password visibility toggles (NovaPhone).
 * Mirrors server-side PHP rules in register.php and login.php for instant user feedback.
 */

document.addEventListener('DOMContentLoaded', () => {
    initPasswordToggles();
    initRegisterFormValidation();
    initLoginFormValidation();
});

function initPasswordToggles() {
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

function setFieldError(input, message) {
    if (!input) return;
    const errorEl = document.getElementById(`${input.id}-error`);
    if (message) {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
        input.setAttribute('aria-invalid', 'true');
        if (errorEl) errorEl.textContent = message;
    } else {
        input.classList.remove('is-invalid');
        if (input.value.trim() !== '') {
            input.classList.add('is-valid');
        } else {
            input.classList.remove('is-valid');
        }
        input.setAttribute('aria-invalid', 'false');
        if (errorEl) errorEl.textContent = '';
    }
}

function isValidEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value);
}

function normalizeVnPhone(value) {
    let cleaned = value.trim().replace(/[\s.\-()]+/g, '');
    if (cleaned.startsWith('+84')) {
        cleaned = '0' + cleaned.slice(3);
    }
    return cleaned;
}

function isValidVnPhone(value) {
    const normalized = normalizeVnPhone(value);
    return /^0(3|5|7|8|9)[0-9]{8}$/.test(normalized);
}

function getPasswordStrengthError(password) {
    if (password.length < 8) {
        return 'Password must be at least 8 characters long.';
    }
    if (!/[A-Z]/.test(password)) {
        return 'Password must include at least one uppercase letter.';
    }
    if (!/[a-z]/.test(password)) {
        return 'Password must include at least one lowercase letter.';
    }
    if (!/[0-9]/.test(password)) {
        return 'Password must include at least one number.';
    }
    return '';
}

function updatePasswordChecklist(password) {
    const rulesList = document.getElementById('password-rules-list');
    if (!rulesList) return;

    const checks = {
        length: password.length >= 8,
        upper: /[A-Z]/.test(password),
        lower: /[a-z]/.test(password),
        digit: /[0-9]/.test(password),
    };

    Object.entries(checks).forEach(([rule, passed]) => {
        const item = rulesList.querySelector(`[data-rule="${rule}"]`);
        if (item) {
            item.classList.toggle('is-passed', passed);
        }
    });
}

function initRegisterFormValidation() {
    const form = document.getElementById('register-form');
    if (!form) return;

    const fullName = document.getElementById('reg-full-name');
    const phone = document.getElementById('reg-phone');
    const email = document.getElementById('reg-email');
    const address = document.getElementById('reg-address');
    const password = document.getElementById('reg-password');
    const confirmPassword = document.getElementById('reg-confirm-password');
    const submitBtn = document.getElementById('register-submit-btn');

    const validators = {
        fullName: () => {
            const val = fullName.value.trim();
            if (!val) return 'Full name is required.';
            if (val.length < 2 || val.length > 100) return 'Full name must be between 2 and 100 characters.';
            return '';
        },
        phone: () => {
            const val = phone.value.trim();
            if (!val) return 'Phone number is required.';
            if (!isValidVnPhone(val)) return 'Enter a valid 10-digit Vietnamese mobile number (e.g. 0912345678).';
            return '';
        },
        email: () => {
            const val = email.value.trim();
            if (!val) return 'Email address is required.';
            if (!isValidEmail(val) || val.length > 150) return 'Please enter a valid email address.';
            return '';
        },
        address: () => {
            const val = address.value.trim();
            if (val.length > 255) return 'Address must not exceed 255 characters.';
            return '';
        },
        password: () => {
            const val = password.value;
            if (!val) return 'Password is required.';
            return getPasswordStrengthError(val);
        },
        confirmPassword: () => {
            const val = confirmPassword.value;
            if (!val) return 'Please confirm your password.';
            if (val !== password.value) return 'Password confirmation does not match.';
            return '';
        },
    };

    const fieldMap = [
        [fullName, validators.fullName],
        [phone, validators.phone],
        [email, validators.email],
        [address, validators.address],
        [password, validators.password],
        [confirmPassword, validators.confirmPassword],
    ];

    fieldMap.forEach(([input, validateFn]) => {
        if (!input) return;
        input.addEventListener('blur', () => setFieldError(input, validateFn()));
        input.addEventListener('input', () => {
            if (input === password) {
                updatePasswordChecklist(password.value);
                if (confirmPassword && confirmPassword.value !== '') {
                    setFieldError(confirmPassword, validators.confirmPassword());
                }
            }
            if (input.classList.contains('is-invalid')) {
                setFieldError(input, validateFn());
            }
        });
    });

    if (password && password.value) {
        updatePasswordChecklist(password.value);
    }

    form.addEventListener('submit', (event) => {
        let firstInvalid = null;
        fieldMap.forEach(([input, validateFn]) => {
            if (!input) return;
            const err = validateFn();
            setFieldError(input, err);
            if (err && !firstInvalid) {
                firstInvalid = input;
            }
        });

        if (firstInvalid) {
            event.preventDefault();
            firstInvalid.focus();
            return;
        }

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creating Account...';
        }
    });
}

function initLoginFormValidation() {
    const form = document.getElementById('login-form');
    if (!form) return;

    const email = document.getElementById('login-email');
    const password = document.getElementById('login-password');
    const submitBtn = document.getElementById('login-submit-btn');

    const validateEmail = () => {
        const val = email.value.trim();
        if (!val) return 'Email address is required.';
        if (!isValidEmail(val)) return 'Please enter a valid email address.';
        return '';
    };

    const validatePassword = () => {
        if (!password.value) return 'Password is required.';
        return '';
    };

    email.addEventListener('blur', () => setFieldError(email, validateEmail()));
    email.addEventListener('input', () => {
        if (email.classList.contains('is-invalid')) setFieldError(email, validateEmail());
    });

    password.addEventListener('blur', () => setFieldError(password, validatePassword()));
    password.addEventListener('input', () => {
        if (password.classList.contains('is-invalid')) setFieldError(password, validatePassword());
    });

    form.addEventListener('submit', (event) => {
        const emailErr = validateEmail();
        const passErr = validatePassword();
        setFieldError(email, emailErr);
        setFieldError(password, passErr);

        if (emailErr || passErr) {
            event.preventDefault();
            (emailErr ? email : password).focus();
            return;
        }

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Signing In...';
        }
    });
}

