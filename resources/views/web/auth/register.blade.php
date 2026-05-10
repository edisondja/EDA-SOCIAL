@extends('web.layout')

@section('title', 'Crear cuenta')

@section('content')
<main class="login-card mx-auto mt-8 max-w-md border border-slate-200/80 px-6 py-8 sm:px-10 sm:py-10">
    <h1 class="text-2xl font-bold tracking-tight text-slate-900">Crear cuenta</h1>
    <p class="mt-2 text-sm text-slate-500">Regístrate para publicar, comentar y guardar tu perfil.</p>
    <form method="post" action="{{ route('register.store') }}" class="login-form mt-8">
        @csrf
        <label class="field-label label-with-icon" for="name">@include('web.partials.form-icon', ['name' => 'user-group']) Nombre</label>
        <input id="name" type="text" name="name" value="{{ old('name') }}" maxlength="120" required autofocus>

        <label class="field-label label-with-icon" for="username">@include('web.partials.form-icon', ['name' => 'hashtag']) Usuario</label>
        <input id="username" type="text" name="username" value="{{ old('username') }}" maxlength="80" required placeholder="tu_usuario">

        <label class="field-label label-with-icon" for="email">@include('web.partials.form-icon', ['name' => 'envelope']) Correo</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required>

        <label class="field-label label-with-icon" for="password">@include('web.partials.form-icon', ['name' => 'lock-closed']) Contraseña</label>
        <input id="password" type="password" name="password" minlength="6" required>

        <label class="field-label label-with-icon" for="password_confirmation">@include('web.partials.form-icon', ['name' => 'check']) Confirmar contraseña</label>
        <input id="password_confirmation" type="password" name="password_confirmation" minlength="6" required>

        <button type="submit" class="btn-primary label-with-icon mt-2 w-full justify-center">@include('web.partials.form-icon', ['name' => 'sparkles']) Crear cuenta</button>
    </form>
    <p class="mt-8 text-center text-sm text-slate-500">
        ¿Ya tienes cuenta?
        <a href="{{ route('login') }}" class="text-link">Iniciar sesión</a>
    </p>
</main>
@endsection
