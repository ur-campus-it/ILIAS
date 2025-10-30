#!/bin/sh

if [ "$(ls -A /var/www/html)" ]; then
    # Move relevant files to a "safe" location.
    mv /var/www/html/ilias.ini.php /tmp/ilias.ini.php
    mv /var/www/html/data /tmp/data

    # Nuke /var/www/html.
    rm -rf /var/www/html/*

    # Bring relevant files back out again.
    mv /tmp/ilias.ini.php /var/www/html/ilias.ini.php
    mv /tmp/data /var/www/html/data
fi

# Move ILIAS data to /var/www/html.
cp -r /app/* /var/www/html

# Delete potentially invalid files.
rm -f /var/www/html/entrypoint.sh

apache2-foreground
