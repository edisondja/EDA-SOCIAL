@extends('web.layout')

@section('title', 'Mi cuenta')

@section('content')
@php
    $accountAvatarSrc = !empty($user->avatar_url) ? \App\Support\MediaSrc::web($user->avatar_url) : '';
@endphp
<main class="login-card mx-auto mt-6 max-w-xl border border-slate-200/80 px-6 py-8 sm:px-10 sm:py-10">
    <h1 class="text-2xl font-bold tracking-tight text-slate-900">Mi cuenta</h1>
    <p class="mt-2 text-sm text-slate-500">Datos de tu perfil en la plataforma.</p>

    <section class="mt-8 rounded-xl border border-slate-100 bg-slate-50/50 p-5">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Foto de perfil</h2>
        <div class="mt-4 flex flex-col items-center gap-6 sm:flex-row sm:items-start">
            @if($accountAvatarSrc !== '')
                <img src="{{ $accountAvatarSrc }}" alt="" class="h-28 w-28 shrink-0 rounded-full border border-slate-200 bg-white object-cover shadow-md ring-4 ring-white" width="112" height="112">
            @else
                <div class="flex h-28 w-28 shrink-0 items-center justify-center rounded-full border border-dashed border-slate-300 bg-white text-3xl font-bold text-slate-400 shadow-inner ring-4 ring-white" aria-hidden="true">{{ \Illuminate\Support\Str::substr($user->name, 0, 1) }}</div>
            @endif
            <form method="post" action="{{ route('account.avatar') }}" enctype="multipart/form-data" class="flex w-full max-w-sm flex-col gap-3">
                @csrf
                <label class="eda-label mb-0" for="account-avatar-input">Nueva imagen</label>
                <input id="account-avatar-input" type="file" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp" required class="block w-full text-sm text-slate-600 file:mr-4 file:rounded-xl file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-slate-800 hover:file:bg-slate-200">
                <p class="text-xs text-slate-500">JPG, PNG, GIF o WebP. Máximo 5&nbsp;MB.</p>
                <button type="submit" class="eda-btn-primary w-full justify-center sm:w-auto">Guardar foto</button>
            </form>
        </div>
        @error('avatar')
            <p class="mt-3 text-sm font-medium text-red-600">{{ $message }}</p>
        @enderror
    </section>

    <dl class="mt-8 divide-y divide-slate-100 rounded-xl border border-slate-100 bg-slate-50/50">
        <div class="grid gap-1 px-4 py-4 sm:grid-cols-[140px_1fr] sm:items-center sm:gap-4">
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Nombre</dt>
            <dd class="text-sm font-medium text-slate-900">{{ $user->name }}</dd>
        </div>
        <div class="grid gap-1 px-4 py-4 sm:grid-cols-[140px_1fr] sm:items-center sm:gap-4">
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Correo</dt>
            <dd class="text-sm font-medium text-slate-900 break-all">{{ $user->email }}</dd>
        </div>
        @if($user->username)
            <div class="grid gap-1 px-4 py-4 sm:grid-cols-[140px_1fr] sm:items-center sm:gap-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Usuario</dt>
                <dd class="text-sm font-medium text-slate-900">{{ '@' . $user->username }}</dd>
            </div>
        @endif
        <div class="grid gap-1 px-4 py-4 sm:grid-cols-[140px_1fr] sm:items-center sm:gap-4">
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Rol</dt>
            <dd class="text-sm font-medium text-slate-900">
                @php
                    $r = optional($user->role)->name;
                    echo $r === 'admin' ? 'Administrador' : ($r === 'moderator' ? 'Moderador' : ($r === 'user' ? 'Usuario' : ($r ?? '—')));
                @endphp
            </dd>
        </div>
    </dl>
    <p class="mt-8 text-center text-sm text-slate-500"><a href="{{ route('explore.index') }}" class="text-link">Volver al feed</a></p>
</main>
@endsection
