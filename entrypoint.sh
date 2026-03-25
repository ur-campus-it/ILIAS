#!/bin/sh

delete_entrypoint() {
    rm -rf /var/www/html/entrypoint.sh
}

upgrade_ilias() {

    DATADIR="/var/www/html/data"
    if [ "$(ls -A /var/www/html/public/data)" ]; then
        DATADIR="/var/www/html/public/data"
    fi

    # Move relevant files to a "safe" location.
    mv /var/www/html/ilias.ini.php /tmp/ilias.ini.php
    mv "$DATADIR" /tmp/data

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
    mv /tmp/data "$DATADIR"

    # todo: how do i determine if i am below version 10?
    # if []; then
    #     mv /var/www/html/data /var/www/html/public/data
    #     mkdir -p /var/www/html/public/Customizing/plugins
    #     mv /var/www/html/Customizing/global/plugins/Services/* /var/www/html/public/Customizing/plugins/
    #     mv /var/www/html/Customizing/global/plugins/Modules/* /var/www/html/public/Customizing/plugins/
    # fi

    # /usr/local/bin/php /var/www/html/cli/setup.php update -q -y
}

install_ilias() {
    # Copy new ILIAS installation
    cp -r /app/* /var/www/html
    cd /var/www/html
    /usr/local/bin/php /var/www/html/cli/setup.php install "${CONFIG_LOCATION}" -y
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
