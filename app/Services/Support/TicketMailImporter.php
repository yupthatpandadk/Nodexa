<?php

namespace Pterodactyl\Services\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Pterodactyl\Models\SupportSetting;
use Pterodactyl\Models\SupportTicket;

/** Mail replies require the private reply address AND the ticket owner's sender address. */
class TicketMailImporter
{
    public function __construct(private TicketWorkflow $workflow)
    {
    }

    public function import(string $key, string $sender, array $recipients, string $body, array $files = []): string
    {
        return DB::transaction(function () use ($key, $sender, $recipients, $body, $files) {
            // A unique claim makes retries/concurrent imports idempotent, including rejected mail.
            $claimed = DB::table('nodexa_support_mail_imports')->insertOrIgnore(['message_key' => $key, 'sender' => mb_substr($sender, 0, 255), 'status' => 'processing', 'created_at' => now(), 'updated_at' => now()]);
            if (!$claimed) {
                return 'duplicate';
            }
            $reject = function (string $reason) use ($key) {
                DB::table('nodexa_support_mail_imports')->where('message_key', $key)->update(['status' => 'rejected', 'reason' => $reason, 'updated_at' => now()]);
                return 'rejected';
            };
            $address = strtolower((string) config('support.mail.address'));
            if (!filter_var($address, FILTER_VALIDATE_EMAIL)) {
                return $reject('Mail reply address is not configured');
            }
            [$local, $domain] = explode('@', $address, 2);
            $tokens = [];
            foreach ($recipients as $recipient) {
                if (preg_match('/^' . preg_quote($local, '/') . '\+([a-zA-Z0-9]{40})@' . preg_quote($domain, '/') . '$/i', $recipient, $m)) {
                    $tokens[] = $m[1];
                }
            }
            if (count(array_unique($tokens)) !== 1) {
                return $reject('Missing or ambiguous private reply address');
            }
            $ticket = SupportTicket::where('reply_token', $tokens[0])->lockForUpdate()->first();
            if (!$ticket || !hash_equals((string) $ticket->reply_token, $tokens[0])) {
                return $reject('Unknown reply address');
            }
            // Merged tickets keep their reply token so replies to old email still reach the conversation.
            $visited = [];
            while ($ticket->merged_into_id) {
                if (isset($visited[$ticket->id])) {
                    return $reject('Invalid merge chain');
                }
                $visited[$ticket->id] = true;
                $ticket = SupportTicket::whereKey($ticket->merged_into_id)->lockForUpdate()->firstOrFail();
            }
            $ticket->load('user');
            if (!$ticket->user || !filter_var($sender, FILTER_VALIDATE_EMAIL) || strcasecmp(trim($ticket->user->email), trim($sender)) !== 0) {
                return $reject('Sender is not the ticket owner');
            }
            if ($ticket->status === 'closed' && SupportSetting::value('allow_reopen', '1') !== '1') {
                return $reject('Ticket is closed; reopening disabled');
            }
            $body = trim(preg_split('/^\s*>*\s*--- Reply above this line ---/m', str_replace("\r\n", "\n", $body), 2)[0]);
            if (Validator::make(['message' => $body, 'attachments' => $files], ['message' => 'required|string|min:2|max:20000', ...TicketWorkflow::FILE_RULES])->fails()) {
                return $reject('Message or attachment failed validation');
            }
            $this->workflow->reply($ticket, $ticket->user, $body, files: $files, reopen: true);
            DB::table('nodexa_support_mail_imports')->where('message_key', $key)->update(['status' => 'imported', 'ticket_id' => $ticket->id, 'updated_at' => now()]);
            return 'imported';
        });
    }

    /** Decode MIME leaf parts. Only validated attachments are persisted by the workflow. */
    public function decode($imap, int $uid, object $part, string $number = ''): array
    {
        if (isset($part->parts)) {
            $result = ['plain' => '', 'html' => '', 'files' => []];
            foreach ($part->parts as $i => $child) {
                $decoded = $this->decode($imap, $uid, $child, $number === '' ? (string) ($i + 1) : $number . '.' . ($i + 1));
                $result['plain'] .= $decoded['plain'];
                $result['html'] .= $decoded['html'];
                $result['files'] = array_merge($result['files'], $decoded['files']);
            }
            return $result;
        }
        $raw = $number === '' ? imap_body($imap, $uid, FT_UID | FT_PEEK) : imap_fetchbody($imap, $uid, $number, FT_UID | FT_PEEK);
        $raw = match ((int) ($part->encoding ?? 0)) {
            3 => base64_decode($raw, true) ?: '', 4 => quoted_printable_decode($raw), default => $raw
        };
        $params = [];
        foreach (array_merge($part->parameters ?? [], $part->dparameters ?? []) as $parameter) {
            $params[strtolower($parameter->attribute)] = $parameter->value;
        }
        $filename = $params['filename'] ?? $params['name'] ?? null;
        if ($filename !== null || strcasecmp($part->disposition ?? '', 'ATTACHMENT') === 0) {
            $name = basename(str_replace('\\', '/', $this->header($filename ?? 'attachment')));
            return ['plain' => '', 'html' => '', 'files' => [['name' => $name, 'bytes' => $raw]]];
        }
        if (($part->type ?? 0) !== 0) {
            return ['plain' => '', 'html' => '', 'files' => []];
        }
        if (!empty($params['charset']) && strcasecmp($params['charset'], 'UTF-8') !== 0) {
            try {
                $raw = mb_convert_encoding($raw, 'UTF-8', $params['charset']);
            } catch (\ValueError) {
                $raw = '';
            }
        }
        $html = strcasecmp($part->subtype ?? '', 'HTML') === 0;
        return ['plain' => $html ? '' : $raw, 'html' => $html ? $raw : '', 'files' => []];
    }

    public function header(string $header): string
    {
        $value = '';
        foreach (imap_mime_header_decode($header) ?: [] as $part) {
            try {
                $value .= in_array(strtoupper($part->charset), ['DEFAULT', 'UTF-8', 'US-ASCII']) ? $part->text : mb_convert_encoding($part->text, 'UTF-8', $part->charset);
            } catch (\ValueError) {
                $value .= $part->text;
            }
        }
        return $value;
    }
}
