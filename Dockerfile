FROM php:8.2-apache

# Enable mod_rewrite (needed for .htaccess rules) and PDO MySQL
RUN a2enmod rewrite headers && \
    docker-php-ext-install pdo pdo_mysql

# Allow .htaccess to override Apache config
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Copy all project files into the web root
COPY . /var/www/html/

# The uploads directory must be writable by Apache (www-data)
RUN chown -R www-data:www-data /var/www/html/starhorse-vessel-admin/uploads \
    && chmod -R 755 /var/www/html/starhorse-vessel-admin/uploads

EXPOSE 80
