@extends('store.layout')
@section('title','Services Hub · Client Area')

@push('styles')
<style>
.hub{padding:38px 0 82px}.hubHead{display:flex;justify-content:space-between;align-items:flex-end;gap:20px;margin-bottom:24px}.hubHead h1{font-size:36px;margin:5px 0}.hubHead p{margin:0;color:#8198b3}.hubGrid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:16px}.hubCard{grid-column:span 6;border:1px solid #17314b;border-radius:17px;background:#081522;padding:20px}.hubCard.full{grid-column:1/-1}.hubCard.third{grid-column:span 4}.hubTitle{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:16px}.hubTitle h2{font-size:18px;margin:0}.hubTitle span{font-size:10px;color:#6f89a5;font-weight:850}.progress{height:10px;background:#102238;border-radius:999px;overflow:hidden}.progress>span{display:block;height:100%;background:linear-gradient(90deg,#3d8dff,#7657ff);border-radius:inherit}.checkGrid{display:grid;grid-template-columns:repeat(2,1fr);gap:8px;margin-top:14px}.check{padding:10px 11px;border:1px solid #18324c;border-radius:10px;color:#88a0ba;font-size:12px}.check.done{color:#bdebd8;background:#0a201a;border-color:#245441}.rows{display:grid;gap:8px}.rowCard{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:12px 13px;border:1px solid #15304a;border-radius:11px;background:#07131f}.rowCard strong{display:block;font-size:13px}.rowCard small{display:block;color:#6d86a2;margin-top:3px}.badge{display:inline-flex;padding:5px 8px;border-radius:999px;background:#102840;color:#8ecbff;font-size:9px;font-weight:900;text-transform:uppercase}.badge.good{background:#0d3025;color:#88e7bf}.badge.warn{background:#30250d;color:#ffd077}.badge.bad{background:#34161d;color:#ff9eae}.formGrid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px}.field label{display:block;color:#88a0bb;font-size:10px;font-weight:800;margin:0 0 5px}.field input,.field select{width:100%;background:#07131f;border:1px solid #1b3855;border-radius:9px;color:#eaf3ff;padding:10px 11px;outline:none}.field input:focus,.field select:focus{border-color:#3e79b0}.hubCard form{margin:0}.tiny{font-size:10px;color:#6f87a2}.tokenBox{padding:13px;border:1px solid #335f83;background:#081d2d;border-radius:10px;overflow-wrap:anywhere;color:#bce1ff;font:12px/1.6 Menlo,monospace;margin-bottom:12px}.sectionSep{height:1px;background:#15304a;margin:18px 0}.inlineActions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.linkish{color:#81c8ff}.linkish:hover{color:#fff}.empty{padding:18px;border:1px dashed #294966;border-radius:11px;color:#758da8;text-align:center;font-size:12px}.noticeHub{padding:12px 13px;border-radius:10px;border:1px solid #27527a;background:#0a1d2f;color:#a7c9e6;font-size:12px;margin-bottom:16px}@media(max-width:900px){.hubCard,.hubCard.third{grid-column:1/-1}}@media(max-width:620px){.hubHead{align-items:flex-start;flex-direction:column}.formGrid,.checkGrid{grid-template-columns:1fr}.rowCard{align-items:flex-start;flex-direction:column}}
</style>
@endpush

@section('content')
<section class="hub">
<div class="container">
    <div class="hubHead">
        <div><div class="eyebrow">CLIENT AREA / SERVICES HUB</div><h1>Dit Nodexa Hub</h1><p>Notifikationer, backups, billing, add-ons, teams, affiliate, API og sikkerhed samlet ét sted.</p></div>
        <a class="btn" href="{{ route('store.client') }}">← Dashboard</a>
    </div>

    @if(session('success'))<div class="noticeHub">{{ session('success') }}</div>@endif
    @if(session('new_api_token'))<div class="tokenBox"><strong>Nyt API-token — kopiér det nu:</strong><br>{{ session('new_api_token') }}</div>@endif

    <div class="hubGrid">
        <section class="hubCard full">
            <div class="hubTitle"><h2>Onboarding</h2><span>{{ $onboardingPercent }}% færdig</span></div>
            <div class="progress"><span style="width:{{ $onboardingPercent }}%"></span></div>
            <div class="checkGrid">
                <div class="check {{ $onboarding['profile'] ? 'done' : '' }}">✓ Profiloplysninger</div>
                <div class="check {{ $onboarding['two_factor'] ? 'done' : '' }}">{{ $onboarding['two_factor'] ? '✓' : '○' }} 2FA på kontoen</div>
                <div class="check {{ $onboarding['server'] ? 'done' : '' }}">{{ $onboarding['server'] ? '✓' : '○' }} Første server</div>
                <div class="check {{ $onboarding['backup'] ? 'done' : '' }}">{{ $onboarding['backup'] ? '✓' : '○' }} Automatisk backup</div>
            </div>
        </section>

        <section class="hubCard">
            <div class="hubTitle"><h2>Notifikationer</h2><form method="POST" action="{{ route('store.client.hub.notifications.read-all') }}">@csrf<button class="btn" type="submit">Markér alle læst</button></form></div>
            <div class="rows">
                @forelse($notifications as $notification)
                <div class="rowCard">
                    <div><strong>{{ $notification->title }}</strong><small>{{ $notification->message }} · {{ CarbonCarbon::parse($notification->created_at)->diffForHumans() }}</small></div>
                    @if(!$notification->read_at)<form method="POST" action="{{ route('store.client.hub.notifications.read',$notification->id) }}">@csrf<button class="btn" type="submit">Læst</button></form>@else<span class="badge good">Læst</span>@endif
                </div>
                @empty<div class="empty">Ingen notifikationer endnu.</div>@endforelse
            </div>
        </section>

        <section class="hubCard">
            <div class="hubTitle"><h2>Billing & fakturaer</h2><a class="linkish" href="{{ route('store.client.billing') }}">Åbn billing →</a></div>
            <div class="rows">
                @forelse($invoices->take(8) as $invoice)
                <div class="rowCard"><div><strong>{{ $invoice->number }}</strong><small>{{ number_format($invoice->total,2,',','.') }} {{ $invoice->currency }} · Forfalder {{ $invoice->due_at ? CarbonCarbon::parse($invoice->due_at)->format('d.m.Y') : '—' }}</small></div><span class="badge {{ $invoice->status==='paid'?'good':($invoice->status==='overdue'?'bad':'warn') }}">{{ $invoice->status }}</span></div>
                @empty<div class="empty">Ingen Nodexa-fakturaer endnu.</div>@endforelse
            </div>
        </section>

        <section class="hubCard full">
            <div class="hubTitle"><h2>Automatiske backups</h2><span>Policy pr. server</span></div>
            <div class="rows">
                @forelse($servers as $server)
                @php($policy=$backupPolicies[$server->id] ?? null)
                <div class="rowCard">
                    <div><strong>{{ $server->name }}</strong><small>{{ $server->node?->name }} · {{ $server->backups->count() }}/{{ $server->backup_limit }} backups @if($policy && $policy->last_status)· Sidst: {{ $policy->last_status }}@endif</small></div>
                    @if($server->owner_id===auth()->id())
                    <form method="POST" action="{{ route('store.client.hub.backups.save') }}" class="inlineActions">
                        @csrf
                        <input type="hidden" name="server_id" value="{{ $server->id }}">
                        <label class="tiny"><input type="checkbox" name="enabled" value="1" {{ !$policy || $policy->enabled ? 'checked' : '' }}> Aktiv</label>
                        <select name="frequency_hours" class="btn">
                            @foreach([6=>'6 timer',12=>'12 timer',24=>'Dagligt',48=>'Hver 2. dag',168=>'Ugentligt'] as $v=>$label)<option value="{{ $v }}" {{ (int)($policy->frequency_hours ?? 24)===$v?'selected':'' }}>{{ $label }}</option>@endforeach
                        </select>
                        <select name="retention_count" class="btn">@for($i=1;$i<=max(1,min(10,$server->backup_limit));$i++)<option value="{{ $i }}" {{ (int)($policy->retention_count ?? min(3,max(1,$server->backup_limit)))===$i?'selected':'' }}>{{ $i }} gemmes</option>@endfor</select>
                        <input type="hidden" name="name_prefix" value="Automatic backup">
                        <button class="btn primary">Gem</button>
                    </form>
                    @else<span class="badge">Subuser</span>@endif
                </div>
                @empty<div class="empty">Du har ingen servere endnu.</div>@endforelse
            </div>
        </section>

        <section class="hubCard">
            <div class="hubTitle"><h2>Service add-ons</h2><span>Opgrader servere</span></div>
            @if($addons->isNotEmpty() && $servers->where('owner_id',auth()->id())->isNotEmpty())
            <form method="POST" action="{{ route('store.client.hub.addons.order') }}">
                @csrf
                <div class="formGrid">
                    <div class="field"><label>Server</label><select name="server_id" required>@foreach($servers->where('owner_id',auth()->id()) as $server)<option value="{{ $server->id }}">{{ $server->name }}</option>@endforeach</select></div>
                    <div class="field"><label>Add-on</label><select name="addon_id" required>@foreach($addons as $addon)<option value="{{ $addon->id }}">{{ $addon->name }} · {{ number_format($addon->price_monthly,2,',','.') }} DKK/md</option>@endforeach</select></div>
                </div>
                <p class="tiny">Der oprettes en faktura. Ressourcerne anvendes automatisk, når fakturaen markeres betalt.</p>
                <button class="btn primary">Bestil add-on</button>
            </form>
            @else<div class="empty">Ingen add-ons er tilgængelige lige nu.</div>@endif
        </section>

        <section class="hubCard">
            <div class="hubTitle"><h2>Affiliate</h2><span>Referral program</span></div>
            @if($affiliate)
                <div class="formGrid">
                    <div class="rowCard"><div><strong>{{ number_format($affiliate->balance,2,',','.') }} DKK</strong><small>Til udbetaling</small></div></div>
                    <div class="rowCard"><div><strong>{{ $affiliate->conversions }}</strong><small>Konverteringer · {{ $affiliate->clicks }} klik</small></div></div>
                </div>
                <div class="sectionSep"></div>
                <div class="tokenBox">{{ route('store.referral',$affiliate->code) }}</div>
                <p class="tiny">Provision: {{ number_format($affiliate->commission_percent,2,',','.') }}%</p>
            @else
                <p class="muted">Aktivér dit personlige referral-link og optjen provision på nye kunder.</p>
                <form method="POST" action="{{ route('store.client.hub.affiliate.enable') }}">@csrf<button class="btn primary">Aktivér affiliate</button></form>
            @endif
        </section>

        <section class="hubCard">
            <div class="hubTitle"><h2>Teams & organisationer</h2><span>Del administration</span></div>
            <form method="POST" action="{{ route('store.client.hub.organizations.create') }}" class="inlineActions">@csrf<div class="field" style="flex:1"><input name="name" required placeholder="Organisationens navn"></div><button class="btn primary">Opret</button></form>
            <div class="sectionSep"></div>
            <div class="rows">
                @forelse($organizations as $org)
                <div class="rowCard" style="display:block">
                    <strong>{{ $org->name }}</strong>
                    <small>{{ ($organizationMembers[$org->id] ?? collect())->count() }} medlem(mer)</small>
                    @if($org->owner_id===auth()->id())
                    <form method="POST" action="{{ route('store.client.hub.organizations.members',$org->id) }}" class="inlineActions" style="margin-top:10px">@csrf<input class="btn" style="flex:1" type="email" name="email" required placeholder="bruger@email.dk"><select class="btn" name="role"><option value="member">Member</option><option value="billing">Billing</option><option value="admin">Admin</option></select><button class="btn">Tilføj medlem</button></form>
                    <form method="POST" action="{{ route('store.client.hub.organizations.servers',$org->id) }}" class="inlineActions" style="margin-top:8px">@csrf<select class="btn" name="server_id" required style="flex:1">@foreach($servers->where('owner_id',auth()->id()) as $server)<option value="{{ $server->id }}">{{ $server->name }}</option>@endforeach</select><button class="btn">Del server</button></form>
                    @if(($organizationServers[$org->id] ?? collect())->isNotEmpty())<div class="tiny" style="margin-top:8px">Delte servere: {{ ($organizationServers[$org->id] ?? collect())->pluck('server_name')->implode(', ') }}</div>@endif
                    @endif
                </div>
                @empty<div class="empty">Ingen teams endnu.</div>@endforelse
            </div>
        </section>

        <section class="hubCard">
            <div class="hubTitle"><h2>Webhooks</h2><span>Developer</span></div>
            <form method="POST" action="{{ route('store.client.hub.webhooks.create') }}">
                @csrf
                <div class="formGrid"><div class="field"><label>Navn</label><input name="name" required placeholder="Min integration"></div><div class="field"><label>Endpoint URL</label><input name="url" required type="url" placeholder="https://example.com/webhook"></div></div>
                <div class="field" style="margin-top:10px"><label>Events</label><select name="events[]" multiple required style="min-height:120px"><option value="invoice.created">invoice.created</option><option value="invoice.paid">invoice.paid</option><option value="addon.ordered">addon.ordered</option><option value="server.provisioned">server.provisioned</option><option value="ticket.reply">ticket.reply</option><option value="status.incident.created">status.incident.created</option><option value="status.incident.updated">status.incident.updated</option><option value="*">Alle events (*)</option></select></div>
                <button class="btn primary" style="margin-top:10px">Opret webhook</button>
            </form>
            <div class="sectionSep"></div>
            <div class="rows">@forelse($webhooks as $webhook)<div class="rowCard"><div><strong>{{ $webhook->name }}</strong><small>{{ $webhook->url }} · {{ $webhook->last_status ?: 'aldrig kørt' }}</small></div><div class="inlineActions"><form method="POST" action="{{ route('store.client.hub.webhooks.test',$webhook->id) }}">@csrf<button class="btn">Test</button></form><form method="POST" action="{{ route('store.client.hub.webhooks.delete',$webhook->id) }}">@csrf @method('DELETE')<button class="btn">Slet</button></form></div></div>@empty<div class="empty">Ingen webhooks endnu.</div>@endforelse</div>
        </section>

        <section class="hubCard">
            <div class="hubTitle"><h2>API Tokens</h2><span>/api/nodexa</span></div>
            <form method="POST" action="{{ route('store.client.hub.tokens.create') }}">@csrf<div class="field"><label>Navn</label><input name="name" required placeholder="Mit API token"></div><div class="inlineActions" style="margin-top:10px"><label class="tiny"><input type="checkbox" name="scopes[]" value="profile.read" checked> profile.read</label><label class="tiny"><input type="checkbox" name="scopes[]" value="servers.read" checked> servers.read</label><label class="tiny"><input type="checkbox" name="scopes[]" value="billing.read"> billing.read</label></div><button class="btn primary" style="margin-top:10px">Opret token</button></form>
            <div class="sectionSep"></div>
            <div class="rows">@forelse($tokens as $token)<div class="rowCard"><div><strong>{{ $token->name }}</strong><small>{{ $token->token_prefix }}•••• · Sidst brugt {{ $token->last_used_at ? CarbonCarbon::parse($token->last_used_at)->diffForHumans() : 'aldrig' }}</small></div><form method="POST" action="{{ route('store.client.hub.tokens.revoke',$token->id) }}">@csrf @method('DELETE')<button class="btn">Revoke</button></form></div>@empty<div class="empty">Ingen API tokens.</div>@endforelse</div>
            <p class="tiny" style="margin-top:10px">Endpoints: <code>/api/nodexa/me</code>, <code>/api/nodexa/servers</code>, <code>/api/nodexa/invoices</code>.</p>
        </section>

        <section class="hubCard full">
            <div class="hubTitle"><h2>Sikkerhed & aktivitet</h2><span>{{ $user->use_totp ? '2FA aktiv' : '2FA ikke aktiv' }}</span></div>
            <div class="rows">
                @forelse($securityActivity as $entry)
                <div class="rowCard"><div><strong>{{ $entry->action }}</strong><small>{{ $entry->area }} · {{ CarbonCarbon::parse($entry->created_at)->format('d.m.Y H:i') }} @if($entry->ip)· {{ $entry->ip }}@endif</small></div></div>
                @empty<div class="empty">Ingen Nodexa-aktivitet logget endnu.</div>@endforelse
            </div>
        </section>
    </div>
</div>
</section>
@endsection