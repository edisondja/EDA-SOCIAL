<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\SharesBranding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthWebController extends Controller
{
    use SharesBranding;

    public function showLogin()
    {
        return view('web.auth.login', ['branding' => $this->branding()]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Credenciales no válidas.'])->onlyInput('email');
        }

        if (Auth::user()->status === 'banned') {
            Auth::logout();

            return back()->withErrors(['email' => 'Tu cuenta está bloqueada.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('explore.index'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('explore.index');
    }
}
