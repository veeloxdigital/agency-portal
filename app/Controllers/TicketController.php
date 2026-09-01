<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Services\MailService;
use App\Services\ActivityService;
use PDO;
use Throwable;

final class TicketController
{
    public function index(): void { $this->listing(false); }
    public function portalIndex(): void { $this->listing(true); }

    private function listing(bool $portal): void
    {
        $db=Database::connection();$search=trim((string)($_GET['search']??''));$status=trim((string)($_GET['status']??''));$where=[];$params=[];
        if($portal){$where[]='customers.user_id=:user';$params['user']=Auth::user()['id'];}
        if($search!==''){$where[]='(tickets.ticket_number LIKE :search OR tickets.subject LIKE :search OR customers.company_name LIKE :search OR customers.contact_name LIKE :search)';$params['search']='%'.$search.'%';}
        if(in_array($status,['open','customer_reply','staff_reply','resolved','closed'],true)){$where[]='tickets.status=:status';$params['status']=$status;}
        $replyVisibility=$portal?' AND ticket_replies.is_internal_note=0':'';$sql='SELECT tickets.*,customers.account_number,customers.company_name,customers.contact_name,ticket_departments.name AS department_name,users.name AS assignee_name,(SELECT COUNT(*) FROM ticket_replies WHERE ticket_replies.ticket_id=tickets.id'.$replyVisibility.') AS reply_count FROM tickets INNER JOIN customers ON customers.id=tickets.customer_id INNER JOIN ticket_departments ON ticket_departments.id=tickets.department_id LEFT JOIN users ON users.id=tickets.assigned_to';if($where)$sql.=' WHERE '.implode(' AND ',$where);$sql.=' ORDER BY COALESCE(tickets.last_reply_at,tickets.created_at) DESC';$s=$db->prepare($sql);$s->execute($params);
        View::render('tickets/index',['title'=>$portal?'My Support Tickets':'Support Tickets','tickets'=>$s->fetchAll(),'portal'=>$portal,'search'=>$search,'status'=>$status,'flash'=>$this->pullFlash()]);
    }

    public function create(): void { $this->createForm(false); }
    public function portalCreate(): void { $this->createForm(true); }
    private function createForm(bool $portal): void
    {
        View::render('tickets/create',['title'=>'Open support ticket','portal'=>$portal,'departments'=>$this->departments(),'customers'=>$portal?[]:$this->customers(),'ticket'=>$_SESSION['_old_ticket']??['customer_id'=>'','department_id'=>'','subject'=>'','priority'=>'normal','message'=>''],'errors'=>$_SESSION['_ticket_errors']??[]]);unset($_SESSION['_old_ticket'],$_SESSION['_ticket_errors']);
    }

    public function store(): never { $this->saveNew(false); }
    public function portalStore(): never { $this->saveNew(true); }
    private function saveNew(bool $portal): never
    {
        $customerId=$portal?$this->portalCustomerId():(int)($_POST['customer_id']??0);$department=(int)($_POST['department_id']??0);$subject=trim((string)($_POST['subject']??''));$message=trim((string)($_POST['message']??''));$priority=in_array($_POST['priority']??'', ['low','normal','high','urgent'],true)?$_POST['priority']:'normal';$errors=[];
        if(!$customerId)$errors[]='Select a customer.';if(!$department)$errors[]='Select a department.';if($subject==='')$errors[]='Enter a subject.';if($message==='')$errors[]='Enter a message.';$upload=$this->validateUpload($_FILES['attachment']??null);if(isset($upload['error']))$errors[]=$upload['error'];
        if($errors){$_SESSION['_old_ticket']=['customer_id'=>$customerId,'department_id'=>$department,'subject'=>$subject,'priority'=>$priority,'message'=>$message];$_SESSION['_ticket_errors']=$errors;$this->redirect($portal?'/portal/tickets/create':'/tickets/create');}
        $db=Database::connection();try{$db->beginTransaction();$number=$this->nextNumber($db);$db->prepare("INSERT INTO tickets (ticket_number,customer_id,department_id,subject,priority,status,last_reply_at) VALUES (:number,:customer,:department,:subject,:priority,'open',NOW())")->execute(['number'=>$number,'customer'=>$customerId,'department'=>$department,'subject'=>$subject,'priority'=>$priority]);$ticketId=(int)$db->lastInsertId();$db->prepare('INSERT INTO ticket_replies (ticket_id,user_id,message,is_internal_note) VALUES (:ticket,:user,:message,0)')->execute(['ticket'=>$ticketId,'user'=>Auth::user()['id'],'message'=>$message]);$replyId=(int)$db->lastInsertId();if($upload)$this->storeUpload($db,$ticketId,$replyId,$upload);$db->commit();(new MailService())->ticket($ticketId,'ticket_created');$this->flash('success',$number.' was opened successfully.');$this->redirect(($portal?'/portal/tickets/':'/tickets/').$ticketId);}catch(Throwable){if($db->inTransaction())$db->rollBack();$this->flash('error','The support ticket could not be created.');$this->redirect($portal?'/portal/tickets/create':'/tickets/create');}
    }

