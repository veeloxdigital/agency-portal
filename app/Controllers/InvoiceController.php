<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Env;
use App\Core\Auth;
use App\Core\View;
use App\Services\InvoiceService;
use PDO;
use Throwable;

final class InvoiceController
{
    public function index(): void
    {
        $db=Database::connection(); $this->markOverdue($db);
        $search=trim((string)($_GET['search']??'')); $status=trim((string)($_GET['status']??'')); $where=[];$params=[];
        if($search!==''){ $where[]='(invoices.invoice_number LIKE :search OR customers.company_name LIKE :search OR customers.contact_name LIKE :search)';$params['search']='%'.$search.'%'; }
        if(in_array($status,['draft','sent','partially_paid','paid','overdue','void','refunded'],true)){ $where[]='invoices.status=:status';$params['status']=$status; }
        $sql='SELECT invoices.*,customers.account_number,customers.company_name,customers.contact_name,orders.order_number FROM invoices INNER JOIN customers ON customers.id=invoices.customer_id LEFT JOIN orders ON orders.id=invoices.order_id';
        if($where)$sql.=' WHERE '.implode(' AND ',$where);$sql.=' ORDER BY invoices.created_at DESC';$s=$db->prepare($sql);$s->execute($params);
        View::render('invoices/index',['title'=>'Invoices','invoices'=>$s->fetchAll(),'search'=>$search,'status'=>$status,'flash'=>$this->pullFlash(),'portal'=>false]);
    }

    public function create(): void
    {
        $orderId=(int)($_GET['order']??0);
        if($orderId){try{$id=(new InvoiceService())->createFromOrder($orderId,'draft');$this->redirect('/invoices/'.$id.'/edit');}catch(Throwable){$this->flash('error','The invoice could not be created from that order.');$this->redirect('/invoices');}}
        View::render('invoices/create',['title'=>'Create invoice','invoice'=>$_SESSION['_old_invoice']??$this->emptyInvoice(),'items'=>$_SESSION['_old_items']??[['description'=>'','quantity'=>'1','unit_price'=>'']],'errors'=>$_SESSION['_invoice_errors']??[],'customers'=>$this->customers(),'orders'=>$this->orders()]);
        unset($_SESSION['_old_invoice'],$_SESSION['_old_items'],$_SESSION['_invoice_errors']);
    }

    public function store(): never { $this->save(null); }
    public function edit(string $id): void
    {
        $invoice=$this->find((int)$id); if((int)$invoice['amount_paid']>0||in_array($invoice['status'],['paid','void','refunded'],true)){ $this->flash('error','Invoices with payments, void invoices and refunded invoices cannot be edited.');$this->redirect('/invoices/'.$invoice['id']); }
        $items=$this->items((int)$invoice['id']);foreach($items as &$item){$item['unit_price']=number_format($item['unit_price_amount']/100,2,'.','');}
        View::render('invoices/edit',['title'=>'Edit invoice','invoice'=>$_SESSION['_old_invoice']??$invoice,'items'=>$_SESSION['_old_items']??$items,'errors'=>$_SESSION['_invoice_errors']??[],'customers'=>$this->customers(),'orders'=>$this->orders(true)]);unset($_SESSION['_old_invoice'],$_SESSION['_old_items'],$_SESSION['_invoice_errors']);
    }
    public function update(string $id): never { $this->save((int)$id); }

    public function show(string $id): void
    {
        $db=Database::connection();$this->markOverdue($db);$invoice=$this->find((int)$id);
        $p=$db->prepare('SELECT * FROM payments WHERE invoice_id=:id ORDER BY created_at DESC');$p->execute(['id'=>$invoice['id']]);
        View::render('invoices/show',['title'=>$invoice['invoice_number'],'invoice'=>$invoice,'items'=>$this->items((int)$invoice['id']),'payments'=>$p->fetchAll(),'flash'=>$this->pullFlash(),'bank'=>$this->bank(),'canManage'=>true]);
    }

