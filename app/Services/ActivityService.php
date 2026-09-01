<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use Throwable;

final class ActivityService
{
    public static function log(string $action,?string $entityType=null,?int $entityId=null,?string $description=null): void
    {
        try{Database::connection()->prepare('INSERT INTO activity_logs (user_id,action,entity_type,entity_id,description,ip_address) VALUES (:user,:action,:type,:entity,:description,:ip)')->execute(['user'=>Auth::user()['id']??null,'action'=>substr($action,0,120),'type'=>$entityType,'entity'=>$entityId,'description'=>$description?substr($description,0,255):null,'ip'=>substr((string)($_SERVER['REMOTE_ADDR']??''),0,45)?:null]);}catch(Throwable){}
    }
}
