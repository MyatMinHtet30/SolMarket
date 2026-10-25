@extends('layouts.app')
@section('title', __('Sell an item'))
@section('content')
<section class="inner-page create-page">
    <a class="back-link" href="{{ route('products.index') }}">← {{ __('Back to the market') }}</a>
    <span class="eyebrow">{{ __('A SECOND CHAPTER STARTS HERE') }}</span>
    <h1>{{ __('Pass on something good') }}<span>.</span></h1><p class="page-intro">{{ __('A few details, one great photo, and your item is ready for its next home.') }}</p>
    @if($errors->any())<div class="error-summary" role="alert"><strong>{{ __('Let’s fix a few details.') }}</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form action="{{ route('products.store') }}" method="post" enctype="multipart/form-data" class="listing-form">@csrf
        <div class="form-main">
            <label for="title">{{ __('What are you selling?') }}</label><input id="title" name="title" value="{{ old('title') }}" required maxlength="120" placeholder="{{ __('e.g. Your favourite study desk') }}">
            <div class="form-row">
                <div><label for="category">{{ __('Category') }}</label><select id="category" name="category" required><option value="">{{ __('Pick a category') }}</option>@foreach($categories as $category)<option value="{{ $category }}" @selected(old('category') === $category)>{{ __($category) }}</option>@endforeach</select></div>
                <div><label for="price">{{ __('Price (€)') }}</label><input id="price" name="price" type="number" min="0.01" max="999999.99" step="0.01" value="{{ old('price') }}" required placeholder="25.00"></div>
            </div>
            <div class="form-row">
                <div><label for="district">{{ __('Berlin district') }}</label><select id="district" name="district"><option value="">{{ __('Select a district (optional)') }}</option>@foreach(\App\Models\Product::DISTRICTS as $district)<option value="{{ $district }}" @selected(old('district') === $district)>{{ $district }}</option>@endforeach</select></div>
                <div><label for="condition">{{ __('Condition') }}</label><select id="condition" name="condition"><option value="">{{ __('Select a condition (optional)') }}</option>@foreach(\App\Models\Product::CONDITIONS as $condition)<option value="{{ $condition }}" @selected(old('condition') === $condition)>{{ __($condition) }}</option>@endforeach</select></div>
            </div>
            <label for="pickup_details">{{ __('Local pickup details (optional)') }}</label><textarea id="pickup_details" name="pickup_details" rows="2" maxlength="500" placeholder="{{ __('e.g. U Hermannplatz, weekday evenings') }}">{{ old('pickup_details') }}</textarea>
            <p class="field-help pickup-help">{{ __('A nearby station or public meeting point is enough. Do not share your home address.') }}</p>
            <label for="description">{{ __('Tell its story') }}</label><textarea id="description" name="description" rows="5" required minlength="10" maxlength="5000" placeholder="{{ __('Condition, useful details, and anything its next owner should know…') }}">{{ old('description') }}</textarea>
            <label for="seller_name">{{ __('Your name') }}</label><input id="seller_name" name="seller_name" value="{{ old('seller_name') }}" required maxlength="80" autocomplete="name" placeholder="{{ __('How should we introduce you?') }}"><p class="field-help">{{ __('Your name will be visible on your public listing.') }}</p>
        </div>
        <div class="form-side">
            <label for="image">{{ __('Give it a good first impression') }}</label><div class="upload-box"><span aria-hidden="true">↥</span><strong>{{ __('One photo. A lot of potential.') }}</strong><p>{{ __('JPG, PNG or WebP · up to 4 MB') }}</p><input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" required aria-describedby="image-help" data-size-error="{{ __('Please choose an image smaller than 4 MB.') }}"><img id="image-preview" alt="{{ __('Your selected product photo') }}" hidden></div>
            <p id="image-help" class="field-help">{{ __('Use your own photo. After a validation error, please select your image again.') }}</p>
            <p class="prototype-note">{{ __('Listings are public and publish immediately. This university prototype has no accounts or moderation.') }}</p>
            <p class="field-help">{{ __('Listing text stays in the language the seller wrote it in.') }}</p>
            <button class="button publish-button" type="submit">{{ __('Publish your listing') }} <span>↗</span></button>
        </div>
    </form>
</section>
@endsection
