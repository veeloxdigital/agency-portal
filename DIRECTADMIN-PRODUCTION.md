# Veelox Digital 1.0.0 — DirectAdmin production guide

## Before upgrading

1. Sign in as an administrator and download a database backup from **Settings** if that option is already available. Otherwise export the database through DirectAdmin/phpMyAdmin.
2. Download a copy of the existing project files.
3. Confirm that you know where `.env` and `storage/installed.lock` are located. DirectAdmin may hide these files.

## Upgrade from 0.9.0

1. Upload and extract the 1.0.0 ZIP over the existing project.
2. Preserve the existing `.env` and `storage/installed.lock` files.
3. Do not run `install.php` and do not import `database/schema.sql`.
4. Sign in as an administrator and visit `/update.php`.
5. Run `010_production.sql` using the browser updater.
6. Open **Settings** and save the agency and invoice defaults.
7. Review **System health** and correct every failed production check.

## DirectAdmin configuration

- Point the portal domain's document root to the project's `public` directory.
- Use PHP 8.2 or newer with PDO MySQL, Fileinfo and OpenSSL enabled.
- Keep `.env` outside the public document root and never download or share it.
- Use HTTPS and keep `APP_URL` set to the exact HTTPS portal URL.
- Set `APP_DEBUG=false` on the live portal.
- Ensure `storage` is writable by PHP. Ticket uploads are stored outside the public directory.
- The branding upload folder is created automatically under `public/uploads/branding`.

## Recovery

If an upgrade fails, restore the backed-up project files and import the matching SQL backup in phpMyAdmin. Do not combine files from one release with a database restored from a later release.
