<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('veelox_installer');
    session_start();
}

$lockFile = BASE_PATH . '/storage/installed.lock';
$envFile = BASE_PATH . '/.env';
$errors = [];
$complete = false;

function input(string $key, string $default = ''): string
{
    return trim((string) ($_POST[$key] ?? $default));
}

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function envValue(string $value): string
{
    $value = str_replace(["\r", "\n"], '', $value);
    return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
}

function detectedUrl(): string
{
    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $secure ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $directory = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return $scheme . '://' . $host . ($directory === '' ? '' : $directory);
}

function importSchema(PDO $database, string $path): void
{
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException('The database schema file could not be read.');
    }

    $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [];
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if ($statement !== '') {
            $database->exec($statement);
        }
    }
}

$requirements = [
    'PHP 8.2 or newer' => version_compare(PHP_VERSION, '8.2.0', '>='),
    'PDO extension' => extension_loaded('pdo'),
    'PDO MySQL extension' => extension_loaded('pdo_mysql'),
    'Writable project directory' => is_writable(BASE_PATH),
    'Writable storage directory' => is_writable(BASE_PATH . '/storage'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !is_file($lockFile)) {
    $postedToken = (string) ($_POST['_token'] ?? '');
    if (empty($_SESSION['_installer_token']) || !hash_equals($_SESSION['_installer_token'], $postedToken)) {
        $errors[] = 'Your installer session expired. Refresh the page and try again.';
    }

    foreach ($requirements as $label => $passed) {
        if (!$passed) {
            $errors[] = $label . ' is required before installation can continue.';
        }
    }

    $required = ['app_url', 'db_host', 'db_name', 'db_user', 'admin_name', 'admin_email', 'admin_password'];
    foreach ($required as $field) {
        if (input($field) === '') {
            $errors[] = 'Please complete every required field.';
            break;
        }
    }

    if (!filter_var(input('app_url'), FILTER_VALIDATE_URL)) {
        $errors[] = 'Enter a valid portal URL.';
    }
    if (!filter_var(input('admin_email'), FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid administrator email address.';
    }
    if (strlen(input('admin_password')) < 12) {
        $errors[] = 'The administrator password must contain at least 12 characters.';
    }
    if (input('admin_password') !== input('admin_password_confirmation')) {
        $errors[] = 'The administrator passwords do not match.';
    }

    if ($errors === []) {
        try {
            $database = new PDO(
                'mysql:host=' . input('db_host') . ';port=' . input('db_port', '3306') . ';dbname=' . input('db_name') . ';charset=utf8mb4',
                input('db_user'),
                input('db_password'),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );

            $tableCheck = $database->query("SHOW TABLES LIKE 'users'")->fetchColumn();
            if ($tableCheck) {
                throw new RuntimeException('This database already contains portal tables. Please use a new, empty database.');
            }

            importSchema($database, BASE_PATH . '/database/schema.sql');

            $administrator = $database->prepare(
                "INSERT INTO users (role_id, name, email, password_hash, status)
                 SELECT id, :name, :email, :password, 'active' FROM roles WHERE slug = 'admin' LIMIT 1"
            );
            $administrator->execute([
                'name' => input('admin_name'),
                'email' => strtolower(input('admin_email')),
                'password' => password_hash(input('admin_password'), PASSWORD_DEFAULT),
            ]);

            $appKey = bin2hex(random_bytes(32));
            $environment = [
                'APP_NAME=' . envValue('Veelox Digital'),
                'APP_ENV=production',
                'APP_DEBUG=false',
                'APP_URL=' . envValue(rtrim(input('app_url'), '/')),
                'APP_TIMEZONE=' . envValue('Europe/London'),
                'APP_KEY=' . envValue($appKey),
                '',
                'DB_HOST=' . envValue(input('db_host')),
                'DB_PORT=' . envValue(input('db_port', '3306')),
                'DB_DATABASE=' . envValue(input('db_name')),
                'DB_USERNAME=' . envValue(input('db_user')),
                'DB_PASSWORD=' . envValue(input('db_password')),
                '',
                'MAIL_HOST=' . envValue(input('mail_host')),
                'MAIL_PORT=' . envValue(input('mail_port', '587')),
                'MAIL_USERNAME=' . envValue(input('mail_username')),
                'MAIL_PASSWORD=' . envValue(input('mail_password')),
                'MAIL_ENCRYPTION=' . envValue(input('mail_encryption', 'tls')),
                'MAIL_FROM_ADDRESS=' . envValue(input('mail_from_address')),
                'MAIL_FROM_NAME=' . envValue('Veelox Digital'),
                '',
                'STRIPE_PUBLIC_KEY=""',
                'STRIPE_SECRET_KEY=""',
                'STRIPE_WEBHOOK_SECRET=""',
                '',
                'CURRENCY=GBP',
                'INVOICE_PREFIX=INV',
                'TAX_ENABLED=false',
                'TAX_RATE=0',
                'BANK_NAME=' . envValue(input('bank_name')),
                'BANK_ACCOUNT_NAME=' . envValue(input('bank_account_name', 'Veelox Digital')),
                'BANK_SORT_CODE=' . envValue(input('bank_sort_code')),
                'BANK_ACCOUNT_NUMBER=' . envValue(input('bank_account_number')),
                '',
            ];

            $temporaryEnv = BASE_PATH . '/.env.installing';
            if (file_put_contents($temporaryEnv, implode(PHP_EOL, $environment), LOCK_EX) === false || !rename($temporaryEnv, $envFile)) {
                throw new RuntimeException('The installer could not create the .env configuration file.');
            }

            if (file_put_contents($lockFile, 'Installed ' . date(DATE_ATOM), LOCK_EX) === false) {
                throw new RuntimeException('The installation lock file could not be created.');
            }

            $_SESSION = [];
            $complete = true;
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }
    }
}

if (empty($_SESSION['_installer_token'])) {
    $_SESSION['_installer_token'] = bin2hex(random_bytes(32));
}

$installed = is_file($lockFile);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Install Veelox Digital</title>
    <style>
        :root{--ink:#171923;--muted:#687083;--line:#e3e6ed;--violet:#7357eb;--bg:#f4f5f8}*{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at 10% 0,rgba(115,87,235,.14),transparent 30%),var(--bg);color:var(--ink);font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.shell{width:min(940px,calc(100% - 32px));margin:55px auto}.brand{display:flex;align-items:center;gap:12px;margin-bottom:25px}.mark{display:grid;place-items:center;width:42px;height:42px;border-radius:13px;background:linear-gradient(145deg,#9178ff,var(--violet));color:#fff;font-weight:800;box-shadow:0 10px 25px rgba(115,87,235,.25)}.brand strong,.brand small{display:block}.brand small{margin-top:2px;color:var(--muted);font-size:10px;letter-spacing:1.8px;text-transform:uppercase}.card{padding:clamp(24px,5vw,48px);background:#fff;border:1px solid var(--line);border-radius:24px;box-shadow:0 20px 60px rgba(28,32,45,.09)}h1{margin:0;font-size:clamp(30px,5vw,45px);letter-spacing:-1.7px}h2{margin:35px 0 15px;padding-top:28px;border-top:1px solid var(--line);font-size:18px}.intro{color:var(--muted);line-height:1.7}.grid{display:grid;grid-template-columns:repeat(2,1fr);gap:16px}.wide{grid-column:1/-1}label{display:block;color:#3f4654;font-size:12px;font-weight:700}input,select{display:block;width:100%;margin-top:7px;padding:12px 13px;border:1px solid #d9dde5;border-radius:10px;background:#fff;outline:0}input:focus,select:focus{border-color:var(--violet);box-shadow:0 0 0 3px rgba(115,87,235,.11)}button,.button{display:inline-flex;justify-content:center;margin-top:28px;padding:14px 20px;border:0;border-radius:11px;background:var(--violet);color:#fff;font-weight:750;text-decoration:none;cursor:pointer}.notice{margin:20px 0;padding:14px 16px;border-radius:11px;font-size:13px;line-height:1.5}.error{background:#fff0f0;border:1px solid #ffd6d6;color:#9d3434}.success{background:#eafaf3;border:1px solid #c9f0df;color:#237354}.checks{display:grid;grid-template-columns:repeat(2,1fr);gap:8px;margin:20px 0}.check{padding:10px 12px;border-radius:9px;background:#f5f6f8;color:#4e5665;font-size:12px}.check.pass:before{content:'✓ ';color:#239467}.check.fail:before{content:'× ';color:#c64141}@media(max-width:650px){.shell{margin:25px auto}.grid,.checks{grid-template-columns:1fr}.wide{grid-column:auto}.card{border-radius:18px}}
    </style>
</head>
<body>
<main class="shell">
    <div class="brand"><span class="mark">V</span><span><strong>Veelox</strong><small>Digital</small></span></div>
    <section class="card">
        <?php if ($installed || $complete): ?>
            <h1>Installation complete.</h1>
            <p class="intro">The portal database, configuration and administrator account are ready. The installer is now locked.</p>
            <div class="notice success">You can now sign in with the administrator email and password you entered.</div>
            <a class="button" href="/login">Open the portal</a>
        <?php else: ?>
            <h1>Let’s set up your portal.</h1>
            <p class="intro">Enter the details from DirectAdmin below. The installer will create the configuration, import the database and create your administrator—no SSH or command line required.</p>

            <div class="checks">
                <?php foreach ($requirements as $label => $passed): ?>
                    <div class="check <?= $passed ? 'pass' : 'fail' ?>"><?= escape($label) ?></div>
                <?php endforeach; ?>
            </div>

            <?php if ($errors !== []): ?><div class="notice error"><?= implode('<br>', array_map('escape', array_unique($errors))) ?></div><?php endif; ?>

            <form method="post">
                <input type="hidden" name="_token" value="<?= escape($_SESSION['_installer_token']) ?>">
                <h2>Portal</h2>
                <div class="grid"><label class="wide">Portal URL *<input name="app_url" value="<?= escape(input('app_url', detectedUrl())) ?>" required></label></div>

                <h2>DirectAdmin database</h2>
                <div class="grid">
                    <label>Database host *<input name="db_host" value="<?= escape(input('db_host', 'localhost')) ?>" required></label>
                    <label>Database port *<input name="db_port" value="<?= escape(input('db_port', '3306')) ?>" required></label>
                    <label>Database name *<input name="db_name" value="<?= escape(input('db_name')) ?>" required></label>
                    <label>Database username *<input name="db_user" value="<?= escape(input('db_user')) ?>" required></label>
                    <label class="wide">Database password<input type="password" name="db_password" value="<?= escape(input('db_password')) ?>"></label>
                </div>

                <h2>Administrator</h2>
                <div class="grid">
                    <label>Full name *<input name="admin_name" value="<?= escape(input('admin_name')) ?>" required></label>
                    <label>Email address *<input type="email" name="admin_email" value="<?= escape(input('admin_email')) ?>" required></label>
                    <label>Password (12+ characters) *<input type="password" name="admin_password" required></label>
                    <label>Confirm password *<input type="password" name="admin_password_confirmation" required></label>
                </div>

                <h2>Email settings</h2>
                <div class="grid">
                    <label>SMTP host<input name="mail_host" value="<?= escape(input('mail_host')) ?>"></label>
                    <label>SMTP port<input name="mail_port" value="<?= escape(input('mail_port', '587')) ?>"></label>
                    <label>SMTP username<input name="mail_username" value="<?= escape(input('mail_username')) ?>"></label>
                    <label>SMTP password<input type="password" name="mail_password" value="<?= escape(input('mail_password')) ?>"></label>
                    <label>Encryption<select name="mail_encryption"><option value="tls">TLS</option><option value="ssl">SSL</option><option value="">None</option></select></label>
                    <label>From email<input type="email" name="mail_from_address" value="<?= escape(input('mail_from_address')) ?>"></label>
                </div>

                <h2>Bank transfer details</h2>
                <div class="grid">
                    <label>Bank name<input name="bank_name" value="<?= escape(input('bank_name')) ?>"></label>
                    <label>Account name<input name="bank_account_name" value="<?= escape(input('bank_account_name', 'Veelox Digital')) ?>"></label>
                    <label>Sort code<input name="bank_sort_code" value="<?= escape(input('bank_sort_code')) ?>"></label>
                    <label>Account number<input name="bank_account_number" value="<?= escape(input('bank_account_number')) ?>"></label>
                </div>

                <button type="submit">Install Veelox Digital</button>
            </form>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
