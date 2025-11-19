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

# Use env-aware connector. Do NOT overwrite db.php.
# The application will auto-detect Supabase/DATABASE_URL/MySQL via `db.php`.

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Render Free uses port 10000 by default; bind Apache to 10000 explicitly
ENV PORT=10000
RUN sed -i 's/Listen 80/Listen 10000/g' /etc/apache2/ports.conf \
 && sed -i 's/<VirtualHost \*:80>/<VirtualHost \*:10000>/g' /etc/apache2/sites-available/000-default.conf

# Expose port 10000
EXPOSE 10000

# Start Apache
CMD ["apache2-foreground"]