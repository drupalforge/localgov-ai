Files in the `.devpanel` directory control DevPanel deployment for this app.


## Startup scripts

- [`custom_package_installer.sh`](custom_package_installer.sh): Installs
  extra system software. Use sudo to run as root.
- [`init-container.sh`](init-container.sh): Checks for a database dump and
  imports it.
- [`init.sh`](init.sh): Start with blank an application. Performs additional startup tasks. Supporting files:
  - [`composer_setup.sh`](composer_setup.sh): Generates composer.json and
    composer.lock files. Not needed if you supply these files yourself.
  - [`settings.devpanel.php`](settings.devpanel.php): Settings for running
    Drupal as a DevPanel app.
  - [`drupal-settings.patch`](drupal-settings.patch): Patch for settings.php
    to include settings.devpanel.php. Installed by the post-drupal-scaffold-cmd
    script. Make sure this works with your Composer project.
  - [`warm`](warm): Loads any path to build caches. If no path is provided,
    defaults to /.
- [`re-config.sh`](re-config.sh): Start with an template that had the .devpanel/dumps folder that contains a sql file and a zip static file.
  Runs when container configuration is changed in DevPanel or the app is deployed to a hosting provider.

## Git integration
- [`config.yml`](config.yml): Defines tasks to run when Git is configured to
  update the app automatically.


## Deployment
- [`init-container.sh`](init-container.sh): Start with a image tag that is build from a template.
  Import database and Synchronize source code from the image tag to the volume of the container

## Export to template
- [`create_quickstart.sh`](create_quickstart.sh): Archives the
  database and files for the _Drupal Forge Docker Publish Workflow_ which can be
  added in [GitHub Actions](../../actions).