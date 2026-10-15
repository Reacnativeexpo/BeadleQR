FROM php:8.4-apache

RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_pgsql zip gd \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

WORKDIR /var/www/html

COPY . /var/www/html/

RUN if [ -f composer.json ]; then \
    php -r "copy('https://getcomposer.org/installer', '/tmp/composer-setup.php');" \
    && php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && composer install --no-dev --optimize-autoloader \
    && rm /tmp/composer-setup.php; \
    fi

EXPOSE 80
