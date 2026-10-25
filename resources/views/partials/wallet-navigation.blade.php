@php
    $walletMessages = [
        'connect' => __('Connect Wallet'),
        'connecting' => __('Connecting…'),
        'disconnect' => __('Disconnect'),
        'disconnecting' => __('Disconnecting…'),
        'connected' => __('Wallet connected. Only your public address is shared. No on-chain transaction is performed.'),
        'pending' => __('Approve sharing your public address in Phantom. No signature is requested.'),
        'missing' => __('Phantom was not detected. Install or enable the Chrome extension, then reload this page.'),
        'rejected' => __('Connection cancelled. You can try again whenever you are ready.'),
        'failed' => __('Could not connect to Phantom. Unlock the extension and try again.'),
        'disconnectFailed' => __('Could not disconnect. Please try again or disconnect this site inside Phantom.'),
        'unavailable' => __('This account is no longer connected. Connect again to select an account.'),
        'insecure' => __('Open SolMarket directly on localhost, 127.0.0.1 or HTTPS to connect Phantom.'),
        'address' => __('Connected wallet address'),
    ];
@endphp
<div class="wallet-nav" data-wallet data-wallet-messages="{{ json_encode($walletMessages) }}">
    <div class="wallet-controls">
        <span class="wallet-environment" title="{{ __('Devnet is the application target, not a verified wallet network.') }}">{{ __('Devnet · test environment') }}</span>
        <button type="button" class="button button-small wallet-connect" data-wallet-connect disabled>{{ __('Connect Wallet') }}</button>
        <span class="wallet-address" data-wallet-address hidden></span>
        <button type="button" class="wallet-disconnect" data-wallet-disconnect hidden>{{ __('Disconnect') }}</button>
    </div>
    <p class="wallet-context">{{ __('Wallet connection only · no on-chain transactions.') }}</p>
    <p class="wallet-status" data-wallet-status role="status" aria-live="polite" aria-atomic="true" hidden></p>
    <a class="wallet-install" data-wallet-install href="https://phantom.com/" target="_blank" rel="noopener noreferrer" hidden>{{ __('Get Phantom from the official website') }} ↗</a>
    <noscript><p class="wallet-status">{{ __('Enable JavaScript to connect Phantom.') }}</p></noscript>
</div>
