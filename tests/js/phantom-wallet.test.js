import { test } from 'node:test';
import assert from 'node:assert/strict';
import { EventEmitter } from 'node:events';
import { createPhantomConnection, initPhantomWallet, shortenAddress } from '../../resources/js/phantom-wallet.js';

const ADDRESS = '26qv4GCcx98RihuK3c4T6ozB3J7L6VwCuFVc7Ta2A3Uo';
const OTHER = '11111111111111111111111111111111';
const key = (address = ADDRESS) => ({ toBase58: () => address });
const tick = () => new Promise(resolve => setImmediate(resolve));

class Phantom extends EventEmitter {
    isPhantom = true;
    calls = [];
    async connect(options) {
        this.calls.push(options);
        return { publicKey: key() };
    }
    async disconnect() { this.emit('disconnect'); }
    // Any accidental signing/payment call fails the tests.
    signMessage() { assert.fail('Signing is outside Phase 1'); }
    signTransaction() { assert.fail('Transactions are outside Phase 1'); }
    signAndSendTransaction() { assert.fail('Payments are outside Phase 1'); }
    request() { assert.fail('Generic RPC requests are outside Phase 1'); }
}

function setup(provider = new Phantom()) {
    let remembered = false;
    const connection = createPhantomConnection({
        getProvider: () => provider,
        remember: (value) => { remembered = value; },
        recalled: () => remembered,
    });
    return { provider, connection, remembered: () => remembered };
}

test('connect obtains only a public address and disconnect clears it and persistence', async () => {
    const { connection, provider, remembered } = setup();
    await connection.connect();
    assert.deepEqual(connection.snapshot(), { status: 'connected', address: ADDRESS, error: null });
    assert.deepEqual(provider.calls, [undefined]);
    assert.equal(remembered(), true);
    assert.equal(shortenAddress(ADDRESS), '26qv…A3Uo');
    await connection.disconnect();
    assert.deepEqual(connection.snapshot(), { status: 'idle', address: null, error: null });
    assert.equal(remembered(), false);
});

test('missing and non-Phantom providers are handled without throwing', async () => {
    for (const provider of [null, { isPhantom: false }]) {
        const { connection } = setup(provider);
        await connection.connect();
        assert.equal(connection.snapshot().error, 'missing');
    }
});

test('rejection and extension failures have recoverable states', async () => {
    for (const [error, expected] of [[{ code: 4001 }, 'rejected'], [new Error('Locked'), 'failed']]) {
        const { provider, connection } = setup();
        provider.connect = async () => { throw error; };
        await connection.connect();
        assert.equal(connection.snapshot().error, expected);
        assert.equal(connection.snapshot().address, null);
        provider.connect = async () => ({ publicKey: key() });
        await connection.connect();
        assert.equal(connection.snapshot().status, 'connected');
    }
});

test('invalid public address responses never produce connected state', async () => {
    const { provider, connection } = setup();
    provider.connect = async () => ({ publicKey: '<script>not a public key</script>' });
    await connection.connect();
    assert.equal(connection.snapshot().status, 'idle');
    assert.equal(connection.snapshot().error, 'failed');
});

test('account changes and extension-side disconnection update displayed state', async () => {
    const { connection, provider, remembered } = setup();
    await connection.connect();
    provider.emit('accountChanged', key(OTHER));
    assert.equal(connection.snapshot().address, OTHER);
    provider.emit('accountChanged', null);
    assert.equal(connection.snapshot().address, null);
    assert.equal(connection.snapshot().error, 'unavailable');
    assert.equal(remembered(), false);
    await connection.connect();
    provider.emit('disconnect');
    assert.equal(connection.snapshot().status, 'idle');
    assert.equal(connection.snapshot().address, null);
});

test('first page load never opens a connection prompt', async () => {
    const { provider, connection } = setup();
    await connection.restore();
    assert.equal(provider.calls.length, 0);
});

test('navigation restoration is trusted-only and silent rejection remains disconnected', async () => {
    const provider = new Phantom();
    const connection = createPhantomConnection({ getProvider: () => provider, recalled: () => true });
    await connection.restore();
    assert.deepEqual(provider.calls, [{ onlyIfTrusted: true }]);
    assert.equal(connection.snapshot().status, 'connected');
    const rejected = new Phantom();
    rejected.connect = async () => { throw { code: 4001 }; };
    const untrusted = createPhantomConnection({ getProvider: () => rejected, recalled: () => true });
    await untrusted.restore();
    assert.deepEqual(untrusted.snapshot(), { status: 'idle', address: null, error: null });
});

