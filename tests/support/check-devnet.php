<?php

// Optional read-only connectivity check. This never signs, submits or funds anything.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    $latest = $app->make(\App\Services\DevnetRpc::class)->call('getLatestBlockhash', [['commitment' => 'confirmed']]);
    echo json_encode([
        'network' => 'devnet', 'genesis_verified' => true,
        'last_valid_block_height' => data_get($latest, 'value.lastValidBlockHeight'),
        'demo_configuration_ready' => \App\Services\DemoPayment::configured(),
    ], JSON_PRETTY_PRINT).PHP_EOL;
} catch (\App\Exceptions\DemoPaymentException $exception) {
    fwrite(STDERR, 'Devnet connectivity check failed: '.$exception->reason.PHP_EOL);
    exit(1);
}
