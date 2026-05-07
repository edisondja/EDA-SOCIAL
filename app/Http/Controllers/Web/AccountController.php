<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\SharesBranding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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

    public function updateAvatar(Request $request)
    {
        $data = $request->validate([
            'avatar' => ['required', 'image', 'max:5120'],
        ]);

        $user = $request->user();
        $this->deleteStoredAvatarIfOwned($user->avatar_url);

        $path = $data['avatar']->store('avatars/' . $user->id, 'public');
        $url = Storage::disk('public')->url($path);

        $user->forceFill(['avatar_url' => $url])->save();

        return redirect()->route('account.show')->with('status', 'Foto de perfil actualizada.');
    }

    /**
     * Elimina del disco público una URL previa guardada por esta app (/storage/…).
     */
    private function deleteStoredAvatarIfOwned(?string $avatarUrl): void
    {
        $avatarUrl = trim((string) $avatarUrl);
        if ($avatarUrl === '') {
            return;
        }

        $path = parse_url($avatarUrl, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return;
        }

        $prefix = '/storage/';
        if (strncmp($path, $prefix, strlen($prefix)) !== 0) {
            return;
        }

        $relative = substr($path, strlen($prefix));
        if ($relative !== '' && strpos($relative, '..') !== false) {
            return;
        }

        if ($relative !== '') {
            Storage::disk('public')->delete($relative);
        }
    }
}
