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

    public function test(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'test_email' => 'required|email',
            'subject' => 'required|string|max:191',
            'message' => 'required|string|max:20000',
        ]);

        $admin = $request->user();
        $name = trim(($admin->name_first ?? '') . ' ' . ($admin->name_last ?? '')) ?: ($admin->username ?? 'Nodexa Admin');
        $body = strtr($data['message'], [
            '{{name}}' => $name,
            '{{username}}' => $admin->username ?? 'admin',
            '{{email}}' => $data['test_email'],
            '{{app_name}}' => config('app.name', 'Nodexa'),
        ]);

        Mail::send('emails.nodexa-message', ['recipient' => $name, 'body' => $body], function ($mail) use ($data) {
            $mail->to($data['test_email'])->subject('[TEST] ' . $data['subject']);
        });

        return redirect()->route('admin.mail')->with('success', 'Test email sent to ' . $data['test_email'] . '.');
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
