{{-- Shared live catalog data; each layout gives these cards a different composition. --}}
@php($visibleProducts = isset($limit) ? $products->take($limit) : $products)
<div class="nx-theme-plans nx-theme-plans--{{ $variant ?? 'grid' }}" id="{{ $catalogId ?? 'nxThemePlans' }}">
    @forelse($visibleProducts as $p)
        <article class="nx-theme-plan {{ $p->featured ? 'is-featured' : '' }}" data-hosting-game="{{ mb_strtolower((string) $p->game) }}">
            <div class="nx-theme-plan-head">
                <span class="nx-theme-plan-type">{{ $p->game }}</span>
                @if($p->featured)<span class="nx-theme-plan-featured">★ Anbefalet</span>@endif
            </div>
            <h3>{{ $p->name }}</h3>
            <p class="nx-theme-plan-description">{{ $p->description ?: 'Hosting med Nodexa Control Panel.' }}</p>
            <div class="nx-theme-price">
                @if($globalSale)
                    <span class="nx-theme-sale">{{ $globalSale->label }} · -{{ number_format($globalSale->percent,0,',','.') }}%</span>
                    <small class="nx-theme-price-old">{{ number_format((float) $p->price_monthly,2,',','.') }} kr</small>
                    <strong>{{ number_format(round((float) $p->price_monthly * (1-((float) $globalSale->percent/100)),2),2,',','.') }} kr</strong>
                @else
                    <strong>{{ number_format((float) $p->price_monthly,2,',','.') }} kr</strong>
                @endif
                <span>/ måned</span>
            </div>
            <div class="nx-theme-plan-specs">
                <span><b>{{ number_format($p->memory / 1024, ($p->memory % 1024 ? 1 : 0), ',', '.') }} GB</b> RAM</span>
                <span><b>{{ $p->cpu }}%</b> CPU</span>
                <span><b>{{ number_format($p->disk / 1024, ($p->disk % 1024 ? 1 : 0), ',', '.') }} GB</b> SSD</span>
                <span>{{ $p->backups }} backups</span>
            </div>
            <a href="{{ route('store.product', $p) }}" class="nx-theme-order">Konfigurér server <span aria-hidden="true">↗</span></a>
        </article>
    @empty
        <div class="nx-theme-empty"><h3>Ingen aktive serverpakker endnu</h3><p>Aktivér dine pakker under Admin → Storefront & Billing for at vise dem her.</p></div>
    @endforelse
</div>