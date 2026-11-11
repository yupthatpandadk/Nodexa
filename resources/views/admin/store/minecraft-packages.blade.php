<section class="box nx-minecraft-packages" aria-labelledby="nx-minecraft-title">
    <div class="nx-minecraft-head">
        <div>
            <span class="nx-minecraft-kicker">Minecraft Hosting</span>
            <h3 id="nx-minecraft-title"><i class="fa fa-cubes" aria-hidden="true"></i> Minecraft-pakker med bloknavne</h3>
            <p>Opret Dirt, Grass, Stone, Iron, Gold, Diamond, Emerald og Obsidian til dit Minecraft Egg. Du kan justere priserne, før pakkerne oprettes.</p>
        </div>
        <span class="nx-minecraft-info"><i class="fa fa-eye-slash" aria-hidden="true"></i> Oprettes som skjulte</span>
    </div>

    <form method="POST" action="{{ route('admin.store.egg-packages.minecraft.generate') }}" class="nx-minecraft-form">
        @csrf

        <div class="nx-minecraft-options">
            <div class="form-group" style="margin:0;min-width:0">
                <label for="minecraft-egg">Pterodactyl Minecraft Egg</label>
                <select id="minecraft-egg" name="egg_id" class="form-control" required {{ $minecraftEggs->isEmpty() ? 'disabled' : '' }}>
                    <option value="">Vælg dit Minecraft Egg</option>
                    @foreach($minecraftEggs as $egg)
                        <option value="{{ $egg->id }}" {{ (string) old('egg_id') === (string) $egg->id ? 'selected' : '' }}>
                            {{ $egg->name }} · {{ $egg->nest?->name ?? 'Ukendt Nest' }} (#{{ $egg->id }})
                        </option>
                    @endforeach
                </select>
            </div>
            <p>CPU er vist i Pterodactyl-procent (100 % ≈ én CPU-kerne). Ressourcer og priser er forslag, som skal passe til din faktiske nodekapacitet.</p>
        </div>

        @if($minecraftEggs->isEmpty())
            <div class="alert alert-warning" style="margin:15px 0 0">
                Ingen Minecraft Eggs fundet. Installér først et Egg til eksempelvis Paper, Spigot, Fabric eller Forge under Admin → Nests → Eggs.
            </div>
        @endif

        <div class="nx-minecraft-grid">
            @foreach($minecraftProfiles as $tier => $profile)
                <article class="nx-minecraft-card">
                    <label class="nx-minecraft-card-select" for="minecraft-tier-{{ $tier }}">
                        <input id="minecraft-tier-{{ $tier }}" type="checkbox" name="tiers[]" value="{{ $tier }}"
                               {{ in_array($tier, old('tiers', array_keys($minecraftProfiles)), true) ? 'checked' : '' }}>
                        <span class="nx-minecraft-avatar nx-minecraft-avatar--{{ $tier }}" aria-hidden="true"></span>
                        <span class="nx-minecraft-card-name">
                            <strong>{{ $profile['name'] }}</strong>
                            <small>{{ $tier === 'stone' ? 'Populær' : 'Minecraft' }}</small>
                        </span>
                    </label>
                    <div class="nx-minecraft-specs">
                        <span><strong>{{ number_format($profile['memory'] / 1024, ($profile['memory'] % 1024 ? 1 : 0), ',', '.') }} GB</strong> RAM</span>
                        <span><strong>{{ $profile['cpu'] }}%</strong> CPU</span>
                        <span><strong>{{ $profile['disk'] / 1024 }} GB</strong> SSD</span>
                    </div>
                    <div class="nx-minecraft-meta">
                        {{ $profile['databases'] }} DB · {{ $profile['backups'] }} backups · {{ $profile['allocations'] }} port{{ $profile['allocations'] === 1 ? '' : 'e' }}
                    </div>
                    <div class="nx-minecraft-price">
                        <label for="minecraft-price-{{ $tier }}">Pris pr. måned (DKK)</label>
                        <input class="form-control input-sm" id="minecraft-price-{{ $tier }}" type="number" name="prices[{{ $tier }}]"
                               min="0.01" max="99999" step="0.01" value="{{ old('prices.'.$tier, $profile['price']) }}">
                    </div>
                </article>
            @endforeach
        </div>

        <div class="nx-minecraft-footer">
            <div>
                <strong><i class="fa fa-lock" aria-hidden="true"></i> Ingen automatisk offentliggørelse</strong>
                <p>Alle nye pakker er deaktiverede. Godkend dem under Storefront & Billing, før kunderne kan bestille. Eksisterende pakker og abonnementspriser ændres ikke.</p>
            </div>
            <button type="submit" class="btn btn-primary" {{ $minecraftEggs->isEmpty() ? 'disabled' : '' }}>
                <i class="fa fa-plus-circle" aria-hidden="true"></i> Opret valgte Minecraft-pakker
            </button>
        </div>
    </form>
</section>
