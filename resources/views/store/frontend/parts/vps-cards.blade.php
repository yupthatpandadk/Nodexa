<div class="nx-theme-plans nx-theme-plans--{{ $variant ?? 'grid' }}">
    @forelse($products as $product)
        <article class="nx-theme-plan {{ $product->featured ? 'is-featured' : '' }}">
            <div class="nx-theme-plan-head"><span class="nx-theme-plan-type">VPS CLOUD</span>@if($product->featured)<span class="nx-theme-plan-featured">★ Anbefalet</span>@endif</div>
            <h3>{{ \Pterodactyl\Services\Nodexa\CustomerBranding::label($product->name) }}</h3>
            <p class="nx-theme-plan-description">{{ \Pterodactyl\Services\Nodexa\CustomerBranding::label($product->description, 'Virtuel privat server med automatisk provisionering.') }}</p>
            <div class="nx-theme-price">
                @if($globalSale)
                    <span class="nx-theme-sale">{{ $globalSale->label }} · -{{ number_format($globalSale->percent,0,',','.') }}%</span>
                    <small class="nx-theme-price-old">{{ number_format((float)$product->monthly_price,2,',','.') }} kr</small>
                    <strong>{{ number_format(round((float)$product->monthly_price * (1-((float)$globalSale->percent/100)),2),2,',','.') }} kr</strong>
                @else
                    <strong>{{ number_format((float)$product->monthly_price,2,',','.') }} kr</strong>
                @endif
                <span>/ måned</span>
            </div>
            <div class="nx-theme-plan-specs">
                <span><b>{{ $product->vcpu ?: '—' }}</b> vCPU</span>
                <span><b>{{ $product->memory_mb ? number_format($product->memory_mb / 1024,1,',','.') : '—' }} GB</b> RAM</span>
                <span><b>{{ $product->disk_gb ?: '—' }} GB</b> SSD</span>
                <span>{{ $product->ipv4_count }} IPv4</span>
            </div>
            <a href="{{ route('store.vps.product', $product->slug) }}" class="nx-theme-order">Konfigurér VPS <span aria-hidden="true">↗</span></a>
        </article>
    @empty
        <div class="nx-theme-empty"><h3>Ingen VPS-pakker endnu</h3><p>Aktivér dine VPS-produkter i Nodexa for at vise dem.</p></div>
    @endforelse
</div>