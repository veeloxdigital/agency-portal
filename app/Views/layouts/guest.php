<?php

use App\Core\Env;

$appName = Env::get('APP_NAME', 'Veelox Digital');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'Portal') ?> · <?= htmlspecialchars((string) $appName) ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="guest-page">
    <main class="login-shell">
        <?= $content ?>
    </main>
</body>
</html>
