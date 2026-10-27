<div x-data="{ loading: false }" x-on:auth-finished.window="loading = false">

    <h1 class="auth-title">Acessar o portal</h1>
    <p class="auth-subtitle">Entre com suas credenciais para continuar.</p>

    @if (session()->has('message-password-reseted'))
        <div class="auth-alert auth-alert--green">{{ session('message-password-reseted') }}</div>
    @endif

    @if (session()->has('message-success'))
        <div class="auth-alert auth-alert--green">{{ session('message-success') }}</div>
    @endif

    @if (session()->has('message-warning'))
        <div class="auth-alert auth-alert--yellow">{{ session('message-warning') }}</div>
    @endif

    <form class="auth-form" wire:submit="submit" novalidate x-on:submit="loading = true">

        <div class="auth-field">
            <label for="email">E-mail</label>
            <input id="email" type="email" autocomplete="username" placeholder="voce@escritorio.com.br"
                   wire:model="email" class="@error('email') has-error @enderror">
            @error('email') <span class="auth-error">{{ $message }}</span> @enderror
        </div>

        <div class="auth-field">
            <label for="password">Senha</label>
            <input id="password" type="password" autocomplete="current-password" placeholder="••••••••"
                   wire:model="password" class="@error('password') has-error @enderror">
            @error('password') <span class="auth-error">{{ $message }}</span> @enderror
        </div>

        <div class="auth-row">
            <label class="auth-check" for="remember">
                <input id="remember" type="checkbox" wire:model="remember">
                <span>Manter conectado</span>
            </label>
            <a class="auth-link" href="{{ route('auth.forgot.password') }}">Recuperar senha</a>
        </div>

        <button class="auth-btn" type="submit" :disabled="loading">
            <span x-show="!loading">ENTRAR</span>
            <span class="auth-btn-load" x-show="loading" x-cloak>
                <span class="auth-spinner" aria-hidden="true"></span>
                <span class="auth-btn-load__txt">Entrando…</span>
            </span>
        </button>
    </form>

    <p class="auth-foot"><strong>Portal do Contador</strong> · Gestão de documentos fiscais</p>

</div>

@section('title', 'Login')