    public function show(string $id): void { $this->showTicket((int)$id,false); }
    public function portalShow(string $id): void { $this->showTicket((int)$id,true); }
    private function showTicket(int $id,bool $portal): void
    {
        $ticket=$this->find($id,$portal);$db=Database::connection();$s=$db->prepare('SELECT ticket_replies.*,users.name,roles.slug AS role FROM ticket_replies INNER JOIN users ON users.id=ticket_replies.user_id INNER JOIN roles ON roles.id=users.role_id WHERE ticket_replies.ticket_id=:id '.($portal?'AND ticket_replies.is_internal_note=0 ':'').'ORDER BY ticket_replies.created_at,ticket_replies.id');$s->execute(['id'=>$id]);$replies=$s->fetchAll();$a=$db->prepare('SELECT * FROM ticket_attachments WHERE ticket_id=:id ORDER BY created_at');$a->execute(['id'=>$id]);$attachments=$a->fetchAll();
        View::render('tickets/show',['title'=>$ticket['ticket_number'],'ticket'=>$ticket,'replies'=>$replies,'attachments'=>$attachments,'portal'=>$portal,'staff'=>$portal?[]:$this->staff(),'departments'=>$this->departments(),'flash'=>$this->pullFlash()]);
    }

    public function reply(string $id): never { $this->saveReply((int)$id,false); }
    public function portalReply(string $id): never { $this->saveReply((int)$id,true); }
    private function saveReply(int $id,bool $portal): never
    {
        $ticket=$this->find($id,$portal);$message=trim((string)($_POST['message']??''));$internal=!$portal&&isset($_POST['internal_note']);$upload=$this->validateUpload($_FILES['attachment']??null);if($message===''||isset($upload['error'])){$this->flash('error',$message===''?'Enter a reply.':$upload['error']);$this->redirect(($portal?'/portal/tickets/':'/tickets/').$id);}
        $db=Database::connection();try{$db->beginTransaction();$db->prepare('INSERT INTO ticket_replies (ticket_id,user_id,message,is_internal_note) VALUES (:ticket,:user,:message,:internal)')->execute(['ticket'=>$id,'user'=>Auth::user()['id'],'message'=>$message,'internal'=>$internal?1:0]);$replyId=(int)$db->lastInsertId();if($upload)$this->storeUpload($db,$id,$replyId,$upload);$status=$internal?$ticket['status']:($portal?'customer_reply':'staff_reply');$db->prepare('UPDATE tickets SET status=:status,last_reply_at=NOW() WHERE id=:id')->execute(['status'=>$status,'id'=>$id]);$db->commit();if(!$internal&&!$portal)(new MailService())->ticket($id,'ticket_reply');$this->flash('success',$internal?'Private note added.':'Reply sent.');}catch(Throwable){if($db->inTransaction())$db->rollBack();$this->flash('error','The reply could not be saved.');}$this->redirect(($portal?'/portal/tickets/':'/tickets/').$id);
    }

    public function update(string $id): never
    {
        $ticket=$this->find((int)$id,false);$status=in_array($_POST['status']??'', ['open','customer_reply','staff_reply','resolved','closed'],true)?$_POST['status']:$ticket['status'];$priority=in_array($_POST['priority']??'', ['low','normal','high','urgent'],true)?$_POST['priority']:$ticket['priority'];$assigned=(int)($_POST['assigned_to']??0)?:null;$department=(int)($_POST['department_id']??0)?:$ticket['department_id'];$closed=$status==='closed'?date('Y-m-d H:i:s'):null;Database::connection()->prepare('UPDATE tickets SET status=:status,priority=:priority,assigned_to=:assigned,department_id=:department,closed_at=:closed WHERE id=:id')->execute(['status'=>$status,'priority'=>$priority,'assigned'=>$assigned,'department'=>$department,'closed'=>$closed,'id'=>$ticket['id']]);ActivityService::log('ticket.updated','ticket',(int)$ticket['id'],$ticket['ticket_number'].' changed to '.$status);$this->flash('success','Ticket settings updated.');$this->redirect('/tickets/'.$ticket['id']);
    }

