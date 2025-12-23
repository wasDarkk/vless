FROM php:8.2-apache

# Disable all MPMs first
RUN a2dismod mpm_event mpm_worker || true

# Enable prefork (required for PHP mod)
RUN a2enmod mpm_prefork

# PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copy app
COPY . /var/www/html/

# Permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
