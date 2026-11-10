/**
 * Interactive Product Detail Behavior (NovaPhone).
 * Handles variant selection (RAM/Storage/Color -> dynamic price, SKU, stock, image),
 * gallery thumbnail switching, and quantity stepper bounds.
 */

document.addEventListener('DOMContentLoaded', () => {
    initGalleryThumbs();
    initVariantSelector();
    initQuantityControls();
});

function initGalleryThumbs() {
    const mainImg = document.getElementById('detail-main-image');
    const thumbBtns = document.querySelectorAll('.detail-thumb-btn');
    if (!mainImg || thumbBtns.length === 0) return;

    thumbBtns.forEach((btn) => {
        btn.addEventListener('click', () => {
            const src = btn.getAttribute('data-image-src');
            const alt = btn.getAttribute('data-image-alt');
            if (src) mainImg.src = src;
            if (alt) mainImg.alt = alt;

            thumbBtns.forEach((b) => b.classList.remove('is-active'));
            btn.classList.add('is-active');
        });
    });
}

function initVariantSelector() {
    const dataEl = document.getElementById('product-variants-data');
    const buttons = document.querySelectorAll('.variant-btn[data-variant-id]');
    if (!dataEl || buttons.length === 0) return;

    let variants = [];
    try {
        variants = JSON.parse(dataEl.textContent || '[]');
    } catch {
        return;
    }

    const variantMap = new Map(variants.map((v) => [String(v.id), v]));

    const variantInput = document.getElementById('selected-variant-id');
    const priceEl = document.getElementById('detail-price');
    const origPriceEl = document.getElementById('detail-original-price');
    const discountEl = document.getElementById('detail-discount-badge');
    const skuEl = document.getElementById('detail-sku');
    const stockBadge = document.getElementById('detail-stock-badge');
    const qtyInput = document.getElementById('detail-quantity');
    const addBtn = document.getElementById('add-to-cart-btn');
    const mainImg = document.getElementById('detail-main-image');
    const specRamStorage = document.getElementById('spec-ram-storage');
    const specColor = document.getElementById('spec-color');

    const lowThreshold = stockBadge ? Number(stockBadge.getAttribute('data-low-threshold') || '5') : 5;

    const applyVariant = (variant) => {
        if (!variant) return;

        if (variantInput) variantInput.value = String(variant.id);
        if (priceEl) priceEl.textContent = variant.price_formatted;

        if (origPriceEl) {
            if (variant.original_formatted) {
                origPriceEl.textContent = variant.original_formatted;
                origPriceEl.classList.remove('is-hidden');
            } else {
                origPriceEl.textContent = '';
                origPriceEl.classList.add('is-hidden');
            }
        }

        if (discountEl) {
            if (variant.discount_percent > 0) {
                discountEl.textContent = `-${variant.discount_percent}%`;
                discountEl.classList.remove('is-hidden');
            } else {
                discountEl.classList.add('is-hidden');
            }
        }

        if (skuEl) skuEl.textContent = variant.sku;
        if (specRamStorage) specRamStorage.textContent = `${variant.ram} / ${variant.storage}`;
        if (specColor) specColor.textContent = variant.color_name;

        if (mainImg && variant.image_url) {
            mainImg.src = variant.image_url;
            if (variant.image_alt) mainImg.alt = variant.image_alt;
        }

        const stock = Number(variant.stock_qty || 0);
        if (stockBadge) {
            stockBadge.classList.remove('badge-stock', 'badge-low', 'badge-out');
            if (stock <= 0) {
                stockBadge.classList.add('badge-out');
                stockBadge.textContent = 'Out of Stock';
            } else if (stock <= lowThreshold) {
                stockBadge.classList.add('badge-low');
                stockBadge.textContent = `Low Stock (${stock} left)`;
            } else {
                stockBadge.classList.add('badge-stock');
                stockBadge.textContent = `In Stock (${stock} available)`;
            }
        }

        if (qtyInput) {
            if (stock <= 0) {
                qtyInput.value = '0';
                qtyInput.min = '0';
                qtyInput.max = '1';
                qtyInput.disabled = true;
            } else {
                qtyInput.disabled = false;
                qtyInput.min = '1';
                qtyInput.max = String(stock);
                const currentVal = Number(qtyInput.value || '1');
                qtyInput.value = String(Math.min(stock, Math.max(1, currentVal)));
            }
        }

        if (addBtn) {
            addBtn.disabled = stock <= 0;
            addBtn.textContent = stock <= 0 ? 'Out of Stock' : 'Add to Shopping Cart';
        }
    };

    buttons.forEach((btn) => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-variant-id');
            const variant = id ? variantMap.get(id) : null;
            if (!variant) return;

            buttons.forEach((b) => {
                b.classList.remove('is-selected');
                b.setAttribute('aria-pressed', 'false');
            });
            btn.classList.add('is-selected');
            btn.setAttribute('aria-pressed', 'true');

            applyVariant(variant);
        });
    });
}

function initQuantityControls() {
    const qtyInput = document.getElementById('detail-quantity');
    const decBtn = document.getElementById('qty-decrease');
    const incBtn = document.getElementById('qty-increase');
    if (!qtyInput || !decBtn || !incBtn) return;

    const clampQuantity = () => {
        if (qtyInput.disabled) return;
        const min = Number(qtyInput.min || '1');
        const max = Number(qtyInput.max || '99');
        let val = parseInt(qtyInput.value, 10);
        if (Number.isNaN(val) || val < min) val = min;
        if (val > max) val = max;
        qtyInput.value = String(val);
    };

    decBtn.addEventListener('click', () => {
        if (qtyInput.disabled) return;
        const min = Number(qtyInput.min || '1');
        const current = parseInt(qtyInput.value || '1', 10);
        qtyInput.value = String(Math.max(min, current - 1));
    });

    incBtn.addEventListener('click', () => {
        if (qtyInput.disabled) return;
        const max = Number(qtyInput.max || '99');
        const current = parseInt(qtyInput.value || '1', 10);
        qtyInput.value = String(Math.min(max, current + 1));
    });

    qtyInput.addEventListener('change', clampQuantity);
}

