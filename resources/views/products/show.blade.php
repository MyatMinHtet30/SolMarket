@extends('layouts.app')
@section('title', $product->title)
@section('content')
<section class="inner-page">
    <a class="back-link" href="{{ route('products.index') }}#marketplace">← {{ __('Back to the market') }}</a>
    <div class="detail-grid">
        <div class="detail-image"><img src="{{ $product->imageUrl() }}" alt="{{ $product->title }}"></div>
        <div class="detail-copy">
            <span class="eyebrow">{{ __($product->category) }} · {{ __('BERLIN CAMPUS FINDS') }}</span>
            <h1>{{ $product->title }}</h1><p class="detail-price">{{ $product->formattedPrice() }}</p>
            <p class="description">{{ $product->description }}</p>
            <dl class="listing-details">
                <div><dt>{{ __('Condition') }}</dt><dd>{{ $product->condition ? __($product->condition) : __('Condition not specified') }}</dd></div>
                <div><dt>{{ __('Berlin district') }}</dt><dd>{{ $product->district ?: __('Location not specified') }}</dd></div>
                <div><dt>{{ __('Local pickup') }}</dt><dd>{{ $product->pickup_details ?: __('Details to be arranged') }}</dd></div>
            </dl>
            <p class="field-help">{{ __('Meet in a public place. Arrange a pickup time with the seller before travelling.') }}</p>
            <div class="seller-panel"><span class="avatar">{{ mb_substr($product->seller_name, 0, 1) }}</span><div><small>{{ __('LISTED BY') }}</small><strong>{{ $product->seller_name }}</strong></div><span class="seller-date">{{ $product->created_at->locale(app()->getLocale())->translatedFormat('d M Y') }}</span></div>
            <p class="prototype-note">{{ __('University prototype · Seller contact is not available yet.') }}</p>
            @include('partials.product-checkout')
        </div>
    </div>
</section>
@endsection
