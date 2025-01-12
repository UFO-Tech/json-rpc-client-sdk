FROM php:8.3-fpm
ARG WORKDIR
WORKDIR ${WORKDIR}

RUN apt-get -y update && apt-get -y install git  \
    unzip  \
    rsync  \
    zlib1g-dev  \
    libicu-dev  \
    g++  \
    librabbitmq-dev  \
    libssl-dev

RUN docker-php-ext-install pdo pdo_mysql
RUN docker-php-ext-configure intl
RUN docker-php-ext-install intl
RUN pecl install amqp \
    && docker-php-ext-enable amqp;
RUN pecl install xdebug && docker-php-ext-enable xdebug;
COPY ./docker/configs/php/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

ENV TERM xterm-256color

RUN php -r "readfile('http://getcomposer.org/installer');" | php -- --install-dir=/usr/bin/ --filename=composer


CMD php-fpm
EXPOSE 9000