# SMF development image: PHP + Apache with everything other/requirements.md asks for.
ARG PHP_VERSION=8.4
FROM php:${PHP_VERSION}-apache

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Required by SMF: mbstring, fileinfo, pgsql (pg_connect), mysqli.
# Recommended by SMF: gd, intl, curl, exif, ftp, xsl.
RUN install-php-extensions \
        pgsql \
        pdo_pgsql \
        mysqli \
        mbstring \
        fileinfo \
        gd \
        intl \
        curl \
        exif \
        ftp \
        xsl \
        zip \
        opcache \
    && a2enmod rewrite headers expires \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        msmtp \
        msmtp-mta \
        postgresql-client \
        default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

# Route mail() at Mailpit so outgoing forum mail is captured, never sent.
COPY .docker/php/msmtprc /etc/msmtprc
RUN chmod 0644 /etc/msmtprc

COPY .docker/php/php.ini /usr/local/etc/php/conf.d/zz-smf.ini
COPY .docker/php/vhost.conf /etc/apache2/sites-available/000-default.conf
COPY .docker/php/entrypoint.sh /usr/local/bin/smf-entrypoint

RUN chmod +x /usr/local/bin/smf-entrypoint

WORKDIR /var/www/html

ENTRYPOINT ["/usr/local/bin/smf-entrypoint"]
CMD ["apache2-foreground"]
