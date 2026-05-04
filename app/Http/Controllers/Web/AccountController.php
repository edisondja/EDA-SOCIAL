<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\SharesBranding;

class AccountController extends Controller
{
    use SharesBranding;

    public function show()
    {
        $user = auth()->user()->load('role', 'channel');

        return view('web.account', [
            'user' => $user,
            'branding' => $this->branding(),
        ]);
    }
}
