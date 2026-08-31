# Veelox Digital changelog

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
