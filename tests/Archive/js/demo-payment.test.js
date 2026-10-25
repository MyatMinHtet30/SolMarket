import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { Keypair, PublicKey, SystemProgram, Transaction } from '@solana/web3.js';
import { buildDemoTransaction, signDemoTransaction, initDemoPayment, MEMO_PROGRAM } from '../../resources/js/demo-payment.js';

const fixture = JSON.parse(readFileSync(new URL('../support/demo-transaction.json', import.meta.url)));
const payer = Keypair.fromSeed(new Uint8Array(32).fill(1)); // Test identity only.
const signature = Transaction.from(Buffer.from(fixture.wire, 'base64')).signature;
const signature58 = '1'.repeat(64); // Display-only mock RPC signature in UI tests.
const order = (overrides = {}) => ({ ...fixture, product_id: 1, status: 'pending', signature: null, ...overrides });
const provider = () => ({
    isPhantom: true, isConnected: true, publicKey: payer.publicKey,
    async signTransaction(transaction) { transaction.sign(payer); return transaction; },
    signAndSendTransaction() { assert.fail('Phantom must never choose a network or broadcast'); },
});

test('constructs exactly the fixed native transfer and signed order memo, independently of EUR price', () => {
    const transaction = buildDemoTransaction(order({ price: '999.99' }));
    assert.equal(transaction.feePayer.toString(), fixture.payer);
    assert.equal(transaction.recentBlockhash, fixture.blockhash);
    assert.equal(transaction.instructions.length, 2);
    assert.ok(transaction.instructions[0].programId.equals(SystemProgram.programId));
    assert.equal(transaction.instructions[0].keys[0].pubkey.toString(), fixture.payer);
    assert.equal(transaction.instructions[0].keys[1].pubkey.toString(), fixture.recipient);
    assert.equal(transaction.instructions[0].data.readBigUInt64LE(4), 1000000n);
    assert.equal(transaction.instructions[1].programId.toString(), MEMO_PROGRAM);
    assert.equal(new TextDecoder().decode(transaction.instructions[1].data), fixture.memo);
    assert.equal(transaction.instructions[1].keys[0].isSigner, true);
});

test('rejects non-Devnet, excessive amounts and self-transfers before invoking Phantom', () => {
    for (const overrides of [{ network: 'mainnet' }, { lamports: 10000001 }, { lamports: .1 }, { recipient: fixture.payer }]) {
        assert.throws(() => buildDemoTransaction(order(overrides)));
    }
});

test('Phantom signs without broadcasting and returns the real SDK wire accepted by the PHP fixture', async () => {
    const wire = await signDemoTransaction(order(), provider());
    assert.equal(wire, fixture.wire);
    const decoded = Transaction.from(Buffer.from(wire, 'base64'));
    assert.equal(decoded.verifySignatures(), true);
    assert.deepEqual(decoded.signature, signature);
});

test('unconnected wallets, cancellation, changed accounts and altered signed messages cannot submit', async () => {
    await assert.rejects(signDemoTransaction(order(), null), error => error.reason === 'wallet');
    const rejected = provider();
    rejected.signTransaction = async () => { throw { code: 4001 }; };
    await assert.rejects(signDemoTransaction(order(), rejected), error => error.code === 4001);
    const changed = provider();
    changed.signTransaction = async (transaction) => { transaction.sign(payer); changed.publicKey = new PublicKey(fixture.recipient); return transaction; };
    await assert.rejects(signDemoTransaction(order(), changed), error => error.reason === 'changed');
    const altered = provider();
    altered.signTransaction = async (transaction) => { transaction.instructions[0].data[4] ^= 1; transaction.sign(payer); return transaction; };
    await assert.rejects(signDemoTransaction(order(), altered), error => error.reason === 'invalidTransaction');
});

