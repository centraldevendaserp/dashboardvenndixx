<div>

    <h1 class="auth-title">Nova senha</h1>
    <p class="auth-subtitle">Defina a nova senha de acesso à sua conta.</p>

    @if (session()->has('message-error'))
        <div class="auth-alert auth-alert--red">{{ session('message-error') }}</div>
    @endif

    <form class="auth-form" wire:submit="submit" novalidate>

        <div class="auth-field">
            <label for="email">E-mail</label>
            <input id="email" type="email" autocomplete="username" placeholder="voce@escritorio.com.br"
                   wire:model="email" class="@error('email') has-error @enderror">
            @error('email') <span class="auth-error">{{ $message }}</span> @enderror
        </div>

        <div class="auth-field">
            <label for="password">Senha</label>
            <input id="password" type="password" autocomplete="new-password" placeholder="••••••••"
                   wire:model="password" class="@error('password') has-error @enderror">
            @error('password') <span class="auth-error">{{ $message }}</span> @enderror
        </div>

        <div class="auth-field">
            <label for="password_confirmation">Confirmar senha</label>
            <input id="password_confirmation" type="password" autocomplete="new-password" placeholder="••••••••"
                   wire:model="password_confirmation" class="@error('password_confirmation') has-error @enderror">
            @error('password_confirmation') <span class="auth-error">{{ $message }}</span> @enderror
        </div>

        <button class="auth-btn" type="submit" wire:loading.attr="disabled">SALVAR NOVA SENHA</button>
    </form>

    <p class="auth-foot"><a class="auth-link" href="{{ route('auth.login') }}">← Voltar ao login</a></p>

</div>

@section('title', 'Nova senha')
