<?php

$databases['default']['default']['database'] = getenv('DB_NAME');
$databases['default']['default']['username'] = getenv('DB_USER');
$databases['default']['default']['password'] = getenv('DB_PASSWORD');
$databases['default']['default']['host'] = getenv('DB_HOST');
$databases['default']['default']['port'] = getenv('DB_PORT');
$databases['default']['default']['driver'] = getenv('DB_DRIVER');
$databases['default']['default']['isolation_level'] = 'READ COMMITTED';
$settings['hash_salt'] = file_get_contents(__DIR__ . '/salt.txt');
$settings['config_sync_directory'] = '../config/sync';
$settings['file_private_path'] = '../private';
$settings['trusted_host_patterns'] = [getenv('DP_HOSTNAME') ?: '.*'];

// Resolve the vector database from the environment at runtime, so a database
// dump made in one environment works in another. DevPanel's PostgreSQL
// service listens on the same port as its MySQL service, so PGPORT may be
// empty there.
if (getenv('PGHOST')) {
  $config['ai_vdb_provider_postgres.settings']['host'] = getenv('PGHOST');
  $config['ai_vdb_provider_postgres.settings']['port'] = (int) (getenv('PGPORT') ?: getenv('DB_PORT') ?: 5432);
  $config['ai_vdb_provider_postgres.settings']['username'] = getenv('PGUSER');
  $config['ai_vdb_provider_postgres.settings']['default_database'] = getenv('PGDATABASE');
  $config['search_api.server.content_for_ai']['backend_config']['database_settings']['database_name'] = getenv('PGDATABASE');
}
