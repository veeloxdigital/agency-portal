<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Env;
use Throwable;

final class MailService
{
    public function order(int $orderId,string $template='order_created'): bool
    {
        $s=Database::connection()->prepare('SELECT orders.*,customers.id AS customer_record_id,customers.contact_name,customers.email,customers.email_notifications FROM orders INNER JOIN customers ON customers.id=orders.customer_id WHERE orders.id=:id');$s->execute(['id'=>$orderId]);$r=$s->fetch();if(!$r)return false;
        return $this->sendTemplate($template,['id'=>$r['customer_record_id'],'contact_name'=>$r['contact_name'],'email'=>$r['email'],'email_notifications'=>$r['email_notifications']],['order_number'=>$r['order_number'],'order_description'=>$r['description'],'order_total'=>'£'.number_format($r['total_amount']/100,2),'order_status'=>ucwords(str_replace('_',' ',(string)$r['status']))]);
    }

    public function invoice(int $invoiceId,string $template='invoice_sent',?int $paymentAmount=null): bool
    {
        $s=Database::connection()->prepare('SELECT invoices.*,customers.id AS customer_record_id,customers.contact_name,customers.email,customers.email_notifications FROM invoices INNER JOIN customers ON customers.id=invoices.customer_id WHERE invoices.id=:id');$s->execute(['id'=>$invoiceId]);$r=$s->fetch();if(!$r)return false;
        return $this->sendTemplate($template,['id'=>$r['customer_record_id'],'contact_name'=>$r['contact_name'],'email'=>$r['email'],'email_notifications'=>$r['email_notifications']],['invoice_number'=>$r['invoice_number'],'invoice_total'=>'£'.number_format($r['total_amount']/100,2),'invoice_due_date'=>date('j F Y',strtotime((string)$r['due_date'])),'invoice_url'=>rtrim((string)Env::get('APP_URL',''),'/').'/portal/invoices/'.$r['id'],'payment_amount'=>'£'.number_format(($paymentAmount??0)/100,2),'invoice_balance'=>'£'.number_format($r['balance_due']/100,2)]);
    }

    public function ticket(int $ticketId,string $template): bool
    {
        $s=Database::connection()->prepare('SELECT tickets.*,customers.id AS customer_record_id,customers.contact_name,customers.email,customers.email_notifications FROM tickets INNER JOIN customers ON customers.id=tickets.customer_id WHERE tickets.id=:id');$s->execute(['id'=>$ticketId]);$r=$s->fetch();if(!$r)return false;
        return $this->sendTemplate($template,['id'=>$r['customer_record_id'],'contact_name'=>$r['contact_name'],'email'=>$r['email'],'email_notifications'=>$r['email_notifications']],['ticket_number'=>$r['ticket_number'],'ticket_subject'=>$r['subject'],'ticket_url'=>rtrim((string)Env::get('APP_URL',''),'/').'/portal/tickets/'.$r['id']]);
    }

    public function sendTemplate(string $key,array $customer,array $variables=[]): bool
    {
        if(isset($customer['email_notifications'])&&!$customer['email_notifications'])return false;
        $db=Database::connection();$s=$db->prepare('SELECT * FROM email_templates WHERE template_key=:key AND enabled=1 LIMIT 1');$s->execute(['key'=>$key]);$template=$s->fetch();if(!$template)return false;
        $defaults=['customer_name'=>$customer['contact_name']??$customer['name']??'Customer','customer_email'=>$customer['email']??'','portal_url'=>rtrim((string)Env::get('APP_URL',''),'/').'/login'];$values=array_merge($defaults,$variables);
        $subject=$this->replace((string)$template['subject'],$values);$body=$this->replace((string)$template['body_html'],$values);$html=$this->layout($body);
        $log=['customer_id'=>$customer['id']??null,'recipient_email'=>$customer['email'],'subject'=>$subject,'template_key'=>$key];
        $db->prepare("INSERT INTO email_logs (customer_id,recipient_email,subject,template_key,status) VALUES (:customer_id,:recipient_email,:subject,:template_key,'queued')")->execute($log);$logId=(int)$db->lastInsertId();
        try{(new SmtpTransport())->send((string)$customer['email'],(string)$defaults['customer_name'],$subject,$html);$db->prepare("UPDATE email_logs SET status='sent',sent_at=NOW() WHERE id=:id")->execute(['id'=>$logId]);return true;}catch(Throwable $exception){$db->prepare("UPDATE email_logs SET status='failed',error_message=:error WHERE id=:id")->execute(['error'=>substr($exception->getMessage(),0,2000),'id'=>$logId]);return false;}
    }

    public function test(string $email): bool{return $this->sendRaw($email,'SMTP test from Veelox Digital','<h1>Your email settings work</h1><p>This test message was sent successfully by the Veelox Digital portal.</p>');}
    public function sendRaw(string $email,string $subject,string $body): bool{$db=Database::connection();$db->prepare("INSERT INTO email_logs (recipient_email,subject,status) VALUES (:email,:subject,'queued')")->execute(['email'=>$email,'subject'=>$subject]);$id=(int)$db->lastInsertId();try{(new SmtpTransport())->send($email,'Veelox Administrator',$subject,$this->layout($body));$db->prepare("UPDATE email_logs SET status='sent',sent_at=NOW() WHERE id=:id")->execute(['id'=>$id]);return true;}catch(Throwable $exception){$db->prepare("UPDATE email_logs SET status='failed',error_message=:error WHERE id=:id")->execute(['error'=>substr($exception->getMessage(),0,2000),'id'=>$id]);return false;}}
    private function replace(string $content,array $values): string{foreach($values as $key=>$value)$content=str_replace('{{'.$key.'}}',htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8'),$content);return $content;}
    private function layout(string $body): string{return '<!doctype html><html><body style="margin:0;background:#f4f5f8;font-family:Arial,sans-serif;color:#18202b"><table width="100%" cellpadding="0" cellspacing="0"><tr><td style="padding:35px 15px"><table width="600" align="center" cellpadding="0" cellspacing="0" style="max-width:600px;background:#fff;border-radius:18px"><tr><td style="padding:28px 35px;background:#171a24;color:#fff;border-radius:18px 18px 0 0"><strong style="font-size:20px">Veelox</strong> <span style="color:#aaa">Digital</span></td></tr><tr><td style="padding:35px;line-height:1.7">'.$body.'</td></tr><tr><td style="padding:20px 35px;border-top:1px solid #eee;color:#777;font-size:12px">Veelox Digital · Eastbourne, East Sussex</td></tr></table></td></tr></table></body></html>';}
}
