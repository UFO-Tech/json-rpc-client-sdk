FROM php:8.3-cli
ARG WORKDIR
WORKDIR ${WORKDIR}

RUN apt-get update && apt-get install -y unzip

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/bin --filename=composer --quiet

ENV COMPOSER_ALLOW_SUPERUSER 1