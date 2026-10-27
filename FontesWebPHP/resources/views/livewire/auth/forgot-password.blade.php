<div>

    <h1 class="auth-title">Recuperar senha</h1>
    <p class="auth-subtitle">Informe seu e-mail e enviaremos um link para criar uma nova senha.</p>

    @if (session()->has('message-success'))
        <div class="auth-alert auth-alert--green">{{ session('message-success') }}</div>
    @endif

    @if (session()->has('message-warning'))
        <div class="auth-alert auth-alert--yellow">{{ session('message-warning') }}</div>
    @endif

    <form class="auth-form" wire:submit="submit" novalidate>

        <div class="auth-field">
            <label for="email">E-mail</label>
            <input id="email" type="email" autocomplete="username" placeholder="voce@escritorio.com.br"
                   wire:model="email" class="@error('email') has-error @enderror">
            @error('email') <span class="auth-error">{{ $message }}</span> @enderror
        </div>

        <div class="auth-row">
            <a class="auth-link" href="{{ route('auth.login') }}">← Voltar ao login</a>
        </div>

        <button class="auth-btn" type="submit" wire:loading.attr="disabled">ENVIAR LINK</button>
    </form>

    <p class="auth-foot"><strong>Portal do Contador</strong> · Gestão de documentos fiscais</p>

</div>

@section('title', 'Recuperar senha')
