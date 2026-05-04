@extends('web.layout')

@section('title', 'Mi cuenta')

@section('content')
<main class="login-card account-card" style="max-width:560px;margin:20px auto;">
    <h1>Mi cuenta</h1>
    <dl class="account-details">
        <div class="account-detail-row">
            <dt>Nombre</dt>
            <dd>{{ $user->name }}</dd>
        </div>
        <div class="account-detail-row">
            <dt>Correo</dt>
            <dd>{{ $user->email }}</dd>
        </div>
        @if($user->username)
            <div class="account-detail-row">
                <dt>Usuario</dt>
                <dd>{{ '@' . $user->username }}</dd>
            </div>
        @endif
        <div class="account-detail-row">
            <dt>Rol</dt>
            <dd>
                @php
                    $r = optional($user->role)->name;
                    echo $r === 'admin' ? 'Administrador' : ($r === 'moderator' ? 'Moderador' : ($r === 'user' ? 'Usuario' : ($r ?? '—')));
                @endphp
            </dd>
        </div>
    </dl>
    <p class="hint-text"><a href="{{ route('explore.index') }}">Volver al feed</a></p>
</main>
@endsection
