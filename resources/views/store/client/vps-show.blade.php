@extends('store.layout')
@section('title', $service->hostname . ' · VPS Kontrolpanel')
@section('content')
<section class="nvp-page">
    <div class="container">
        <nav class="nvp-breadcrumb" aria-label="Brødkrummesti">
            <a href="{{ route('store.client') }}">Client Area</a>
            <span>/</span>
            <a href="{{ route('store.client.vps') }}">Mine VPS'er</a>
            <span>/</span>
            <strong>{{ $service->hostname }}</strong>
        </nav>

        <header class="nvp-header">
            <div>
                <div class="nvp-kicker">VPS KONTROLPANEL <span>·</span> #{{ $service->id }}</div>
                <h1>{{ $service->hostname }}</h1>
                <p>{{ $service->product_name ?: 'VPS Hosting' }} · {{ $service->os_label ?: 'Operativsystem ikke angivet' }}</p>
            </div>
            <div class="nvp-header-actions">
                <span class="nvp-status {{ $service->status === 'active' ? 'is-active' : ($service->status === 'provisioning_failed' || $service->status === 'suspended' ? 'is-error' : 'is-pending') }}">
                    <span class="nvp-status-dot"></span>
                    {{ strtoupper(str_replace('_', ' ', $service->status)) }}
                </span>
                <a class="nvp-outline" href="{{ route('store.client.vps') }}">← Alle VPS'er</a>
            </div>
        </header>

        @if(session('success'))
            <div class="nvp-notice nvp-notice-success" role="status">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="nvp-notice nvp-notice-error" role="alert">{{ $errors->first() }}</div>
        @endif
        @if($service->status === 'provisioning_failed')
            <div class="nvp-notice nvp-notice-error">
                <strong>Provisionering fejlede.</strong>
                @if($service->last_error)
                    <span>{{ $service->last_error }}</span>
                @else
                    <span>Support kan undersøge fejlen og forsøge igen uden en ny ordre.</span>
                @endif
            </div>
        @elseif($service->last_error)
            <div class="nvp-notice nvp-notice-error">{{ $service->last_error }}</div>
        @endif

        <div class="nvp-layout">
            <main class="nvp-main">
                <section class="nvp-panel" aria-labelledby="nvp-overview-title">
                    <div class="nvp-panel-heading">
                        <div>
                            <span class="nvp-eyebrow">OVERSIGT</span>
                            <h2 id="nvp-overview-title">Serverstatus</h2>
                        </div>
                        @if($service->upstream_server_id)
                            <form method="POST" action="{{ route('store.client.vps.action', $service->id) }}">
                                @csrf
                                <input type="hidden" name="action" value="refresh">
                                <button class="nvp-outline" type="submit">↻ Opdatér status</button>
                            </form>
                        @endif
                    </div>
                    <div class="nvp-stats">
                        <div class="nvp-stat">
                            <span>DRIFTSTATUS</span>
                            <strong class="{{ $running === true ? 'nvp-text-green' : '' }}">
                                {{ $running === true ? 'Kører' : ($running === false ? 'Stoppet' : 'Ukendt') }}
                            </strong>
                            <small>{{ $service->status === 'active' ? 'Status fra Flax' : 'Afventer aktiv service' }}</small>
                        </div>
                        <div class="nvp-stat">
                            <span>PRIMÆR IP</span>
                            <strong class="nvp-mono">{{ $primaryIp ?: 'Afventer' }}</strong>
                            <small>{{ $primaryIp ? 'Serverens IP-adresse' : 'Ikke tildelt endnu' }}</small>
                        </div>
                        <div class="nvp-stat">
                            <span>OPERATIVSYSTEM</span>
                            <strong>{{ $service->os_label ?: 'Ikke angivet' }}</strong>
                            <small>Installeret OS / skabelon</small>
                        </div>
                        <div class="nvp-stat">
                            <span>SENESTE SYNKRONISERING</span>
                            <strong>{{ $service->last_synced_at ? \Carbon\Carbon::parse($service->last_synced_at)->format('d.m.Y H:i') : 'Aldrig' }}</strong>
                            <small>Opdateres via statusknappen</small>
                        </div>
                    </div>
                </section>

                <section class="nvp-panel" aria-labelledby="nvp-power-title">
                    <div class="nvp-panel-heading">
                        <div>
                            <span class="nvp-eyebrow">SERVERSTYRING</span>
                            <h2 id="nvp-power-title">Strømstyring</h2>
                        </div>
                    </div>
                    @if($service->status === 'active' && $service->upstream_server_id)
                        <p class="nvp-panel-desc">Alle handlinger gælder kun denne VPS. Status hentes fra Flax efter hver handling.</p>
                        <div class="nvp-power-actions">
                            <form method="POST" action="{{ route('store.client.vps.action', $service->id) }}">
                                @csrf
                                <input type="hidden" name="action" value="boot">
                                <button class="nvp-action nvp-action-primary" type="submit" {{ $running === true ? 'disabled' : '' }}>▶ Start</button>
                            </form>
                            <form method="POST" action="{{ route('store.client.vps.action', $service->id) }}" onsubmit="return confirm('Vil du genstarte {{ $service->hostname }}?');">
                                @csrf
                                <input type="hidden" name="action" value="restart">
                                <button class="nvp-action" type="submit">↻ Genstart</button>
                            </form>
                            <form method="POST" action="{{ route('store.client.vps.action', $service->id) }}" onsubmit="return confirm('Vil du lukke {{ $service->hostname }} ned?');">
                                @csrf
                                <input type="hidden" name="action" value="shutdown">
                                <button class="nvp-action" type="submit" {{ $running === false ? 'disabled' : '' }}>⏻ Luk ned</button>
                            </form>
                            <form method="POST" action="{{ route('store.client.vps.action', $service->id) }}" onsubmit="return confirm('Tving slukning af {{ $service->hostname }}? Ikke-gemte data kan gå tabt.');">
                                @csrf
                                <input type="hidden" name="action" value="poweroff">
                                <button class="nvp-action nvp-action-danger" type="submit" {{ $running === false ? 'disabled' : '' }}>■ Tving slukning</button>
                            </form>
                        </div>
                    @elseif($service->status === 'awaiting_payment')
                        <div class="nvp-empty">
                            <strong>VPS'en afventer betaling</strong>
                            <p>Strømstyring bliver tilgængelig, når ordren er betalt og VPS'en er oprettet.</p>
                            <a class="nvp-action nvp-action-primary" href="{{ route('store.vps.checkout', $service->id) }}">Åbn ordre →</a>
                        </div>
                    @elseif($service->status === 'provisioning_failed')
                        <div class="nvp-empty">
                            <strong>VPS'en er ikke oprettet hos Flax</strong>
                            <p>Kontakt support, så den eksisterende ordre kan undersøges og provisioneringen forsøges igen.</p>
                            <a class="nvp-action" href="{{ route('store.client.tickets.create') }}">Kontakt support →</a>
                        </div>
                    @else
                        <div class="nvp-empty">
                            <strong>Strømstyring er ikke tilgængelig</strong>
                            <p>Den aktuelle servicestatus er {{ str_replace('_', ' ', $service->status) }}.</p>
                        </div>
                    @endif
                </section>
            </main>

            <aside class="nvp-sidebar">
                <section class="nvp-panel">
                    <span class="nvp-eyebrow">SERVERKONFIGURATION</span>
                    <h2>Ressourcer</h2>
                    <dl class="nvp-detail-list">
                        <div><dt>Produkt</dt><dd>{{ $service->product_name ?: 'VPS Hosting' }}</dd></div>
                        <div><dt>CPU</dt><dd>{{ $service->vcpu ?: '—' }} vCPU</dd></div>
                        <div><dt>RAM</dt><dd>{{ $service->memory_mb ? number_format($service->memory_mb / 1024, 1, ',', '.') . ' GB' : '—' }}</dd></div>
                        <div><dt>Disk</dt><dd>{{ $service->disk_gb ? $service->disk_gb . ' GB' : '—' }}</dd></div>
                        <div><dt>IPv4</dt><dd>{{ $service->ipv4_count ?? '—' }}</dd></div>
                        <div><dt>Trafik</dt><dd>{{ $service->bandwidth ?: '—' }}</dd></div>
                    </dl>
                </section>

                <section class="nvp-panel" id="nvpCredentials" aria-labelledby="nvp-password-heading">
                    <span class="nvp-eyebrow">ADGANG TIL VPS</span>
                    <h2 id="nvp-password-heading">Password</h2>
                    <div class="nvp-password-identity">
                        <span>Standard login-bruger</span>
                        <strong>{{ stripos((string) $service->os_label, 'windows') !== false ? 'Administrator' : 'root' }}</strong>
                    </div>
                    @if($service->upstream_server_id && in_array($service->status, ['active', 'suspended'], true))
                        @if($hasPassword)
                            <label class="nvp-password-label" for="nvpStoredPassword">Gemt adgangskode</label>
                            <div class="nvp-password-display">
                                <input type="password" id="nvpStoredPassword" class="nvp-password-input" placeholder="••••••••••••" autocomplete="off" readonly aria-label="Gemt VPS-adgangskode">
                                <button type="button" class="nvp-outline" id="nvpPasswordReveal" data-url="{{ route('store.client.vps.password.reveal', $service->id) }}">Vis</button>
                                <button type="button" class="nvp-outline" id="nvpPasswordCopy">Kopiér</button>
                            </div>
                            <p id="nvpPasswordMessage" class="nvp-password-feedback" role="status" aria-live="polite">Adgangskoden er skjult, indtil du trykker Vis.</p>
                        @else
                            <p class="nvp-panel-desc nvp-password-empty">Der er endnu ingen adgangskode gemt i Nodexa.</p>
                        @endif
                        <form method="POST" action="{{ route('store.client.vps.password.save', $service->id) }}" class="nvp-password-form" autocomplete="off">
                            @csrf
                            <label class="nvp-password-label" for="nvpPasswordSave">{{ $hasPassword ? 'Erstat gemt adgangskode' : 'Gem adgangskode' }}</label>
                            <input id="nvpPasswordSave" class="nvp-password-input" type="password" name="password" minlength="8" maxlength="255" required autocomplete="new-password" placeholder="Indtast serverens nuværende kode">
                            <button class="nvp-action nvp-action-primary" type="submit">{{ $hasPassword ? 'Opdatér gemt kode' : 'Gem password' }}</button>
                        </form>
                        @if($hasPassword)
                            <form method="POST" action="{{ route('store.client.vps.password.delete', $service->id) }}" class="nvp-password-remove" onsubmit="return confirm('Fjern den gemte adgangskode fra Nodexa? Serverens faktiske adgangskode ændres ikke.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit">Fjern gemt adgangskode</button>
                            </form>
                        @endif
                    @else
                        <p class="nvp-panel-desc nvp-password-empty">Password kan først gemmes, når VPS'en er provisioneret og tilgængelig.</p>
                    @endif
                    <p class="nvp-password-hint">Dette er en krypteret kopi af adgangskoden, som du selv gemmer i Nodexa. Feltet læser eller ændrer <strong>ikke</strong> serverens faktiske password hos Flax.</p>
                    <a href="https://flaxhosting.com/knowledgebase/10/Sadan-skifter-du-din-kode-til-din-vps..html" target="_blank" rel="noopener noreferrer" class="nvp-password-guide">Sådan nulstiller du password hos Flax ↗</a>
                </section>

                <section class="nvp-panel">
                    <span class="nvp-eyebrow">ABONNEMENT</span>
                    <h2>Fakturering</h2>
                    <dl class="nvp-detail-list">
                        <div><dt>Pris</dt><dd>{{ number_format((float) $service->monthly_price, 2, ',', '.') }} {{ $service->currency }}/md.</dd></div>
                        <div><dt>Abonnement</dt><dd>{{ $service->subscription_status ? ucfirst(str_replace('_', ' ', $service->subscription_status)) : '—' }}</dd></div>
                        <div><dt>Næste faktura</dt><dd>{{ $service->next_invoice_at ? \Carbon\Carbon::parse($service->next_invoice_at)->format('d.m.Y') : '—' }}</dd></div>
                        <div><dt>Udløber</dt><dd>{{ $service->expires_at ? \Carbon\Carbon::parse($service->expires_at)->format('d.m.Y H:i') : '—' }}</dd></div>
                    </dl>
                    <a class="nvp-outline nvp-full-link" href="{{ route('store.client.billing') }}">Billing & ordrer →</a>
                </section>
            </aside>
        </div>
    </div>
