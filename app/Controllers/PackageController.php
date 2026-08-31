<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\View;
use PDO;
use Throwable;

final class PackageController
{
    public function index(): void
    {
        $database = Database::connection();
        $search = trim((string) ($_GET['search'] ?? ''));
        $billing = trim((string) ($_GET['billing'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? ''));
        $where = [];
        $parameters = [];

        if ($search !== '') {
            $where[] = '(packages.name LIKE :search OR packages.description LIKE :search)';
            $parameters['search'] = '%' . $search . '%';
        }
        if (in_array($billing, ['one_off', 'monthly', 'yearly'], true)) {
            if ($billing === 'one_off') {
                $where[] = "packages.billing_type = 'one_off'";
            } else {
                $where[] = "packages.billing_type = 'recurring' AND packages.billing_interval = :billing";
                $parameters['billing'] = $billing;
            }
        }
        if (in_array($status, ['active', 'inactive', 'archived'], true)) {
            $where[] = 'packages.status = :status';
            $parameters['status'] = $status;
        } else {
            $where[] = "packages.status <> 'archived'";
        }

        $sql = 'SELECT packages.*, COUNT(orders.id) AS order_count,
                       COALESCE(SUM(CASE WHEN orders.status IN (\'paid\',\'active\',\'completed\') THEN orders.total_amount ELSE 0 END), 0) AS revenue_amount
                FROM packages LEFT JOIN orders ON orders.package_id = packages.id';
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' GROUP BY packages.id ORDER BY packages.created_at DESC';
        $statement = $database->prepare($sql);
        $statement->execute($parameters);

        View::render('packages/index', [
            'title' => 'Plans & Packages', 'packages' => $statement->fetchAll(),
            'search' => $search, 'billing' => $billing, 'status' => $status,
            'flash' => $this->pullFlash(),
        ]);
    }

    public function create(): void
    {
        View::render('packages/create', [
            'title' => 'Create package', 'package' => $_SESSION['_old_package'] ?? $this->emptyPackage(),
            'features' => $_SESSION['_old_features'] ?? [''], 'errors' => $_SESSION['_package_errors'] ?? [],
        ]);
        unset($_SESSION['_old_package'], $_SESSION['_old_features'], $_SESSION['_package_errors']);
    }

    public function store(): never
    {
        $data = $this->packageData();
        $features = $this->featureData();
        $errors = $this->validate($data);
        if ($errors) $this->formError('/packages/create', $data, $features, $errors);

        $database = Database::connection();
        try {
            $database->beginTransaction();
            $data['slug'] = $this->uniqueSlug($database, $data['name']);
            $statement = $database->prepare(
                'INSERT INTO packages (name, slug, description, billing_type, billing_interval, price_amount, setup_fee_amount, currency, is_public, status)
                 VALUES (:name, :slug, :description, :billing_type, :billing_interval, :price_amount, :setup_fee_amount, :currency, :is_public, :status)'
            );
            $statement->execute($this->persistenceData($data));
            $id = (int) $database->lastInsertId();
            $this->saveFeatures($database, $id, $features);
            $database->commit();
            $this->flash('success', $data['name'] . ' was created successfully.');
            $this->redirect('/packages/' . $id);
        } catch (Throwable $exception) {
            if ($database->inTransaction()) $database->rollBack();
            $this->formError('/packages/create', $data, $features, ['The package could not be saved. Please try again.']);
        }
    }

    public function show(string $id): void
    {
        $package = $this->findPackage((int) $id);
        $database = Database::connection();
        $features = $this->features($database, (int) $package['id']);
        $statement = $database->prepare(
            "SELECT COUNT(*) AS order_count, COUNT(DISTINCT customer_id) AS customer_count,
                    COALESCE(SUM(CASE WHEN status IN ('paid','active','completed') THEN total_amount ELSE 0 END), 0) AS revenue_amount,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_count
             FROM orders WHERE package_id = :id"
        );
        $statement->execute(['id' => $package['id']]);
        $metrics = $statement->fetch() ?: [];

        View::render('packages/show', [
            'title' => $package['name'], 'package' => $package, 'features' => $features,
            'metrics' => $metrics, 'flash' => $this->pullFlash(),
        ]);
    }

    public function edit(string $id): void
    {
        $package = $this->findPackage((int) $id);
        View::render('packages/edit', [
            'title' => 'Edit package', 'package' => $_SESSION['_old_package'] ?? $package,
            'features' => $_SESSION['_old_features'] ?? $this->features(Database::connection(), (int) $package['id']),
            'errors' => $_SESSION['_package_errors'] ?? [],
        ]);
        unset($_SESSION['_old_package'], $_SESSION['_old_features'], $_SESSION['_package_errors']);
    }

    public function update(string $id): never
    {
        $package = $this->findPackage((int) $id);
        $data = $this->packageData();
        $features = $this->featureData();
        $errors = $this->validate($data);
        if ($errors) {
            $data['id'] = $package['id'];
            $this->formError('/packages/' . $package['id'] . '/edit', $data, $features, $errors);
        }

        $database = Database::connection();
        try {
            $database->beginTransaction();
            $data['id'] = $package['id'];
            $database->prepare(
                'UPDATE packages SET name = :name, description = :description, billing_type = :billing_type,
                 billing_interval = :billing_interval, price_amount = :price_amount, setup_fee_amount = :setup_fee_amount,
                 currency = :currency, is_public = :is_public, status = :status WHERE id = :id'
            )->execute($this->persistenceData($data));
            $database->prepare('DELETE FROM package_features WHERE package_id = :id')->execute(['id' => $package['id']]);
            $this->saveFeatures($database, (int) $package['id'], $features);
            $database->commit();
            $this->flash('success', 'Package details were updated.');
            $this->redirect('/packages/' . $package['id']);
        } catch (Throwable) {
            if ($database->inTransaction()) $database->rollBack();
            $data['id'] = $package['id'];
            $this->formError('/packages/' . $package['id'] . '/edit', $data, $features, ['The package could not be updated.']);
        }
    }

    public function duplicate(string $id): never
    {
        $package = $this->findPackage((int) $id);
        $database = Database::connection();
        try {
            $database->beginTransaction();
            $name = $package['name'] . ' Copy';
            $statement = $database->prepare(
                "INSERT INTO packages (name, slug, description, billing_type, billing_interval, price_amount, setup_fee_amount, currency, is_public, status)
                 VALUES (:name, :slug, :description, :billing_type, :billing_interval, :price_amount, :setup_fee_amount, :currency, :is_public, 'inactive')"
            );
            $statement->execute([
                'name' => $name, 'slug' => $this->uniqueSlug($database, $name), 'description' => $package['description'],
                'billing_type' => $package['billing_type'], 'billing_interval' => $package['billing_interval'],
                'price_amount' => $package['price_amount'], 'setup_fee_amount' => $package['setup_fee_amount'],
                'currency' => $package['currency'], 'is_public' => $package['is_public'],
            ]);
            $newId = (int) $database->lastInsertId();
            $this->saveFeatures($database, $newId, $this->features($database, (int) $package['id']));
            $database->commit();
            $this->flash('success', 'Package duplicated as an inactive copy.');
            $this->redirect('/packages/' . $newId . '/edit');
        } catch (Throwable) {
            if ($database->inTransaction()) $database->rollBack();
            $this->flash('error', 'The package could not be duplicated.');
            $this->redirect('/packages/' . $package['id']);
        }
    }

    public function archive(string $id): never
    {
        $package = $this->findPackage((int) $id);
        Database::connection()->prepare("UPDATE packages SET status = 'archived' WHERE id = :id")->execute(['id' => $package['id']]);
        $this->flash('success', 'Package was archived. Existing customer orders were not changed.');
        $this->redirect('/packages');
    }

    private function packageData(): array
    {
        $billingType = ($_POST['billing_type'] ?? '') === 'recurring' ? 'recurring' : 'one_off';
        $interval = $billingType === 'recurring' && in_array($_POST['billing_interval'] ?? '', ['monthly', 'yearly'], true) ? $_POST['billing_interval'] : 'none';
        return [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
            'billing_type' => $billingType, 'billing_interval' => $interval,
            'price_amount' => $this->moneyToPence((string) ($_POST['price'] ?? '')),
            'setup_fee_amount' => $this->moneyToPence((string) ($_POST['setup_fee'] ?? '0')),
            'currency' => 'GBP', 'is_public' => isset($_POST['is_public']) ? 1 : 0,
            'status' => in_array($_POST['status'] ?? '', ['active', 'inactive', 'archived'], true) ? $_POST['status'] : 'active',
            'price' => trim((string) ($_POST['price'] ?? '')),
            'setup_fee' => trim((string) ($_POST['setup_fee'] ?? '0')),
        ];
    }

    private function validate(array $data): array
    {
        $errors = [];
        if ($data['name'] === '') $errors[] = 'A package name is required.';
        if ($data['price_amount'] === null) $errors[] = 'Enter a valid price with no more than two decimal places.';
        if ($data['setup_fee_amount'] === null) $errors[] = 'Enter a valid setup fee.';
        return $errors;
    }

    private function moneyToPence(string $value): ?int
    {
        $value = str_replace([',', '£', ' '], '', trim($value));
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) return null;
        [$pounds, $pence] = array_pad(explode('.', $value, 2), 2, '');
        return ((int) $pounds * 100) + (int) str_pad($pence, 2, '0');
    }

    private function persistenceData(array $data): array
    {
        unset($data['price'], $data['setup_fee']);
        return $data;
    }

    private function featureData(): array
    {
        $features = is_array($_POST['features'] ?? null) ? $_POST['features'] : [];
        return array_values(array_filter(array_map(static fn ($feature): string => substr(trim((string) $feature), 0, 255), $features)));
    }

    private function saveFeatures(PDO $database, int $packageId, array $features): void
    {
        $statement = $database->prepare('INSERT INTO package_features (package_id, feature, sort_order) VALUES (:package_id, :feature, :sort_order)');
        foreach ($features as $index => $feature) $statement->execute(['package_id' => $packageId, 'feature' => $feature, 'sort_order' => $index]);
    }

    private function features(PDO $database, int $packageId): array
    {
        $statement = $database->prepare('SELECT feature FROM package_features WHERE package_id = :id ORDER BY sort_order, id');
        $statement->execute(['id' => $packageId]);
        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }

    private function uniqueSlug(PDO $database, string $name): string
    {
        $base = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $name), '-')) ?: 'package';
        $slug = $base;
        $suffix = 2;
        $statement = $database->prepare('SELECT COUNT(*) FROM packages WHERE slug = :slug');
        while (true) {
            $statement->execute(['slug' => $slug]);
            if ((int) $statement->fetchColumn() === 0) return $slug;
            $slug = $base . '-' . $suffix++;
        }
    }

    private function findPackage(int $id): array
    {
        $statement = Database::connection()->prepare('SELECT * FROM packages WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $package = $statement->fetch();
        if (!$package) { http_response_code(404); exit('Package not found.'); }
        $package['price'] = number_format($package['price_amount'] / 100, 2, '.', '');
        $package['setup_fee'] = number_format($package['setup_fee_amount'] / 100, 2, '.', '');
        return $package;
    }

    private function emptyPackage(): array
    {
        return ['name' => '', 'description' => '', 'billing_type' => 'one_off', 'billing_interval' => 'none', 'price' => '', 'setup_fee' => '0.00', 'is_public' => 1, 'status' => 'active'];
    }

    private function formError(string $location, array $data, array $features, array $errors): never
    {
        $_SESSION['_old_package'] = $data; $_SESSION['_old_features'] = $features; $_SESSION['_package_errors'] = $errors; $this->redirect($location);
    }
    private function flash(string $type, string $message): void { $_SESSION['_flash'] = compact('type', 'message'); }
    private function pullFlash(): ?array { $flash = $_SESSION['_flash'] ?? null; unset($_SESSION['_flash']); return $flash; }
    private function redirect(string $location): never { header('Location: ' . $location); exit; }
}
