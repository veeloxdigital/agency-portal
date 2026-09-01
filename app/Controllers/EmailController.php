<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\View;
use App\Services\MailService;

final class EmailController
{
    public function index(): void
    {
        $db=Database::connection();$templates=$db->query('SELECT * FROM email_templates ORDER BY name')->fetchAll();$logs=$db->query('SELECT email_logs.*,customers.contact_name,customers.company_name FROM email_logs LEFT JOIN customers ON customers.id=email_logs.customer_id ORDER BY email_logs.created_at DESC LIMIT 100')->fetchAll();
        View::render('emails/index',['title'=>'Email Centre','templates'=>$templates,'logs'=>$logs,'flash'=>$this->pullFlash()]);
    }
    public function edit(string $id): void{$s=Database::connection()->prepare('SELECT * FROM email_templates WHERE id=:id');$s->execute(['id'=>$id]);$template=$s->fetch();if(!$template){http_response_code(404);exit('Template not found.');}View::render('emails/edit',['title'=>'Edit email template','template'=>$template,'flash'=>$this->pullFlash()]);}
    public function update(string $id): never{$subject=trim((string)($_POST['subject']??''));$body=trim((string)($_POST['body_html']??''));if(!$subject||!$body){$this->flash('error','Subject and email content are required.');$this->redirect('/emails/templates/'.$id.'/edit');}Database::connection()->prepare('UPDATE email_templates SET subject=:subject,body_html=:body,enabled=:enabled WHERE id=:id')->execute(['subject'=>$subject,'body'=>$body,'enabled'=>isset($_POST['enabled'])?1:0,'id'=>$id]);$this->flash('success','Email template updated.');$this->redirect('/emails');}
    public function test(): never{$email=trim((string)($_POST['email']??''));if(!filter_var($email,FILTER_VALIDATE_EMAIL))$this->flash('error','Enter a valid test email address.');elseif((new MailService())->test($email))$this->flash('success','Test email sent successfully.');else $this->flash('error','Test email failed. Check the delivery log and SMTP settings.');$this->redirect('/emails');}
    public function resendInvoice(string $id): never{$db=Database::connection();$s=$db->prepare('SELECT invoices.*,customers.id AS customer_id,customers.contact_name,customers.email,customers.email_notifications FROM invoices INNER JOIN customers ON customers.id=invoices.customer_id WHERE invoices.id=:id');$s->execute(['id'=>$id]);$invoice=$s->fetch();if(!$invoice){http_response_code(404);exit('Invoice not found.');}$ok=(new MailService())->sendTemplate('invoice_sent',['id'=>$invoice['customer_id'],'contact_name'=>$invoice['contact_name'],'email'=>$invoice['email'],'email_notifications'=>$invoice['email_notifications']],['invoice_number'=>$invoice['invoice_number'],'invoice_total'=>'£'.number_format($invoice['total_amount']/100,2),'invoice_due_date'=>date('j F Y',strtotime((string)$invoice['due_date'])),'invoice_url'=>rtrim((string)\App\Core\Env::get('APP_URL',''),'/').'/portal/invoices/'.$invoice['id']]);$this->flash($ok?'success':'error',$ok?'Invoice email sent.':'Invoice email failed or customer notifications are disabled.');$this->redirect('/invoices/'.$invoice['id']);}
    private function flash(string $type,string $message):void{$_SESSION['_flash']=compact('type','message');}private function pullFlash():?array{$f=$_SESSION['_flash']??null;unset($_SESSION['_flash']);return $f;}private function redirect(string $url):never{header('Location: '.$url);exit;}
}
