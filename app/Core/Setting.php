<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

final class Setting
{
    private static ?array $values=null;
    public static function get(string $key,mixed $default=null): mixed{if(self::$values===null){try{self::$values=[];foreach(Database::connection()->query('SELECT setting_key,setting_value FROM settings')->fetchAll() as $row)self::$values[$row['setting_key']]=$row['setting_value'];}catch(Throwable){self::$values=[];}}$value=self::$values[$key]??null;return$value===null||$value===''?$default:$value;}
    public static function clear(): void{self::$values=null;}
}
