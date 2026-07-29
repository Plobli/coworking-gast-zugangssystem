FROM php:8.2-apache

RUN docker-php-ext-install opcache \
    && a2enmod rewrite headers expires

COPY config /var/www/config
COPY public /var/www/html
COPY docker/apache-app.conf /etc/apache2/conf-available/app.conf
RUN a2enconf app

RUN mkdir -p /var/www/storage/logs \
    && chown -R www-data:www-data /var/www

WORKDIR /var/www/html
