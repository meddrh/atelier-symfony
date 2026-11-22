FROM php:8.1-cli

# Identifiants de l'utilisateur du conteneur : ceux de ton compte sur Linux (`id -u`, `id -g`), 1000 sinon.
ARG APP_UID=1000
ARG APP_GID=1000

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libicu-dev libzip-dev libsqlite3-dev \
    && docker-php-ext-install intl zip pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY php.ini /usr/local/etc/php/conf.d/formation.ini

# Utilisateur ordinaire : les fichiers créés dans le projet monté (var/, vendor/) t'appartiennent, pas à root.
# --non-unique : ton UID ou ton GID peut déjà exister dans l'image (par exemple 33, www-data) ; sans cela, la construction échoue.
RUN groupadd --non-unique --gid "${APP_GID}" app \
    && useradd --non-unique --uid "${APP_UID}" --gid "${APP_GID}" --create-home --shell /bin/bash app \
    && mkdir -p /app \
    && chown app:app /app

WORKDIR /app
USER app
