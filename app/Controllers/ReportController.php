<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\View;
use DateTimeImmutable;
use PDO;

final class ReportController
{
    public function index(): void
    {
        [$from,$to]=$this->dates();$db=Database::connection();$range=['from'=>$from,'to'=>$to];
        $metrics=$this->metrics($db,$range);
        View::render('reports/index',[
            'title'=>'Revenue Reports','from'=>$from,'to'=>$to,'metrics'=>$metrics,
            'monthly'=>$this->monthly($db,$range),'providers'=>$this->providers($db,$range),
            'billing'=>$this->billing($db,$range),'packages'=>$this->packages($db,$range),
            'customers'=>$this->customers($db,$range),'overdue'=>$this->overdue($db),
            'renewals'=>$this->renewals($db),'activity'=>$this->activity($db,$range),
        ]);
    }

    public function export(): never
    {
        [$from,$to]=$this->dates();$type=in_array($_GET['type']??'payments',['payments','outstanding','renewals'],true)?$_GET['type']:'payments';$db=Database::connection();
        $filename='veelox-'.$type.'-'.$from.'-to-'.$to.'.csv';header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="'.$filename.'"');$out=fopen('php://output','wb');fwrite($out,"\xEF\xBB\xBF");
        if($type==='outstanding'){$rows=$this->overdue($db,true);fputcsv($out,['Invoice','Customer','Due date','Status','Balance (GBP)']);foreach($rows as $r)fputcsv($out,[$r['invoice_number'],$r['customer_name'],$r['due_date'],str_replace('_',' ',$r['status']),number_format($r['balance_due']/100,2,'.','')]);}
        elseif($type==='renewals'){$rows=$this->renewals($db);fputcsv($out,['Order','Customer','Service','Renewal date','Billing','Value (GBP)']);foreach($rows as $r)fputcsv($out,[$r['order_number'],$r['customer_name'],$r['description'],$r['renews_at'],$r['billing_interval'],number_format($r['total_amount']/100,2,'.','')]);}
        else{$s=$db->prepare("SELECT payments.paid_at,payments.provider,payments.payment_reference,payments.amount,payments.refunded_amount,invoices.invoice_number,COALESCE(customers.company_name,customers.contact_name) customer_name FROM payments INNER JOIN invoices ON invoices.id=payments.invoice_id INNER JOIN customers ON customers.id=payments.customer_id WHERE payments.status IN ('succeeded','partially_refunded') AND DATE(payments.paid_at) BETWEEN :from AND :to ORDER BY payments.paid_at DESC");$s->execute(['from'=>$from,'to'=>$to]);fputcsv($out,['Paid at','Invoice','Customer','Method','Reference','Gross (GBP)','Refunded (GBP)','Net (GBP)']);foreach($s->fetchAll() as $r)fputcsv($out,[$r['paid_at'],$r['invoice_number'],$r['customer_name'],str_replace('_',' ',$r['provider']),$r['payment_reference'],number_format($r['amount']/100,2,'.',''),number_format($r['refunded_amount']/100,2,'.',''),number_format(($r['amount']-$r['refunded_amount'])/100,2,'.','')]);}
        fclose($out);exit;
    }

