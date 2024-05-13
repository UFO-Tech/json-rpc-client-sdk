FROM php:8.3-cli

RUN apt-get update && apt-get install -y unzip libxslt-dev \
 && docker-php-ext-install xsl pdo pdo_mysql

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/bin --filename=composer --quiet

ENV COMPOSER_ALLOW_SUPERUSER 1

WORKDIR /app