<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\View;
use PDO;
use Throwable;
use App\Services\MailService;
use App\Services\ActivityService;

final class CustomerController
{
    public function index(): void
    {
        $database = Database::connection();
        $search = trim((string) ($_GET['search'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 15;
        $where = [];
        $parameters = [];

        if ($search !== '') {
            $where[] = '(customers.account_number LIKE :search OR customers.company_name LIKE :search OR customers.contact_name LIKE :search OR customers.email LIKE :search)';
            $parameters['search'] = '%' . $search . '%';
        }
        if (in_array($status, ['lead', 'active', 'inactive', 'archived'], true)) {
            $where[] = 'customers.status = :status';
            $parameters['status'] = $status;
        } else {
            $where[] = "customers.status <> 'archived'";
        }

        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $count = $database->prepare('SELECT COUNT(*) FROM customers' . $whereSql);
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);

        $statement = $database->prepare(
            'SELECT customers.*, users.status AS portal_status
             FROM customers LEFT JOIN users ON users.id = customers.user_id' . $whereSql . '
             ORDER BY customers.created_at DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($parameters as $key => $value) {
            $statement->bindValue(':' . $key, $value);
        }
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();

        View::render('customers/index', [
            'title' => 'Customers', 'customers' => $statement->fetchAll(), 'search' => $search,
            'status' => $status, 'page' => $page, 'pages' => $pages, 'total' => $total,
            'flash' => $this->pullFlash(),
        ]);
    }

    public function create(): void
    {
        View::render('customers/create', [
            'title' => 'Add customer',
            'customer' => $_SESSION['_old_customer'] ?? $this->emptyCustomer(),
            'errors' => $_SESSION['_customer_errors'] ?? [],
        ]);
        unset($_SESSION['_old_customer'], $_SESSION['_customer_errors']);
    }

    public function store(): never
    {
        $data = $this->customerData();
        $errors = $this->validate($data);
        if ($errors !== []) {
            $_SESSION['_old_customer'] = $data;
            $_SESSION['_customer_errors'] = $errors;
            $this->redirect('/customers/create');
        }

        $database = Database::connection();
        $data['account_number'] = $this->nextAccountNumber($database);
        $statement = $database->prepare(
            'INSERT INTO customers
             (account_number, type, company_name, contact_name, email, phone, address_line_1, address_line_2, town_city, county, postcode, country_code, status, internal_notes, email_notifications)
             VALUES (:account_number, :type, :company_name, :contact_name, :email, :phone, :address_line_1, :address_line_2, :town_city, :county, :postcode, :country_code, :status, :internal_notes, :email_notifications)'
        );
        $statement->execute($data);
        $id = (int) $database->lastInsertId();
        ActivityService::log('customer.created','customer',$id,$data['contact_name'].' created');
        $this->flash('success', $data['contact_name'] . ' was added successfully.');
        $this->redirect('/customers/' . $id);
    }

    public function show(string $id): void
    {
        $customer = $this->findCustomer((int) $id);
        $database = Database::connection();
        $metrics = [];
        foreach ([
            'orders' => 'SELECT COUNT(*) FROM orders WHERE customer_id = :id',
            'invoices' => 'SELECT COUNT(*) FROM invoices WHERE customer_id = :id',
            'outstanding' => "SELECT COALESCE(SUM(balance_due), 0) FROM invoices WHERE customer_id = :id AND status IN ('sent','overdue','partially_paid')",
            'tickets' => "SELECT COUNT(*) FROM tickets WHERE customer_id = :id AND status IN ('open','customer_reply','staff_reply')",
        ] as $key => $sql) {
            $statement = $database->prepare($sql);
            $statement->execute(['id' => $customer['id']]);
            $metrics[$key] = (int) $statement->fetchColumn();
        }
        View::render('customers/show', [
            'title' => $customer['company_name'] ?: $customer['contact_name'], 'customer' => $customer,
            'metrics' => $metrics, 'flash' => $this->pullFlash(),
            'temporaryPassword' => $_SESSION['_temporary_password'] ?? null,
        ]);
        unset($_SESSION['_temporary_password']);
    }

    public function edit(string $id): void
    {
        $customer = $this->findCustomer((int) $id);
        View::render('customers/edit', [
            'title' => 'Edit customer', 'customer' => $_SESSION['_old_customer'] ?? $customer,
            'errors' => $_SESSION['_customer_errors'] ?? [],
        ]);
        unset($_SESSION['_old_customer'], $_SESSION['_customer_errors']);
    }

    public function update(string $id): never
    {
        $customer = $this->findCustomer((int) $id);
        $data = $this->customerData();
        $errors = $this->validate($data);
        if ($errors !== []) {
            $data['id'] = $customer['id'];
            $_SESSION['_old_customer'] = $data;
            $_SESSION['_customer_errors'] = $errors;
            $this->redirect('/customers/' . $customer['id'] . '/edit');
        }

        $data['id'] = $customer['id'];
        Database::connection()->prepare(
            'UPDATE customers SET type = :type, company_name = :company_name, contact_name = :contact_name,
             email = :email, phone = :phone, address_line_1 = :address_line_1, address_line_2 = :address_line_2,
             town_city = :town_city, county = :county, postcode = :postcode, country_code = :country_code,
             status = :status, internal_notes = :internal_notes, email_notifications = :email_notifications WHERE id = :id'
        )->execute($data);
        if ($customer['user_id']) {
            $portalStatus = in_array($data['status'], ['inactive', 'archived'], true) ? 'suspended' : 'active';
            Database::connection()->prepare('UPDATE users SET name = :name, email = :email, status = :status WHERE id = :id')
                ->execute(['name' => $data['contact_name'], 'email' => $data['email'], 'status' => $portalStatus, 'id' => $customer['user_id']]);
        }
        ActivityService::log('customer.updated','customer',(int)$id,$data['contact_name'].' updated');
        $this->flash('success', 'Customer details were updated.');
        $this->redirect('/customers/' . $customer['id']);
    }

    public function archive(string $id): never
    {
        $customer = $this->findCustomer((int) $id);
        Database::connection()->prepare("UPDATE customers SET status = 'archived' WHERE id = :id")->execute(['id' => $customer['id']]);
        if ($customer['user_id']) {
            Database::connection()->prepare("UPDATE users SET status = 'suspended' WHERE id = :id")->execute(['id' => $customer['user_id']]);
        }
        ActivityService::log('customer.archived','customer',(int)$id,'Customer account archived');
        $this->flash('success', 'Customer account was archived.');
        $this->redirect('/customers');
    }

    public function createPortalAccess(string $id): never
    {
        $customer = $this->findCustomer((int) $id);
        if ($customer['user_id']) {
            $this->flash('error', 'This customer already has portal access.');
            $this->redirect('/customers/' . $customer['id']);
        }

        $database = Database::connection();
        $temporaryPassword = rtrim(strtr(base64_encode(random_bytes(14)), '+/', 'XZ'), '=') . '!7';
        try {
            $database->beginTransaction();
            $role = $database->query("SELECT id FROM roles WHERE slug = 'customer' LIMIT 1")->fetchColumn();
            $database->prepare("INSERT INTO users (role_id, name, email, password_hash, status) VALUES (:role_id, :name, :email, :password, 'active')")
                ->execute(['role_id' => $role, 'name' => $customer['contact_name'], 'email' => strtolower($customer['email']), 'password' => password_hash($temporaryPassword, PASSWORD_DEFAULT)]);
            $userId = (int) $database->lastInsertId();
            $database->prepare('UPDATE customers SET user_id = :user_id WHERE id = :id')->execute(['user_id' => $userId, 'id' => $customer['id']]);
            $database->commit();
            $_SESSION['_temporary_password'] = $temporaryPassword;
            (new MailService())->sendTemplate('portal_welcome',['id'=>$customer['id'],'contact_name'=>$customer['contact_name'],'email'=>$customer['email'],'email_notifications'=>1],['temporary_password'=>$temporaryPassword]);
            $this->flash('success', 'Customer portal access was created. Copy the temporary password now.');
        } catch (Throwable) {
            if ($database->inTransaction()) $database->rollBack();
            $this->flash('error', 'Portal access could not be created. The email address may already be in use.');
        }
        $this->redirect('/customers/' . $customer['id']);
    }

    private function findCustomer(int $id): array
    {
        $statement = Database::connection()->prepare('SELECT customers.*, users.status AS portal_status, users.last_login_at FROM customers LEFT JOIN users ON users.id = customers.user_id WHERE customers.id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $customer = $statement->fetch();
        if (!$customer) { http_response_code(404); exit('Customer not found.'); }
        return $customer;
    }

    private function customerData(): array
    {
        $fields = ['company_name', 'contact_name', 'email', 'phone', 'address_line_1', 'address_line_2', 'town_city', 'county', 'postcode', 'internal_notes'];
        $data = [];
        foreach ($fields as $field) {
            $value = trim((string) ($_POST[$field] ?? ''));
            $data[$field] = $value === '' ? null : $value;
        }
        $data['type'] = in_array($_POST['type'] ?? '', ['individual', 'business'], true) ? $_POST['type'] : 'business';
        $data['status'] = in_array($_POST['status'] ?? '', ['lead', 'active', 'inactive', 'archived'], true) ? $_POST['status'] : 'active';
        $data['country_code'] = strtoupper(substr(trim((string) ($_POST['country_code'] ?? 'GB')), 0, 2)) ?: 'GB';
        $data['email'] = strtolower((string) $data['email']);
        $data['email_notifications'] = isset($_POST['email_notifications']) ? 1 : 0;
        return $data;
    }

    private function validate(array $data): array
    {
        $errors = [];
        if (empty($data['contact_name'])) $errors[] = 'A contact name is required.';
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
        if ($data['type'] === 'business' && empty($data['company_name'])) $errors[] = 'A company name is required for business customers.';
        return $errors;
    }

    private function nextAccountNumber(PDO $database): string
    {
        $next = (int) $database->query('SELECT COALESCE(MAX(id), 0) + 1 FROM customers')->fetchColumn();
        return 'VXL-' . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    private function emptyCustomer(): array
    {
        return ['type' => 'business', 'company_name' => '', 'contact_name' => '', 'email' => '', 'phone' => '', 'address_line_1' => '', 'address_line_2' => '', 'town_city' => '', 'county' => 'East Sussex', 'postcode' => '', 'country_code' => 'GB', 'status' => 'active', 'internal_notes' => '', 'email_notifications' => 1];
    }

    private function flash(string $type, string $message): void { $_SESSION['_flash'] = compact('type', 'message'); }
    private function pullFlash(): ?array { $flash = $_SESSION['_flash'] ?? null; unset($_SESSION['_flash']); return $flash; }
    private function redirect(string $location): never { header('Location: ' . $location); exit; }
}