</section>

<script>
(function() {
    const panel = document.getElementById('nvpCredentials');
    if (!panel) return;
    const reveal = document.getElementById('nvpPasswordReveal');
    const copy = document.getElementById('nvpPasswordCopy');
    const input = document.getElementById('nvpStoredPassword');
    const message = document.getElementById('nvpPasswordMessage');
    if (!reveal || !copy || !input || !message) return;

    let clearHandle = null;
    let busy = false;
    function clearSecret() {
        if (clearHandle) clearTimeout(clearHandle);
        clearHandle = null;
        input.value = '';
        input.type = 'password';
        reveal.textContent = 'Vis';
    }

    function showMessage(text, failed) {
        message.textContent = text;
        message.classList.toggle('is-error', Boolean(failed));
    }

    async function loadSecret() {
        const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
        if (!token) throw new Error('Sikkerhedstoken mangler. Genindlæs siden.');
        const response = await fetch(reveal.dataset.url, {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json'
            }
        });
        let body = {};
        try { body = await response.json(); } catch (_error) {}
        if (!response.ok || typeof body.password !== 'string') {
            throw new Error(body.message || 'Adgangskoden kunne ikke hentes. Prøv igen.');
        }
        return body.password;
    }

    reveal.addEventListener('click', async () => {
        if (busy) return;
        if (input.value && input.type === 'text') {
            clearSecret();
            showMessage('Adgangskoden er skjult.');
            return;
        }
        busy = true;
        reveal.disabled = true;
        try {
            input.value = await loadSecret();
            input.type = 'text';
            reveal.textContent = 'Skjul';
            showMessage('Adgangskoden vises kun midlertidigt.');
            clearHandle = setTimeout(clearSecret, 30000);
        } catch (error) {
            clearSecret();
            showMessage(error.message, true);
        } finally {
            busy = false;
            reveal.disabled = false;
        }
    });

    copy.addEventListener('click', async () => {
        if (busy) return;
        busy = true;
        copy.disabled = true;
        try {
            const secret = input.value || await loadSecret();
            if (!navigator.clipboard?.writeText) {
                throw new Error('Kopiering kræver en sikker HTTPS-forbindelse.');
            }
            await navigator.clipboard.writeText(secret);
            showMessage('Password kopieret. Undgå at dele det med andre.');
        } catch (error) {
            showMessage(error.message, true);
        } finally {
            busy = false;
            copy.disabled = false;
        }
    });

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) clearSecret();
    });
    window.addEventListener('pagehide', clearSecret);
})();
</script>

