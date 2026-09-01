# Veelox Digital changelog

## Version 1.0.1 — Compact Mobile Navigation

- Replaced the bulky two-column mobile menu with a compact horizontal navigation strip.
- Placed agency branding and account controls on one short top row.
- Added touch-friendly horizontal scrolling for portals with every module enabled.
- Reduced mobile navigation padding, icon size and dashboard top spacing.
- Preserved active-page highlighting and access to every permitted module.
- No database update is required.

## Version 1.0.0 — Production Release

- Added browser-managed agency identity, contact details, address, invoice defaults and footer.
- Added secure PNG, JPG and WebP agency-logo uploads and live invoice branding.
- Added administrator and staff account creation, role changes and suspension.
- Added one-time temporary staff password generation and administrator resets.
- Added authenticated password changes for administrators, staff and customers.
- Prevented self-suspension and removal of the final active administrator.
- Added support department creation, routing email and active/inactive controls.
- Added persistent login rate limiting after repeated failed attempts.
- Added HSTS on HTTPS, anti-framing, MIME-sniffing, referrer and browser-permission headers.
- Added maintenance mode that preserves administrator access.
- Added streaming SQL database backups from the browser.
- Added PHP, database, HTTPS, debug, storage, SMTP and Stripe health checks.
- Added audit history for authentication, customer, order, invoice, payment, ticket and settings events.
- Connected agency details and invoice terms to invoices, emails and application branding.
- Removed the final unfinished navigation placeholder and completed mobile administration layouts.

## Version 0.9.0 — Revenue Reports

- Added revenue reporting for custom date ranges.
- Added net successful-payment totals with refunds deducted.
- Added invoiced, outstanding and overdue balance metrics.
- Added a live six-month revenue chart to the staff dashboard.
- Added monthly revenue trends and business activity totals.
- Added Stripe, bank-transfer and manual payment breakdowns.
- Added one-off, recurring and unassigned revenue breakdowns.
- Added package performance and highest-value customer tables.
- Added overdue invoice and upcoming 60-day renewal reports.
- Added UTF-8 CSV exports for payments, outstanding invoices and renewals.
- Kept all agency financial reporting inaccessible to customer accounts.

## Version 0.8.0 — Support Tickets

- Added separate staff and customer support-ticket areas.
- Added ticket creation, threaded replies and collision-safe ticket numbers.
- Added configurable departments, low-to-urgent priorities and ticket assignments.
- Added open, customer-reply, staff-reply, resolved and closed workflows.
- Added staff-only private notes that are never exposed in the customer portal.
- Added protected JPG, PNG, GIF, PDF, TXT and ZIP attachments up to 5 MB.
- Added ownership checks for customer ticket pages and attachment downloads.
- Added ticket-created and staff-reply customer email notifications.
- Added ticket search, status, priority and department filters.
- Connected the customer dashboard support shortcut and working navigation links.

## Version 0.7.0 — Transactional Email

- Added self-contained authenticated SMTP delivery with STARTTLS, SSL/TLS and plain SMTP modes.
- Added browser-based SMTP configuration for DirectAdmin hosting without SSH.
- Added responsive Veelox Digital HTML emails with plain-text alternatives.
- Added editable portal welcome, order, invoice and payment templates.
- Added template enable and disable controls and documented template variables.
- Added automatic portal welcome and temporary-password emails.
- Added automatic order-created and order-status emails.
- Added automatic invoice emails when order invoices become outstanding.
- Added Stripe payment confirmation emails after verified webhook completion.
- Added manual invoice resend controls.
- Added test-email delivery.
- Added queued, sent and failed delivery logs with SMTP error details.
- Added per-customer transactional email notification preferences.

## Version 0.6.2 — Stripe Checkout Compatibility Hotfix

- Explicitly disables Stripe Managed Payments for Veelox invoice Checkout Sessions.
- Prevents Managed Payments from requiring Stripe product tax codes.
- Keeps the customer charge equal to the outstanding Veelox invoice balance.
- Retains Stripe-hosted Checkout, signed webhook confirmation and payment reconciliation.
- No database migration or Stripe key reconfiguration is required.

