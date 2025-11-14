# Image de base PHP 8.2 avec FPM
FROM php:8.2-fpm

# Installer dépendances système utiles à Laravel
RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libfreetype6-dev zip git unzip curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql gd

# Installer Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Dossier de travail
WORKDIR /var/www

# Copier le code source
COPY . .

# Installer dépendances Laravel
RUN composer install --no-dev --optimize-autoloader

# Donner les bons droits à Laravel
RUN chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www/storage

# Lancer PHP-FPM
CMD ["php-fpm"]
