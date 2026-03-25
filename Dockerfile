FROM php:8.4-apache

ARG DEBIAN_FRONTEND=noninteractive

ADD --chmod=0755 https://deb.nodesource.com/setup_22.x /tmp/setup_node
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
ADD --chmod=0755 https://getcomposer.org/installer /tmp/setup_composer

# Copy ILIAS
COPY . /app
WORKDIR /app

# Install dependencies
RUN /tmp/setup_node
RUN apt update; apt upgrade; apt install -y default-jdk ssmtp ghostscript nano nodejs
RUN php /tmp/setup_composer --install-dir=/usr/local/bin --filename=composer

RUN install-php-extensions gd dom xsl pdo pdo_mysql curl json simplexml libxml xml zip imagick mbstring xmlrpc soap ldap

# Install composer
RUN npm clean-install --omit=dev --ignore-scripts
RUN composer install --no-dev

# Apply php configuration.
RUN echo "max_execution_time = 600" >> $PHP_INI_DIR/conf.d/10-docker-php.ini && \
    echo "memory_limit = 512M" >> $PHP_INI_DIR/conf.d/10-docker-php.ini && \
    echo "error_reporting = E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT" >> $PHP_INI_DIR/conf.d/10-docker-php.ini && \
    echo "display_errors = Off" >> $PHP_INI_DIR/conf.d/10-docker-php.ini && \
    echo "post_max_size = 256M" >> $PHP_INI_DIR/conf.d/10-docker-php.ini && \
    echo "upload_max_filesize = 256M" >> $PHP_INI_DIR/conf.d/10-docker-php.ini && \
    echo "session.gc_probability = 1" >> $PHP_INI_DIR/conf.d/10-docker-php.ini && \
    echo "session.gc_divisor = 100" >> $PHP_INI_DIR/conf.d/10-docker-php.ini && \
    echo "session.gc_maxlifetime = 14400" >> $PHP_INI_DIR/conf.d/10-docker-php.ini && \
    echo "session.hash_function = 0" >> $PHP_INI_DIR/conf.d/10-docker-php.ini && \
    echo "session.cookie_httponly = On" >> $PHP_INI_DIR/conf.d/10-docker-php.ini && \
    echo "session.save_handler = files" >> $PHP_INI_DIR/conf.d/10-docker-php.ini && \
    echo "session.cookie_secure = On" >> $PHP_INI_DIR/conf.d/10-docker-php.ini && \
    echo "allow_url_fopen = 1" >> $PHP_INI_DIR/conf.d/10-docker-php.ini && \
    echo "max_input_vars = 10000" >> $PHP_INI_DIR/conf.d/10-docker-php.ini

RUN sed -i '$ d' /etc/apache2/sites-enabled/000-default.conf && \
    sed -i 's|/var/www/html|/var/www/html/public|' /etc/apache2/sites-enabled/000-default.conf && \
    echo "RemoteIPHeader X-Real-IP" >> /etc/apache2/sites-enabled/000-default.conf && \
    echo "RemoteIPTrustedProxy 0.0.0.0/0" >> /etc/apache2/sites-enabled/000-default.conf && \
    echo "</VirtualHost>" >> /etc/apache2/sites-enabled/000-default.conf

# Apply apache httpd configuration.
RUN a2enmod remoteip headers ssl

RUN composer du

CMD ["apache2-foreground"]
ENTRYPOINT ["/app/entrypoint.sh"]