    public function attachment(string $id): never
    {
        $db=Database::connection();$s=$db->prepare('SELECT ticket_attachments.*,tickets.customer_id,customers.user_id,ticket_replies.is_internal_note FROM ticket_attachments INNER JOIN tickets ON tickets.id=ticket_attachments.ticket_id INNER JOIN customers ON customers.id=tickets.customer_id LEFT JOIN ticket_replies ON ticket_replies.id=ticket_attachments.reply_id WHERE ticket_attachments.id=:id');$s->execute(['id'=>$id]);$file=$s->fetch();if(!$file){http_response_code(404);exit('Attachment not found.');}$role=Auth::user()['role']??'';if($role==='customer'&&((int)$file['user_id']!==(int)Auth::user()['id']||!empty($file['is_internal_note']))){http_response_code(404);exit('Attachment not found.');}$path=BASE_PATH.'/storage/uploads/tickets/'.$file['stored_name'];if(!is_file($path)){http_response_code(404);exit('File missing.');}header('Content-Type: '.$file['mime_type']);header('Content-Length: '.$file['file_size']);header('Content-Disposition: attachment; filename="'.str_replace(['"',"\r","\n"],'',(string)$file['original_name']).'"');readfile($path);exit;
    }

    private function find(int $id,bool $portal): array{$sql='SELECT tickets.*,customers.account_number,customers.company_name,customers.contact_name,customers.email,customers.user_id,ticket_departments.name AS department_name,users.name AS assignee_name FROM tickets INNER JOIN customers ON customers.id=tickets.customer_id INNER JOIN ticket_departments ON ticket_departments.id=tickets.department_id LEFT JOIN users ON users.id=tickets.assigned_to WHERE tickets.id=:id';$params=['id'=>$id];if($portal){$sql.=' AND customers.user_id=:user';$params['user']=Auth::user()['id'];}$s=Database::connection()->prepare($sql);$s->execute($params);$ticket=$s->fetch();if(!$ticket){http_response_code(404);exit('Ticket not found.');}return $ticket;}
    private function portalCustomerId(): int{$s=Database::connection()->prepare('SELECT id FROM customers WHERE user_id=:user');$s->execute(['user'=>Auth::user()['id']]);return (int)($s->fetchColumn()?:0);}
    private function departments(): array{return Database::connection()->query("SELECT * FROM ticket_departments WHERE status='active' ORDER BY name")->fetchAll();}private function customers(): array{return Database::connection()->query("SELECT id,account_number,company_name,contact_name FROM customers WHERE status='active' ORDER BY COALESCE(company_name,contact_name)")->fetchAll();}private function staff(): array{return Database::connection()->query("SELECT users.id,users.name FROM users INNER JOIN roles ON roles.id=users.role_id WHERE users.status='active' AND roles.slug IN ('admin','staff') ORDER BY users.name")->fetchAll();}
    private function nextNumber(PDO $db): string{$prefix='TKT-'.date('Y').'-';$s=$db->prepare('SELECT ticket_number FROM tickets WHERE ticket_number LIKE :prefix ORDER BY id DESC LIMIT 1 FOR UPDATE');$s->execute(['prefix'=>$prefix.'%']);$last=(string)($s->fetchColumn()?:'');return $prefix.str_pad((string)($last?((int)substr($last,-4))+1:1),4,'0',STR_PAD_LEFT);}
    private function validateUpload(?array $file): array{if(!$file||($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)return [];if($file['error']!==UPLOAD_ERR_OK)return ['error'=>'The attachment upload failed.'];if((int)$file['size']>5*1024*1024)return ['error'=>'Attachments must be 5MB or smaller.'];$mime=(new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);$allowed=['image/jpeg','image/png','image/gif','application/pdf','text/plain','application/zip'];if(!in_array($mime,$allowed,true))return ['error'=>'Allowed attachments: JPG, PNG, GIF, PDF, TXT and ZIP.'];return ['tmp'=>$file['tmp_name'],'name'=>substr(basename((string)$file['name']),0,255),'mime'=>$mime,'size'=>(int)$file['size'],'stored'=>bin2hex(random_bytes(20))];}
    private function storeUpload(PDO $db,int $ticketId,int $replyId,array $upload): void{$directory=BASE_PATH.'/storage/uploads/tickets';if(!is_dir($directory)&&!mkdir($directory,0750,true)&&!is_dir($directory))throw new \RuntimeException('Attachment directory could not be created.');$path=$directory.'/'.$upload['stored'];if(!move_uploaded_file($upload['tmp'],$path))throw new \RuntimeException('Attachment could not be stored.');$db->prepare('INSERT INTO ticket_attachments (ticket_id,reply_id,original_name,stored_name,mime_type,file_size) VALUES (:ticket,:reply,:name,:stored,:mime,:size)')->execute(['ticket'=>$ticketId,'reply'=>$replyId,'name'=>$upload['name'],'stored'=>$upload['stored'],'mime'=>$upload['mime'],'size'=>$upload['size']]);}
    private function flash(string $type,string $message):void{$_SESSION['_flash']=compact('type','message');}private function pullFlash():?array{$f=$_SESSION['_flash']??null;unset($_SESSION['_flash']);return $f;}private function redirect(string $url):never{header('Location: '.$url);exit;}
}
