<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use RuntimeException;

final class StripeClient
{
    public function enabled(): bool { return str_starts_with((string)Env::get('STRIPE_SECRET_KEY',''),'sk_'); }

    public function createCheckoutSession(array $invoice): array
    {
        if(!$this->enabled()) throw new RuntimeException('Stripe is not configured.');
        if(!function_exists('curl_init')) throw new RuntimeException('The PHP cURL extension is required.');
        $appUrl=rtrim((string)Env::get('APP_URL',''),'/');
        if(!str_starts_with($appUrl,'https://')) throw new RuntimeException('APP_URL must use HTTPS for Stripe Checkout.');
        $payload=[
            'mode'=>'payment',
            'managed_payments'=>['enabled'=>'false'],
            'success_url'=>$appUrl.'/portal/invoices/'.(int)$invoice['id'].'?stripe=success&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'=>$appUrl.'/portal/invoices/'.(int)$invoice['id'].'?stripe=cancelled',
            'customer_email'=>$invoice['email'],
            'client_reference_id'=>$invoice['invoice_number'],
            'locale'=>'en-GB',
            'metadata'=>['invoice_id'=>(string)$invoice['id'],'invoice_number'=>$invoice['invoice_number']],
            'payment_intent_data'=>['metadata'=>['invoice_id'=>(string)$invoice['id'],'invoice_number'=>$invoice['invoice_number']]],
            'line_items'=>[0=>['quantity'=>1,'price_data'=>['currency'=>strtolower($invoice['currency']),'unit_amount'=>(int)$invoice['balance_due'],'product_data'=>['name'=>'Invoice '.$invoice['invoice_number'],'description'=>'Payment to Veelox Digital']]]],
        ];
        $curl=curl_init('https://api.stripe.com/v1/checkout/sessions');
        curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($payload),CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.Env::get('STRIPE_SECRET_KEY'),'Content-Type: application/x-www-form-urlencoded','Idempotency-Key: invoice-'.$invoice['id'].'-'.$invoice['balance_due'].'-'.bin2hex(random_bytes(8))]]);
        $body=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);$error=curl_error($curl);curl_close($curl);
        if($body===false)throw new RuntimeException('Stripe connection failed: '.$error);
        $result=json_decode($body,true);
        if($status<200||$status>=300||!is_array($result)||empty($result['id'])||empty($result['url']))throw new RuntimeException((string)($result['error']['message']??'Stripe rejected the checkout request.'));
        return $result;
    }

    public function verifyWebhook(string $payload,string $header): array
    {
        $secret=(string)Env::get('STRIPE_WEBHOOK_SECRET','');
        if(!str_starts_with($secret,'whsec_'))throw new RuntimeException('Webhook secret is not configured.');
        $parts=[];foreach(explode(',',$header) as $part){[$key,$value]=array_pad(explode('=',$part,2),2,'');$parts[$key][]=$value;}
        $timestamp=(int)($parts['t'][0]??0);$signatures=$parts['v1']??[];
        if(!$timestamp||abs(time()-$timestamp)>300)throw new RuntimeException('Webhook timestamp is outside the allowed tolerance.');
        $expected=hash_hmac('sha256',$timestamp.'.'.$payload,$secret);$valid=false;foreach($signatures as $signature){if(hash_equals($expected,$signature)){$valid=true;break;}}
        if(!$valid)throw new RuntimeException('Webhook signature verification failed.');
        $event=json_decode($payload,true);if(!is_array($event)||empty($event['id'])||empty($event['type']))throw new RuntimeException('Invalid webhook payload.');
        return $event;
    }
}
