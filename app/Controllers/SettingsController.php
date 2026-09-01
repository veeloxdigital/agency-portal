<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Env;
use App\Core\Setting;
use App\Core\View;
use App\Services\ActivityService;
use App\Services\MailService;
use PDO;
use Throwable;

final class SettingsController
{
    public function index(): void
    {
        $db=Database::connection();$settings=[];foreach($db->query('SELECT setting_key,setting_value FROM settings')->fetchAll() as $row)$settings[$row['setting_key']]=$row['setting_value'];
        $staff=$db->query("SELECT users.id,users.name,users.email,users.status,users.last_login_at,users.created_at,roles.slug role FROM users INNER JOIN roles ON roles.id=users.role_id WHERE roles.slug IN ('admin','staff') ORDER BY users.name")->fetchAll();
        $departments=$db->query("SELECT ticket_departments.*,(SELECT COUNT(*) FROM tickets WHERE tickets.department_id=ticket_departments.id) ticket_count FROM ticket_departments ORDER BY ticket_departments.name")->fetchAll();
        $activity=$db->query('SELECT activity_logs.*,users.name user_name FROM activity_logs LEFT JOIN users ON users.id=activity_logs.user_id ORDER BY activity_logs.created_at DESC LIMIT 150')->fetchAll();
        View::render('settings/index',['title'=>'Settings','settings'=>$settings,'staff'=>$staff,'departments'=>$departments,'activity'=>$activity,'health'=>$this->health(),'flash'=>$this->pullFlash(),'temporaryPassword'=>$_SESSION['_staff_password']??null]);unset($_SESSION['_staff_password']);
    }

    public function save(): never
    {
        $allowed=['agency_name','agency_email','agency_phone','agency_address','currency','invoice_prefix','invoice_due_days','invoice_footer','maintenance_mode'];$values=[];foreach($allowed as $key)$values[$key]=trim((string)($_POST[$key]??''));$values['agency_name']=$values['agency_name']?:'Veelox Digital';$values['currency']=in_array(strtoupper($values['currency']),['GBP','EUR','USD'],true)?strtoupper($values['currency']):'GBP';$values['invoice_prefix']=strtoupper((string)preg_replace('/[^A-Z0-9]/i','',$values['invoice_prefix']))?:'INV';$values['invoice_due_days']=(string)max(1,min(365,(int)$values['invoice_due_days']));$values['maintenance_mode']=isset($_POST['maintenance_mode'])?'1':'0';if($values['agency_email']!==''&&!filter_var($values['agency_email'],FILTER_VALIDATE_EMAIL)){$this->flash('error','Enter a valid agency email address.');$this->redirect('/settings');}
        $values['currency']='GBP';$logo=$this->logo($_FILES['agency_logo']??null);if(isset($logo['error'])){$this->flash('error',$logo['error']);$this->redirect('/settings');}if(isset($logo['path']))$values['agency_logo']=$logo['path'];$db=Database::connection();$s=$db->prepare('INSERT INTO settings (setting_key,setting_value,is_secret) VALUES (:key,:value,0) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');foreach($values as $key=>$value)$s->execute(['key'=>$key,'value'=>$value]);Setting::clear();ActivityService::log('settings.updated','settings',null,'Agency and invoice settings updated');$this->flash('success','Settings saved successfully.');$this->redirect('/settings');
    }

