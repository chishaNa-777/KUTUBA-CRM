
FROM php:8.2-apache

# Install PostgreSQL support for PHP
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo_pgsql pgsql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache URL rewriting
RUN a2enmod rewrite

# Copy the CRM project into Apache's web directory
COPY . /var/www/html/

# Set the correct permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
