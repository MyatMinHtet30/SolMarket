@extends('layouts.app')
@section('content')
<section class="hero">
    <div class="hero-copy">
        <span class="eyebrow"><span class="status-dot"></span> {{ __('BERLIN’S STUDENT SECOND-HAND MARKETPLACE') }}</span>
        <h1>{{ __('Your campus.') }} <br><span>{{ __('Your marketplace.') }}</span></h1>
        <p>{{ __('Good finds.') }} {{ __('From shared flats in Neukölln to study breaks in Mitte. Find pre-loved essentials nearby, save a little, and keep good things in Berlin.') }}</p>
        <a href="#marketplace" class="button">{{ __('Find your next favourite') }} <span>↗</span></a>
        <div class="hero-note"><span>✳</span> {{ __('Six districts. One student community.') }}</div>
    </div>
    <div class="hero-art">
        <div class="art-grid"></div><div class="art-orbit"></div>
        <img class="hero-headphones" src="{{ asset('samples/headphones.svg') }}" alt="{{ __('Illustrated headphones') }}">
        <img class="hero-books" src="{{ asset('samples/books.svg') }}" alt="{{ __('Illustrated textbooks') }}">
        <div class="art-sticker">{{ __('GOOD STUFF') }}<br><strong>{{ __('goes around.') }}</strong><span>↗</span></div>
        <div class="price-sticker">{{ __('small prices.') }}<br><strong>{{ __('big potential.') }}</strong></div><span class="art-star">✳</span>
    </div>
