FROM php:8.1-apache

# Install MySQL extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable Apache modules
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy all files
COPY . /var/www/html/

# Create logs directory
RUN mkdir -p /var/www/html/logs && chmod 777 /var/www/html/logs

# Apache configuration for Render
RUN echo "Listen \${PORT:-8080}" > /etc/apache2/ports.conf && \
    echo "<VirtualHost *:\${PORT:-8080}>" > /etc/apache2/sites-available/000-default.conf && \
    echo "    DocumentRoot /var/www/html" >> /etc/apache2/sites-available/000-default.conf && \
    echo "    <Directory /var/www/html>" >> /etc/apache2/sites-available/000-default.conf && \
    echo "        AllowOverride All" >> /etc/apache2/sites-available/000-default.conf && \
    echo "        Require all granted" >> /etc/apache2/sites-available/000-default.conf && \
    echo "    </Directory>" >> /etc/apache2/sites-available/000-default.conf && \
    echo "</VirtualHost>" >> /etc/apache2/sites-available/000-default.conf

# Start Apache
CMD ["apache2-foreground"]