<?php
// Header wallets stay off. Product checkout permits connection only; transfers stay locked.
return [
    'presentation_only' => true,
    'enabled' => false,
    'wallet_enabled' => false,
    'payments_enabled' => false,
    // Retained review metadata only. The presentation/payment guards above stay locked.
    'demo_recipient' => env('SOLANA_DEMO_RECIPIENT') ?: null,
    'demo_lamports' => 1000000,
    'network' => 'devnet',
    // Future development target only. Wallet connection makes no RPC requests.
    'rpc_url' => 'https://api.devnet.solana.com',
];
