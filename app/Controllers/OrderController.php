<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Services\InvoiceService;
use App\Services\MailService;
use PDO;
use Throwable;

final class OrderController
{
    private const STATUSES = ['draft', 'pending', 'awaiting_payment', 'paid', 'active', 'completed', 'cancelled', 'refunded'];

    public function index(): void
    {
        $database = Database::connection();
        $search = trim((string) ($_GET['search'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? ''));
        $billing = trim((string) ($_GET['billing'] ?? ''));
        $where = [];
        $parameters = [];

        if ($search !== '') {
            $where[] = '(orders.order_number LIKE :search OR orders.description LIKE :search OR customers.company_name LIKE :search OR customers.contact_name LIKE :search)';
            $parameters['search'] = '%' . $search . '%';
        }
        if (in_array($status, self::STATUSES, true)) {
            $where[] = 'orders.status = :status';
            $parameters['status'] = $status;
        }
        if (in_array($billing, ['one_off', 'monthly', 'yearly'], true)) {
            $where[] = $billing === 'one_off' ? "orders.billing_type = 'one_off'" : "orders.billing_type = 'recurring' AND orders.billing_interval = :billing";
            if ($billing !== 'one_off') $parameters['billing'] = $billing;
        }

        $sql = 'SELECT orders.*, customers.account_number, customers.company_name, customers.contact_name,
                       packages.name AS package_name, users.name AS assignee_name
                FROM orders
                INNER JOIN customers ON customers.id = orders.customer_id
                LEFT JOIN packages ON packages.id = orders.package_id
                LEFT JOIN users ON users.id = orders.assigned_to';
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY orders.created_at DESC';
        $statement = $database->prepare($sql);
        $statement->execute($parameters);

        View::render('orders/index', [
            'title' => 'Customer Orders', 'orders' => $statement->fetchAll(), 'search' => $search,
            'status' => $status, 'billing' => $billing, 'flash' => $this->pullFlash(),
        ]);
    }

    public function create(): void
    {
        $hasOldInput = isset($_SESSION['_old_order']);
        $data = $_SESSION['_old_order'] ?? $this->emptyOrder();
        if (isset($_GET['customer'])) $data['customer_id'] = (int) $_GET['customer'];
        if (isset($_GET['package'])) {
            $data['package_id'] = (int) $_GET['package'];
            if (!$hasOldInput) {
                foreach ($this->packages() as $package) {
                    if ((int) $package['id'] !== $data['package_id']) continue;
                    $data['description'] = $package['name'];
                    $data['price'] = number_format($package['price_amount'] / 100, 2, '.', '');
                    $data['setup_fee'] = number_format($package['setup_fee_amount'] / 100, 2, '.', '');
                    $data['billing_type'] = $package['billing_type'];
                    $data['billing_interval'] = $package['billing_interval'];
                    break;
                }
            }
        }
        View::render('orders/create', [
            'title' => 'Create order', 'order' => $data, 'errors' => $_SESSION['_order_errors'] ?? [],
            'customers' => $this->customers(), 'packages' => $this->packages(), 'staff' => $this->staff(),
        ]);
        unset($_SESSION['_old_order'], $_SESSION['_order_errors']);
    }

    public function store(): never
    {
        $data = $this->orderData();
        $errors = $this->validate($data);
        if ($errors) $this->formError('/orders/create', $data, $errors);

        $database = Database::connection();
        try {
            $database->beginTransaction();
            $data['order_number'] = $this->nextOrderNumber($database);
            $statement = $database->prepare(
                'INSERT INTO orders (order_number, customer_id, package_id, assigned_to, status, billing_type, billing_interval,
                 description, subtotal_amount, setup_fee_amount, discount_amount, tax_amount, total_amount, currency, starts_at, renews_at, internal_notes)
                 VALUES (:order_number, :customer_id, :package_id, :assigned_to, :status, :billing_type, :billing_interval,
                 :description, :subtotal_amount, :setup_fee_amount, 0, 0, :total_amount, :currency, :starts_at, :renews_at, :internal_notes)'
            );
            $statement->execute($this->persistenceData($data));
            $id = (int) $database->lastInsertId();
            $database->commit();
            (new MailService())->order($id);
            if ($data['status'] === 'awaiting_payment') {
                try {
                    $invoiceId = (new InvoiceService())->createFromOrder($id, 'sent');
                    $this->flash('success', $data['order_number'] . ' and its outstanding invoice were created.');
                    $this->redirect('/invoices/' . $invoiceId);
                } catch (Throwable) {
                    $this->flash('error', $data['order_number'] . ' was created, but its invoice could not be created.');
                    $this->redirect('/orders/' . $id);
                }
            }
            $this->flash('success', $data['order_number'] . ' was created successfully.');
            $this->redirect('/orders/' . $id);
        } catch (Throwable) {
            if ($database->inTransaction()) $database->rollBack();
            $this->formError('/orders/create', $data, ['The order could not be created. Please try again.']);
        }
    }

    public function show(string $id): void
    {
        View::render('orders/show', ['title' => 'Order details', 'order' => $this->findOrder((int) $id), 'flash' => $this->pullFlash()]);
    }

