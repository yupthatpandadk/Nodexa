<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SystemIssue extends Model
{
    protected $fillable = [
        'fingerprint','severity','source','type','title','message','node_id','server_id',
        'context','status','occurrences','first_seen_at','last_seen_at','resolved_at',
    ];

    protected $casts = [
        'context' => 'array',
        'occurrences' => 'integer',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public static function report(
        string $source,
        string $title,
        ?string $message = null,
        string $severity = 'error',
        ?string $type = null,
        ?int $nodeId = null,
        ?string $serverId = null,
        array $context = []
    ): self {
        $fingerprint = hash('sha256', implode('|', [
            $source,
            $type ?? '',
            $title,
            $nodeId ?? '',
            $serverId ?? '',
            Str::limit((string) $message, 500),
        ]));

        $issue = self::where('fingerprint', $fingerprint)->first();
        if ($issue) {
            $issue->update([
                'severity' => $severity,
                'message' => $message,
                'context' => $context,
                'status' => 'open',
                'occurrences' => $issue->occurrences + 1,
                'last_seen_at' => now(),
                'resolved_at' => null,
            ]);
            return $issue->fresh();
        }

        return self::create([
            'fingerprint' => $fingerprint,
            'severity' => $severity,
            'source' => $source,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'node_id' => $nodeId,
            'server_id' => $serverId,
            'context' => $context,
            'status' => 'open',
            'occurrences' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
    }

    public function resolveIssue(): void
    {
        $this->update(['status' => 'resolved', 'resolved_at' => now()]);
    }
}
