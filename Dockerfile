FROM php:8.2-apache

# Disable conflicting MPMs
RUN a2dismod mpm_event mpm_worker || true
RUN a2enmod mpm_prefork

# PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Clean Apache cache (important on Railway)
RUN rm -rf /var/lib/apt/lists/*

# Copy app
COPY . /var/www/html/

# Permissions
RUN chown -R www-data:www-data /var/www/html

# Apache must stay in foreground
CMD ["apache2-foreground"]
