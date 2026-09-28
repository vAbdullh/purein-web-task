FROM php:8.2-apache

# Enable SQLite and enforce private-file rules in Apache.
RUN apt-get update && apt-get install -y --no-install-recommends libsqlite3-dev libicu-dev \
    && docker-php-ext-install pdo_sqlite intl \
    && rm -rf /var/lib/apt/lists/*
COPY . /var/www/html/
RUN cp /var/www/html/.htaccess /etc/apache2/conf-available/private-files.conf \
    && a2enconf private-files \
    && chown -R www-data:www-data /var/www/html
EXPOSE 80
