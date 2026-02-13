FROM php:8.4-apache

RUN a2enmod rewrite headers ssl

RUN openssl req -x509 -nodes -days 3650 -newkey rsa:2048 \
    -keyout /etc/ssl/private/localhost.key \
    -out /etc/ssl/certs/localhost.crt \
    -subj "/CN=localhost"

RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    libsqlite3-dev \
    iputils-ping \
    unzip \
    && docker-php-ext-install pdo pdo_sqlite curl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

RUN echo '<Directory /var/www/html/public>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/allow-override.conf \
    && a2enconf allow-override

RUN echo '<VirtualHost *:443>\n\
    DocumentRoot ${APACHE_DOCUMENT_ROOT}\n\
    SSLEngine on\n\
    SSLCertificateFile /etc/ssl/certs/localhost.crt\n\
    SSLCertificateKeyFile /etc/ssl/private/localhost.key\n\
    <Directory ${APACHE_DOCUMENT_ROOT}>\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
</VirtualHost>' > /etc/apache2/sites-available/default-ssl.conf \
    && a2ensite default-ssl

RUN echo "expose_php = Off" > /usr/local/etc/php/conf.d/security.ini

WORKDIR /var/www/html
