FROM php:5.4-apache
RUN apt-get update
RUN apt-get install -y zip libxml2-dev libpng-dev
RUN docker-php-ext-install mysql simplexml mbstring gd mysqli
RUN pecl install xdebug-2.4.0 && docker-php-ext-enable xdebug
RUN echo "xdebug.remote_enable=on" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
RUN echo "xdebug.remote_connect_back=on" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
# nasty hack to solve perms issues
RUN chmod 777 /var/www/html

