<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\CustomerController;
use App\Controllers\DashboardController;
use App\Controllers\PackageController;
use App\Controllers\OrderController;
use App\Controllers\InvoiceController;
use App\Controllers\StripeController;
use App\Controllers\EmailController;
use App\Controllers\TicketController;

return [
    ['GET', '/', [DashboardController::class, 'index'], ['auth']],
    ['POST', '/stripe/webhook', [StripeController::class, 'webhook'], []],
    ['GET', '/login', [AuthController::class, 'showLogin'], ['guest']],
    ['POST', '/login', [AuthController::class, 'login'], ['guest', 'csrf']],
    ['POST', '/logout', [AuthController::class, 'logout'], ['auth', 'csrf']],
    ['GET', '/customers', [CustomerController::class, 'index'], ['auth', 'role:admin,staff']],
    ['GET', '/customers/create', [CustomerController::class, 'create'], ['auth', 'role:admin,staff']],
    ['POST', '/customers', [CustomerController::class, 'store'], ['auth', 'role:admin,staff', 'csrf']],
    ['GET', '/customers/{id}', [CustomerController::class, 'show'], ['auth', 'role:admin,staff']],
    ['GET', '/customers/{id}/edit', [CustomerController::class, 'edit'], ['auth', 'role:admin,staff']],
    ['POST', '/customers/{id}', [CustomerController::class, 'update'], ['auth', 'role:admin,staff', 'csrf']],
    ['POST', '/customers/{id}/archive', [CustomerController::class, 'archive'], ['auth', 'role:admin,staff', 'csrf']],
    ['POST', '/customers/{id}/portal-access', [CustomerController::class, 'createPortalAccess'], ['auth', 'role:admin,staff', 'csrf']],
    ['GET', '/packages', [PackageController::class, 'index'], ['auth', 'role:admin,staff']],
    ['GET', '/packages/create', [PackageController::class, 'create'], ['auth', 'role:admin,staff']],
    ['POST', '/packages', [PackageController::class, 'store'], ['auth', 'role:admin,staff', 'csrf']],
    ['GET', '/packages/{id}', [PackageController::class, 'show'], ['auth', 'role:admin,staff']],
    ['GET', '/packages/{id}/edit', [PackageController::class, 'edit'], ['auth', 'role:admin,staff']],
    ['POST', '/packages/{id}', [PackageController::class, 'update'], ['auth', 'role:admin,staff', 'csrf']],
    ['POST', '/packages/{id}/duplicate', [PackageController::class, 'duplicate'], ['auth', 'role:admin,staff', 'csrf']],
    ['POST', '/packages/{id}/archive', [PackageController::class, 'archive'], ['auth', 'role:admin,staff', 'csrf']],
    ['GET', '/orders', [OrderController::class, 'index'], ['auth', 'role:admin,staff']],
    ['GET', '/orders/create', [OrderController::class, 'create'], ['auth', 'role:admin,staff']],
    ['POST', '/orders', [OrderController::class, 'store'], ['auth', 'role:admin,staff', 'csrf']],
    ['GET', '/orders/{id}', [OrderController::class, 'show'], ['auth', 'role:admin,staff']],
    ['GET', '/orders/{id}/edit', [OrderController::class, 'edit'], ['auth', 'role:admin,staff']],
    ['POST', '/orders/{id}', [OrderController::class, 'update'], ['auth', 'role:admin,staff', 'csrf']],
    ['POST', '/orders/{id}/status', [OrderController::class, 'status'], ['auth', 'role:admin,staff', 'csrf']],
    ['GET', '/invoices', [InvoiceController::class, 'index'], ['auth', 'role:admin,staff']],
    ['GET', '/invoices/create', [InvoiceController::class, 'create'], ['auth', 'role:admin,staff']],
    ['POST', '/invoices', [InvoiceController::class, 'store'], ['auth', 'role:admin,staff', 'csrf']],
    ['GET', '/invoices/{id}', [InvoiceController::class, 'show'], ['auth', 'role:admin,staff']],
    ['GET', '/invoices/{id}/edit', [InvoiceController::class, 'edit'], ['auth', 'role:admin,staff']],
    ['POST', '/invoices/{id}', [InvoiceController::class, 'update'], ['auth', 'role:admin,staff', 'csrf']],
    ['POST', '/invoices/{id}/status', [InvoiceController::class, 'status'], ['auth', 'role:admin,staff', 'csrf']],
    ['POST', '/invoices/{id}/payments', [InvoiceController::class, 'payment'], ['auth', 'role:admin,staff', 'csrf']],
    ['GET', '/portal/invoices', [InvoiceController::class, 'portalIndex'], ['auth', 'role:customer']],
    ['GET', '/portal/invoices/{id}', [InvoiceController::class, 'portalShow'], ['auth', 'role:customer']],
    ['POST', '/portal/invoices/{id}/stripe-checkout', [StripeController::class, 'checkout'], ['auth', 'role:customer', 'csrf']],
    ['GET', '/emails', [EmailController::class, 'index'], ['auth', 'role:admin']],
    ['POST', '/emails/test', [EmailController::class, 'test'], ['auth', 'role:admin', 'csrf']],
    ['GET', '/emails/templates/{id}/edit', [EmailController::class, 'edit'], ['auth', 'role:admin']],
    ['POST', '/emails/templates/{id}', [EmailController::class, 'update'], ['auth', 'role:admin', 'csrf']],
    ['POST', '/invoices/{id}/email', [EmailController::class, 'resendInvoice'], ['auth', 'role:admin,staff', 'csrf']],
    ['GET', '/tickets', [TicketController::class, 'index'], ['auth', 'role:admin,staff']],
    ['GET', '/tickets/create', [TicketController::class, 'create'], ['auth', 'role:admin,staff']],
    ['POST', '/tickets', [TicketController::class, 'store'], ['auth', 'role:admin,staff', 'csrf']],
    ['GET', '/tickets/{id}', [TicketController::class, 'show'], ['auth', 'role:admin,staff']],
    ['POST', '/tickets/{id}/reply', [TicketController::class, 'reply'], ['auth', 'role:admin,staff', 'csrf']],
    ['POST', '/tickets/{id}/update', [TicketController::class, 'update'], ['auth', 'role:admin,staff', 'csrf']],
    ['GET', '/portal/tickets', [TicketController::class, 'portalIndex'], ['auth', 'role:customer']],
    ['GET', '/portal/tickets/create', [TicketController::class, 'portalCreate'], ['auth', 'role:customer']],
    ['POST', '/portal/tickets', [TicketController::class, 'portalStore'], ['auth', 'role:customer', 'csrf']],
    ['GET', '/portal/tickets/{id}', [TicketController::class, 'portalShow'], ['auth', 'role:customer']],
    ['POST', '/portal/tickets/{id}/reply', [TicketController::class, 'portalReply'], ['auth', 'role:customer', 'csrf']],
    ['GET', '/ticket-attachments/{id}', [TicketController::class, 'attachment'], ['auth']],
];
