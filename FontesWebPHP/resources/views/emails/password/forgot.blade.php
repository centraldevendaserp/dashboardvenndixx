@component('mail::message')
# Recuperação de senha

Clique no botão para criar a nova senha.

@component('mail::button', ['url' => $url])
Redefinir nova senha
@endcomponent

Obrigado,<br>
{{ config('app.name') }}
@endcomponent
