@extends('web.layout')

@section('title', 'Iniciar sesión')

@section('content')
<main class="login-card" style="max-width:420px;margin:24px auto;">
    <h1>Iniciar sesión</h1>
    <p class="hint-text">Accede para publicar, comentar y administrar.</p>
    <form method="post" action="{{ route('login') }}" class="login-form">
        @csrf
        <label class="field-label label-with-icon" for="email">@include('web.partials.form-icon', ['name' => 'envelope']) Correo</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
        <label class="field-label label-with-icon" for="password">@include('web.partials.form-icon', ['name' => 'lock-closed']) Contraseña</label>
        <input id="password" type="password" name="password" required>
        <label class="checkbox-with-icon">
            @include('web.partials.form-icon', ['name' => 'check', 'size' => 16])
            <span class="checkbox-with-icon-body checkbox-row" style="margin:0;">
                <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                Recordarme
            </span>
        </label>
        <button type="submit" class="btn-primary label-with-icon login-submit-btn">@include('web.partials.form-icon', ['name' => 'paper-airplane']) Entrar</button>
    </form>
    <p class="hint-text" style="margin-top:16px;"><a href="{{ route('explore.index') }}">Volver al inicio</a></p>
</main>
@endsection
