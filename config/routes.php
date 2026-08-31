<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\CustomerController;
use App\Controllers\DashboardController;
use App\Controllers\PackageController;
use App\Controllers\OrderController;
use App\Controllers\InvoiceController;

return [
    ['GET', '/', [DashboardController::class, 'index'], ['auth']],
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
];
