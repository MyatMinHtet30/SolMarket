@php
    $paymentReady = \App\Services\DemoPayment::configured();
    $paymentMessages = [
        'pay' => __('Pay with Test SOL'), 'working' => __('Preparing Devnet payment…'),
        'approve' => __('Approve the test SOL transfer in Phantom. Network fees are additional.'),
        'checking' => __('Submitted to Devnet. Waiting for backend verification…'),
        'confirmed' => __('Devnet demo payment verified. No real-money purchase was made.'),
        'pending' => __('Verification is still pending. Check again or retry the same signed transaction; do not pay again.'),
        'cancelled' => __('Payment cancelled. No transaction was submitted by SolMarket.'),
        'wallet' => __('Connect Phantom in the navigation first.'),
        'changed' => __('The connected account changed. Please start again with the intended account.'),
        'failed' => __('The demo payment could not be completed. Check the order status before trying again.'),
        'notConfigured' => __('Demo payments need an enabled configuration and a valid demo recipient.'),
        'wrongNetwork' => __('Devnet could not be verified. Payment is blocked.'),
        'rpcUnavailable' => __('Devnet RPC is unavailable. Any existing submission remains pending; check again before retrying.'),
        'submissionRejected' => __('Devnet did not accept the submission. Check the test SOL balance and fees, then check this order before retrying.'),
        'sameRecipient' => __('Use a payer wallet different from the demo recipient.'),
        'invalidTransaction' => __('Transaction verification failed. No payment success has been recorded.'),
        'duplicateSignature' => __('This transaction signature is already assigned to another demo order.'),
        'orderBusy' => __('A demo order is already pending. Check its status before starting another.'),
        'orderClosed' => __('This demo order is closed or already has a different transaction.'),
        'expired' => __('The transaction expired. No matching Devnet payment was found; you can start a new demo.'),
        'chainFailed' => __('The Devnet transaction failed. A network fee may still have been charged.'),
        'server' => __('The request failed. Reload if your session expired; otherwise check the existing order before retrying.'),
    ];
@endphp
<aside class="wallet-placeholder demo-payment" data-demo-payment
    data-product-id="{{ $product->id }}" data-create-url="{{ route('demo-orders.store', $product) }}"
    data-order-base="{{ url('/demo-orders') }}" data-csrf="{{ csrf_token() }}"
    data-ready="{{ $paymentReady ? '1' : '0' }}" data-messages="{{ json_encode($paymentMessages) }}">
    <span class="eyebrow">{{ __('DEVNET ONLY · TEST SOL') }}</span>
    <h3>{{ __('Solana Devnet demo payment') }}</h3>
    <p>{{ __('This demo transfer is separate from the EUR listing price. Test SOL has no monetary value and does not purchase or reserve this item.') }}</p>
    @if($paymentReady)
        <dl class="demo-payment-details"><div><dt>{{ __('Fixed demo amount') }}</dt><dd>{{ rtrim(rtrim(number_format((int) config('solana.demo_lamports') / 1000000000, 9, app()->getLocale() === 'de' ? ',' : '.', ''), '0'), '.,') }} Test SOL</dd></div><div><dt>{{ __('Demo recipient') }}</dt><dd class="demo-recipient">{{ config('solana.demo_recipient') }}</dd></div></dl>
        <p>{{ __('Select Solana Devnet in Phantom and fund the payer with faucet test SOL. SolMarket submits signed transactions exclusively to Devnet.') }}</p>
    @else
        <p>{{ __('Demo payments are unavailable until a demo recipient, amount and enable flag are configured by the developer.') }}</p>
    @endif
    <div class="demo-payment-actions">
        <button class="button wallet-button" type="button" data-pay disabled>{{ __('Pay with Test SOL') }}</button>
        <button class="filter-apply" type="button" data-check hidden>{{ __('Check confirmation') }}</button>
        <button class="filter-apply" type="button" data-retry hidden>{{ __('Retry same transaction') }}</button>
    </div>
    <p class="demo-payment-status" data-payment-status role="status" aria-live="polite" aria-atomic="true"></p>
    <div class="demo-receipt" data-receipt hidden><p>{{ __('Demo order') }}: <span data-order-id></span></p><p>{{ __('Transaction signature') }}: <span data-signature></span></p><a data-explorer target="_blank" rel="noopener noreferrer">{{ __('View on Solana Explorer (Devnet)') }} ↗</a></div>
    <noscript><p>{{ __('Enable JavaScript to use the Devnet demo.') }}</p></noscript>
</aside>
