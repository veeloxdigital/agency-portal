<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use PDO;

final class DashboardController
{
    public function index(): void
    {
        $database = Database::connection();
        $user = Auth::user();

        if (($user['role'] ?? '') === 'customer') {
            $statement = $database->prepare('SELECT id FROM customers WHERE user_id = :user_id LIMIT 1');
            $statement->execute(['user_id' => $user['id']]);
            $customerId = (int) ($statement->fetchColumn() ?: 0);
            $metrics = $this->customerMetrics($database, $customerId);
            $recentOrders = $this->customerOrders($database, $customerId);
        } else {
            $metrics = $this->staffMetrics($database);
            $recentOrders = [];
        }

        View::render('dashboard/index', [
            'title' => 'Dashboard',
            'user' => $user,
            'metrics' => $metrics,
            'recentOrders' => $recentOrders,
        ]);
    }

    private function staffMetrics(PDO $database): array
    {
        return [
            'customers' => (int) $database->query("SELECT COUNT(*) FROM customers WHERE status = 'active'")->fetchColumn(),
            'orders' => (int) $database->query("SELECT COUNT(*) FROM orders WHERE status IN ('pending', 'awaiting_payment', 'paid', 'active')")->fetchColumn(),
            'outstanding' => (int) $database->query("SELECT COALESCE(SUM(balance_due), 0) FROM invoices WHERE status IN ('sent', 'overdue', 'partially_paid')")->fetchColumn(),
            'tickets' => (int) $database->query("SELECT COUNT(*) FROM tickets WHERE status IN ('open', 'customer_reply', 'staff_reply')")->fetchColumn(),
        ];
    }

    private function customerMetrics(PDO $database, int $customerId): array
    {
        $queries = [
            'orders' => "SELECT COUNT(*) FROM orders WHERE customer_id = :id AND status IN ('pending', 'awaiting_payment', 'paid', 'active')",
            'outstanding' => "SELECT COALESCE(SUM(balance_due), 0) FROM invoices WHERE customer_id = :id AND status IN ('sent', 'overdue', 'partially_paid')",
            'tickets' => "SELECT COUNT(*) FROM tickets WHERE customer_id = :id AND status IN ('open', 'customer_reply', 'staff_reply')",
        ];
        $metrics = [];
        foreach ($queries as $key => $sql) {
            $statement = $database->prepare($sql);
            $statement->execute(['id' => $customerId]);
            $metrics[$key] = (int) $statement->fetchColumn();
        }
        return $metrics;
    }

    private function customerOrders(PDO $database, int $customerId): array
    {
        $statement = $database->prepare(
            'SELECT order_number, description, status, billing_type, billing_interval, total_amount, renews_at
             FROM orders WHERE customer_id = :id ORDER BY created_at DESC LIMIT 5'
        );
        $statement->execute(['id' => $customerId]);
        return $statement->fetchAll();
    }
}
