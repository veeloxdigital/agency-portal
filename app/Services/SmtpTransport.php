<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use RuntimeException;

final class SmtpTransport
{
    private $socket;

    public function send(string $to,string $name,string $subject,string $html): void
    {
        $host=(string)Env::get('MAIL_HOST','');$port=(int)Env::get('MAIL_PORT','587');$encryption=strtolower((string)Env::get('MAIL_ENCRYPTION','tls'));
        $username=(string)Env::get('MAIL_USERNAME','');$password=(string)Env::get('MAIL_PASSWORD','');$from=(string)Env::get('MAIL_FROM_ADDRESS',$username);$fromName=(string)Env::get('MAIL_FROM_NAME','Veelox Digital');
        if(!$host||!$from||!filter_var($to,FILTER_VALIDATE_EMAIL))throw new RuntimeException('SMTP settings or recipient are incomplete.');
        $target=($encryption==='ssl'?'ssl://':'').$host.':'.$port;$this->socket=@stream_socket_client($target,$errno,$error,20,STREAM_CLIENT_CONNECT);
        if(!$this->socket)throw new RuntimeException('SMTP connection failed: '.$error);stream_set_timeout($this->socket,20);$this->expect([220]);
        $hostname=$_SERVER['SERVER_NAME']??'localhost';$this->command('EHLO '.$hostname,[250]);
        if($encryption==='tls'){$this->command('STARTTLS',[220]);if(!stream_socket_enable_crypto($this->socket,true,STREAM_CRYPTO_METHOD_TLS_CLIENT))throw new RuntimeException('Could not start SMTP encryption.');$this->command('EHLO '.$hostname,[250]);}
        if($username!==''){$this->command('AUTH LOGIN',[334]);$this->command(base64_encode($username),[334]);$this->command(base64_encode($password),[235]);}
        $this->command('MAIL FROM:<'.$from.'>',[250]);$this->command('RCPT TO:<'.$to.'>',[250,251]);$this->command('DATA',[354]);
        $boundary='=_Veelox_'.bin2hex(random_bytes(12));$plain=trim(html_entity_decode(strip_tags(preg_replace('/<br\s*\/?>/i',"\n",$html)),ENT_QUOTES|ENT_HTML5,'UTF-8'));
        $headers=['Date: '.date(DATE_RFC2822),'From: '.$this->encode($fromName).' <'.$from.'>','To: '.$this->encode($name).' <'.$to.'>','Subject: '.$this->encode($subject),'Message-ID: <'.bin2hex(random_bytes(12)).'@'.$hostname.'>','MIME-Version: 1.0','Content-Type: multipart/alternative; boundary="'.$boundary.'"'];
        $message=implode("\r\n",$headers)."\r\n\r\n--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n".quoted_printable_encode($plain)."\r\n--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n".quoted_printable_encode($html)."\r\n--{$boundary}--";
        $message=preg_replace('/(?m)^\./','..',$message)."\r\n.";fwrite($this->socket,$message."\r\n");$this->expect([250]);$this->command('QUIT',[221]);fclose($this->socket);
    }

    private function command(string $command,array $codes): void{fwrite($this->socket,$command."\r\n");$this->expect($codes);}
    private function expect(array $codes): string{$response='';while(($line=fgets($this->socket,515))!==false){$response.=$line;if(strlen($line)<4||$line[3]!=='-')break;}$code=(int)substr($response,0,3);if(!in_array($code,$codes,true))throw new RuntimeException('SMTP error: '.trim($response));return $response;}
    private function encode(string $value): string{return preg_match('/[^\x20-\x7E]/',$value)?'=?UTF-8?B?'.base64_encode($value).'?=':str_replace(["\r","\n"],'',$value);}
}