    public function portalIndex(): void
    {
        $db=Database::connection();$this->markOverdue($db);$customerId=$this->portalCustomerId();
        $s=$db->prepare("SELECT invoices.*,customers.account_number,customers.company_name,customers.contact_name,orders.order_number FROM invoices INNER JOIN customers ON customers.id=invoices.customer_id LEFT JOIN orders ON orders.id=invoices.order_id WHERE invoices.customer_id=:customer AND invoices.status NOT IN ('draft','void') ORDER BY invoices.created_at DESC");$s->execute(['customer'=>$customerId]);
        View::render('invoices/index',['title'=>'My Invoices','invoices'=>$s->fetchAll(),'search'=>'','status'=>'','flash'=>null,'portal'=>true]);
    }

    public function portalShow(string $id): void
    {
        $db=Database::connection();$this->markOverdue($db);$invoice=$this->find((int)$id);
        if((int)$invoice['customer_id']!==$this->portalCustomerId()){http_response_code(404);exit('Invoice not found.');}
        $p=$db->prepare('SELECT * FROM payments WHERE invoice_id=:id ORDER BY created_at DESC');$p->execute(['id'=>$invoice['id']]);
        View::render('invoices/show',['title'=>$invoice['invoice_number'],'invoice'=>$invoice,'items'=>$this->items((int)$invoice['id']),'payments'=>$p->fetchAll(),'flash'=>null,'bank'=>$this->bank(),'canManage'=>false]);
    }

    public function status(string $id): never
    {
        $invoice=$this->find((int)$id);$status=(string)($_POST['status']??'');
        if(!in_array($status,['draft','sent','void'],true)||$invoice['amount_paid']>0){$this->flash('error','That invoice status change is not available.');}
        else{Database::connection()->prepare('UPDATE invoices SET status=:status WHERE id=:id')->execute(['status'=>$status,'id'=>$invoice['id']]);$this->flash('success','Invoice status updated.');}
        $this->redirect('/invoices/'.$invoice['id']);
    }

    public function payment(string $id): never
    {
        $invoice=$this->find((int)$id);$amount=$this->money((string)($_POST['amount']??''));$reference=trim((string)($_POST['reference']??''));$date=$this->date($_POST['paid_at']??'');
        if($amount===null||$amount<=0||$amount>(int)$invoice['balance_due']||!$date){$this->flash('error','Enter a valid payment date and an amount no greater than the outstanding balance.');$this->redirect('/invoices/'.$invoice['id']);}
        $db=Database::connection();try{$db->beginTransaction();$db->prepare("INSERT INTO payments (invoice_id,customer_id,payment_reference,provider,status,amount,currency,paid_at) VALUES (:invoice,:customer,:reference,'bank_transfer','succeeded',:amount,:currency,:paid_at)")->execute(['invoice'=>$invoice['id'],'customer'=>$invoice['customer_id'],'reference'=>$reference?:null,'amount'=>$amount,'currency'=>$invoice['currency'],'paid_at'=>$date.' '.date('H:i:s')]);$paid=(int)$invoice['amount_paid']+$amount;$balance=max(0,(int)$invoice['total_amount']-$paid);$status=$balance===0?'paid':'partially_paid';$db->prepare('UPDATE invoices SET amount_paid=:paid,balance_due=:balance,status=:status,paid_at=:paid_at WHERE id=:id')->execute(['paid'=>$paid,'balance'=>$balance,'status'=>$status,'paid_at'=>$balance===0?date('Y-m-d H:i:s'):null,'id'=>$invoice['id']]);if($balance===0&&$invoice['order_id'])$db->prepare("UPDATE orders SET status='paid' WHERE id=:id AND status='awaiting_payment'")->execute(['id'=>$invoice['order_id']]);$db->commit();$this->flash('success','Bank-transfer payment recorded.');}catch(Throwable){if($db->inTransaction())$db->rollBack();$this->flash('error','The payment could not be recorded.');}$this->redirect('/invoices/'.$invoice['id']);
    }

