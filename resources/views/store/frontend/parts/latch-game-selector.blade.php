{{-- Choose only games that Nodexa actually sells. No fabricated plan/region prices. --}}
@php($availableGames = $products->pluck('game')->filter()->unique()->values())
<div class="nx-latch-game-select" id="nxLatchGameSelect">
    <div class="nx-latch-game-header"><strong>VÆLG SPILTYPE</strong><span>Se de aktive hostingpakker</span></div>
    <div class="nx-latch-game-choices" role="group" aria-label="Vælg spil">
        <button type="button" data-latch-game="all" aria-pressed="true" class="is-active"><span>◈</span>Alle spil</button>
        @foreach($availableGames as $game)
        <button type="button" data-latch-game="{{ mb_strtolower(trim($game)) }}" aria-pressed="false">
            <span>{{ mb_strtolower(trim($game)) === 'minecraft' ? '▦' : (mb_strtolower(trim($game)) === 'fivem' ? '◆' : '◉') }}</span>{{ $game }}
        </button>
        @endforeach
    </div>
</div>
<script>
(function () {
    function init() {
        var selector = document.getElementById('nxLatchGameSelect');
        var catalog = document.getElementById('nxLatchCatalog');
        var empty = document.getElementById('nxLatchNoMatch');
        if (!selector || !catalog) return;
        var cards = catalog.querySelectorAll('[data-hosting-game]');
        var buttons = selector.querySelectorAll('button[data-latch-game]');
        buttons.forEach(function (button) {
            button.addEventListener('click', function () {
                var value = button.getAttribute('data-latch-game');
                buttons.forEach(function (b) {
                    var active = b === button;
                    b.classList.toggle('is-active', active);
                    b.setAttribute('aria-pressed', active ? 'true' : 'false');
                });
                var shown = 0;
                cards.forEach(function (card) {
                    var visible = value === 'all' || (card.getAttribute('data-hosting-game') || '').trim() === value;
                    card.hidden = !visible;
                    if (visible) shown++;
                });
                if (empty) empty.hidden = shown > 0;
            });
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, {once:true});
    else init();
})();
</script>
