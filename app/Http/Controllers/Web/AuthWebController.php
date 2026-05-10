<?php

namespace App\Http\Controllers\Web;

use App\Channel;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\SharesBranding;
use App\Role;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthWebController extends Controller
{
    use SharesBranding;

    public function showLogin()
    {
        return view('web.auth.login', ['branding' => $this->branding()]);
    }

    public function showRegister()
    {
        return view('web.auth.register', ['branding' => $this->branding()]);
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

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'username' => 'required|string|max:80|unique:users,username',
            'email' => 'required|email|max:190|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        try {
            $user = DB::transaction(function () use ($data) {
                $role = Role::where('name', 'user')->first();

                $baseUsername = Str::slug((string) $data['username']);
                if ($baseUsername === '') {
                    $baseUsername = 'usuario';
                }
                $username = $baseUsername;
                $attempt = 1;
                while (User::where('username', $username)->exists()) {
                    $username = $baseUsername . '-' . $attempt;
                    $attempt++;
                }

                $user = User::create([
                    'name' => trim((string) $data['name']),
                    'username' => $username,
                    'email' => trim((string) $data['email']),
                    'password' => Hash::make((string) $data['password']),
                    'role_id' => $role ? $role->id : null,
                    'status' => 'active',
                ]);

                Channel::create([
                    'user_id' => $user->id,
                    'slug' => Str::slug($username . '-' . $user->id),
                    'display_name' => $user->name,
                ]);

                return $user;
            });
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['email' => 'No se pudo crear la cuenta. Intenta de nuevo.'])->withInput();
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('explore.index')->with('status', 'Cuenta creada correctamente. ¡Bienvenido!');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('explore.index');
    }
}
