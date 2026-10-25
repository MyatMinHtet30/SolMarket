<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ __('The second-hand marketplace for student life in Berlin.') }}">
    <title>@yield('title', __('Your campus. Your marketplace.')) · SolMarket</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<header class="site-header">
    <a href="{{ route('products.index') }}" class="brand" aria-label="{{ __('SolMarket home') }}"><span class="brand-symbol">s↗</span>solmarket<span class="brand-dot">.</span></a>
    <nav aria-label="{{ __('Main navigation') }}">
        <a class="browse-link" href="{{ route('products.index') }}#marketplace">{{ __('Explore the market') }}</a>
        <div class="language-switch" role="group" aria-label="{{ __('Language') }}">
            @foreach(['en' => 'English', 'de' => 'Deutsch'] as $locale => $label)
                <form method="post" action="{{ route('locale.update', $locale) }}">@csrf
                    <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                    <button type="submit" lang="{{ $locale }}" class="{{ app()->getLocale() === $locale ? 'selected' : '' }}" aria-pressed="{{ app()->getLocale() === $locale ? 'true' : 'false' }}">{{ $label }}</button>
                </form>
            @endforeach
        </div>
        <a class="button button-small" href="{{ route('products.create') }}">＋ {{ __('Sell an item') }}</a>
    </nav>
</header>
<main>@if(session('success'))<div class="flash" role="status">{{ session('success') }}</div>@endif @yield('content')</main>
<footer><a class="footer-brand" href="{{ route('products.index') }}">solmarket.</a><p>{{ __('Pre-loved things. New beginnings.') }}</p><span>{{ __('Made for student life in Berlin.') }} ♡</span></footer>
</body>
</html>
