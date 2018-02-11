FROM php:7.2.0-fpm-alpine
RUN docker-php-ext-install pcntl

#-------------PHPUNIT----------------
ARG INSTALL_PHPUNIT=false
RUN if [ ${INSTALL_PHPUNIT} = true ]; then \
php -r "copy('https://phar.phpunit.de/phpunit.phar', 'phpunit.phar');" && \
    chmod +x phpunit.phar && \
    mv phpunit.phar /usr/local/bin/phpunit \
	;fi

#-------------COMPOSER----------------
ARG INSTALL_COMPOSER=false
RUN if [ ${INSTALL_COMPOSER} = true ]; then \
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
     && php composer-setup.php \
     && php -r "unlink('composer-setup.php');" \
     && mv composer.phar /usr/local/bin/composer \
    ;fi