    private function save(?int $id): never
    {
        $invoice=$id?$this->find($id):null;$data=$this->data();$items=$this->postedItems();$errors=$this->validate($data,$items);if($errors)$this->formError($id?'/invoices/'.$id.'/edit':'/invoices/create',$data,$items,$errors);
        $subtotal=array_sum(array_column($items,'line_total_amount'));$db=Database::connection();try{$db->beginTransaction();if($id){$db->prepare('UPDATE invoices SET customer_id=:customer_id,order_id=:order_id,status=:status,issue_date=:issue_date,due_date=:due_date,subtotal_amount=:subtotal,total_amount=:total,balance_due=:balance,notes=:notes WHERE id=:id')->execute(['customer_id'=>$data['customer_id'],'order_id'=>$data['order_id'],'status'=>$data['status'],'issue_date'=>$data['issue_date'],'due_date'=>$data['due_date'],'subtotal'=>$subtotal,'total'=>$subtotal,'balance'=>$subtotal-(int)$invoice['amount_paid'],'notes'=>$data['notes'],'id'=>$id]);$db->prepare('DELETE FROM invoice_items WHERE invoice_id=:id')->execute(['id'=>$id]);$invoiceId=$id;}else{$number=(new InvoiceService())->nextNumber($db);$db->prepare('INSERT INTO invoices (invoice_number,customer_id,order_id,status,issue_date,due_date,subtotal_amount,total_amount,balance_due,currency,notes) VALUES (:number,:customer_id,:order_id,:status,:issue_date,:due_date,:subtotal,:total,:balance,\'GBP\',:notes)')->execute(['number'=>$number,'customer_id'=>$data['customer_id'],'order_id'=>$data['order_id'],'status'=>$data['status'],'issue_date'=>$data['issue_date'],'due_date'=>$data['due_date'],'subtotal'=>$subtotal,'total'=>$subtotal,'balance'=>$subtotal,'notes'=>$data['notes']]);$invoiceId=(int)$db->lastInsertId();} $s=$db->prepare('INSERT INTO invoice_items (invoice_id,description,quantity,unit_price_amount,line_total_amount,sort_order) VALUES (:invoice,:description,:quantity,:unit,:total,:sort)');foreach($items as $i=>$item)$s->execute(['invoice'=>$invoiceId,'description'=>$item['description'],'quantity'=>$item['quantity'],'unit'=>$item['unit_price_amount'],'total'=>$item['line_total_amount'],'sort'=>$i]);$db->commit();$this->flash('success',$id?'Invoice updated.':'Invoice created.');$this->redirect('/invoices/'.$invoiceId);}catch(Throwable){if($db->inTransaction())$db->rollBack();$this->formError($id?'/invoices/'.$id.'/edit':'/invoices/create',$data,$items,['The invoice could not be saved.']);}
    }

