<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Services\StripeClient;
use Throwable;

final class StripeController
{
    public function checkout(string $id): never
    {
        $invoice=$this->customerInvoice((int)$id);
        if((int)$invoice['balance_due']<=0||in_array($invoice['status'],['draft','paid','void','refunded'],true)){$this->flash('error','This invoice is not available for card payment.');$this->redirect('/portal/invoices/'.$invoice['id']);}
        try{
            $session=(new StripeClient())->createCheckoutSession($invoice);
            Database::connection()->prepare("INSERT INTO payments (invoice_id,customer_id,payment_reference,provider,provider_payment_id,status,amount,currency,metadata) VALUES (:invoice,:customer,:reference,'stripe',:session,'pending',:amount,:currency,:metadata)")
                ->execute(['invoice'=>$invoice['id'],'customer'=>$invoice['customer_id'],'reference'=>$invoice['invoice_number'],'session'=>$session['id'],'amount'=>$invoice['balance_due'],'currency'=>$invoice['currency'],'metadata'=>json_encode(['checkout_session_id'=>$session['id']])]);
            header('Location: '.$session['url'],true,303);exit;
        }catch(Throwable $exception){$this->flash('error','Stripe Checkout could not be started. '.$exception->getMessage());$this->redirect('/portal/invoices/'.$invoice['id']);}
    }

    public function webhook(): never
    {
        $payload=(string)file_get_contents('php://input');$signature=(string)($_SERVER['HTTP_STRIPE_SIGNATURE']??'');
        try{$event=(new StripeClient())->verifyWebhook($payload,$signature);$this->process($event);http_response_code(200);echo 'ok';}catch(Throwable $exception){http_response_code(400);echo 'invalid';}exit;
    }

    private function process(array $event): void
    {
        $db=Database::connection();$known=$db->prepare('SELECT COUNT(*) FROM stripe_webhook_events WHERE stripe_event_id=:id');$known->execute(['id'=>$event['id']]);if($known->fetchColumn())return;
        $object=$event['data']['object']??[];$type=(string)$event['type'];$sessionId=(string)($object['id']??'');
        $db->beginTransaction();try{
            $db->prepare('INSERT INTO stripe_webhook_events (stripe_event_id,event_type) VALUES (:id,:type)')->execute(['id'=>$event['id'],'type'=>$type]);
            if(in_array($type,['checkout.session.completed','checkout.session.async_payment_succeeded'],true)&&($object['payment_status']??'paid')==='paid')$this->completePayment($db,$sessionId,(string)($object['payment_intent']??$sessionId));
            if(in_array($type,['checkout.session.async_payment_failed','checkout.session.expired'],true))$db->prepare("UPDATE payments SET status='failed' WHERE provider='stripe' AND provider_payment_id=:id AND status='pending'")->execute(['id'=>$sessionId]);
            $db->commit();
        }catch(Throwable $exception){if($db->inTransaction())$db->rollBack();throw $exception;}
    }

    private function completePayment(\PDO $db,string $sessionId,string $paymentIntent): void
    {
        $s=$db->prepare("SELECT payments.*,invoices.total_amount,invoices.amount_paid,invoices.order_id FROM payments INNER JOIN invoices ON invoices.id=payments.invoice_id WHERE payments.provider='stripe' AND payments.provider_payment_id=:id LIMIT 1 FOR UPDATE");$s->execute(['id'=>$sessionId]);$payment=$s->fetch();if(!$payment||$payment['status']==='succeeded')return;
        $amount=(int)$payment['amount'];$paid=min((int)$payment['total_amount'],(int)$payment['amount_paid']+$amount);$balance=max(0,(int)$payment['total_amount']-$paid);$status=$balance===0?'paid':'partially_paid';
        $db->prepare("UPDATE payments SET status='succeeded',provider_payment_id=:intent,paid_at=NOW() WHERE id=:id")->execute(['intent'=>$paymentIntent,'id'=>$payment['id']]);
        $db->prepare('UPDATE invoices SET amount_paid=:paid,balance_due=:balance,status=:status,paid_at=:paid_at WHERE id=:id')->execute(['paid'=>$paid,'balance'=>$balance,'status'=>$status,'paid_at'=>$balance===0?date('Y-m-d H:i:s'):null,'id'=>$payment['invoice_id']]);
        if($balance===0&&$payment['order_id'])$db->prepare("UPDATE orders SET status='paid' WHERE id=:id AND status='awaiting_payment'")->execute(['id'=>$payment['order_id']]);
    }

    private function customerInvoice(int $id): array{$s=Database::connection()->prepare('SELECT invoices.*,customers.email FROM invoices INNER JOIN customers ON customers.id=invoices.customer_id WHERE invoices.id=:id AND customers.user_id=:user LIMIT 1');$s->execute(['id'=>$id,'user'=>Auth::user()['id']]);$invoice=$s->fetch();if(!$invoice){http_response_code(404);exit('Invoice not found.');}return $invoice;}
    private function flash(string $type,string $message): void{$_SESSION['_flash']=compact('type','message');}private function redirect(string $url): never{header('Location: '.$url);exit;}
}
