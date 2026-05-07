@extends('web.layout')

@section('title', 'Iniciar sesión')

@section('content')
<main class="login-card mx-auto mt-8 max-w-md border border-slate-200/80 px-6 py-8 sm:px-10 sm:py-10">
    <h1 class="text-2xl font-bold tracking-tight text-slate-900">Iniciar sesión</h1>
    <p class="mt-2 text-sm text-slate-500">Accedé para publicar, comentar y administrar.</p>
    <form method="post" action="{{ route('login') }}" class="login-form mt-8">
        @csrf
        <label class="field-label label-with-icon" for="email">@include('web.partials.form-icon', ['name' => 'envelope']) Correo</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
        <label class="field-label label-with-icon" for="password">@include('web.partials.form-icon', ['name' => 'lock-closed']) Contraseña</label>
        <input id="password" type="password" name="password" required>
        <label class="checkbox-with-icon">
            @include('web.partials.form-icon', ['name' => 'check', 'size' => 16])
            <span class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" name="remember" value="1" class="rounded border-slate-300" {{ old('remember') ? 'checked' : '' }}>
                Recordarme
            </span>
        </label>
        <button type="submit" class="btn-primary label-with-icon mt-2 w-full justify-center">@include('web.partials.form-icon', ['name' => 'paper-airplane']) Entrar</button>
    </form>
    <p class="mt-8 text-center text-sm text-slate-500"><a href="{{ route('explore.index') }}" class="text-link">Volver al inicio</a></p>
</main>
@endsection
