<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="_token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Nodexa') }} - Tickets</title>
    @include('partials.nodexa-theme')
    <style>
        *{box-sizing:border-box}body{margin:0;background:var(--nodexa-bg);color:var(--nodexa-text);font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.wrap{max-width:1180px;margin:0 auto;padding:24px}.top{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:22px}.btn,.input,select,textarea{border:1px solid var(--nodexa-border-strong);border-radius:12px;background:var(--nodexa-surface-2);color:var(--nodexa-text)}.btn{display:inline-flex;align-items:center;justify-content:center;padding:10px 14px;text-decoration:none;font-weight:700;cursor:pointer}.btn-primary{background:var(--nodexa-accent);color:#04100c}.grid{display:grid;grid-template-columns:360px 1fr;gap:18px}.card{border:1px solid var(--nodexa-border);border-radius:16px;background:var(--nodexa-surface);padding:16px}.ticket{display:block;padding:12px;border:1px solid var(--nodexa-border);border-radius:12px;margin-bottom:10px;color:var(--nodexa-text);text-decoration:none}.ticket.active{border-color:var(--nodexa-accent);background:var(--nodexa-accent-soft)}.muted{color:var(--nodexa-muted)}.pill{display:inline-block;padding:4px 8px;border-radius:999px;background:var(--nodexa-accent-soft);font-size:12px}.msg{padding:12px;border:1px solid var(--nodexa-border);border-radius:12px;margin-bottom:10px;background:var(--nodexa-surface-2)}.msg.staff{border-left:3px solid var(--nodexa-accent)}.input,select,textarea{width:100%;padding:11px 12px;margin-top:6px}label{display:block;margin-bottom:12px;font-weight:600}textarea{min-height:120px;resize:vertical}.row{display:grid;grid-template-columns:1fr 1fr;gap:12px}.flash{padding:12px;border-radius:12px;background:var(--nodexa-accent-soft);margin-bottom:14px}@media(max-width:820px){.grid{grid-template-columns:1fr}.wrap{padding:14px}.row{grid-template-columns:1fr}.top{align-items:flex-start;flex-direction:column}}
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div><h1 style="margin:0">Support Tickets</h1><div class="muted">Opret en sag og skriv direkte med staff.</div></div>
        <a class="btn" href="{{ route('index') }}">Tilbage til panelet</a>
    </div>

    @if(session('success'))<div class="flash">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="flash">{{ $errors->first() }}</div>@endif

    <div class="grid">
        <div>
            <div class="card" style="margin-bottom:18px">
                <h3 style="margin-top:0">Ny ticket</h3>
                <form method="POST" action="{{ route('tickets.store') }}">
                    {!! csrf_field() !!}
                    <label>Emne<input class="input" name="subject" maxlength="180" required></label>
                    <div class="row">
                        <label>Kategori<select name="category"><option value="support">Support</option><option value="server">Server</option><option value="billing">Betaling</option><option value="other">Andet</option></select></label>
                        <label>Prioritet<select name="priority"><option value="low">Lav</option><option value="normal" selected>Normal</option><option value="high">Høj</option><option value="urgent">Akut</option></select></label>
                    </div>
                    <label>Besked<textarea name="message" required></textarea></label>
                    <button class="btn btn-primary" type="submit">Opret ticket</button>
                </form>
            </div>

            <div class="card">
                <h3 style="margin-top:0">Mine tickets</h3>
                @forelse($tickets as $ticket)
                    <a class="ticket {{ $selected && $selected->id === $ticket->id ? 'active' : '' }}" href="{{ route('tickets.index', ['ticket' => $ticket->id]) }}">
                        <strong>#{{ $ticket->id }} · {{ $ticket->subject }}</strong><br>
                        <span class="pill">{{ $ticket->status }}</span> <span class="muted">{{ $ticket->priority }}</span>
                    </a>
                @empty
                    <div class="muted">Du har ingen tickets endnu.</div>
                @endforelse
            </div>
        </div>

        <div class="card">
            @if($selected)
                <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center">
                    <div><h2 style="margin:0">#{{ $selected->id }} · {{ $selected->subject }}</h2><div class="muted">{{ $selected->category }} · {{ $selected->priority }} · {{ $selected->status }}</div></div>
                    @if($selected->status !== 'closed')
                    <form method="POST" action="{{ route('tickets.close', $selected->id) }}">{!! csrf_field() !!}<button class="btn" type="submit">Luk ticket</button></form>
                    @endif
                </div>
                <hr style="border-color:var(--nodexa-border);margin:18px 0">
                @foreach($messages as $message)
                    <div class="msg {{ $message->is_staff ? 'staff' : '' }}"><strong>{{ $message->is_staff ? 'Staff · ' : '' }}{{ $message->username }}</strong><div class="muted" style="font-size:12px">{{ $message->created_at }}</div><div style="white-space:pre-wrap;margin-top:8px">{{ $message->message }}</div></div>
                @endforeach
                @if($selected->status !== 'closed')
                <form method="POST" action="{{ route('tickets.reply', $selected->id) }}">{!! csrf_field() !!}<label>Svar<textarea name="message" required></textarea></label><button class="btn btn-primary" type="submit">Send svar</button></form>
                @else
                <div class="muted">Denne ticket er lukket.</div>
                @endif
            @else
                <div class="muted">Vælg en ticket i venstre side for at se samtalen.</div>
            @endif
        </div>
    </div>
</div>
</body>
</html>
