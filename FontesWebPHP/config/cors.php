<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    /*
     * VAZIO de proposito. `api/*` atende UM cliente: o agente Delphi
     * (THTTPClient), que nao e navegador e nao consulta CORS. Nenhum JS do
     * portal chama /api/ — conferido por varredura em resources/ e
     * public/assets/.
     *
     * Com '*' aqui, qualquer site conseguia LER a resposta de /api/docs/* a
     * partir do navegador de uma vitima. Sem origem liberada, o navegador
     * bloqueia a leitura e o agente segue igual.
     */
    'allowed_origins' => [],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
