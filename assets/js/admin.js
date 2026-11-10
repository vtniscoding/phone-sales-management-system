/**
 * Admin Panel Interactive Controls (NovaPhone).
 * Handles responsive sidebar drawer, destructive action confirmations,
 * image upload preview/validation, and dynamic product variant rows.
 */

document.addEventListener('DOMContentLoaded', () => {
    initAdminSidebar();
    initDeleteConfirmations();
    initProductImagePreview();
    initVariantEditor();
});

function initAdminSidebar() {
    const toggleBtn = document.getElementById('admin-sidebar-toggle');
    const sidebar = document.getElementById('admin-sidebar');
    if (!toggleBtn || !sidebar) return;

    toggleBtn.addEventListener('click', () => {
        const isOpen = sidebar.classList.toggle('is-open');
        toggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
}

function initDeleteConfirmations() {
    document.querySelectorAll('form[data-confirm-delete]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const msg = form.getAttribute('data-confirm-delete') || 'Are you sure you want to delete this item?';
            if (!window.confirm(msg)) {
                event.preventDefault();
            }
        });
    });
}

function initProductImagePreview() {
    const input = document.getElementById('product-image-input');
    const preview = document.getElementById('product-image-preview');
    const errorEl = document.getElementById('product-image-error');
    if (!input || !preview) return;

    const maxBytes = 2 * 1024 * 1024;
    const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

    input.addEventListener('change', () => {
        const file = input.files && input.files[0];
        if (!file) return;

        if (!allowedTypes.includes(file.type)) {
            if (errorEl) errorEl.textContent = 'Only JPG, PNG, and WebP images are allowed.';
            input.value = '';
            return;
        }
        if (file.size > maxBytes) {
            if (errorEl) errorEl.textContent = 'Image file size must not exceed 2 MB.';
            input.value = '';
            return;
        }

        if (errorEl) errorEl.textContent = '';
        const reader = new FileReader();
        reader.onload = (e) => {
            if (e.target && typeof e.target.result === 'string') {
                preview.src = e.target.result;
            }
        };
        reader.readAsDataURL(file);
    });
}

function initVariantEditor() {
    const container = document.getElementById('variants-container');
    const template = document.getElementById('variant-row-template');
    const addBtn = document.getElementById('add-variant-btn');
    if (!container || !template || !addBtn) return;

    const reindexRadios = () => {
        const rows = container.querySelectorAll('[data-variant-row]');
        let hasChecked = false;
        rows.forEach((row, index) => {
            const radio = row.querySelector('input[type="radio"][name="default_variant_index"]');
            if (radio) {
                radio.value = String(index);
                if (radio.checked) hasChecked = true;
            }
            const removeBtn = row.querySelector('[data-remove-variant]');
            if (removeBtn) {
                removeBtn.disabled = rows.length <= 1;
            }
        });

        if (!hasChecked && rows.length > 0) {
            const firstRadio = rows[0].querySelector('input[type="radio"][name="default_variant_index"]');
            if (firstRadio) firstRadio.checked = true;
        }
    };

    addBtn.addEventListener('click', () => {
        const clone = template.content.cloneNode(true);
        container.appendChild(clone);
        reindexRadios();
    });

    container.addEventListener('click', (event) => {
        const btn = event.target.closest('[data-remove-variant]');
        if (!btn) return;
        const rows = container.querySelectorAll('[data-variant-row]');
        if (rows.length <= 1) return;

        const row = btn.closest('[data-variant-row]');
        if (row) {
            row.remove();
            reindexRadios();
        }
    });

    reindexRadios();
}

