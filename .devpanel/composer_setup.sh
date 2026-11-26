#!/usr/bin/env bash
set -eu -o pipefail
cd $APP_ROOT

# Create required composer.json and composer.lock files
composer create-project -n --no-install ${PROJECT:=drupal/localgov_project}:^4
cp -r "${PROJECT#*/}"/* ./
rm -rf "${PROJECT#*/}" patches.lock.json

# Scaffold settings.php from Drupal core instead of LocalGov's assets.
# LocalGov's settings.php includes settings.lando.php, whose database settings
# override the DevPanel ones, and the DevPanel patch is written for core's file.
composer config -jm extra.drupal-scaffold.file-mapping '{
    "[web-root]/sites/default/settings.php": {
        "path": "web/core/assets/scaffold/files/default.settings.php",
        "overwrite": false
    },
    "[web-root]/sites/default/settings.lando.php": false
}'

# Patch settings.php.
composer config scripts.post-drupal-scaffold-cmd \
    'cd web/sites/default && test -z "$(grep '\''include \$devpanel_settings;'\'' settings.php)" && patch -Np1 -r /dev/null < $APP_ROOT/.devpanel/drupal-settings.patch || :'
