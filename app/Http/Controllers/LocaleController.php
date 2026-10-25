<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function update(Request $request, string $locale)
    {
        abort_unless(in_array($locale, ['en', 'de'], true), 404);
        $request->session()->put('locale', $locale);

        // Accept only relative paths, so switching cannot redirect off-site.
        $returnTo = $request->input('return_to', '/');
        if (! is_string($returnTo) || ! preg_match('~^/(?!/)[^\\\\\x00-\x20]*$~D', $returnTo)) {
            $returnTo = '/';
        }

        return redirect($returnTo, 303);
    }
}
