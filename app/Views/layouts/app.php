<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Env;

$currentUser = Auth::user();
$appName = Env::get('APP_NAME', 'Veelox Digital');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'Portal') ?> · <?= htmlspecialchars((string) $appName) ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <link rel="stylesheet" href="/assets/css/customers.css">
    <link rel="stylesheet" href="/assets/css/packages.css">
    <link rel="stylesheet" href="/assets/css/orders.css">
    <link rel="stylesheet" href="/assets/css/invoices.css">
    <link rel="stylesheet" href="/assets/css/emails.css">
    <link rel="stylesheet" href="/assets/css/tickets.css">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <a class="brand" href="/">
            <span class="brand-mark">V</span>
            <span><strong>Veelox</strong><small>Digital</small></span>
        </a>
        <nav class="nav-list" aria-label="Main navigation">
            <?php $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH); ?>
            <a class="<?= $path === '/' ? 'active' : '' ?>" href="/"><span>⌂</span> Dashboard</a>
            <?php if (($currentUser['role'] ?? '') !== 'customer'): ?><a class="<?= str_starts_with((string) $path, '/customers') ? 'active' : '' ?>" href="/customers"><span>◎</span> Customers</a><?php endif; ?>
            <?php if (($currentUser['role'] ?? '') !== 'customer'): ?><a class="<?= str_starts_with((string) $path, '/packages') ? 'active' : '' ?>" href="/packages"><span>▦</span> Plans &amp; packages</a><?php endif; ?>
            <?php if (($currentUser['role'] ?? '') !== 'customer'): ?><a class="<?= str_starts_with((string) $path, '/orders') ? 'active' : '' ?>" href="/orders"><span>◇</span> Orders</a><?php endif; ?>
            <?php if (($currentUser['role'] ?? '') !== 'customer'): ?><a class="<?= str_starts_with((string) $path, '/invoices') ? 'active' : '' ?>" href="/invoices"><span>▤</span> Invoices</a><?php else: ?><a class="<?= str_starts_with((string) $path, '/portal/invoices') ? 'active' : '' ?>" href="/portal/invoices"><span>▤</span> My invoices</a><?php endif; ?>
            <?php if (($currentUser['role'] ?? '') === 'customer'): ?><a class="<?= str_starts_with((string)$path,'/portal/tickets')?'active':'' ?>" href="/portal/tickets"><span>✦</span> Support</a><?php else: ?><a class="<?= str_starts_with((string)$path,'/tickets')?'active':'' ?>" href="/tickets"><span>✦</span> Support tickets</a><?php endif; ?>
            <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
                <span class="nav-disabled"><span>↗</span> Reports <small>Soon</small></span>
                <a class="<?= str_starts_with((string) $path, '/emails') ? 'active' : '' ?>" href="/emails"><span>✉</span> Email centre</a>
                <span class="nav-disabled"><span>⚙</span> Settings <small>Soon</small></span>
            <?php endif; ?>
        </nav>
        <div class="sidebar-user">
            <div class="avatar"><?= htmlspecialchars(strtoupper(substr((string) ($currentUser['name'] ?? 'V'), 0, 1))) ?></div>
            <div><strong><?= htmlspecialchars((string) ($currentUser['name'] ?? 'User')) ?></strong><small><?= htmlspecialchars(ucfirst((string) ($currentUser['role'] ?? ''))) ?></small></div>
            <form action="/logout" method="post">
                <input type="hidden" name="_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                <button class="logout" type="submit" title="Sign out">↪</button>
            </form>
        </div>
    </aside>
    <main class="main-content">
        <?= $content ?>
    </main>
</div>
</body>
</html>
