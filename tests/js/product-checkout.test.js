import { test } from 'node:test';
import assert from 'node:assert/strict';
import { EventEmitter } from 'node:events';
import { initProductCheckout } from '../../resources/js/product-checkout.js';

const ADDRESS = '26qv4GCcx98RihuK3c4T6ozB3J7L6VwCuFVc7Ta2A3Uo';
const tick = () => new Promise(resolve => setImmediate(resolve));
function element() {
    return {
        hidden: false, disabled: false, attributes: {}, listeners: {}, dataset: {}, textContent: '',
        setAttribute(name, value) { this.attributes[name] = value; },
        addEventListener(name, callback) { this.listeners[name] = callback; },
        removeEventListener(name) { delete this.listeners[name]; },
        focus() { this.focused = true; }, scrollIntoView(options) { this.scrolled = options; },
        click() { if (!this.disabled) this.listeners.click?.({ preventDefault() {} }); },
    };
}
function setup() {
    const root = element();
    const messages = { connect: 'Wallet verbinden', connecting: 'Verbinden…', connected: 'Wallet verbunden',
        address: 'Adresse', blocked: 'Zahlung gesperrt', missing: 'Phantom fehlt', rejected: 'Abgebrochen',
        failed: 'Verbindung fehlgeschlagen', unavailable: 'Getrennt' };
    root.dataset.messages = JSON.stringify(messages);
    const controls = Object.fromEntries(['open', 'close', 'panel', 'heading', 'connect', 'address', 'status', 'continue'].map(name => [name, element()]));
    controls.panel.hidden = true;
    controls.continue.disabled = true;
    root.querySelector = selector => controls[selector.slice('[data-checkout-'.length, -1)];
    const provider = new EventEmitter();
    provider.isPhantom = true;
    let lookups = 0;
    let connections = 0;
    provider.connect = async () => { connections++; return { publicKey: { toBase58: () => ADDRESS } }; };
    provider.disconnect = async () => {};
    provider.signTransaction = () => assert.fail('Transfers must remain blocked');
    provider.signAndSendTransaction = () => assert.fail('No automatic or manual broadcast while review is unresolved');
    const cleanup = initProductCheckout(root, { getProvider: () => { lookups++; return provider; }, reducedMotion: () => true });
    return { controls, provider, cleanup, lookups: () => lookups, connections: () => connections };
}

test('checkout initialization and opening never connect, sign, submit or show payment confirmation', () => {
    const { controls, lookups, connections } = setup();
    assert.equal(lookups(), 0);
    assert.equal(connections(), 0);
    assert.equal(controls.panel.hidden, true);
    assert.equal(controls.continue.disabled, true);
    controls.open.click();
    assert.equal(controls.panel.hidden, false);
    assert.equal(controls.open.attributes['aria-expanded'], 'true');
    assert.equal(controls.heading.focused, true);
    assert.equal(controls.panel.scrolled.behavior, 'instant');
    assert.equal(lookups(), 0);
    assert.equal(controls.status.textContent, '');
});

test('explicit connection shares an address but cannot enable Continue to Payment', async () => {
    const { controls, connections } = setup();
    controls.open.click();
    controls.connect.click();
    assert.equal(controls.connect.disabled, true);
    await tick();
    assert.equal(connections(), 1);
    assert.equal(controls.address.textContent, '26qv…A3Uo');
    assert.equal(controls.address.title, ADDRESS);
    assert.equal(controls.status.textContent, 'Wallet verbunden');
    assert.equal(controls.continue.disabled, true);
    // DOM tampering cannot activate retained payment code.
    controls.continue.disabled = false;
    controls.continue.click();
    assert.equal(controls.continue.disabled, true);
    assert.equal(controls.status.textContent, 'Zahlung gesperrt');
});

test('wallet rejection is recoverable and never changes transfer availability', async () => {
    const { controls, provider } = setup();
    provider.connect = async () => { throw { code: 4001 }; };
    controls.connect.click();
    await tick();
    assert.equal(controls.status.textContent, 'Abgebrochen');
    assert.equal(controls.connect.disabled, false);
    assert.equal(controls.address.hidden, true);
    assert.equal(controls.continue.disabled, true);
});

test('Escape and close return focus to Buy Now; cleanup removes provider listeners', async () => {
    const { controls, provider, cleanup } = setup();
    controls.open.click();
    controls.panel.listeners.keydown({ key: 'Escape', preventDefault() {} });
    assert.equal(controls.panel.hidden, true);
    assert.equal(controls.open.focused, true);
    assert.equal(controls.open.attributes['aria-expanded'], 'false');
    controls.open.click();
    controls.close.click();
    assert.equal(controls.panel.hidden, true);
    controls.connect.click();
    await tick();
    cleanup();
    assert.equal(provider.listenerCount('accountChanged'), 0);
    assert.equal(Object.keys(controls.connect.listeners).length, 0);
});

test('there is no checkout initialization on pages without the product-only root', () => {
    let requested = false;
    assert.equal(initProductCheckout(null, { getProvider: () => { requested = true; } }), undefined);
    assert.equal(requested, false);
});
