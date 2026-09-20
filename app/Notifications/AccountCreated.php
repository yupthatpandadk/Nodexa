<?php

namespace Pterodactyl\Notifications;

use Pterodactyl\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class AccountCreated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public User $user, public ?string $token = null)
    {
    }

    public function via(): array
    {
        return ['mail'];
    }

    public function toMail()
    {
        $name = trim(($this->user->name_first ?? '') . ' ' . ($this->user->name_last ?? '')) ?: $this->user->username;

        $body = "Hej {$name},\n\nVelkommen til Nodexa. Din konto er nu oprettet og klar til brug.\n\nBrugernavn: {$this->user->username}\nE-mail: {$this->user->email}";

        if (!is_null($this->token)) {
            $body .= "\n\nAktivér din konto og opret din adgangskode her:\n" .
                url('/auth/password/reset/' . $this->token . '?email=' . urlencode($this->user->email));
        } else {
            $body .= "\n\nÅbn Nodexa Control Panel:\n" . url('/');
        }

        $body .= "\n\nHar du spørgsmål eller brug for hjælp, er du altid velkommen til at kontakte os.\n\nMed venlig hilsen,\nNodexa";

        return (new \Illuminate\Notifications\Messages\MailMessage())
            ->subject('Velkommen til Nodexa')
            ->view('emails.nodexa-message', [
                'recipient' => $name,
                'body' => $body,
            ]);
    }
}
