FROM php:8.4-fpm-alpine

# Arguments defined in docker-compose.yml
ARG user
ARG uid

# Install system dependencies
RUN apk add --no-cache \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    sqlite-dev \
    postgresql-dev \
    nodejs \
    npm

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql pdo_pgsql pdo_sqlite mbstring exif pcntl bcmath gd zip intl

# Copy custom php config
COPY .docker/php/local.ini /usr/local/etc/php/conf.d/local.ini

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Create system user to run Composer and Artisan Commands
RUN adduser -G www-data -u $uid -h /home/$user -D $user
RUN mkdir -p /home/$user/.composer && \
    chown -R $user:www-data /home/$user

# Set working directory
WORKDIR /var/www

USER $user
