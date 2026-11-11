<div class="nx-theme-filters" id="nxThemeFilters" role="group" aria-label="Filtrér serverpakker">
    <button type="button" data-filter="all" class="is-active" aria-pressed="true">Alle</button>
    <button type="button" data-filter="minecraft" aria-pressed="false">Minecraft</button>
    <button type="button" data-filter="fivem" aria-pressed="false">FiveM</button>
    <button type="button" data-filter="other" aria-pressed="false">Andre spil</button>
</div>
<script>
(function(){
    function initCatalogFilters() {
    var root=document.getElementById('nxThemeFilters');
    if(!root)return;
    var cards=document.querySelectorAll('#nxThemeCatalog [data-hosting-game]');
    var empty=document.getElementById('nxThemeNoMatches');
    root.querySelectorAll('button[data-filter]').forEach(function(button){
        button.addEventListener('click',function(){
            var selected=button.getAttribute('data-filter');
            root.querySelectorAll('button[data-filter]').forEach(function(b){
                var active=b===button;
                b.classList.toggle('is-active',active);
                b.setAttribute('aria-pressed',active?'true':'false');
            });
            var shown=0;
            cards.forEach(function(card){
                var game=(card.getAttribute('data-hosting-game')||'').toLowerCase();
                var visible=selected==='all'||game===selected||(selected==='other'&&game!=='minecraft'&&game!=='fivem');
                card.hidden=!visible;
                if(visible)shown++;
            });
            if(empty)empty.hidden=shown>0;
        });
    });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCatalogFilters, { once: true });
    } else {
        initCatalogFilters();
    }
})();
</script>