# SMF 2.1 development image: PHP + Apache with everything other/requirements.md asks for.
#
# 2.1 supports PHP 7.1 - 8.4. 8.2 is the default here rather than the newest:
# 2.1 is procedural, PHP-5-era code, and 8.3/8.4 add deprecation notices that
# would flood the log on every page. Override PHP_VERSION to test another one.
ARG PHP_VERSION=8.2
FROM php:${PHP_VERSION}-apache

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

# Required by SMF: mbstring, fileinfo, pgsql (pg_connect), mysqli.
# Recommended by SMF: gd, intl, curl, exif, ftp, xsl.
# No PDO: 2.1 talks to the database through pg_* and mysqli_* only.
RUN install-php-extensions \
        pgsql \
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
        unzip \
        curl \
        ca-certificates \
        msmtp \
        msmtp-mta \
        postgresql-client \
        default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

# Note on the database clients: they are here for pg_isready/mysqladmin (the
# entrypoint's readiness waits) and for interactive psql/mysql sessions. They
# are deliberately NOT used to dump the baseline -- Debian ships pg_dump 15,
# which refuses to dump a PostgreSQL 17 server. .docker/baseline/dump.sh runs
# the dump inside the database containers instead. Do not "fix" that.
#
# There is also no composer here on purpose: SMF 2.1 has no runtime vendor/
# directory, and its only composer dependency is a dev-only build-tools package
# pulled from a git repository. Installing it at boot would cost minutes and
# buy nothing.

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