    public function edit(string $id): void
    {
        $order = $this->findOrder((int) $id);
        $order['price'] = number_format($order['subtotal_amount'] / 100, 2, '.', '');
        $order['setup_fee'] = number_format($order['setup_fee_amount'] / 100, 2, '.', '');
        View::render('orders/edit', [
            'title' => 'Edit order', 'order' => $_SESSION['_old_order'] ?? $order,
            'errors' => $_SESSION['_order_errors'] ?? [], 'customers' => $this->customers(),
            'packages' => $this->packages(true), 'staff' => $this->staff(),
        ]);
        unset($_SESSION['_old_order'], $_SESSION['_order_errors']);
    }

    public function update(string $id): never
    {
        $order = $this->findOrder((int) $id);
        $data = $this->orderData();
        $errors = $this->validate($data);
        if ($errors) { $data['id'] = $order['id']; $this->formError('/orders/' . $order['id'] . '/edit', $data, $errors); }
        $data['id'] = $order['id'];
        Database::connection()->prepare(
            'UPDATE orders SET customer_id=:customer_id, package_id=:package_id, assigned_to=:assigned_to, status=:status,
             billing_type=:billing_type, billing_interval=:billing_interval, description=:description,
             subtotal_amount=:subtotal_amount, setup_fee_amount=:setup_fee_amount, total_amount=:total_amount,
             currency=:currency, starts_at=:starts_at, renews_at=:renews_at, internal_notes=:internal_notes WHERE id=:id'
        )->execute($this->persistenceData($data));
        if ($data['status'] !== $order['status']) (new MailService())->order((int) $order['id'], 'order_status');
        if ($data['status'] === 'awaiting_payment') {
            try {
                $invoiceId = (new InvoiceService())->createFromOrder((int) $order['id'], 'sent');
                $this->flash('success', 'Order updated and its invoice is now outstanding.');
                $this->redirect('/invoices/' . $invoiceId);
            } catch (Throwable) {
                $this->flash('error', 'Order updated, but its invoice could not be created.');
                $this->redirect('/orders/' . $order['id']);
            }
        }
        $this->flash('success', 'Order details were updated.');
        $this->redirect('/orders/' . $order['id']);
    }

    public function status(string $id): never
    {
        $order = $this->findOrder((int) $id);
        $status = (string) ($_POST['status'] ?? '');
        if (!in_array($status, self::STATUSES, true)) {
            $this->flash('error', 'That order status is not valid.');
        } else {
            $completedAt = $status === 'completed' ? date('Y-m-d H:i:s') : null;
            Database::connection()->prepare('UPDATE orders SET status=:status, completed_at=:completed_at WHERE id=:id')
                ->execute(['status' => $status, 'completed_at' => $completedAt, 'id' => $order['id']]);
            (new MailService())->order((int) $order['id'], 'order_status');
            if ($status === 'awaiting_payment') {
                try {
                    $invoiceId = (new InvoiceService())->createFromOrder((int) $order['id'], 'sent');
                    $this->flash('success', 'Order is awaiting payment and its invoice is now outstanding.');
                    $this->redirect('/invoices/' . $invoiceId);
                } catch (Throwable) {
                    $this->flash('error', 'Order status changed, but its invoice could not be created.');
                    $this->redirect('/orders/' . $order['id']);
                }
            }
            $this->flash('success', 'Order status changed to ' . str_replace('_', ' ', $status) . '.');
        }
        $this->redirect('/orders/' . $order['id']);
    }

    private function orderData(): array
    {
        $billingType = ($_POST['billing_type'] ?? '') === 'recurring' ? 'recurring' : 'one_off';
        $billingInterval = $billingType === 'recurring' && in_array($_POST['billing_interval'] ?? '', ['monthly', 'yearly'], true) ? $_POST['billing_interval'] : 'none';
        $price = $this->moneyToPence((string) ($_POST['price'] ?? ''));
        $setupFee = $this->moneyToPence((string) ($_POST['setup_fee'] ?? '0'));
        return [
            'customer_id' => (int) ($_POST['customer_id'] ?? 0),
            'package_id' => (int) ($_POST['package_id'] ?? 0) ?: null,
            'assigned_to' => (int) ($_POST['assigned_to'] ?? 0) ?: null,
            'status' => in_array($_POST['status'] ?? '', self::STATUSES, true) ? $_POST['status'] : 'draft',
            'billing_type' => $billingType, 'billing_interval' => $billingInterval,
            'description' => trim((string) ($_POST['description'] ?? '')),
            'subtotal_amount' => $price, 'setup_fee_amount' => $setupFee,
            'total_amount' => $price !== null && $setupFee !== null ? $price + $setupFee : null,
            'currency' => 'GBP', 'starts_at' => $this->dateOrNull($_POST['starts_at'] ?? null),
            'renews_at' => $billingType === 'recurring' ? $this->dateOrNull($_POST['renews_at'] ?? null) : null,
            'internal_notes' => trim((string) ($_POST['internal_notes'] ?? '')) ?: null,
            'price' => trim((string) ($_POST['price'] ?? '')), 'setup_fee' => trim((string) ($_POST['setup_fee'] ?? '0')),
        ];
    }

