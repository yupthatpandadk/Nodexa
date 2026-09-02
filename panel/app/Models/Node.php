<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Node extends Model
{
    protected $fillable = [
        'name','fqdn','scheme','daemon_port','sftp_port','token','memory_mb','disk_mb','location','setup_options',
        'health_status','health_latency_ms','health_last_checked_at','health_last_seen_at','health_message',
        'agent_version','agent_api_version','agent_hostname','agent_started_at',
        'host_memory_total_bytes','host_memory_available_bytes','host_disk_total_bytes','host_disk_free_bytes',
        'host_load_1','host_load_5','host_load_15','host_cpu_count','host_uptime_seconds','metrics_updated_at',
    ];
    protected $hidden = ['token'];
    protected $casts = [
        'daemon_port'=>'integer',
        'sftp_port'=>'integer',
        'memory_mb'=>'integer',
        'disk_mb'=>'integer',
        'setup_options'=>'array',
        'health_latency_ms'=>'integer',
        'health_last_checked_at'=>'datetime',
        'health_last_seen_at'=>'datetime',
        'agent_api_version'=>'integer',
        'agent_started_at'=>'datetime',
        'host_memory_total_bytes'=>'integer',
        'host_memory_available_bytes'=>'integer',
        'host_disk_total_bytes'=>'integer',
        'host_disk_free_bytes'=>'integer',
        'host_load_1'=>'float',
        'host_load_5'=>'float',
        'host_load_15'=>'float',
        'host_cpu_count'=>'integer',
        'host_uptime_seconds'=>'integer',
        'metrics_updated_at'=>'datetime',
    ];

    public function servers() { return $this->hasMany(Server::class); }
    public function databaseHosts() { return $this->hasMany(DatabaseHost::class); }
    public function allocations() { return $this->hasMany(Allocation::class); }
}
