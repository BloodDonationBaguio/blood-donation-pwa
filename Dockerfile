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

# Change Apache to listen on port 10000 for Render
RUN sed -i 's/80/${PORT}/g' /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf
RUN sed -i 's/Listen 0.0.0.0:80/Listen 0.0.0.0:${PORT}/g' /etc/apache2/ports.conf

# Expose port
EXPOSE ${PORT}

# Start Apache
CMD ["apache2-foreground"]