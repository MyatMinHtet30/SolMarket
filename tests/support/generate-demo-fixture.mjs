// Test-only deterministic identities, never funded or used as application defaults.
import { Keypair } from '@solana/web3.js';
import { writeFileSync } from 'node:fs';
import { buildDemoTransaction } from '../../resources/js/demo-payment.js';

const payer = Keypair.fromSeed(new Uint8Array(32).fill(1));
const recipient = Keypair.fromSeed(new Uint8Array(32).fill(2));
const id = '00000000-0000-4000-8000-000000000001';
const order = {
    id, payer: payer.publicKey.toString(), recipient: recipient.publicKey.toString(),
    lamports: 1000000, blockhash: Keypair.fromSeed(new Uint8Array(32).fill(3)).publicKey.toString(),
    memo: `SolMarket demo ${id}`, network: 'devnet',
};
const transaction = buildDemoTransaction(order);
transaction.sign(payer);
writeFileSync(new URL('./demo-transaction.json', import.meta.url), JSON.stringify({ ...order, wire: transaction.serialize().toString('base64') }, null, 2)+'\n');
