#!/bin/sh

upgrade_ilias() {
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
}

if [ -n "$DEVELOPMENT" ]; then
    echo "Development mode enabled. Skipping entrypoint.sh."
else
    upgrade_ilias
fi

# Move ILIAS data to /var/www/html.
cp -r /app/* /var/www/html

# Delete potentially invalid files.
rm -f /var/www/html/entrypoint.sh

# Super duper ugly hack that removes google fonts dependency
# sed -i '/preconnect/,+1d' /var/www/html/Customizing/global/plugins/Services/Repository/RepositoryObject/LongEssayAssessment/vendor/edutiek/long-essay-assessment-service/node_modules/long-essay-assessment-writer/dist/index.html
# sed -i '/preconnect/,+1d' /var/www/html/Customizing/global/plugins/Services/Repository/RepositoryObject/LongEssayAssessment/vendor/edutiek/long-essay-assessment-service/node_modules/long-essay-assessment-corrector/dist/index.html

if [ -z "$CONFIG_FILE" ]; then
    CONFIG_FILE="/config/config.json"
fi

/usr/local/bin/php /var/www/html/cli/setup.php install -q -y $CONFIG_FILE

# Run apache.
apache2-foreground
