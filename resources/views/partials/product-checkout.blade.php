@php
    $checkoutMessages = [
        'connect' => __('Connect Wallet'),
        'connecting' => __('Connecting…'),
        'connected' => __('Wallet connected'),
        'address' => __('Connected wallet address'),
        'missing' => __('Phantom was not found. Enable the extension and try again.'),
        'rejected' => __('Connection cancelled. You can try again when you are ready.'),
        'failed' => __('We could not connect your wallet. Unlock Phantom and try again.'),
        'unavailable' => __('Your wallet was disconnected. Connect again to continue.'),
        'blocked' => __('Payments are paused while the recipient is checked.'),
    ];
@endphp
<section class="product-checkout" data-product-checkout data-messages="{{ json_encode($checkoutMessages) }}" aria-label="{{ __('Checkout') }}">
    <div class="checkout-start">
        <button type="button" class="button checkout-buy" data-checkout-open aria-expanded="false" aria-controls="product-checkout-panel">{{ __('Buy Now') }} <span aria-hidden="true">↗</span></button>
    </div>
    <noscript><p class="checkout-wallet-status">{{ __('Checkout requires JavaScript.') }}</p></noscript>
    <div id="product-checkout-panel" class="checkout-panel" data-checkout-panel hidden aria-labelledby="checkout-heading">
        <div class="checkout-heading-row"><h2 id="checkout-heading" tabindex="-1" data-checkout-heading>{{ __('Payment Method') }}</h2><button type="button" class="checkout-close" data-checkout-close aria-label="{{ __('Close checkout') }}">×</button></div>
        <div class="checkout-product-summary">
            <h3>{{ $product->title }}</h3>
            <div class="checkout-listed-price"><span class="sr-only">{{ __('Listing price') }}: </span><strong>{{ $product->formattedPrice() }}</strong></div>
        </div>
        <fieldset class="checkout-methods"><legend class="sr-only">{{ __('Payment Method') }}</legend><label class="checkout-method"><input type="radio" name="checkout-method" value="solana" checked aria-describedby="checkout-confirmation-note"><span class="checkout-method-icon" aria-hidden="true"><svg viewBox="0 0 32 32" fill="currentColor"><path d="M8 7h20l-4 4H4zm-4 7h20l4 4H8zm4 7h20l-4 4H4z"/></svg></span><strong>{{ __('Solana') }}</strong><span class="checkout-selected" aria-hidden="true">✓</span></label></fieldset>
        <div class="checkout-wallet-row"><button type="button" class="checkout-connect" data-checkout-connect disabled>{{ __('Connect Wallet') }}</button><span class="checkout-wallet-address" data-checkout-address hidden></span></div>
        <p class="checkout-wallet-status" data-checkout-status role="status" aria-live="polite" hidden></p>
        <p class="checkout-unavailable" id="checkout-unavailable">{{ __('Payments are paused while the recipient is checked.') }}</p>
        <details class="checkout-confirmation">
            <summary>{{ __('Checkout confirmation') }}</summary>
            <p id="checkout-confirmation-note">{{ __('Any payment here would use 0.001 Devnet test SOL. It would not pay for or purchase the EUR-listed item.') }}</p>
        </details>
        <div class="checkout-action">
            <button type="button" class="button checkout-continue" data-checkout-continue disabled aria-describedby="checkout-confirmation-note checkout-unavailable">{{ __('Continue to Payment') }} <span aria-hidden="true">→</span></button>
        </div>
        <noscript><p class="checkout-wallet-status">{{ __('Enable JavaScript to open checkout and connect your wallet.') }}</p></noscript>
    </div>
</section>
