@extends('store.layout') @section('title','Min profil · Client Area') @section('content')<div class="container clientShell"><aside class="clientSide"><div class="clientIdentity"><div class="avatar">{{strtoupper(substr(auth()->user()->name_first ?: auth()->user()->username,0,1))}}</div><div><strong>{{auth()->user()->name}}</strong><small>{{auth()->user()->email}}</small></div></div><nav><a href="{{route('store.client')}}">⌂ <span>Oversigt</span></a><a href="{{route('store.client.servers')}}">▣ <span>Mine servere</span></a><a href="{{route('store.client.vps')}}">☁ <span>Mine VPS’er</span></a><a href="{{route('store.client.billing')}}">▤ <span>Billing & ordrer</span></a><a href="{{route('store.client.hub')}}">◆ <span>Services Hub</span></a><a href="{{route('store.client.profile')}}">◎ <span>Min profil</span></a><a href="{{route('store.client.tickets')}}">? <span>Support Tickets</span></a><a href="{{route('store.hosting')}}">＋ <span>Bestil server</span></a></nav><div class="clientPanel"><div class="miniLabel">CONTROL PANEL</div><p>Avanceret serveradministration</p><a class="btn" href="{{rtrim(config('app.url'), '/')}}/panel">Åbn panel ↗</a></div></aside><main><div class="clientHead"><div><div class="eyebrow">Client Area / Account</div><h1>Min profil</h1><p class="muted">Kontooplysninger for din Nodexa-konto.</p></div></div><div class="card"><div class="sectionNo">ACCOUNT INFORMATION</div><div style="margin-top:18px"><div class="orderLine"><span class="muted">Navn</span><strong>{{$user->name}}</strong></div><div class="orderLine"><span class="muted">Brugernavn</span><strong>{{$user->username}}</strong></div><div class="orderLine"><span class="muted">E-mail</span><strong>{{$user->email}}</strong></div><div class="orderLine"><span class="muted">Sprog</span><strong>{{strtoupper($user->language)}}</strong></div><div class="orderLine"><span class="muted">2FA</span><strong>{{$user->use_totp ? 'Aktiveret' : 'Ikke aktiveret'}}</strong></div></div><div class="cardAction"><a class="btn primary" href="{{rtrim(config('app.url'), '/')}}/panel">Administrér konto i kontrolpanel →</a></div></div>
<section class="card nodexaAccountSecurity" aria-labelledby="security-title">
    <div class="sectionNo">KONTOSIKKERHED</div>
    <div class="nodexaSecurityHead">
        <div>
            <h2 id="security-title">Beskyt din konto</h2>
            <p>Indstillingerne for login og tofaktorgodkendelse administreres i dit eksisterende Nodexa-kontrolpanel.</p>
        </div>
        <span class="nodexaSecurityTag {{ $user->use_totp ? 'is-on' : 'is-off' }}">{{ $user->use_totp ? '2FA aktiveret' : '2FA ikke aktiveret' }}</span>
    </div>
    <a class="btn" href="{{ rtrim(config('app.url'), '/') }}/panel">Åbn kontoindstillinger →</a>
    <div class="nodexaSecurityActivity">
        <h3>Seneste kontoaktivitet</h3>
        @forelse($recentActivity as $entry)
            <div class="nodexaSecurityEntry">
                <div><strong>{{ $entry->action }}</strong><small>{{ $entry->area }}</small></div>
                <time>{{ \Carbon\Carbon::parse($entry->created_at)->format('d.m.Y H:i') }}</time>
            </div>
        @empty
            <p class="muted">Ingen registrerede kontohændelser endnu.</p>
        @endforelse
        <a class="nodexaSecurityMore" href="{{ route('store.client.hub') }}">Se flere hændelser i Services Hub →</a>
    </div>
</section>
</main></div>@push('styles')<style>
.nodexaAccountSecurity{margin-top:16px}
.nodexaSecurityHead{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;margin:12px 0 16px}
.nodexaSecurityHead h2{font-size:21px;margin:0 0 5px}
.nodexaSecurityHead p{margin:0;font-size:12px;color:#8ca6c1;max-width:520px}
.nodexaSecurityTag{flex:0 0 auto;border-radius:8px;padding:7px 9px;font-size:10px;font-weight:750}
.nodexaSecurityTag.is-on{background:#103024;color:#88dbb2}
.nodexaSecurityTag.is-off{background:#35271c;color:#eac38f}
.nodexaSecurityActivity{margin-top:20px;border-top:1px solid #23374e;padding-top:17px}
.nodexaSecurityActivity h3{font-size:15px;margin:0 0 9px}
.nodexaSecurityEntry{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid #1a2c41}
.nodexaSecurityEntry strong,.nodexaSecurityEntry small{display:block}
.nodexaSecurityEntry strong{font-size:12px}
.nodexaSecurityEntry small,.nodexaSecurityEntry time{font-size:10px;color:#839bb5}
.nodexaSecurityMore{display:inline-block;margin-top:14px;font-size:11px;color:#9ec7f4;font-weight:750}
@media(max-width:580px){.nodexaSecurityHead{flex-direction:column}.nodexaSecurityTag{align-self:flex-start}}
.clientShell{display:grid;grid-template-columns:250px minmax(0,1fr);gap:28px;padding:36px 0 75px}.clientSide{height:max-content;position:sticky;top:126px;border:1px solid var(--line);border-radius:17px;background:#07131f;padding:12px}.clientIdentity{display:flex;align-items:center;gap:11px;padding:10px 9px 17px;border-bottom:1px solid var(--line);margin-bottom:9px}.clientIdentity .avatar{width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,var(--blue),var(--purple));display:grid;place-items:center;font-weight:900}.clientIdentity strong,.clientIdentity small{display:block}.clientIdentity strong{font-size:13px}.clientIdentity small{font-size:10px;color:#68819d;max-width:160px;overflow:hidden;text-overflow:ellipsis}.clientSide nav{display:grid;gap:3px}.clientSide nav a{padding:10px 11px;border-radius:9px;color:#8198b3;font-size:12px;font-weight:750}.clientSide nav a:hover{background:#102640;color:#e0efff}.clientPanel{margin-top:12px;padding:13px;border-radius:11px;background:#0a1b2c;border:1px solid #193652}.clientPanel p{font-size:11px;color:#7890aa}.clientPanel .btn{width:100%;min-height:36px;font-size:11px}.clientHead{display:flex;justify-content:space-between;align-items:flex-end;gap:20px;margin-bottom:25px}.clientHead h1{font-size:34px;margin:5px 0}.clientHead p{margin:0}.clientCards{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.clientStat{padding:18px;border:1px solid var(--line);border-radius:14px;background:#081522}.clientStat span{font-size:9px;color:#69819c;font-weight:850;letter-spacing:.09em}.clientStat strong{display:block;font-size:24px;margin-top:4px}.clientSection{margin-top:30px}.clientRow{display:flex;justify-content:space-between;align-items:center;gap:20px;padding:17px 19px;border:1px solid var(--line);border-radius:13px;background:#081522;margin-top:8px}.clientRow h3{font-size:14px;margin:0 0 3px}.clientRow p{font-size:11px;margin:0;color:#7087a2}@media(max-width:800px){.clientShell{grid-template-columns:1fr}.clientSide{position:static}.clientSide nav{display:flex;overflow:auto}.clientSide nav a{white-space:nowrap}.clientPanel{display:none}.clientCards{grid-template-columns:1fr}.clientHead{align-items:flex-start;flex-direction:column}}@media(max-width:580px){.clientRow{align-items:flex-start;flex-direction:column}}</style>@endpush@endsection