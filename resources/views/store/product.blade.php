@extends('store.layout')
@section('title', $product->name)
@section('description', $product->description ?: 'Konfigurér og bestil din Nodexa server.')

@section('content')
<section class="pagehero">
    <div class="container">
        <span class="tag">{{ $product->game }}</span>
        <h1>{{ $product->name }}</h1>
        <p class="lead">{{ $product->description ?: 'Konfigurér din server og fortsæt gennem Nodexa checkout.' }}</p>
        <div class="steps">
            <div class="step active"><i>1</i><span>Konfiguration</span></div><i class="stepSep"></i>
            <div class="step"><i>2</i><span>Checkout</span></div><i class="stepSep"></i>
            <div class="step"><i>3</i><span>Aktivering</span></div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container" style="max-width:1050px">
        <div class="product-layout">
            <div>
                <div class="card">
                    <div class="sectionNo">SERVER SPECIFICATION</div>
                    <h2>Din serverpakke</h2>
                    <div class="specs" style="font-size:14px;gap:13px">
                        <span class="resource"><b>{{ $product->memory }} MB</b>&nbsp; RAM</span>
                        <span class="resource"><b>{{ $product->cpu }}%</b>&nbsp; CPU</span>
                        <span class="resource"><b>{{ $product->disk }} MB</b>&nbsp; SSD</span>
                        <span class="resource"><b>{{ $product->backups }}</b>&nbsp; backups</span>
                        <span class="resource"><b>{{ $product->databases }}</b>&nbsp; databaser</span>
                        <span class="resource"><b>{{ $product->allocations }}</b>&nbsp; allocations</span>
                    </div>
                </div>
                <div class="card" style="margin-top:15px">
                    <div class="sectionNo">INCLUDED PLATFORM</div>
                    <h3>Nodexa Control Panel</h3>
                    <p class="muted">Serveren administreres gennem Nodexa med live konsol, filhåndtering, SFTP, databaser, backups, schedules og netværksstyring.</p>
                </div>
                @if($isMinecraft)
                    <div class="card" style="margin-top:15px">
                        <div class="sectionNo">MINECRAFT INSTALLATION</div>
                        <h3>Vælg den rigtige servertype</h3>
                        <p class="muted">Du kan vælge Paper til plugins, Fabric eller Forge til mods, eller Vanilla til den originale Minecraft-oplevelse. Listen hentes fra de Eggs, der faktisk er installeret i Minecraft Nest.</p>
                        <p class="muted" style="margin-top:8px">Tilgængelige Minecraft-versioner afhænger af det valgte Eggs installationsscript. Et Egg uden versionsstyring bruger sine egne standardindstillinger.</p>
                    </div>
                @endif
            </div>

            <aside class="card featured">
                <div class="productTop">
                    <span class="tag">Månedlig hosting</span><span class="miniLabel">DKK</span>
                </div>

                @if($globalSale)
                    <div class="miniLabel" style="color:#75e3b1;margin:8px 0 4px">
                        {{ $globalSale->label }} · -{{ number_format($globalSale->percent,0,',','.') }}% i {{ $globalSale->duration_months }} mdr.
                    </div>
                    <div style="font-size:12px;color:#637b96;text-decoration:line-through">
                        {{ number_format($product->price_monthly,2,',','.') }} kr
                    </div>
                    <div class="price">
                        {{ number_format(round((float)$product->price_monthly*(1-((float)$globalSale->percent/100)),2),2,',','.') }} kr <small>/ måned</small>
                    </div>
                @else
                    <div class="price">{{ number_format($product->price_monthly,2,',','.') }} kr <small>/ måned</small></div>
                @endif

                <div class="notice">Ordren oprettes først. Serveren installeres efter registreret betaling.</div>

                @if($errors->any())
                    <div class="notice" role="alert" style="margin-top:12px;border-color:#b65a63;color:#ffe4e6">
                        {{ $errors->first() }}
                    </div>
                @endif

                @auth
                    <form method="POST" action="{{ route('store.cart.add-server', $product) }}" class="nodexa-product-form">
                        @csrf
                        <label class="formLabel" for="server_name">Servernavn</label>
                        <input class="input" id="server_name" name="server_name" required minlength="3" maxlength="100"
                               value="{{ old('server_name') }}" placeholder="Min server">

                        @if($isMinecraft)
                            <fieldset class="minecraft-order-options">
                                <legend>Minecraft-indstillinger</legend>
                                <label class="formLabel" for="minecraft_egg_id">Servertype / software</label>
                                <select class="input" id="minecraft_egg_id" name="minecraft_egg_id"
                                        required {{ $minecraftEggs->isEmpty() ? 'disabled' : '' }}>
                                    <option value="">Vælg Paper, Fabric, Forge osv.</option>
                                    @foreach($minecraftEggs as $egg)
                                        <option value="{{ $egg->id }}"
                                            data-version-support="{{ !empty($minecraftVersionSupport[$egg->id]) ? '1' : '0' }}"
                                            {{ (string) old('minecraft_egg_id') === (string) $egg->id ? 'selected' : '' }}>
                                            {{ $egg->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="minecraft-hint">Listen opdateres automatisk, når nye Eggs tilføjes i Minecraft Nest.</p>

                                <div id="minecraft-version-area" hidden>
                                    <label class="formLabel" for="minecraft_version">Minecraft Java-version</label>
                                    <select class="input" id="minecraft_version" name="minecraft_version" disabled>
                                        @foreach($minecraftVersions as $version)
                                            <option value="{{ $version }}" {{ old('minecraft_version', 'latest') === $version ? 'selected' : '' }}>
                                                {{ $version === 'latest' ? 'Automatisk version efter Egg (latest)' : $version }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <p class="minecraft-hint">Versionerne hentes fra Mojangs officielle udgivelsesliste. Ikke alle servertyper understøtter alle versioner.</p>
                                </div>

                                <p id="minecraft-version-unavailable" class="minecraft-hint" hidden>
                                    Dette Egg har ingen genkendelig Minecraft-versionsvariabel. Installationen bruger derfor Eggs egne versionsindstillinger.
                                </p>

                                @if($minecraftEggs->isEmpty())
                                    <div class="notice" role="alert" style="margin-top:10px">
                                        Der er endnu ingen Eggs i Minecraft Nest. Kontakt administrator, før du bestiller.
                                    </div>
                                @endif
                            </fieldset>
                        @endif

                        <button type="submit" class="btn primary" style="width:100%;margin-top:12px"
                            {{ $isMinecraft && $minecraftEggs->isEmpty() ? 'disabled' : '' }}>
                            Læg i kurv →
                        </button>
                    </form>
                @else
                    <a class="btn primary" style="width:100%;margin-top:18px" href="/auth/login">Log ind for at bestille →</a>
                    <p class="micro" style="text-align:center">En Nodexa-konto er nødvendig for at oprette ordren.</p>
                @endauth
            </aside>
        </div>
    </div>
</section>

@if($isMinecraft)
<script>
(function () {
    const egg = document.getElementById('minecraft_egg_id');
    const group = document.getElementById('minecraft-version-area');
    const version = document.getElementById('minecraft_version');
    const unavailable = document.getElementById('minecraft-version-unavailable');
    if (!egg || !group || !version || !unavailable) return;

    function syncMinecraftVersion() {
        const selected = egg.options[egg.selectedIndex];
        const hasEgg = !!egg.value;
        const supported = hasEgg && selected && selected.dataset.versionSupport === '1';

        group.hidden = !supported;
        version.disabled = !supported;
        version.required = !!supported;
        unavailable.hidden = !hasEgg || supported;
    }

    egg.addEventListener('change', syncMinecraftVersion);
    syncMinecraftVersion();
})();
</script>
@endif

@push('styles')
<style>
.product-layout { display:grid; grid-template-columns:minmax(0,1.35fr) minmax(300px,.65fr); gap:18px; align-items:start; }
.nodexa-product-form { display:flex; flex-direction:column; gap:7px; margin-top:16px; }
.nodexa-product-form .input { width:100%; min-width:0; }
.minecraft-order-options {
    min-width:0; border:1px solid var(--line2); border-radius:11px;
    background:color-mix(in srgb,var(--card) 85%,var(--surface)); padding:16px;
    margin:13px 0 0; display:flex; flex-direction:column; gap:8px;
}
.minecraft-order-options legend { width:auto; padding:0 7px; margin:0; font-size:12px; font-weight:800; color:#d5e6f8; }
.minecraft-order-options select { padding-right:28px; color-scheme:dark; }
.minecraft-order-options .minecraft-hint { font-size:11px; line-height:1.55; color:#9aacc0; margin:2px 0 5px; }
.minecraft-order-options #minecraft-version-area { margin-top:8px; }
.minecraft-order-options #minecraft-version-area:not([hidden]) { display:flex; flex-direction:column; gap:8px; }
.minecraft-order-options [hidden] { display:none!important; }
@media(max-width:760px) { .product-layout { grid-template-columns:1fr; } }
</style>
@endpush
@endsection