    private function data(): array{return ['customer_id'=>(int)($_POST['customer_id']??0),'order_id'=>(int)($_POST['order_id']??0)?:null,'status'=>in_array($_POST['status']??'', ['draft','sent'],true)?$_POST['status']:'draft','issue_date'=>$this->date($_POST['issue_date']??''),'due_date'=>$this->date($_POST['due_date']??''),'notes'=>trim((string)($_POST['notes']??''))?:null];}
    private function postedItems(): array{$des=$_POST['item_description']??[];$qty=$_POST['item_quantity']??[];$prices=$_POST['item_price']??[];$items=[];foreach((array)$des as $i=>$description){$description=trim((string)$description);if($description==='')continue;$quantity=(float)($qty[$i]??1);$unit=$this->money((string)($prices[$i]??''));if($quantity<=0||$unit===null){$items[]=['description'=>$description,'quantity'=>$quantity,'unit_price_amount'=>$unit,'unit_price'=>(string)($prices[$i]??''),'line_total_amount'=>0,'invalid'=>true];continue;}$items[]=['description'=>substr($description,0,255),'quantity'=>$quantity,'unit_price_amount'=>$unit,'unit_price'=>(string)($prices[$i]??''),'line_total_amount'=>(int)round($unit*$quantity)];}return $items;}
    private function validate(array $d,array $items): array{$e=[];if(!$d['customer_id'])$e[]='Select a customer.';if(!$d['issue_date']||!$d['due_date'])$e[]='Valid issue and due dates are required.';if($d['issue_date']&&$d['due_date']&&$d['due_date']<$d['issue_date'])$e[]='Due date cannot be before issue date.';if(!$items)$e[]='Add at least one invoice item.';foreach($items as $item)if(!empty($item['invalid'])){$e[]='Every item needs a valid quantity and price.';break;}return $e;}
    private function find(int $id): array{$s=Database::connection()->prepare('SELECT invoices.*,customers.account_number,customers.company_name,customers.contact_name,customers.email,customers.address_line_1,customers.address_line_2,customers.town_city,customers.county,customers.postcode,orders.order_number FROM invoices INNER JOIN customers ON customers.id=invoices.customer_id LEFT JOIN orders ON orders.id=invoices.order_id WHERE invoices.id=:id LIMIT 1');$s->execute(['id'=>$id]);$r=$s->fetch();if(!$r){http_response_code(404);exit('Invoice not found.');}return $r;}
    private function items(int $id): array{$s=Database::connection()->prepare('SELECT * FROM invoice_items WHERE invoice_id=:id ORDER BY sort_order,id');$s->execute(['id'=>$id]);return $s->fetchAll();}
    private function customers(): array{return Database::connection()->query("SELECT id,account_number,company_name,contact_name FROM customers WHERE status IN ('lead','active') ORDER BY COALESCE(company_name,contact_name)")->fetchAll();}
    private function orders(bool $all=false): array{return Database::connection()->query("SELECT orders.id,orders.order_number,orders.customer_id,orders.description FROM orders LEFT JOIN invoices ON invoices.order_id=orders.id AND invoices.status<>'void' WHERE ".($all?'1=1':'invoices.id IS NULL')." ORDER BY orders.created_at DESC")->fetchAll();}
    private function markOverdue(PDO $db): void{$db->exec("UPDATE invoices SET status='overdue' WHERE status='sent' AND balance_due>0 AND due_date<CURDATE()");}
    private function portalCustomerId(): int{$s=Database::connection()->prepare('SELECT id FROM customers WHERE user_id=:user LIMIT 1');$s->execute(['user'=>Auth::user()['id']]);$id=(int)($s->fetchColumn()?:0);if(!$id){http_response_code(404);exit('Customer account not found.');}return $id;}
    private function bank(): array{return ['name'=>Env::get('BANK_NAME',''),'account_name'=>Env::get('BANK_ACCOUNT_NAME','Veelox Digital'),'sort_code'=>Env::get('BANK_SORT_CODE',''),'account_number'=>Env::get('BANK_ACCOUNT_NUMBER','')];}
    private function emptyInvoice(): array{return ['customer_id'=>'','order_id'=>'','status'=>'draft','issue_date'=>date('Y-m-d'),'due_date'=>date('Y-m-d',strtotime('+14 days')),'notes'=>''];}
    private function money(string $v): ?int{$v=str_replace([',','£',' '],'',trim($v));if(!preg_match('/^\d+(?:\.\d{1,2})?$/',$v))return null;[$a,$b]=array_pad(explode('.',$v,2),2,'');return (int)$a*100+(int)str_pad($b,2,'0');}
    private function date(mixed $v): ?string{$v=trim((string)$v);$d=\DateTimeImmutable::createFromFormat('!Y-m-d',$v);return $d&&$d->format('Y-m-d')===$v?$v:null;}
    private function formError(string $url,array $data,array $items,array $errors): never{$_SESSION['_old_invoice']=$data;$_SESSION['_old_items']=$items;$_SESSION['_invoice_errors']=$errors;$this->redirect($url);}
    private function flash(string $type,string $message): void{$_SESSION['_flash']=compact('type','message');}private function pullFlash(): ?array{$f=$_SESSION['_flash']??null;unset($_SESSION['_flash']);return $f;}private function redirect(string $url): never{header('Location: '.$url);exit;}
}
