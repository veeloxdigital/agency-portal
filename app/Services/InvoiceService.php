<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

final class InvoiceService
{
    public function createFromOrder(int $orderId, string $status = 'sent'): int
    {
        $database = Database::connection();
        $existing = $database->prepare("SELECT id FROM invoices WHERE order_id=:id AND status <> 'void' ORDER BY id DESC LIMIT 1");
        $existing->execute(['id' => $orderId]);
        if ($id = $existing->fetchColumn()) return (int) $id;

        $statement = $database->prepare('SELECT * FROM orders WHERE id=:id LIMIT 1');
        $statement->execute(['id' => $orderId]);
        $order = $statement->fetch();
        if (!$order) throw new \RuntimeException('Order not found.');

        $ownsTransaction = !$database->inTransaction();
        if ($ownsTransaction) $database->beginTransaction();
        try {
            $issue = date('Y-m-d');
            $due = date('Y-m-d', strtotime('+' . $this->dueDays($database) . ' days'));
            $invoiceNumber = $this->nextNumber($database);
            $database->prepare(
                'INSERT INTO invoices (invoice_number,customer_id,order_id,status,issue_date,due_date,subtotal_amount,discount_amount,tax_amount,total_amount,amount_paid,balance_due,currency,notes)
                 VALUES (:number,:customer,:order_id,:status,:issue,:due,:subtotal,0,0,:total,0,:balance,:currency,:notes)'
            )->execute(['number'=>$invoiceNumber,'customer'=>$order['customer_id'],'order_id'=>$orderId,'status'=>$status,'issue'=>$issue,'due'=>$due,'subtotal'=>$order['total_amount'],'total'=>$order['total_amount'],'balance'=>$order['total_amount'],'currency'=>$order['currency'],'notes'=>null]);
            $invoiceId = (int) $database->lastInsertId();
            $sort = 0;
            $database->prepare('INSERT INTO invoice_items (invoice_id,description,quantity,unit_price_amount,line_total_amount,sort_order) VALUES (:invoice,:description,1,:price,:total,:sort)')
                ->execute(['invoice'=>$invoiceId,'description'=>$order['description'],'price'=>$order['subtotal_amount'],'total'=>$order['subtotal_amount'],'sort'=>$sort++]);
            if ((int)$order['setup_fee_amount'] > 0) {
                $database->prepare('INSERT INTO invoice_items (invoice_id,description,quantity,unit_price_amount,line_total_amount,sort_order) VALUES (:invoice,:description,1,:price,:total,:sort)')
                    ->execute(['invoice'=>$invoiceId,'description'=>'Setup fee','price'=>$order['setup_fee_amount'],'total'=>$order['setup_fee_amount'],'sort'=>$sort]);
            }
            if ($ownsTransaction) $database->commit();
            if ($status === 'sent') (new MailService())->invoice($invoiceId);
            return $invoiceId;
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $database->inTransaction()) $database->rollBack();
            throw $exception;
        }
    }

    public function nextNumber(PDO $database): string
    {
        $prefix = $this->setting($database, 'invoice_prefix', 'INV') . '-' . date('Y') . '-';
        $statement = $database->prepare('SELECT invoice_number FROM invoices WHERE invoice_number LIKE :prefix ORDER BY id DESC LIMIT 1 FOR UPDATE');
        $statement->execute(['prefix'=>$prefix.'%']);
        $last=(string)($statement->fetchColumn()?:'');
        return $prefix.str_pad((string)($last?((int)substr($last,-4))+1:1),4,'0',STR_PAD_LEFT);
    }

    private function dueDays(PDO $database): int { return max(0,min(365,(int)$this->setting($database,'invoice_due_days','14'))); }
    private function setting(PDO $database,string $key,string $default): string { $s=$database->prepare('SELECT setting_value FROM settings WHERE setting_key=:key LIMIT 1');$s->execute(['key'=>$key]);return (string)($s->fetchColumn()?:$default); }
}