    private function dates(): array
    {
        $today=new DateTimeImmutable('today');$defaultFrom=$today->modify('first day of January')->format('Y-m-d');$from=$this->validDate((string)($_GET['from']??''))?:$defaultFrom;$to=$this->validDate((string)($_GET['to']??''))?:$today->format('Y-m-d');if($from>$to)[$from,$to]=[$to,$from];return[$from,$to];
    }
    private function validDate(string $value): ?string{$d=DateTimeImmutable::createFromFormat('!Y-m-d',$value);return $d&&$d->format('Y-m-d')===$value?$value:null;}
    private function scalar(PDO $db,string $sql,array $params=[]): int{$s=$db->prepare($sql);$s->execute($params);return(int)$s->fetchColumn();}
    private function metrics(PDO $db,array $r): array
    {
        $paid="payments.status IN ('succeeded','partially_refunded') AND DATE(payments.paid_at) BETWEEN :from AND :to";
        return[
            'revenue'=>$this->scalar($db,"SELECT COALESCE(SUM(amount-refunded_amount),0) FROM payments WHERE $paid",$r),
            'invoiced'=>$this->scalar($db,"SELECT COALESCE(SUM(total_amount),0) FROM invoices WHERE issue_date BETWEEN :from AND :to AND status NOT IN ('void','refunded')",$r),
            'outstanding'=>$this->scalar($db,"SELECT COALESCE(SUM(balance_due),0) FROM invoices WHERE status IN ('sent','partially_paid','overdue')"),
            'overdue'=>$this->scalar($db,"SELECT COALESCE(SUM(balance_due),0) FROM invoices WHERE due_date<CURDATE() AND status IN ('sent','partially_paid','overdue')"),
        ];
    }
    private function monthly(PDO $db,array $r): array{$s=$db->prepare("SELECT DATE_FORMAT(paid_at,'%Y-%m') month,COALESCE(SUM(amount-refunded_amount),0) total FROM payments WHERE status IN ('succeeded','partially_refunded') AND DATE(paid_at) BETWEEN :from AND :to GROUP BY DATE_FORMAT(paid_at,'%Y-%m') ORDER BY month");$s->execute($r);return$s->fetchAll();}
    private function providers(PDO $db,array $r): array{$s=$db->prepare("SELECT provider,COUNT(*) transactions,COALESCE(SUM(amount-refunded_amount),0) total FROM payments WHERE status IN ('succeeded','partially_refunded') AND DATE(paid_at) BETWEEN :from AND :to GROUP BY provider ORDER BY total DESC");$s->execute($r);return$s->fetchAll();}
    private function billing(PDO $db,array $r): array{$s=$db->prepare("SELECT COALESCE(orders.billing_type,'unassigned') billing_type,COALESCE(SUM(payments.amount-payments.refunded_amount),0) total FROM payments INNER JOIN invoices ON invoices.id=payments.invoice_id LEFT JOIN orders ON orders.id=invoices.order_id WHERE payments.status IN ('succeeded','partially_refunded') AND DATE(payments.paid_at) BETWEEN :from AND :to GROUP BY COALESCE(orders.billing_type,'unassigned') ORDER BY total DESC");$s->execute($r);return$s->fetchAll();}
    private function packages(PDO $db,array $r): array{$s=$db->prepare("SELECT COALESCE(packages.name,orders.description,'Custom invoices') package_name,COUNT(DISTINCT payments.id) transactions,COUNT(DISTINCT invoices.customer_id) customers,COALESCE(SUM(payments.amount-payments.refunded_amount),0) total FROM payments INNER JOIN invoices ON invoices.id=payments.invoice_id LEFT JOIN orders ON orders.id=invoices.order_id LEFT JOIN packages ON packages.id=orders.package_id WHERE payments.status IN ('succeeded','partially_refunded') AND DATE(payments.paid_at) BETWEEN :from AND :to GROUP BY COALESCE(packages.name,orders.description,'Custom invoices') ORDER BY total DESC LIMIT 10");$s->execute($r);return$s->fetchAll();}
    private function customers(PDO $db,array $r): array{$s=$db->prepare("SELECT customers.id,customers.account_number,COALESCE(customers.company_name,customers.contact_name) customer_name,COUNT(DISTINCT payments.id) payments,COALESCE(SUM(payments.amount-payments.refunded_amount),0) total FROM payments INNER JOIN customers ON customers.id=payments.customer_id WHERE payments.status IN ('succeeded','partially_refunded') AND DATE(payments.paid_at) BETWEEN :from AND :to GROUP BY customers.id,customers.account_number,customers.company_name,customers.contact_name ORDER BY total DESC LIMIT 10");$s->execute($r);return$s->fetchAll();}
    private function overdue(PDO $db,bool $allOutstanding=false): array{$where=$allOutstanding?'1=1':'invoices.due_date<CURDATE()';$s=$db->query("SELECT invoices.id,invoices.invoice_number,invoices.due_date,invoices.status,invoices.balance_due,COALESCE(customers.company_name,customers.contact_name) customer_name FROM invoices INNER JOIN customers ON customers.id=invoices.customer_id WHERE $where AND invoices.status IN ('sent','partially_paid','overdue') ORDER BY invoices.due_date ASC LIMIT 1000");return$s->fetchAll();}
    private function renewals(PDO $db): array{$s=$db->query("SELECT orders.id,orders.order_number,orders.description,orders.billing_interval,orders.renews_at,orders.total_amount,COALESCE(customers.company_name,customers.contact_name) customer_name FROM orders INNER JOIN customers ON customers.id=orders.customer_id WHERE orders.billing_type='recurring' AND orders.status IN ('paid','active') AND orders.renews_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 60 DAY) ORDER BY orders.renews_at LIMIT 1000");return$s->fetchAll();}
    private function activity(PDO $db,array $r): array{return['customers'=>$this->scalar($db,'SELECT COUNT(*) FROM customers WHERE DATE(created_at) BETWEEN :from AND :to',$r),'orders'=>$this->scalar($db,"SELECT COUNT(*) FROM orders WHERE DATE(created_at) BETWEEN :from AND :to AND status NOT IN ('draft','cancelled')",$r),'invoices'=>$this->scalar($db,"SELECT COUNT(*) FROM invoices WHERE issue_date BETWEEN :from AND :to AND status<>'void'",$r)];}
}