    private function validate(array $data): array
    {
        $errors = [];
        if (!$data['customer_id'] || !$this->exists('customers', $data['customer_id'])) $errors[] = 'Select a valid customer.';
        if ($data['package_id'] && !$this->exists('packages', $data['package_id'])) $errors[] = 'Select a valid package.';
        if ($data['assigned_to'] && !$this->isStaff($data['assigned_to'])) $errors[] = 'Select a valid staff member.';
        if ($data['description'] === '') $errors[] = 'An order description is required.';
        if ($data['subtotal_amount'] === null) $errors[] = 'Enter a valid price with no more than two decimal places.';
        if ($data['setup_fee_amount'] === null) $errors[] = 'Enter a valid setup fee.';
        if ($data['billing_type'] === 'recurring' && !$data['renews_at']) $errors[] = 'A renewal date is required for recurring orders.';
        return $errors;
    }

    private function persistenceData(array $data): array
    {
        unset($data['price'], $data['setup_fee']);
        return $data;
    }

    private function findOrder(int $id): array
    {
        $statement = Database::connection()->prepare(
            'SELECT orders.*, customers.account_number, customers.company_name, customers.contact_name, customers.email AS customer_email,
                    packages.name AS package_name, users.name AS assignee_name,
                    (SELECT invoices.id FROM invoices WHERE invoices.order_id=orders.id AND invoices.status<>\'void\' ORDER BY invoices.id DESC LIMIT 1) AS invoice_id
             FROM orders INNER JOIN customers ON customers.id=orders.customer_id
             LEFT JOIN packages ON packages.id=orders.package_id LEFT JOIN users ON users.id=orders.assigned_to
             WHERE orders.id=:id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $order = $statement->fetch();
        if (!$order) { http_response_code(404); exit('Order not found.'); }
        return $order;
    }

    private function customers(): array { return Database::connection()->query("SELECT id, account_number, company_name, contact_name FROM customers WHERE status IN ('lead','active') ORDER BY COALESCE(company_name,contact_name)")->fetchAll(); }
    private function packages(bool $includeArchived = false): array { return Database::connection()->query("SELECT id,name,billing_type,billing_interval,price_amount,setup_fee_amount,status FROM packages WHERE status " . ($includeArchived ? "IN ('active','inactive','archived')" : "= 'active'") . ' ORDER BY name')->fetchAll(); }
    private function staff(): array { return Database::connection()->query("SELECT users.id,users.name FROM users INNER JOIN roles ON roles.id=users.role_id WHERE roles.slug IN ('admin','staff') AND users.status='active' ORDER BY users.name")->fetchAll(); }
    private function exists(string $table, int $id): bool { $statement=Database::connection()->prepare("SELECT COUNT(*) FROM {$table} WHERE id=:id"); $statement->execute(['id'=>$id]); return (bool)$statement->fetchColumn(); }
    private function isStaff(int $id): bool { $statement=Database::connection()->prepare("SELECT COUNT(*) FROM users INNER JOIN roles ON roles.id=users.role_id WHERE users.id=:id AND users.status='active' AND roles.slug IN ('admin','staff')"); $statement->execute(['id'=>$id]); return (bool)$statement->fetchColumn(); }

    private function nextOrderNumber(PDO $database): string
    {
        $prefix = 'ORD-' . date('Y') . '-';
        $statement = $database->prepare('SELECT order_number FROM orders WHERE order_number LIKE :prefix ORDER BY id DESC LIMIT 1 FOR UPDATE');
        $statement->execute(['prefix' => $prefix . '%']);
        $last = (string) ($statement->fetchColumn() ?: '');
        $next = $last ? ((int) substr($last, -4)) + 1 : 1;
        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function moneyToPence(string $value): ?int { $value=str_replace([',','£',' '],'',trim($value)); if(!preg_match('/^\d+(?:\.\d{1,2})?$/',$value))return null; [$pounds,$pence]=array_pad(explode('.',$value,2),2,''); return ((int)$pounds*100)+(int)str_pad($pence,2,'0'); }
    private function dateOrNull(mixed $value): ?string { $value=trim((string)$value); if($value==='')return null; $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$value); return $date&&$date->format('Y-m-d')===$value?$value:null; }
    private function emptyOrder(): array { return ['customer_id'=>'','package_id'=>'','assigned_to'=>Auth::user()['id']??'','status'=>'draft','billing_type'=>'one_off','billing_interval'=>'none','description'=>'','price'=>'','setup_fee'=>'0.00','starts_at'=>date('Y-m-d'),'renews_at'=>'','internal_notes'=>'']; }
    private function formError(string $location,array $data,array $errors): never { $_SESSION['_old_order']=$data; $_SESSION['_order_errors']=$errors; $this->redirect($location); }
    private function flash(string $type,string $message): void { $_SESSION['_flash']=compact('type','message'); }
    private function pullFlash(): ?array { $flash=$_SESSION['_flash']??null; unset($_SESSION['_flash']); return $flash; }
    private function redirect(string $location): never { header('Location: '.$location); exit; }
}
