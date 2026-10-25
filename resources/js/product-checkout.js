import { createPhantomConnection, shortenAddress } from './phantom-wallet.js';

// Product-only checkout preview. Recipient review is unresolved: no payment code is loaded.
export function initProductCheckout(root, {
    getProvider = () => window.phantom?.solana,
    reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches,
} = {}) {
    if (!root) return;
    const messages = JSON.parse(root.dataset.messages);
    const openButton = root.querySelector('[data-checkout-open]');
    const closeButton = root.querySelector('[data-checkout-close]');
    const panel = root.querySelector('[data-checkout-panel]');
    const heading = root.querySelector('[data-checkout-heading]');
    const connectButton = root.querySelector('[data-checkout-connect]');
    const address = root.querySelector('[data-checkout-address]');
    const feedback = root.querySelector('[data-checkout-status]');
    const continueButton = root.querySelector('[data-checkout-continue]');
    const connection = createPhantomConnection({ getProvider });
    // Deliberately no restore(), storage access, network requests, signing or automatic connect.
    const unsubscribe = connection.subscribe(state => {
        connectButton.disabled = state.status === 'connecting' || Boolean(state.address);
        connectButton.textContent = state.status === 'connecting' ? messages.connecting : state.address ? messages.connected : messages.connect;
        address.hidden = !state.address;
        address.textContent = state.address ? shortenAddress(state.address) : '';
        address.title = state.address || '';
        address.setAttribute('aria-label', state.address ? `${messages.address}: ${state.address}` : '');
        feedback.textContent = state.error ? messages[state.error] || messages.failed : state.address ? messages.connected : '';
        feedback.hidden = !feedback.textContent;
        continueButton.disabled = true;
    });
    const setOpen = expanded => {
        panel.hidden = !expanded;
        openButton.setAttribute('aria-expanded', String(expanded));
        if (expanded) {
            heading.focus({ preventScroll: true });
            panel.scrollIntoView?.({ behavior: reducedMotion() ? 'instant' : 'smooth', block: 'nearest' });
        } else openButton.focus();
    };
    const onOpen = () => setOpen(true);
    const onClose = () => setOpen(false);
    const onConnect = () => { void connection.connect(); };
    const onContinue = event => {
        // Even a synthetic click / removed disabled attribute must not initiate a transfer.
        event.preventDefault();
        continueButton.disabled = true;
        feedback.textContent = messages.blocked;
        feedback.hidden = false;
    };
    const onKeydown = event => {
        if (event.key === 'Escape' && !panel.hidden) { event.preventDefault(); setOpen(false); }
    };
    openButton.addEventListener('click', onOpen);
    closeButton.addEventListener('click', onClose);
    connectButton.addEventListener('click', onConnect);
    continueButton.addEventListener('click', onContinue);
    panel.addEventListener('keydown', onKeydown);

    return () => {
        openButton.removeEventListener('click', onOpen);
        closeButton.removeEventListener('click', onClose);
        connectButton.removeEventListener('click', onConnect);
        continueButton.removeEventListener('click', onContinue);
        panel.removeEventListener('keydown', onKeydown);
        unsubscribe();
        connection.dispose();
    };
}
