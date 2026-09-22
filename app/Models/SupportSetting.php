<?php
namespace Pterodactyl\Models;
class SupportSetting extends Model {
 protected $table='nodexa_support_settings';
 protected $fillable=['key','value'];
 public static function value(string $key,$default=null){return static::query()->where('key',$key)->value('value')??$default;}
 public static function put(string $key,$value): void {static::query()->updateOrCreate(['key'=>$key],['value'=>$value]);}
}