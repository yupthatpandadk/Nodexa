<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\User;

class MailCenterController extends Controller
{
    public function index(): View
    {
        return view('admin.mail.index', [
            'users' => User::query()->orderBy('email')->get(['id', 'email', 'username', 'name_first', 'name_last']),
            'templates' => config('nodexa-mail.templates', []),
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'audience' => 'required|in:single,admins,all',
            'user_id' => 'nullable|required_if:audience,single|integer|exists:users,id',
            'subject' => 'required|string|max:191',
            'message' => 'required|string|max:20000',
        ]);

        $query = User::query();
        if ($data['audience'] === 'single') $query->whereKey($data['user_id']);
        if ($data['audience'] === 'admins') $query->where('root_admin', true);

        $sent = 0;
        foreach ($query->cursor() as $user) {
            $name = trim(($user->name_first ?? '') . ' ' . ($user->name_last ?? '')) ?: $user->username;
            $body = strtr($data['message'], [
                '{{name}}' => $name,
                '{{username}}' => $user->username,
                '{{email}}' => $user->email,
                '{{app_name}}' => config('app.name', 'Nodexa'),
            ]);

            Mail::send('emails.nodexa-message', ['recipient' => $name, 'body' => $body], function ($mail) use ($user, $data) {
                $mail->to($user->email)->subject($data['subject']);
            });
            $sent++;
        }

        return redirect()->route('admin.mail')->with('success', "Mail sent to {$sent} recipient(s).");
    }
}
