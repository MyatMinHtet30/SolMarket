import { PublicKey, SystemProgram, Transaction, TransactionInstruction } from '@solana/web3.js';

export const MEMO_PROGRAM = 'MemoSq4gqABAXKb96qnH8TysNcWxMyWCqXgDLGmfcHr';

export function buildDemoTransaction(order) {
    if (order.network !== 'devnet' || !Number.isSafeInteger(order.lamports) || order.lamports < 1 || order.lamports > 10_000_000) {
        throw new Error('Invalid Devnet demo configuration');
    }
    const payer = new PublicKey(order.payer);
    const recipient = new PublicKey(order.recipient);
    if (payer.equals(recipient)) throw new Error('Payer equals recipient');
    return new Transaction({ feePayer: payer, recentBlockhash: order.blockhash }).add(
        SystemProgram.transfer({ fromPubkey: payer, toPubkey: recipient, lamports: order.lamports }),
        new TransactionInstruction({
            programId: new PublicKey(MEMO_PROGRAM),
            keys: [{ pubkey: payer, isSigner: true, isWritable: false }],
            data: new TextEncoder().encode(order.memo),
        }),
    );
}

export async function signDemoTransaction(order, provider) {
    if (!provider?.isPhantom || !provider.isConnected || typeof provider.signTransaction !== 'function') {
        throw Object.assign(new Error('Wallet not connected'), { reason: 'wallet' });
    }
    if (provider.publicKey?.toString() !== order.payer) {
        throw Object.assign(new Error('Account changed'), { reason: 'changed' });
    }
    const transaction = buildDemoTransaction(order);
    const expected = transaction.serializeMessage();
    // Sign only. Phantom is deliberately not allowed to choose a broadcast endpoint.
    const signed = await provider.signTransaction(transaction);
    if (provider.publicKey?.toString() !== order.payer || !provider.isConnected) {
        throw Object.assign(new Error('Account changed'), { reason: 'changed' });
    }
    const message = signed.serializeMessage();
    if (message.length !== expected.length || message.some((byte, index) => byte !== expected[index])) {
        throw Object.assign(new Error('Transaction changed'), { reason: 'invalidTransaction' });
    }
    return btoa(String.fromCharCode(...signed.serialize()));
}

export function initDemoPayment(root, { fetcher = window.fetch.bind(window), getProvider = () => window.phantom?.solana, storage, sleep = ms => new Promise(resolve => setTimeout(resolve, ms)) } = {}) {
    if (!root) return;
    try { storage ??= window.sessionStorage; } catch { /* Browser storage is optional. */ }
    const messages = JSON.parse(root.dataset.messages);
    const pay = root.querySelector('[data-pay]');
    const check = root.querySelector('[data-check]');
    const retry = root.querySelector('[data-retry]');
    const status = root.querySelector('[data-payment-status]');
    const receipt = root.querySelector('[data-receipt]');
    const signature = root.querySelector('[data-signature]');
    const orderLabel = root.querySelector('[data-order-id]');
    const explorer = root.querySelector('[data-explorer]');
    const key = `solmarket.demo-order.${root.dataset.productId}`;
    let order = null;
    let busy = false;
    const say = reason => { status.textContent = messages[reason] || messages.failed; };
    const remember = id => { try { storage?.setItem(key, id); } catch { /* Optional. */ } };
    const render = () => {
        pay.disabled = busy || root.dataset.ready !== '1' || order?.status === 'confirmed' || Boolean(order?.signature && order.status === 'pending');
        pay.textContent = busy ? messages.working : messages.pay;
        const pending = order?.status === 'pending' && Boolean(order?.signature);
        check.hidden = !pending;
        retry.hidden = !pending;
        check.disabled = retry.disabled = busy;
        root.setAttribute('aria-busy', String(busy));
        receipt.hidden = !order?.signature;
        orderLabel.textContent = order?.id || '';
        signature.textContent = order?.signature || '';
        // Construct the URL locally: never trust a client-provided cluster or arbitrary URL.
        if (order?.signature && /^[1-9A-HJ-NP-Za-km-z]{64,88}$/.test(order.signature)) {
            explorer.href = `https://explorer.solana.com/tx/${order.signature}?cluster=devnet`;
        } else explorer.removeAttribute('href');
    };
    const request = async (url, body = null) => {
        const response = await fetcher(url, {
            method: body === null ? 'GET' : 'POST', credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': root.dataset.csrf },
            ...(body === null ? {} : { body: JSON.stringify(body) }),
        });
        let data;
        try { data = await response.json(); } catch { throw { reason: 'server' }; }
        if (!response.ok) throw { reason: data.error || 'server' };
        if (data.network !== 'devnet' || String(data.product_id) !== root.dataset.productId) throw { reason: 'invalidTransaction' };
        order = data;
        remember(order.id);
        render();
        return data;
    };
    const endpoint = action => `${root.dataset.orderBase}/${encodeURIComponent(order.id)}${action ? '/'+action : ''}`;
    const outcome = () => {
        if (order?.status === 'confirmed') say('confirmed');
        else if (order?.status === 'failed') say(order.failure_reason || 'failed');
        else if (order?.status === 'cancelled') say('cancelled');
        else say('pending');
    };
    const poll = async () => {
        say('checking');
        for (let attempt = 0; attempt < 20; attempt++) {
            await request(endpoint('verify'), {});
            if (order.status !== 'pending') break;
            await sleep(2500);
        }
        outcome();
    };
    const run = async operation => {
        if (busy) return;
        busy = true;
        render();
        try { await operation(); }
        catch (error) { say(error.reason || 'server'); }
        finally { busy = false; render(); }
    };
    pay.addEventListener('click', () => run(async () => {
        const provider = getProvider();
        if (!provider?.isPhantom || !provider.isConnected || !provider.publicKey) { say('wallet'); return; }
        say('working');
        await request(root.dataset.createUrl, { payer: provider.publicKey.toString() });
        if (order.signature || order.status !== 'pending') { await poll(); return; }
        say('approve');
        let wire;
        try { wire = await signDemoTransaction(order, provider); }
        catch (error) {
            // Signing does not broadcast. Close the unsubmitted order before any retry.
            await request(endpoint('cancel'), {});
            if (Number(error?.code) === 4001) {
                say('cancelled');
                return;
            }
            throw { reason: error?.reason || 'failed' };
        }
        try { await request(endpoint('submit'), { transaction: wire }); }
        catch (error) {
            // Submission may have reached Devnet even if the HTTP response was lost.
            // Reload the server order; never create/sign a replacement automatically.
            await request(endpoint());
            if (order.signature) { say(error.reason || 'pending'); return; }
            throw error;
        }
        await poll();
    }));
    check.addEventListener('click', () => run(poll));
    retry.addEventListener('click', () => run(async () => {
        await request(endpoint('verify'), {});
        if (order.status !== 'pending') { outcome(); return; }
        await request(endpoint('submit'), {}); // Exact saved transaction and signature only.
        await poll();
    }));
    let saved;
    try { saved = storage?.getItem(key); } catch { /* Optional. */ }
    if (saved && /^[a-f0-9-]{36}$/i.test(saved)) {
        order = { id: saved, status: 'pending' };
        void run(async () => { await request(endpoint()); if (order.signature) await poll(); });
    } else render();
}
