
FROM php:8.2-apache

# Install PostgreSQL support
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo_pgsql pgsql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Enable URL rewriting
RUN a2enmod rewrite

# Copy CRM project
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Configure Apache to use Render's assigned port
CMD ["bash", "-c", "PORT=${PORT:-10000}; sed -i 's/^Listen .*/Listen 0.0.0.0:'\"$PORT\"'/' /etc/apache2/ports.conf; sed -i 's/<VirtualHost \\*:80>/<VirtualHost *:'\"$PORT\"'>/' /etc/apache2/sites-available/000-default.conf; apache2-foreground"]
