<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    protected $fillable = ['server_id','name','mode','cron_minute','cron_hour','cron_day_of_month','cron_month','cron_day_of_week','timezone','enabled','only_when_online','last_run_at','next_run_at'];
    protected $casts = ['enabled'=>'boolean','only_when_online'=>'boolean','last_run_at'=>'datetime','next_run_at'=>'datetime'];
    public function server() { return $this->belongsTo(Server::class); }
    public function tasks() { return $this->hasMany(ScheduleTask::class)->orderBy('sequence'); }
}
