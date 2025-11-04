FROM php:8.2-fpm-alpine

# Install system packages needed to build and enable pdo_mysql on Alpine
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS mariadb-dev \
	&& docker-php-ext-install pdo pdo_mysql \
	&& apk del .build-deps

WORKDIR /var/www/html
