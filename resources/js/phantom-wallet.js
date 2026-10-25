// Official Phantom Solana provider API, independent of Blade and the DOM.
// https://docs.phantom.com/solana/establishing-a-connection
// This module has no signing, transaction, RPC or key-handling methods.
export function publicAddress(key) {
    const address = key?.toBase58?.() ?? key?.toString?.();
    return typeof address === 'string' && /^[1-9A-HJ-NP-Za-km-z]{32,44}$/.test(address)
        ? address : null;
}

export function shortenAddress(address) {
    return `${address.slice(0, 4)}…${address.slice(-4)}`;
}

export function createPhantomConnection({ getProvider, remember = () => {}, recalled = () => false }) {
    let state = { status: 'idle', address: null, error: null };
    let provider;
    let generation = 0;
    let disposed = false;
    const subscribers = new Set();
    const snapshot = () => ({ ...state });
    const update = (next) => {
        if (disposed) return;
        state = { ...state, ...next };
        for (const subscriber of subscribers) subscriber(snapshot());
    };
    const persist = (connected) => {
        try { remember(connected); } catch { /* Storage may be blocked; connection still works. */ }
    };
    const disconnected = (error = null) => {
        generation++;
        persist(false);
        update({ status: 'idle', address: null, error });
    };
    const onDisconnect = () => disconnected();
    const onAccountChanged = (key) => {
        if (state.status !== 'connected') return;
        const address = publicAddress(key);
        if (!address) disconnected('unavailable');
        else update({ address, error: null });
    };
    const unbind = () => {
        provider?.removeListener('disconnect', onDisconnect);
        provider?.removeListener('accountChanged', onAccountChanged);
    };
    const detect = () => {
        const candidate = getProvider();
        if (!candidate?.isPhantom || typeof candidate.connect !== 'function'
            || typeof candidate.disconnect !== 'function' || typeof candidate.on !== 'function'
            || typeof candidate.removeListener !== 'function') return null;
        if (candidate !== provider) {
            unbind();
            provider = candidate;
            provider.on('disconnect', onDisconnect);
            provider.on('accountChanged', onAccountChanged);
        }
        return provider;
    };
    const busy = () => ['connecting', 'disconnecting'].includes(state.status);

    async function connect({ trustedOnly = false } = {}) {
        if (disposed || busy() || state.status === 'connected') return;
        let wallet;
        try { wallet = detect(); } catch { /* Report a controlled error below. */ }
        if (!wallet) {
            if (trustedOnly) { persist(false); return; }
            update({ error: 'missing' });
            return;
        }
        const operation = ++generation;
        update({ status: 'connecting', error: null });
        try {
            const result = await wallet.connect(trustedOnly ? { onlyIfTrusted: true } : undefined);
            if (disposed || operation !== generation) return;
            const address = publicAddress(result?.publicKey);
            if (!address) throw new Error('No public address returned');
            persist(true);
            update({ status: 'connected', address, error: null });
        } catch (error) {
            if (disposed || operation !== generation) return;
            persist(false);
            update({ status: 'idle', address: null, error: trustedOnly ? null : Number(error?.code) === 4001 ? 'rejected' : 'failed' });
        }
    }

    async function disconnect() {
        if (disposed || busy() || state.status !== 'connected') return;
        const operation = ++generation;
        update({ status: 'disconnecting', error: null });
        try {
            await provider.disconnect();
            if (!disposed && operation === generation) disconnected();
        } catch {
            if (!disposed && operation === generation) update({ status: 'connected', error: 'disconnectFailed' });
        }
    }

    return {
        snapshot, connect, disconnect,
        subscribe(callback) {
            subscribers.add(callback);
            callback(snapshot());
            return () => subscribers.delete(callback);
        },
        async restore() {
            let shouldRestore = false;
            try { shouldRestore = recalled(); } catch { /* Storage is optional. */ }
            if (shouldRestore) await connect({ trustedOnly: true });
        },
        dispose() {
            disposed = true;
            generation++;
            unbind();
            subscribers.clear();
        },
    };
}

export function initPhantomWallet(root, { getProvider = () => window.phantom?.solana, storage, secureContext = window.isSecureContext } = {}) {
    if (!root) return;
    if (storage === undefined) {
        try { storage = window.sessionStorage; } catch { storage = null; }
    }
    const messages = JSON.parse(root.dataset.walletMessages);
    const connectButton = root.querySelector('[data-wallet-connect]');
    const disconnectButton = root.querySelector('[data-wallet-disconnect]');
    const addressLabel = root.querySelector('[data-wallet-address]');
    const feedback = root.querySelector('[data-wallet-status]');
    const installLink = root.querySelector('[data-wallet-install]');
    const storageKey = 'solmarket.phantom.connected';
    const connection = createPhantomConnection({
        getProvider,
        remember: (connected) => connected ? storage?.setItem(storageKey, '1') : storage?.removeItem(storageKey),
        recalled: () => storage?.getItem(storageKey) === '1',
    });
    const unsubscribe = connection.subscribe(({ status, address, error }) => {
        const busy = status === 'connecting' || status === 'disconnecting';
        root.setAttribute('aria-busy', String(busy));
        connectButton.hidden = Boolean(address);
        connectButton.disabled = busy;
        connectButton.textContent = status === 'connecting' ? messages.connecting : messages.connect;
        disconnectButton.hidden = !address;
        disconnectButton.disabled = busy;
        disconnectButton.textContent = status === 'disconnecting' ? messages.disconnecting : messages.disconnect;
        addressLabel.hidden = !address;
        addressLabel.textContent = address ? shortenAddress(address) : '';
        addressLabel.title = address || '';
        addressLabel.setAttribute('aria-label', address ? `${messages.address}: ${address}` : '');
        const errorKey = error === 'missing' && !secureContext ? 'insecure' : error;
        const message = errorKey ? messages[errorKey] : status === 'connecting' ? messages.pending : status === 'connected' ? messages.connected : '';
        feedback.textContent = message;
        feedback.hidden = !message;
        feedback.dataset.tone = error ? 'error' : 'neutral';
        installLink.hidden = errorKey !== 'missing';
    });
    const onConnect = () => { void connection.connect(); };
    const onDisconnect = () => { void connection.disconnect(); };
    connectButton.addEventListener('click', onConnect);
    disconnectButton.addEventListener('click', onDisconnect);
    void connection.restore();

    return () => {
        connectButton.removeEventListener('click', onConnect);
        disconnectButton.removeEventListener('click', onDisconnect);
        unsubscribe();
        connection.dispose();
    };
}
