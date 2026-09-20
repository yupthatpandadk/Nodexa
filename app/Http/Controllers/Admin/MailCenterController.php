<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;

class MailCenterController extends Controller
{
    public function index(): View
    {
        return view('admin.mail.index', [
            'users' => User::query()->orderBy('email')->get(['id', 'email', 'username', 'name_first', 'name_last']),
            'templates' => config('nodexa-mail.templates', []),
            'recentServers' => Server::query()->with('user')->latest('id')->limit(50)->get(),
        ]);
    }

    public function resendWelcome(User $user): RedirectResponse
    {
        $name = trim(($user->name_first ?? '') . ' ' . ($user->name_last ?? '')) ?: $user->username;
        $body = "Hi {$name},\n\nWelcome to Nodexa. Your account is ready.\n\nUsername: {$user->username}\nEmail: {$user->email}\nPanel: " . url('/') . "\n\nRegards,\nThe Nodexa Team";

        Mail::send('emails.nodexa-message', ['recipient' => $name, 'body' => $body], function ($mail) use ($user) {
            $mail->to($user->email)->subject('Welcome to Nodexa');
        });

        return redirect()->route('admin.mail')->with('success', 'Welcome email resent to ' . $user->email . '.');
    }

    public function resendServer(Server $server): RedirectResponse
    {
        $server->loadMissing(['user', 'allocation', 'node']);
        $user = $server->user;
        $name = trim(($user->name_first ?? '') . ' ' . ($user->name_last ?? '')) ?: $user->username;
        $address = $server->allocation ? ($server->allocation->ip_alias ?: $server->allocation->ip) . ':' . $server->allocation->port : 'Not assigned';
        $node = $server->node ? $server->node->name : 'Unknown';
        $body = "Hi {$name},\n\nA game server has been assigned to your Nodexa account.\n\nServer: {$server->name}\nServer ID: {$server->id}\nServer UUID: {$server->uuid}\nNode: {$node}\nAddress: {$address}\nMemory: {$server->memory} MB\nDisk: {$server->disk} MB\nCPU limit: {$server->cpu}%\n\nOpen panel: " . url('/server/' . $server->uuidShort) . "\n\nRegards,\nThe Nodexa Team";

        Mail::send('emails.nodexa-message', ['recipient' => $name, 'body' => $body], function ($mail) use ($user, $server) {
            $mail->to($user->email)->subject('Your Nodexa server is ready: ' . $server->name);
        });

        return redirect()->route('admin.mail')->with('success', 'Server assignment email resent to ' . $user->email . '.');
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
