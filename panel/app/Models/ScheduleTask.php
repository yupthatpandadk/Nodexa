<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduleTask extends Model
{
    protected $fillable = ['schedule_id','sequence','action','payload','time_offset','continue_on_failure'];
    protected $casts = ['sequence'=>'integer','time_offset'=>'integer','continue_on_failure'=>'boolean'];
    public function schedule() { return $this->belongsTo(Schedule::class); }
}
