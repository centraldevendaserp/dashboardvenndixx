# Foco: PHP 8.4 (default). Compatível com 8.3 -> build com PHP_VERSION=8.3
ARG PHP_VERSION=8.4
FROM php:${PHP_VERSION}-apache

# --- Dependencias de sistema + extensoes PHP usadas pela app -----------------
# gd        -> DANFE (nfephp-org/sped-da) e dompdf
# zip       -> composer / pacotes
# pdo_mysql -> banco
# mbstring/exif/bcmath/intl -> Laravel + manipulacao de strings/numeros
# soap      -> nfephp-org/sped-common (NF-e) exige ext-soap (usa libxml2-dev, ja instalado abaixo)
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        git unzip libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
        libonig-dev libxml2-dev libicu-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring zip gd bcmath exif intl opcache soap; \
    a2enmod rewrite headers; \
    apt-get clean; \
    rm -rf /var/lib/apt/lists/*

# Composer (binario oficial)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Config do PHP e do virtualhost (docroot = public/)
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-portal.ini
COPY docker/php/000-default.conf /etc/apache2/sites-available/000-default.conf

# Entrypoint: prepara .env / APP_KEY / permissoes e sobe o Apache
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh && chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