test('explicit disconnect prevents restoration and storage restrictions do not break connection', async () => {
    const { provider, connection } = setup();
    await connection.connect();
    await connection.disconnect();
    await connection.restore();
    assert.equal(provider.calls.length, 1);
    const blocked = createPhantomConnection({ getProvider: () => provider, remember: () => { throw new Error('Storage blocked'); } });
    await blocked.connect();
    assert.equal(blocked.snapshot().status, 'connected');
    await blocked.disconnect();
    assert.equal(blocked.snapshot().status, 'idle');
});

test('rapid repeated clicks produce one prompt and stale results cannot restore disconnected state', async () => {
    const { provider, connection } = setup();
    let resolve;
    let calls = 0;
    provider.connect = () => { calls++; return new Promise(done => { resolve = done; }); };
    const pending = connection.connect();
    assert.equal(connection.snapshot().status, 'connecting');
    await connection.connect();
    assert.equal(calls, 1);
    provider.emit('disconnect');
    resolve({ publicKey: key() });
    await pending;
    assert.equal(connection.snapshot().status, 'idle');
    assert.equal(connection.snapshot().address, null);
});

test('failed disconnect keeps the actual connected address and permits retry', async () => {
    const { provider, connection } = setup();
    await connection.connect();
    provider.disconnect = async () => { throw new Error('Extension failure'); };
    await connection.disconnect();
    assert.deepEqual(connection.snapshot(), { status: 'connected', address: ADDRESS, error: 'disconnectFailed' });
    provider.disconnect = async () => {};
    await connection.disconnect();
    assert.equal(connection.snapshot().address, null);
});

test('disposal removes extension listeners and ignores pending responses', async () => {
    const { provider, connection } = setup();
    let resolve;
    provider.connect = () => new Promise(done => { resolve = done; });
    const pending = connection.connect();
    connection.dispose();
    assert.equal(provider.listenerCount('disconnect'), 0);
    assert.equal(provider.listenerCount('accountChanged'), 0);
    resolve({ publicKey: key() });
    await pending;
    assert.equal(connection.snapshot().address, null);
});

// Minimal DOM surface for testing the actual button bindings and translated rendering.
function element() {
    return {
        dataset: {}, attributes: {}, listeners: {}, hidden: false, disabled: false, textContent: '',
        setAttribute(name, value) { this.attributes[name] = value; },
        addEventListener(name, callback) { this.listeners[name] = callback; },
        removeEventListener(name) { delete this.listeners[name]; },
        click() { this.listeners.click?.(); },
    };
}
function navigation() {
    const root = element();
    const messages = { connect: 'Wallet verbinden', disconnect: 'Trennen', connecting: 'Verbinden…', disconnecting: 'Trennen…', connected: 'Keine Transaktion ausgeführt.', pending: 'In Phantom bestätigen.', missing: 'Phantom fehlt.', insecure: 'HTTPS verwenden.', rejected: 'Abgebrochen.', address: 'Wallet-Adresse' };
    root.dataset.walletMessages = JSON.stringify(messages);
    const controls = Object.fromEntries(['connect', 'disconnect', 'address', 'status', 'install'].map(name => [name, element()]));
    root.querySelector = selector => controls[selector.replace('[data-wallet-', '').replace(']', '')];
    return { root, controls, messages };
}

test('navigation buttons render localized pending/connected/disconnected UI and full address title', async () => {
    const { root, controls } = navigation();
    const provider = new Phantom();
    const storage = new Map();
    const cleanup = initPhantomWallet(root, { getProvider: () => provider, storage: { getItem: key => storage.get(key), setItem: (key, value) => storage.set(key, value), removeItem: key => storage.delete(key) }, secureContext: true });
    assert.equal(controls.connect.textContent, 'Wallet verbinden');
    assert.equal(controls.connect.disabled, false);
    controls.connect.click();
    assert.equal(controls.connect.disabled, true);
    await tick();
    assert.equal(controls.connect.hidden, true);
    assert.equal(controls.address.textContent, '26qv…A3Uo');
    assert.equal(controls.address.title, ADDRESS);
    assert.equal(controls.disconnect.textContent, 'Trennen');
    assert.equal(controls.status.textContent, 'Keine Transaktion ausgeführt.');
    controls.disconnect.click();
    await tick();
    assert.equal(controls.address.hidden, true);
    assert.equal(controls.address.title, '');
    assert.equal(controls.connect.hidden, false);
    assert.equal(storage.size, 0);
    cleanup();
});

test('missing extension and insecure context show helpful messages, without opening external tabs', async () => {
    for (const secureContext of [true, false]) {
        const { root, controls } = navigation();
        initPhantomWallet(root, { getProvider: () => undefined, storage: null, secureContext });
        controls.connect.click();
        await tick();
        assert.equal(controls.status.textContent, secureContext ? 'Phantom fehlt.' : 'HTTPS verwenden.');
        assert.equal(controls.install.hidden, !secureContext);
    }
});
