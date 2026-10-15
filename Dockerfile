FROM php:8.2-apache

# Enable mod_rewrite + headers (for .htaccess) and PDO PostgreSQL
RUN a2enmod rewrite headers && \
    apt-get update -qq && \
    apt-get install -y --no-install-recommends libpq-dev && \
    docker-php-ext-install pdo pdo_pgsql && \
    rm -rf /var/lib/apt/lists/*

# Allow .htaccess to override Apache config
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Install Composer dependencies in the image; .env is excluded from the build context.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy all project files into the web root
COPY . /var/www/html/

RUN composer install --working-dir=/var/www/html --no-dev --prefer-dist --no-interaction --classmap-authoritative \
    && chown -R www-data:www-data /var/www/html/starhorse-vessel-admin/uploads \
    && chmod -R 755 /var/www/html/starhorse-vessel-admin/uploads

EXPOSE 80