    public function createStaff(): never
    {
        $name=trim((string)($_POST['name']??''));$email=strtolower(trim((string)($_POST['email']??'')));$role=in_array($_POST['role']??'', ['admin','staff'],true)?$_POST['role']:'staff';if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL)){$this->flash('error','A name and valid email address are required.');$this->redirect('/settings#staff');}$password=$this->password();$db=Database::connection();try{$roleId=$db->prepare('SELECT id FROM roles WHERE slug=:role');$roleId->execute(['role'=>$role]);$db->prepare("INSERT INTO users (role_id,name,email,password_hash,status) VALUES (:role,:name,:email,:password,'active')")->execute(['role'=>$roleId->fetchColumn(),'name'=>$name,'email'=>$email,'password'=>password_hash($password,PASSWORD_DEFAULT)]);$id=(int)$db->lastInsertId();$_SESSION['_staff_password']=$password;(new MailService())->sendRaw($email,'Your Veelox Digital staff account','<h1>Your staff account is ready</h1><p>Email: <strong>'.htmlspecialchars($email).'</strong><br>Temporary password: <strong>'.htmlspecialchars($password).'</strong></p><p><a href="'.htmlspecialchars(rtrim((string)Env::get('APP_URL',''),'/').'/login').'">Sign in</a></p><p>Please ask an administrator to reset this password if it is shared insecurely.</p>');ActivityService::log('staff.created','user',$id,$name.' added as '.$role);$this->flash('success','Staff account created. Copy the temporary password now.');}catch(Throwable){$this->flash('error','That email is already used or the account could not be created.');}$this->redirect('/settings#staff');
    }

    public function updateStaff(string $id): never
    {
        $target=(int)$id;$current=(int)(Auth::user()['id']??0);$db=Database::connection();$s=$db->prepare("SELECT users.*,roles.slug role FROM users INNER JOIN roles ON roles.id=users.role_id WHERE users.id=:id AND roles.slug IN ('admin','staff')");$s->execute(['id'=>$target]);$user=$s->fetch();if(!$user){http_response_code(404);exit('Staff account not found.');}$role=in_array($_POST['role']??'', ['admin','staff'],true)?$_POST['role']:$user['role'];$status=in_array($_POST['status']??'', ['active','suspended'],true)?$_POST['status']:$user['status'];if($target===$current&&$status!=='active'){$this->flash('error','You cannot suspend your own account.');$this->redirect('/settings#staff');}if($user['role']==='admin'&&($role!=='admin'||$status!=='active')&&$this->activeAdmins($db)<=1){$this->flash('error','The final active administrator cannot be removed.');$this->redirect('/settings#staff');}$rs=$db->prepare('SELECT id FROM roles WHERE slug=:role');$rs->execute(['role'=>$role]);$db->prepare('UPDATE users SET role_id=:role,status=:status WHERE id=:id')->execute(['role'=>$rs->fetchColumn(),'status'=>$status,'id'=>$target]);ActivityService::log('staff.updated','user',$target,$user['name'].' changed to '.$role.' / '.$status);$this->flash('success','Staff permissions updated.');$this->redirect('/settings#staff');
    }

    public function resetStaff(string $id): never
    {
        $db=Database::connection();$s=$db->prepare("SELECT users.id,users.name,users.email FROM users INNER JOIN roles ON roles.id=users.role_id WHERE users.id=:id AND roles.slug IN ('admin','staff')");$s->execute(['id'=>$id]);$user=$s->fetch();if(!$user){http_response_code(404);exit('Staff account not found.');}$password=$this->password();$db->prepare('UPDATE users SET password_hash=:password,status=\'active\' WHERE id=:id')->execute(['password'=>password_hash($password,PASSWORD_DEFAULT),'id'=>$id]);$_SESSION['_staff_password']=$password;(new MailService())->sendRaw($user['email'],'Your Veelox Digital password was reset','<h1>Your temporary password</h1><p>Your administrator reset your account password.</p><p><strong>'.htmlspecialchars($password).'</strong></p><p><a href="'.htmlspecialchars(rtrim((string)Env::get('APP_URL',''),'/').'/login').'">Sign in</a></p>');ActivityService::log('staff.password_reset','user',(int)$id,$user['name'].' password reset');$this->flash('success','Temporary password generated. Copy it now.');$this->redirect('/settings#staff');
    }

    public function department(): never
    {
        $name=trim((string)($_POST['name']??''));$email=trim((string)($_POST['email']??''));if($name===''||($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))){$this->flash('error','Enter a department name and an optional valid email.');$this->redirect('/settings#support');}$db=Database::connection();$db->prepare("INSERT INTO ticket_departments (name,email,status) VALUES (:name,:email,'active')")->execute(['name'=>$name,'email'=>$email?:null]);$id=(int)$db->lastInsertId();ActivityService::log('department.created','ticket_department',$id,$name.' created');$this->flash('success','Support department created.');$this->redirect('/settings#support');
    }

    public function updateDepartment(string $id): never
    {
        $name=trim((string)($_POST['name']??''));$email=trim((string)($_POST['email']??''));$status=in_array($_POST['status']??'', ['active','inactive'],true)?$_POST['status']:'active';if($name===''||($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))){$this->flash('error','Enter valid department details.');$this->redirect('/settings#support');}Database::connection()->prepare('UPDATE ticket_departments SET name=:name,email=:email,status=:status WHERE id=:id')->execute(['name'=>$name,'email'=>$email?:null,'status'=>$status,'id'=>$id]);ActivityService::log('department.updated','ticket_department',(int)$id,$name.' changed to '.$status);$this->flash('success','Department updated.');$this->redirect('/settings#support');
    }

    public function backup(): never
    {
        ActivityService::log('database.backup','system',null,'Database backup downloaded');$db=Database::connection();header('Content-Type: application/sql; charset=UTF-8');header('Content-Disposition: attachment; filename="veelox-backup-'.date('Y-m-d-His').'.sql"');echo "SET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\n\n";$tables=$db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);foreach($tables as $table){$safe=str_replace('`','``',(string)$table);$create=$db->query('SHOW CREATE TABLE `'.$safe.'`')->fetch(PDO::FETCH_NUM);echo 'DROP TABLE IF EXISTS `'.$safe."`;\n".$create[1].";\n";$rows=$db->query('SELECT * FROM `'.$safe.'`');while($row=$rows->fetch(PDO::FETCH_ASSOC)){echo 'INSERT INTO `'.$safe.'` (`'.implode('`,`',array_map(static fn($v)=>str_replace('`','``',(string)$v),array_keys($row))).'`) VALUES ('.implode(',',array_map(static fn($v)=>$v===null?'NULL':Database::connection()->quote((string)$v),array_values($row))).");\n";}echo "\n";}echo "SET FOREIGN_KEY_CHECKS=1;\n";exit;
    }

    private function health(): array{return[['label'=>'PHP 8.2 or newer','ok'=>version_compare(PHP_VERSION,'8.2.0','>=')],['label'=>'PDO MySQL and Fileinfo extensions','ok'=>extension_loaded('pdo_mysql')&&extension_loaded('fileinfo')],['label'=>'Database update 010 applied','ok'=>$this->migrationReady()],['label'=>'Secure HTTPS application URL','ok'=>str_starts_with((string)Env::get('APP_URL',''),'https://')],['label'=>'Production debug mode disabled','ok'=>!Env::get('APP_DEBUG',false)],['label'=>'Environment file writable','ok'=>is_writable(BASE_PATH.'/.env')],['label'=>'Storage directory writable','ok'=>is_writable(BASE_PATH.'/storage')],['label'=>'SMTP configured','ok'=>(bool)Env::get('MAIL_HOST','')&&(bool)Env::get('MAIL_FROM_ADDRESS','')],['label'=>'Stripe webhook configured','ok'=>(bool)Env::get('STRIPE_SECRET_KEY','')&&(bool)Env::get('STRIPE_WEBHOOK_SECRET','')]];}
    private function migrationReady(): bool{try{$s=Database::connection()->prepare('SELECT COUNT(*) FROM migrations WHERE migration=:migration');$s->execute(['migration'=>'010_production.sql']);return(bool)$s->fetchColumn();}catch(Throwable){return false;}}
    private function activeAdmins(PDO $db): int{return(int)$db->query("SELECT COUNT(*) FROM users INNER JOIN roles ON roles.id=users.role_id WHERE roles.slug='admin' AND users.status='active'")->fetchColumn();}
    private function password(): string{return substr(strtr(base64_encode(random_bytes(18)),'+/','-_'),0,20);}
    private function logo(?array $file): array{if(!$file||($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)return[];if($file['error']!==UPLOAD_ERR_OK)return['error'=>'The logo upload failed.'];if((int)$file['size']>2*1024*1024)return['error'=>'The logo must be 2MB or smaller.'];$mime=(new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);$extensions=['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'];if(!isset($extensions[$mime]))return['error'=>'Use a PNG, JPG or WebP logo.'];$directory=BASE_PATH.'/public/uploads/branding';if(!is_dir($directory)&&!mkdir($directory,0755,true)&&!is_dir($directory))return['error'=>'The branding upload directory is not writable.'];$name='agency-logo-'.substr(bin2hex(random_bytes(8)),0,12).'.'.$extensions[$mime];if(!move_uploaded_file($file['tmp_name'],$directory.'/'.$name))return['error'=>'The logo could not be saved.'];return['path'=>'/uploads/branding/'.$name];}
    private function flash(string $type,string $message):void{$_SESSION['_flash']=compact('type','message');}private function pullFlash():?array{$f=$_SESSION['_flash']??null;unset($_SESSION['_flash']);return$f;}private function redirect(string $url):never{header('Location: '.$url);exit;}
}
