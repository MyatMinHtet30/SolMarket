import { initMarketFilters } from './market-filters';

initMarketFilters(document.querySelector('[data-market-discovery]'));

const checkoutRoot = document.querySelector('[data-product-checkout]');
if (checkoutRoot) {
    Promise.all([import('./product-checkout'), import('../css/product-checkout.css')])
        .then(([{ initProductCheckout }]) => initProductCheckout(checkoutRoot))
        .catch(() => {
            const status = checkoutRoot.querySelector('[data-checkout-status]');
            status.textContent = JSON.parse(checkoutRoot.dataset.messages).failed;
            status.hidden = false;
        });
}

const input = document.querySelector('#image');
const preview = document.querySelector('#image-preview');
let previewUrl;
input?.addEventListener('change', () => {
    if (previewUrl) URL.revokeObjectURL(previewUrl);
    const file = input.files[0];
    input.setCustomValidity(file && file.size > 4 * 1024 * 1024 ? input.dataset.sizeError : '');
    preview.hidden = !file || !input.checkValidity();
    if (!preview.hidden) {
        previewUrl = URL.createObjectURL(file);
        preview.src = previewUrl;
    }
});