function element() {
    return {
        hidden: false, disabled: false, textContent: '', attributes: {}, listeners: {},
        addEventListener(name, callback) { this.listeners[name] = callback; },
        setAttribute(name, value) { this.attributes[name] = value; },
        removeAttribute(name) { delete this.attributes[name]; delete this[name]; },
        async click() { if (!this.disabled) await this.listeners.click?.(); },
    };
}
function ui() {
    const root = element();
    const controls = Object.fromEntries(['pay', 'check', 'retry', 'payment-status', 'receipt', 'signature', 'order-id', 'explorer'].map(name => [name, element()]));
    const messages = Object.fromEntries(['pay', 'working', 'approve', 'checking', 'confirmed', 'pending', 'cancelled', 'wallet', 'changed', 'failed', 'server', 'rpcUnavailable'].map(value => [value, `translated:${value}`]));
    root.dataset = { productId: '1', ready: '1', createUrl: '/products/1/demo-orders', orderBase: '/demo-orders', csrf: 'test-csrf', messages: JSON.stringify(messages) };
    root.querySelector = selector => controls[selector.slice(6, -1)];
    const saved = new Map();
    const storage = { setItem: (key, value) => saved.set(key, value), getItem: key => saved.get(key) };
    return { root, controls, storage, saved };
}
const response = (data, ok = true) => ({ ok, async json() { return data; } });

test('UI only announces success after backend confirmation, and builds a Devnet-only Explorer link', async () => {
    const { root, controls, storage } = ui();
    const calls = [];
    let verifications = 0;
    initDemoPayment(root, {
        storage, getProvider: provider, sleep: async () => {},
        fetcher: async (url, options) => {
            calls.push(url);
            assert.equal(options.headers['X-CSRF-TOKEN'], 'test-csrf');
            if (url.endsWith('/submit')) {
                assert.equal(controls['payment-status'].textContent, 'translated:approve');
                assert.equal(JSON.parse(options.body).transaction, fixture.wire);
                return response(order({ signature: signature58 }));
            }
            if (url.endsWith('/verify')) {
                assert.notEqual(controls['payment-status'].textContent, 'translated:confirmed');
                verifications++;
                return response(order({ signature: signature58, status: verifications === 1 ? 'pending' : 'confirmed' }));
            }
            return response(order());
        },
    });
    await controls.pay.click();
    assert.equal(verifications, 2);
    assert.equal(controls['payment-status'].textContent, 'translated:confirmed');
    assert.equal(controls.pay.disabled, true);
    assert.equal(controls.signature.textContent, signature58);
    assert.equal(controls.explorer.href, `https://explorer.solana.com/tx/${signature58}?cluster=devnet`);
    assert.equal(calls.length, 4);
});

test('rejected approval cancels the pending order and never calls the submit endpoint', async () => {
    const { root, controls, storage } = ui();
    const wallet = provider();
    wallet.signTransaction = async () => { throw { code: 4001 }; };
    const calls = [];
    initDemoPayment(root, { storage, getProvider: () => wallet, fetcher: async url => {
        calls.push(url);
        return response(order({ status: url.endsWith('/cancel') ? 'cancelled' : 'pending' }));
    } });
    await controls.pay.click();
    assert.equal(controls['payment-status'].textContent, 'translated:cancelled');
    assert.equal(calls.some(url => url.endsWith('/submit')), false);
    assert.equal(controls.pay.disabled, false);
});

test('submission timeout keeps the signed pending order and blocks a new payment', async () => {
    const { root, controls, storage } = ui();
    initDemoPayment(root, { storage, getProvider: provider, fetcher: async url => {
        if (url.endsWith('/submit')) throw new Error('Network timeout');
        return response(order({ signature: url.endsWith('/demo-orders') ? null : signature58 }));
    } });
    await controls.pay.click();
    assert.equal(controls.pay.disabled, true);
    assert.equal(controls.check.hidden, false);
    assert.equal(controls.retry.hidden, false);
    assert.notEqual(controls['payment-status'].textContent, 'translated:confirmed');
});

test('existing signed order is verified without requesting another signature', async () => {
    const { root, controls, storage } = ui();
    const wallet = provider();
    wallet.signTransaction = () => assert.fail('Do not sign another transfer');
    initDemoPayment(root, { storage, getProvider: () => wallet, sleep: async () => {}, fetcher: async url =>
        response(order({ signature: signature58, status: url.endsWith('/verify') ? 'confirmed' : 'pending' })) });
    await controls.pay.click();
    assert.equal(controls['payment-status'].textContent, 'translated:confirmed');
});

test('missing wallet and unavailable configuration cannot submit any transaction', async () => {
    const { root, controls, storage } = ui();
    initDemoPayment(root, { storage, getProvider: () => null, fetcher: () => assert.fail('No request allowed') });
    await controls.pay.click();
    assert.equal(controls['payment-status'].textContent, 'translated:wallet');
    root.dataset.ready = '0';
    initDemoPayment(root, { storage, getProvider: provider, fetcher: () => assert.fail('No request allowed') });
    assert.equal(controls.pay.disabled, true);
});
