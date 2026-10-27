#!/bin/sh
set -e

cd /var/www/html

# 1) .env -- se nao existir (caso do _Fontes_lojadev), cria a partir do exemplo.
#    No _Servidor o .env real ja existe e nao e tocado; o banco/credenciais de dev
#    sao injetados via "environment:" do docker-compose (sobrepoem o .env em runtime).
if [ ! -f .env ]; then
    if [ -f .env.example ]; then
        cp .env.example .env
        echo "[entrypoint] .env criado a partir de .env.example"
    fi
fi

# 2) Dependencias -- so instala se vendor/ estiver ausente.
if [ ! -f vendor/autoload.php ]; then
    echo "[entrypoint] vendor/ ausente, rodando composer install..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# 3) APP_KEY -- gera apenas se estiver vazia no .env.
if [ -f .env ] && ! grep -qE '^APP_KEY=base64:' .env; then
    php artisan key:generate --force || true
fi

# 4) Permissoes de escrita do Laravel.
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R ug+rw storage bootstrap/cache 2>/dev/null || true

# 5) Limpa caches de config/rotas para refletir as envs injetadas pelo compose.
php artisan config:clear 2>/dev/null || true

exec "$@"
