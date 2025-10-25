FROM php:8.1-apache

# Install system dependencies for PostgreSQL
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql \
    && apt-get clean

# Enable Apache rewrite
RUN a2enmod rewrite

# Copy application
COPY . /var/www/html/

# Copy production db config as db.php
RUN cp /var/www/html/db_production.php /var/www/html/db.php

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Expose port
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]