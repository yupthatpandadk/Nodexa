<?php

namespace Pterodactyl\Console\Commands\Nodexa;

use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Services\Support\TicketMailImporter;

class ImportSupportMailCommand extends Command
{
    protected $signature = 'nodexa:support:mail';
    protected $description = 'Import customer replies from the TLS IMAP support mailbox';

    public function handle(TicketMailImporter $importer): int
    {
        if (!config('support.mail.enabled')) {
            $this->info('Support mail import is disabled.');
            return self::SUCCESS;
        }
        if (!extension_loaded('imap')) {
            $this->error('Enable the PHP IMAP extension to import support replies.');
            return self::FAILURE;
        }
        $config = config('support.mail');
        if (!preg_match('/^[a-zA-Z0-9.-]+$/', (string) $config['host']) || !preg_match('/^[a-zA-Z0-9_ .\/-]+$/', (string) $config['folder']) || $config['port'] < 1 || $config['port'] > 65535 || !filter_var($config['address'], FILTER_VALIDATE_EMAIL) || !$config['username'] || !$config['password']) {
            $this->error('Configure SUPPORT_MAIL_ADDRESS and the SUPPORT_IMAP_* environment variables.');
            return self::FAILURE;
        }
        imap_timeout(IMAP_OPENTIMEOUT, 15);
        imap_timeout(IMAP_READTIMEOUT, 20);
        $mailbox = '{' . $config['host'] . ':' . $config['port'] . '/imap/ssl/validate-cert}' . $config['folder'];
        $imap = @imap_open($mailbox, $config['username'], $config['password'], 0, 1);
        if (!$imap) {
            imap_errors();
            $this->error('Cannot connect to the support mailbox. Check TLS and credentials.');
            return self::FAILURE;
        }
        $failed = false;
        try {
            $uids = imap_search($imap, 'UNSEEN', SE_UID) ?: [];
            sort($uids);
            $status = imap_status($imap, $mailbox, SA_UIDVALIDITY);
            foreach (array_slice($uids, 0, 100) as $uid) {
                $tempFiles = [];
                try {
                    $overview = imap_fetch_overview($imap, (string) $uid, FT_UID)[0] ?? null;
                    if (!$overview) {
                        throw new \RuntimeException('Cannot read mail metadata.');
                    }
                    $key = hash('sha256', $mailbox . '|' . $config['username'] . '|' . ($status->uidvalidity ?? '') . '|' . ($overview->message_id ?? $uid));
                    if (DB::table('nodexa_support_mail_imports')->where('message_key', $key)->exists()) {
                        imap_setflag_full($imap, (string) $uid, '\\Seen', ST_UID);
                        continue;
                    }
                    $header = imap_rfc822_parse_headers(imap_fetchheader($imap, $uid, FT_UID));
                    $senders = $header->from ?? [];
                    $sender = count($senders) === 1 ? ($senders[0]->mailbox ?? '') . '@' . ($senders[0]->host ?? '') : '';
                    $recipients = array_map(fn ($a) => ($a->mailbox ?? '') . '@' . ($a->host ?? ''), array_merge($header->to ?? [], $header->cc ?? []));
                    $automatic = preg_match('/^(Auto-Submitted:\s*(?!no\s*$)\S+|Precedence:\s*(bulk|list|junk)|X-Autoreply:)/im', imap_fetchheader($imap, $uid, FT_UID));
                    if (($overview->size ?? 0) > $config['max_bytes'] || $automatic) {
                        DB::table('nodexa_support_mail_imports')->insertOrIgnore(['message_key' => $key, 'sender' => mb_substr($sender, 0, 255), 'status' => 'rejected', 'reason' => $automatic ? 'Automatic message ignored' : 'Message exceeds 25 MB', 'created_at' => now(), 'updated_at' => now()]);
                    } else {
                        $structure = imap_fetchstructure($imap, $uid, FT_UID);
                        if (!$structure) {
                            throw new \RuntimeException('Cannot read MIME structure.');
                        }
                        $decoded = $importer->decode($imap, $uid, $structure);
                        $files = [];
                        foreach ($decoded['files'] as $file) {
                            $temp = tempnam(sys_get_temp_dir(), 'nodexa-mail-');
                            $tempFiles[] = $temp;
                            file_put_contents($temp, $file['bytes']);
                            $files[] = new UploadedFile($temp, $file['name'], null, null, true);
                        }
                        $body = $decoded['plain'] ?: html_entity_decode(strip_tags(preg_replace('/<(br|\/p|\/div)\s*\/?\s*>/i', "\n", $decoded['html'])), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        $importer->import($key, $sender, $recipients, $body, $files);
                    }
                    imap_setflag_full($imap, (string) $uid, '\\Seen', ST_UID);
                } catch (\Throwable $e) {
                    imap_clearflag_full($imap, (string) $uid, '\\Seen', ST_UID);
                    report($e);
                    $this->warn('Mail UID ' . $uid . ' failed; it remains unread for retry.');
                    $failed = true;
                } finally {
                    foreach ($tempFiles as $file) {
                        if (is_file($file)) {
                            unlink($file);
                        }
                    }
                }
            }
        } finally {
            imap_close($imap);
        }
        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
