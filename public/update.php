<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Env;

define('BASE_PATH', dirname(__DIR__));

$composer = BASE_PATH . '/vendor/autoload.php';
if (is_file($composer)) {
    require $composer;
} else {
    spl_autoload_register(static function (string $class): void {
        if (!str_starts_with($class, 'App\\')) return;
        $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) require $file;
    });
}

Env::load(BASE_PATH . '/.env');
date_default_timezone_set(Env::get('APP_TIMEZONE', 'Europe/London'));

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('veelox_portal');
    session_set_cookie_params(['httponly' => true, 'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite' => 'Lax', 'path' => '/']);
    session_start();
}

if (!Auth::check()) {
    header('Location: /login');
    exit;
}
if ((Auth::user()['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Administrator access is required.');
}

$errors = [];
$applied = [];
$database = Database::connection();
$database->exec("CREATE TABLE IF NOT EXISTS migrations (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, migration VARCHAR(190) NOT NULL UNIQUE, applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$completed = $database->query('SELECT migration FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
$files = glob(BASE_PATH . '/database/migrations/*.sql') ?: [];
sort($files, SORT_NATURAL);
$pending = array_values(array_filter($files, static fn (string $file): bool => !in_array(basename($file), $completed, true)));

function importMigration(PDO $database, string $path): void
{
    $sql = file_get_contents($path);
    if ($sql === false) throw new RuntimeException('Migration file could not be read.');
    foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [] as $statement) {
        if (trim($statement) !== '') $database->exec(trim($statement));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['_token'] ?? null)) {
        $errors[] = 'Your session expired. Refresh and try again.';
    } else {
        foreach ($pending as $file) {
            try {
                importMigration($database, $file);
                $statement = $database->prepare('INSERT INTO migrations (migration) VALUES (:migration)');
                $statement->execute(['migration' => basename($file)]);
                $applied[] = basename($file);
            } catch (Throwable $exception) {
                $errors[] = basename($file) . ': ' . $exception->getMessage();
                break;
            }
        }
        $completed = $database->query('SELECT migration FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
        $pending = array_values(array_filter($files, static fn (string $file): bool => !in_array(basename($file), $completed, true)));
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Update Veelox Digital</title><style>:root{--ink:#18202b;--muted:#697386;--line:#e4e7ed;--violet:#7357eb}*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:radial-gradient(circle at 15% 10%,rgba(115,87,235,.16),transparent 32%),#f4f5f8;color:var(--ink);font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.card{width:min(100%,650px);padding:42px;background:#fff;border:1px solid var(--line);border-radius:24px;box-shadow:0 24px 70px rgba(26,30,42,.12)}.brand{display:flex;align-items:center;gap:11px;margin-bottom:32px}.mark{display:grid;place-items:center;width:40px;height:40px;border-radius:12px;background:var(--violet);color:#fff;font-weight:800}.brand small,.brand strong{display:block}.brand small{color:var(--muted);font-size:9px;letter-spacing:1.6px;text-transform:uppercase}h1{margin:0;font-size:36px;letter-spacing:-1.3px}p{color:var(--muted);line-height:1.65}.notice{margin:20px 0;padding:14px 16px;border-radius:10px;font-size:13px}.success{background:#e9f9f1;color:#267255}.error{background:#fff0f0;color:#a13e3e}.pending{margin:22px 0;padding:18px;border:1px solid var(--line);border-radius:12px}.pending strong,.pending small{display:block}.pending small{margin-top:5px;color:var(--muted)}button,.button{display:inline-flex;padding:13px 18px;border:0;border-radius:10px;background:var(--violet);color:#fff;font-weight:750;text-decoration:none;cursor:pointer}.button.secondary{margin-left:8px;background:#f0eef9;color:#5d49b4}@media(max-width:550px){.card{padding:28px 22px}h1{font-size:30px}.button.secondary{margin:10px 0 0}}</style></head><body><main class="card"><div class="brand"><span class="mark">V</span><span><strong>Veelox</strong><small>Digital updater</small></span></div><h1>System update</h1><p>This secure updater applies required database changes without SSH or phpMyAdmin.</p>
<?php if ($applied): ?><div class="notice success">Applied successfully: <?= htmlspecialchars(implode(', ', $applied)) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="notice error"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div><?php endif; ?>
<?php if ($pending): ?><div class="pending"><strong><?= count($pending) ?> update<?= count($pending) === 1 ? '' : 's' ?> ready</strong><small><?= htmlspecialchars(implode(', ', array_map('basename', $pending))) ?></small></div><form method="post"><input type="hidden" name="_token" value="<?= htmlspecialchars(Csrf::token()) ?>"><button type="submit">Run database update</button><a class="button secondary" href="/">Cancel</a></form><?php else: ?><div class="notice success">Your database is fully up to date.</div><a class="button" href="/settings">Open System Settings</a><a class="button secondary" href="/">Dashboard</a><?php endif; ?>
</main></body></html>
