# Veelox Digital Agency Portal

Current version: **1.0.2**

The initial foundation for a lightweight customer, order, billing and support platform designed for DirectAdmin hosting with PHP 8.2+ and MySQL/MariaDB.

## Included in this foundation

- Secure session authentication with administrator, staff and customer roles
- Responsive Veelox Digital dashboard
- Database schema for customers, mixed one-off/recurring packages, orders, invoices, Stripe/manual payments, tickets, email logs and activity logs
- Monetary values stored as integer pence to avoid rounding errors
- CSRF protection, prepared database queries and secure password hashing
- PHPMailer and Stripe dependencies declared for the next implementation stages
- GBP defaults, no VAT calculation, and configurable invoice/bank settings
- Complete customer management with search, filters, profiles, editing, archiving, internal notes and customer portal login creation
- Complete plans and packages management with one-off, monthly and yearly billing, setup fees, feature lists, visibility controls, duplication, archiving and performance totals
- Complete customer order management with package assignment, custom pricing, recurring dates, staff assignment and workflow statuses
- Complete invoicing with line items, printable customer invoices, outstanding balances and manual bank-transfer payment recording
- Stripe Checkout card payments with signed webhooks, idempotent reconciliation and customer payment buttons
- Self-contained authenticated SMTP email delivery, editable templates, notification preferences and delivery logs
- Complete support ticket system with departments, priorities, assignments, customer replies, private notes and protected attachments
- Revenue reporting with date filters, payment and billing breakdowns, package performance, overdue balances, renewals and CSV exports
- Production administration with agency branding, staff access, support departments, audit history, backups, health checks and security hardening

## Upgrading an existing 1.0.1 installation

1. Back up the existing files and database.
2. Download and extract the 1.0.2 package locally.
3. Upload the contents over the existing project and allow DirectAdmin to replace matching application files.
4. Keep your existing `.env` file and `storage/installed.lock` file.
5. Do not run `install.php` again and do not re-import `database/schema.sql`.
6. Refresh the portal on a mobile device. No database update is required.

## DirectAdmin installation — no SSH required

1. Create a new, empty MySQL database and database user in DirectAdmin. Give that user full permissions for the database.
2. Upload and extract the project files.
3. Point the portal domain or subdomain document root at the project's `public` directory.
4. Select PHP 8.2 or newer and enable PDO MySQL in DirectAdmin.
5. Visit `https://your-portal-domain.example/install.php` in your browser.
6. Enter the DirectAdmin database details, first administrator, SMTP details and optional bank details.
7. Press **Install Veelox Digital**. The browser installer creates `.env`, imports the complete database and creates the administrator automatically.
8. Sign in. The installer locks itself as soon as installation succeeds.

The finished release will include the PHPMailer and Stripe libraries in the upload package; Composer and SSH will not be required on your hosting account.

## Production operation

- Use **Settings → Download database backup** before upgrades or major changes.
- Keep `.env` and `storage/installed.lock` when replacing application files.
- Keep `APP_DEBUG=false` on the live portal.
- Review **Settings → System health** after changing PHP versions or server configuration.
- Maintenance mode blocks customer and staff pages while administrators retain access.
- Never expose the project root publicly; the domain document root must remain the `public` directory.

Never commit or share the `.env` file. Stripe secret keys, SMTP passwords and database credentials belong there only.
