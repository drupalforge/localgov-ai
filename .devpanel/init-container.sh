#!/bin/bash
# ---------------------------------------------------------------------
# Copyright (C) 2025 DevPanel
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU Affero General Public License as
# published by the Free Software Foundation version 3 of the
# License.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Affero General Public License for more details.
#
# For GNU Affero General Public License see <https://www.gnu.org/licenses/>.
# ----------------------------------------------------------------------

#== Import database
if [[ $(mysql -h$DB_HOST -P$DB_PORT -u$DB_USER -p$DB_PASSWORD $DB_NAME -e "show tables;") == '' ]]; then
  if [[ -f "$APP_ROOT/.devpanel/dumps/db.sql.gz" ]]; then
    echo  'Import mysql file ...'
    drush sqlq --file="$APP_ROOT/.devpanel/dumps/db.sql.gz" --file-delete
  fi

  #== Import vector files
  if [[ -f "$APP_ROOT/.devpanel/dumps/pgvector.sql.gz" ]]; then
    # Make sure to create the extension before importing the SQL file.
    PGPASSWORD="db" psql --quiet --host=$PG_HOST --username=db -d db -c "CREATE EXTENSION IF NOT EXISTS vector;"
    # Extract the pgvector.sql.gz file
    sudo gunzip -c "$APP_ROOT/.devpanel/dumps/pgvector.sql.gz" > "$APP_ROOT/.devpanel/dumps/pgvector.sql"
    PGPASSWORD="db" psql --quiet --host=$PG_HOST --username=db -d db -f "$APP_ROOT/.devpanel/dumps/pgvector.sql"
    # Remove the extracted file
    sudo rm -rf $APP_ROOT/.devpanel/dumps/pgvector.sql
  fi
fi

if [[ -n "$DB_SYNC_VOL" ]]; then
  if [[ ! -f "/var/www/build/.devpanel/init-container.sh" ]]; then
    echo  'Sync volume...'
    sudo chown -R 1000:1000 /var/www/build
    rsync -av --delete --delete-excluded $APP_ROOT/ /var/www/build
  fi
fi

drush -n updb
echo
echo 'Run cron.'
drush cron
echo
echo 'Populate caches.'
drush cache:warm
$APP_ROOT/.devpanel/warm