</section>
<div class="values-strip"><span>↻ &nbsp; {{ __('Second-hand, first choice') }}</span><span>♡ &nbsp; {{ __('Made for student budgets') }}</span><span>⌖ &nbsp; {{ __('Local pickup in Berlin') }}</span></div>
<section id="marketplace" class="marketplace">
    <div class="market-discovery" data-market-discovery>
        <div class="section-heading market-discovery-heading">
            <h2>{{ __('Good finds for your everyday') }}<span>.</span></h2>
            <span class="market-result-count">{{ trans_choice('{1} :count find|[0,*] :count finds', $products->total()) }}</span>
        </div>
        @if($errors->any())<div class="error-summary" role="alert">{{ $errors->first() }} <a href="{{ route('products.index') }}#marketplace">{{ __('Reset filters') }}</a></div>@endif
        <form method="get" action="{{ route('products.index') }}#marketplace" role="search" aria-label="{{ __('Search products') }}">
            <div class="market-toolbar">
                <div class="market-search-field">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4.5 4.5" stroke-linecap="round"/></svg>
                    <label class="sr-only" for="market-search">{{ __('Search products') }}</label>
                    <input id="market-search" type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Find your next favourite…') }}" maxlength="120">
                    <button type="submit" class="market-search-submit" aria-label="{{ __('Search') }}" title="{{ __('Search') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M5 12h14m-5-5 5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                </div>
                @php($panelFilterCount = (int) filled(request('district')) + (int) filled(request('condition')))
                <button type="button" id="market-filter-toggle" class="market-filter-toggle {{ $panelFilterCount ? 'has-filters' : '' }}" data-filter-toggle aria-expanded="false" aria-controls="market-filter-panel" aria-label="{{ __('Show filters') }}" data-expand-label="{{ __('Show filters') }}" data-collapse-label="{{ __('Hide filters') }}" hidden>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M4 7h3m6 0h7M4 17h9m6 0h1" stroke-linecap="round"/><circle cx="10" cy="7" r="3"/><circle cx="16" cy="17" r="3"/></svg>
                    <span>{{ __('Filters') }}</span>@if($panelFilterCount)<span class="market-filter-count">{{ $panelFilterCount }}<span class="sr-only"> {{ __('active filters') }}</span></span>@endif
                </button>
            </div>
            @if(request('category'))<input type="hidden" name="category" value="{{ request('category') }}">@endif
            {{-- Progressive enhancement: filters remain usable if JavaScript is unavailable. --}}
            <div id="market-filter-panel" class="market-filter-panel" data-filter-panel>
                <div class="market-filter-clip"><div class="market-filter-fields">
                    <div><label for="district-filter">{{ __('Berlin district') }}</label><select id="district-filter" name="district"><option value="">{{ __('All districts') }}</option>@foreach(\App\Models\Product::DISTRICTS as $district)<option value="{{ $district }}" @selected(request('district') === $district)>{{ $district }}</option>@endforeach</select></div>
                    <div><label for="condition-filter">{{ __('Condition') }}</label><select id="condition-filter" name="condition"><option value="">{{ __('All conditions') }}</option>@foreach(\App\Models\Product::CONDITIONS as $condition)<option value="{{ $condition }}" @selected(request('condition') === $condition)>{{ __($condition) }}</option>@endforeach</select></div>
                    <button class="market-filter-apply" type="submit">{{ __('Apply filters') }}</button>
                </div></div>
            </div>
            <nav class="categories market-categories" aria-label="{{ __('Filter by category') }}">
                <a href="{{ route('products.index', request()->only('q', 'district', 'condition')) }}#marketplace" class="chip {{ !request('category') ? 'active' : '' }}" @if(!request('category')) aria-current="true" @endif>{{ __('All finds') }}</a>
                @foreach($categories as $category)<a href="{{ route('products.index', [...request()->only('q', 'district', 'condition'), 'category' => $category]) }}#marketplace" class="chip {{ request('category') === $category ? 'active' : '' }}" @if(request('category') === $category) aria-current="true" @endif>{{ __($category) }}</a>@endforeach
            </nav>
        </form>
        @if(request('q') || request('category') || request('district') || request('condition'))
            <div class="market-active-filters" aria-label="{{ __('Active filters:') }}">
                @if(request('q'))<span class="market-active-chip">{{ __('Search') }}: {{ request('q') }}</span>@endif
                @if(request('category'))<span class="market-active-chip">{{ __('Category') }}: {{ __(request('category')) }}</span>@endif
                @if(request('district'))<span class="market-active-chip">{{ __('Berlin district') }}: {{ request('district') }}</span>@endif
                @if(request('condition'))<span class="market-active-chip">{{ __('Condition') }}: {{ __(request('condition')) }}</span>@endif
                <a class="market-reset" href="{{ route('products.index') }}#marketplace">{{ __('Reset filters') }}</a>
            </div>
        @endif
    </div>
    <div class="product-grid">
        @forelse($products as $product)
            <article class="product-card">
                <a href="{{ route('products.show', $product) }}" class="product-image"><img src="{{ $product->imageUrl() }}" alt="{{ $product->title }}" loading="lazy"><span class="image-tag">{{ __($product->category) }}</span><span class="card-arrow" aria-hidden="true">↗</span></a>
                <div class="product-info">
                    <div class="product-meta"><span>{{ $product->condition ? __($product->condition) : __('Condition not specified') }}</span><span>{{ $product->formattedPrice() }}</span></div>
                    <h3><a href="{{ route('products.show', $product) }}">{{ $product->title }}</a></h3>
                    <p class="listing-location">⌖ {{ $product->district ? 'Berlin · '.$product->district : __('Location not specified') }}</p>
                    <p class="pickup-summary">{{ __('Local pickup') }} · {{ $product->pickup_details ?: __('Details to be arranged') }}</p>
                    <p class="seller"><span class="avatar">{{ mb_substr($product->seller_name, 0, 1) }}</span><span>{{ __('Listed by :name', ['name' => $product->seller_name]) }}</span></p>
                </div>
            </article>
        @empty
            <div class="empty-state"><span>⌕</span><h3>{{ __('No finds just yet.') }}</h3><p>{{ __('Try another search or give a different category a look.') }}</p><a class="button" href="{{ route('products.index') }}#marketplace">{{ __('Explore all finds') }}</a></div>
        @endforelse
    </div>
    <div class="pagination">{{ $products->links() }}</div>
</section>
<section class="sell-banner"><div><span class="eyebrow">{{ __('LESS IN YOUR WG. MORE FOR YOUR KIEZ.') }}</span><h2>{{ __('Your old favourite.') }}<br>{{ __('Their next great find.') }}</h2></div><a class="button" href="{{ route('products.create') }}">{{ __('List your first item') }} <span>↗</span></a></section>
@endsection
