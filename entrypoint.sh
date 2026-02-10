#!/bin/sh

delete_entrypoint() {
    rm -rf /var/www/html/entrypoint.sh
}

upgrade_ilias() {
    # Move relevant files to a "safe" location.
    mv /var/www/html/ilias.ini.php /tmp/ilias.ini.php
    mv /var/www/html/data /tmp/data

    # Nuke /var/www/html.
    rm -rf /var/www/html/*

    # Copy new ILIAS installation
    if [ -w "/app" ]; then
        mv /app/* /var/www/html
    else
        cp -r /app/* /var/www/html
    fi

    # Bring relevant files back out again.
    mv /tmp/ilias.ini.php /var/www/html/ilias.ini.php
    mv /tmp/data /var/www/html/data

    /usr/local/bin/php /var/www/html/setup/cli.php update -q -y
}

install_ilias() {
    # Copy new ILIAS installation
    cp -r /app/* /var/www/html

    /usr/local/bin/php /var/www/html/setup/cli.php install /config.json -q -y
}


if [ "$(ls -A /var/www/html)" ] && [ -z "$DEVELOPMENT" ]; then
    upgrade_ilias
    delete_entrypoint
fi

if [ -z "$(ls -A /var/www/html)" ] && [ -z "$DEVELOPMENT" ]; then
    install_ilias
    delete_entrypoint
fi

apache2-foreground