## Version 0.6.1 — Stripe Managed Payments Hotfix

- Removed the unsupported `payment_method_types` Checkout parameter.
- Added compatibility with Stripe accounts where Managed Payments is enabled by default.
- Stripe now selects the available payment methods using the account's Managed Payments configuration.
- No database migration is required.

## Version 0.6.0 — Stripe Card Payments

- Added Stripe-hosted Checkout for outstanding customer invoices.
- Added a customer portal card-payment button that appears only when Stripe is configured.
- Added a browser-based Stripe configuration screen for DirectAdmin installations without SSH.
- Added a signed webhook endpoint with five-minute timestamp tolerance.
- Added webhook event deduplication to prevent repeated Stripe events from recording payment twice.
- Added pending, successful, failed and expired Stripe payment tracking.
- Added automatic invoice reconciliation after verified payment confirmation.
- Added automatic linked-order payment status updates.
- Added Checkout success and cancellation messages without trusting browser redirects as payment proof.
- Preserved manual bank-transfer payment support alongside Stripe.
- Added test-mode and live-mode key validation.

## Version 0.5.0 — Invoices & Bank Transfers

- Fixed Active Orders so pending, awaiting-payment, paid and active orders are counted.
- Connected Outstanding to sent, overdue and partially-paid invoice balances.
- Automatically creates a sent invoice whenever an order moves to Awaiting Payment.
- Added invoice listing, search and status filters.
- Added manual and order-linked invoices with multiple line items.
- Added automatic `INV-2026-0001` invoice numbering and configurable due days.
- Added draft, sent, partially paid, paid, overdue, void and refunded states.
- Added printable invoice layouts with customer and bank-transfer details.
- Added full and partial manual bank-transfer payment recording.
- Automatically marks fully paid linked orders as paid.
- Added customer-only invoice listing and printable invoice access with ownership checks.

## Version 0.4.0 — Customer Orders

- Replaced the customer dashboard with a private account-only overview; customers no longer see Veelox Digital performance or internal agency statistics.
- Added searchable customer order listing with status and billing filters.
- Added package-based and custom service orders.
- Added automatic package price, setup fee and billing defaults.
- Added per-order custom pricing without changing the package price.
- Added collision-safe yearly order numbers such as `ORD-2026-0001`.
- Added one-off, monthly and yearly billing terms.
- Added start and next-renewal dates.
- Added administrator and staff assignment.
- Added draft, pending, awaiting payment, paid, active, completed, cancelled and refunded workflows.
- Added order detail, edit and quick status-change screens.
- Added private internal order notes.
- Added order totals stored as integer pence.
- Added customer and package links from order records.
- Added responsive order layouts and a working dashboard shortcut.

## Version 0.3.0 — Plans & Packages

- Added a working Plans & Packages navigation section.
- Added searchable package listing with billing and status filters.
- Added create, view and edit package screens.
- Added one-off, monthly and yearly billing options.
- Added setup fees with all monetary values stored safely as integer pence.
- Added package descriptions and ordered included-feature lists.
- Added public and internal-only visibility controls.
- Added active, inactive and archived package states.
- Added package duplication as an inactive copy.
- Added safe archiving without changing existing orders.
- Added order, active-order, customer and revenue totals for each package.
- Added package creation to dashboard quick actions.
- Added administrator and staff route permissions and CSRF protection.
- Added responsive desktop, tablet and mobile package layouts.
- Added a browser database updater for DirectAdmin hosting without SSH.

## Version 0.2.0 — Customer Management

- Added customer listing, search and status filters.
- Added customer creation, profiles, editing and archiving.
- Added internal notes, billing addresses and portal access creation.
- Added automatic Veelox customer account numbers.

## Version 0.1.0 — Foundation

- Added secure authentication, roles, dashboard and initial database schema.
- Added the browser-based DirectAdmin installer.