@push('styles')
<style>
.nvp-password-identity{display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #20344b;padding:13px 0 14px;margin-top:9px;gap:12px;font-size:12px}.nvp-password-identity span{color:#8da4bd}.nvp-password-identity strong{color:#e6f2ff;font-weight:750}.nvp-password-label{font-size:11px;font-weight:750;color:#acc2d9;display:block;margin:15px 0 7px}.nvp-password-display{display:flex;align-items:center;gap:6px;min-width:0}.nvp-password-input{font:inherit;font-size:12px;background:#06101e;border:1px solid #29445e;border-radius:8px;color:#e6f2ff;min-height:41px;padding:9px 12px;outline:none;min-width:0;width:100%}.nvp-password-input:focus{border-color:#679dcd;box-shadow:0 0 0 2px rgba(87,157,222,.15)}.nvp-password-display .nvp-outline{flex:0 0 auto;min-height:41px;padding:8px;font-size:11px}.nvp-password-feedback{color:#7d98b7;font-size:10px;margin:6px 0 4px}.nvp-password-feedback.is-error{color:#ff9fab}.nvp-password-form{display:grid;gap:9px;margin-top:16px}.nvp-password-form .nvp-password-label{margin:0}.nvp-password-form .nvp-action{justify-self:start}.nvp-password-remove{margin-top:12px}.nvp-password-remove button{background:none;border:0;padding:0;color:#e59eaa;text-decoration:underline;cursor:pointer;font-size:11px}.nvp-password-empty{margin-top:15px}.nvp-password-hint{font-size:11px;line-height:1.6;color:#8097b0;margin:17px 0 8px}.nvp-password-guide{font-size:11px;color:#8ec5ff;text-decoration:underline}.nvp-password-display .nvp-password-input{flex:1 1 100px}@media(max-width:380px){.nvp-password-display{flex-wrap:wrap}.nvp-password-display .nvp-password-input{flex:1 1 100%}.nvp-password-display .nvp-outline{flex:1}}.nvp-page{padding:29px 0 85px;background:#050b14}.nvp-page .container{width:min(1180px,calc(100% - 32px))}.nvp-breadcrumb{display:flex;gap:10px;align-items:center;color:#7f96b2;font-size:12px;flex-wrap:wrap;margin-bottom:24px}.nvp-breadcrumb a:hover{color:#f2f6ff}.nvp-breadcrumb strong{color:#c8d8ef;font-weight:650}.nvp-breadcrumb span{color:#42607c}.nvp-header{display:flex;justify-content:space-between;align-items:center;gap:20px;margin-bottom:25px}.nvp-kicker,.nvp-eyebrow{font-size:10px;letter-spacing:.13em;font-weight:800;color:#7190b1}.nvp-kicker span{padding:0 5px}.nvp-header h1{font-size:clamp(29px,4vw,42px);line-height:1.18;letter-spacing:-.04em;margin:8px 0 7px}.nvp-header p{color:#8ca2be;font-size:13px;margin:0}.nvp-header-actions{display:flex;gap:12px;align-items:center;flex-wrap:wrap}.nvp-outline{display:inline-flex;justify-content:center;align-items:center;border:1px solid #29415c;color:#d9e7f8;padding:9px 13px;border-radius:9px;font-size:12px;font-weight:650;background:#0b1826;cursor:pointer;min-height:39px}.nvp-outline:hover{background:#15263b}.nvp-status{display:inline-flex;align-items:center;gap:8px;border-radius:8px;padding:8px 11px;font-size:10px;letter-spacing:.035em;font-weight:800;background:#3b301c;color:#e8c985}.nvp-status-dot{width:7px;height:7px;border-radius:50%;background:currentColor}.nvp-status.is-active{background:#102f26;color:#71dcaf}.nvp-status.is-error{background:#3a1e23;color:#f2a2a9}.nvp-layout{display:grid;grid-template-columns:minmax(0,1.7fr) minmax(275px,.85fr);gap:17px;align-items:start}.nvp-main,.nvp-sidebar{display:grid;gap:17px;min-width:0}.nvp-panel{border:1px solid #20344b;border-radius:14px;background:#091522;padding:23px;min-width:0}.nvp-panel h2{font-size:21px;letter-spacing:-.025em;margin:5px 0 0;font-weight:750}.nvp-panel-heading{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:22px}.nvp-panel-heading form{margin:0}.nvp-stats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.nvp-stat{border:1px solid #1c3047;border-radius:10px;padding:17px;background:#0c1b2a;min-width:0}.nvp-stat>span{font-size:9px;font-weight:850;letter-spacing:.09em;color:#6f8dab;display:block}.nvp-stat>strong{display:block;color:#e8f1ff;font-size:17px;overflow-wrap:anywhere;line-height:1.35;margin:8px 0 4px}.nvp-stat>small{color:#7c96b2;font-size:11px}.nvp-stat .nvp-text-green{color:#77ddb2}.nvp-mono{font-family:ui-monospace,SFMono-Regular,Consolas,monospace}.nvp-panel-desc{font-size:12px;color:#8ca1bb;margin:-7px 0 19px}.nvp-power-actions{display:flex;flex-wrap:wrap;gap:10px}.nvp-power-actions form{margin:0}.nvp-action{display:inline-flex;justify-content:center;align-items:center;gap:6px;min-height:42px;border-radius:9px;padding:10px 16px;background:#14273a;color:#e8f1ff;border:1px solid #35516d;font-family:inherit;font-size:12px;font-weight:750;cursor:pointer}.nvp-action:hover{background:#1e364f}.nvp-action-primary{background:#1b518e;border-color:#326cad;color:#fff}.nvp-action-primary:hover{background:#2263aa}.nvp-action-danger{color:#f5abb4;border-color:#5f3541;background:#291923}.nvp-action-danger:hover{background:#38222e}.nvp-action:disabled{opacity:.45;cursor:not-allowed}.nvp-detail-list{margin:17px 0 0}.nvp-detail-list div{display:flex;align-items:baseline;justify-content:space-between;gap:15px;padding:12px 0;border-bottom:1px solid #1b3045}.nvp-detail-list div:last-child{border-bottom:0}.nvp-detail-list dt{color:#839ab6;font-size:12px}.nvp-detail-list dd{margin:0;text-align:right;color:#e4edfa;font-size:12px;font-weight:650;overflow-wrap:anywhere}.nvp-full-link{margin-top:17px;width:100%}.nvp-notice{border:1px solid #36526b;background:#102337;color:#c8def8;border-radius:10px;padding:12px 15px;margin-bottom:17px;font-size:12px}.nvp-notice strong{display:block;margin-bottom:3px}.nvp-notice-error{background:#2b1b25;color:#f2bbc7;border-color:#69404e}.nvp-notice-success{background:#102e26;color:#b4f4da;border-color:#2c604f}.nvp-empty{border:1px dashed #2d4460;border-radius:10px;background:#0c1b2a;padding:22px}.nvp-empty strong{display:block;color:#e0edfa;font-size:14px}.nvp-empty p{font-size:12px;color:#90a7c2;margin:8px 0 17px}.nvp-empty .nvp-action{margin-top:0}@media(max-width:890px){.nvp-layout{grid-template-columns:1fr}.nvp-sidebar{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:600px){.nvp-header{align-items:flex-start;flex-direction:column}.nvp-header-actions{width:100%;justify-content:space-between}.nvp-sidebar{grid-template-columns:1fr}.nvp-stats{grid-template-columns:1fr}.nvp-panel{padding:17px}.nvp-panel-heading{align-items:flex-start;flex-wrap:wrap}.nvp-power-actions form{flex:1 1 42%}.nvp-power-actions button{width:100%}}
</style>
@endpush
@endsection
