@unless(\App\Services\DemoPayment::configured())
<aside class="wallet-placeholder" aria-label="{{ __('Future Solana integration') }}">
    <span class="eyebrow">{{ __('ON THE HORIZON') }}</span><h3>{{ __('Solana Devnet payments') }}</h3>
    <p>{{ __('Connect Phantom using the navigation. Payments are not available. No transactions are created and no funds are moved.') }}</p>
    <button disabled class="button wallet-button">{{ __('Payments · coming later') }}</button>
</aside>
@endunless
