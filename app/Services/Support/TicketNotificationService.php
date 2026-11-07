<?php

namespace Pterodactyl\Services\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Pterodactyl\Models\SupportSetting;
use Pterodactyl\Models\SupportTicket;

class TicketNotificationService
{
    private function mail(SupportTicket $ticket, string $subject, string $body): void
    {
        $ticket->loadMissing('user');
        $user = $ticket->user;
        if (!$user || !filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $address = (string) config('support.mail.address');
        $replyTo = null;
        if (config('support.mail.enabled') && filter_var($address, FILTER_VALIDATE_EMAIL)) {
            // Existing tickets receive a token lazily on their first outgoing mail after upgrade.
            if (!$ticket->reply_token) {
                SupportTicket::whereKey($ticket->id)->whereNull('reply_token')->update(['reply_token' => Str::random(40)]);
                $ticket->refresh();
            }
            [$local, $domain] = explode('@', $address, 2);
            $replyTo = $local . '+' . $ticket->reply_token . '@' . $domain;
        }
        $url = route('store.client.tickets.show', $ticket);
        $body = "--- Reply above this line ---\n\n" . $body . "\n\nView ticket: " . $url . ($replyTo ? "\nYou can reply directly to this email. Keep the private reply address unchanged." : "\nPlease sign in to reply in the Client Area.");
        try {
            Mail::send('emails.nodexa-message', ['recipient' => $user->name ?: $user->username, 'body' => $body], function ($mail) use ($user, $subject, $replyTo) {
                $mail->to($user->email)->subject($subject);
                if ($replyTo) {
                    $mail->replyTo($replyTo, 'Nodexa Support');
                }
            });
        } catch (\Throwable $e) {
            Log::warning('Ticket email failed', ['ticket_id' => $ticket->id, 'exception' => get_class($e)]);
        }
    }

    private function discord(string $title, string $description, int $color = 3447003): void
    {
        $url = SupportSetting::value('discord_webhook');
        if (!$url || !preg_match('~^https://(?:(?:canary|ptb)\.)?discord(?:app)?\.com/api/webhooks/[0-9]+/[^/?#]+$~', $url)) {
            return;
        }
        try {
            Http::timeout(5)->withOptions(['allow_redirects' => false])->post($url, ['username' => 'Nodexa Support', 'allowed_mentions' => ['parse' => []], 'embeds' => [['title' => mb_substr($title, 0, 256), 'description' => mb_substr($description, 0, 4096), 'color' => $color, 'timestamp' => now()->toIso8601String()]]])->throw();
        } catch (\Throwable $e) {
            Log::warning('Ticket Discord webhook failed', ['exception' => get_class($e)]);
        }
    }

    public function created(SupportTicket $ticket): void
    {
        $this->mail($ticket, "[{$ticket->ticket_number}] Ticket received", "We have received your ticket: {$ticket->subject}.\nTicket: {$ticket->ticket_number}\nOur support team will respond as soon as possible.");
        $this->discord('New ticket · ' . $ticket->ticket_number, $ticket->subject . "\nDepartment: " . $ticket->department . "\n" . route('admin.tickets.show', $ticket), 15105570);
    }

    public function customerReply(SupportTicket $ticket): void
    {
        $this->discord('Customer reply · ' . $ticket->ticket_number, $ticket->subject . "\n" . route('admin.tickets.show', $ticket), 15844367);
    }

    public function staffReply(SupportTicket $ticket): void
    {
        $message = $ticket->messages()->where('staff', true)->where('internal', false)->latest('id')->value('message');
        $this->mail($ticket, "[{$ticket->ticket_number}] Reply from Nodexa Support", "Your ticket: {$ticket->subject}\n\n" . $message);
    }

    public function closed(SupportTicket $ticket): void
    {
        $this->mail($ticket, "[{$ticket->ticket_number}] Ticket closed", "Your ticket {$ticket->ticket_number} · {$ticket->subject} has been closed.\n\nHow was your experience? Open the ticket to leave a 1–5 rating and feedback.");
    }

    public function escalated(SupportTicket $ticket): void
    {
        $this->discord('Overdue ticket · ' . $ticket->ticket_number, $ticket->subject . "\nPriority: " . $ticket->priority . "\n" . route('admin.tickets.show', $ticket), 15158332);
    }
}
