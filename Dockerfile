# Stage 1: Install PHP dependencies
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Stage 2: Production image
FROM php:8.4-apache

# System setup: Apache modules, SSL cert, PHP extensions
RUN a2enmod rewrite headers ssl \
    && openssl req -x509 -nodes -days 3650 -newkey rsa:2048 \
       -keyout /etc/ssl/private/localhost.key \
       -out /etc/ssl/certs/localhost.crt \
       -subj "/CN=localhost" \
    && apt-get update && apt-get install -y --no-install-recommends \
       libcurl4-openssl-dev libsqlite3-dev iputils-ping \
    && docker-php-ext-install pdo pdo_sqlite curl \
    && rm -rf /var/lib/apt/lists/*

# Apache + PHP config
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
      /etc/apache2/sites-available/*.conf \
      /etc/apache2/apache2.conf \
      /etc/apache2/conf-available/*.conf \
    && printf '<Directory /var/www/html/public>\n AllowOverride All\n Require all granted\n</Directory>\n' \
       > /etc/apache2/conf-available/allow-override.conf \
    && a2enconf allow-override \
    && printf '<VirtualHost *:443>\n DocumentRoot ${APACHE_DOCUMENT_ROOT}\n SSLEngine on\n SSLCertificateFile /etc/ssl/certs/localhost.crt\n SSLCertificateKeyFile /etc/ssl/private/localhost.key\n <Directory ${APACHE_DOCUMENT_ROOT}>\n  AllowOverride All\n  Require all granted\n </Directory>\n</VirtualHost>\n' \
       > /etc/apache2/sites-available/default-ssl.conf \
    && a2ensite default-ssl \
    && echo "expose_php = Off" > /usr/local/etc/php/conf.d/security.ini

WORKDIR /var/www/html

COPY --from=vendor /app/vendor ./vendor
COPY composer.json ./
COPY app/ ./app/
COPY bin/ ./bin/
COPY public/ ./public/
COPY .htaccess ./

ARG VERSION=dev
RUN echo "$VERSION" > VERSION

RUN mkdir -p data && chown www-data:www-data data
VOLUME /var/www/html/data

EXPOSE 80 443
