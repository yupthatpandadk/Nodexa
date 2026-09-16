@php
    $nodexaIdentityData = [];
    $nodexaIdentityFile = '/var/lib/nodexa/version.json';
    if (is_readable($nodexaIdentityFile)) {
        $decodedIdentity = json_decode((string) file_get_contents($nodexaIdentityFile), true);
        $nodexaIdentityData = is_array($decodedIdentity) ? $decodedIdentity : [];
    }
    $nodexaInstalledVersion = (string) ($nodexaIdentityData['version'] ?? 'unknown');
@endphp

<script id="nodexa-global-theme-bootstrap">
    (function () {
        var KEY = 'nodexa_theme_accent';
        var DEFAULT_ACCENT = '#42e9a6';

        function normalize(value) {
            value = String(value || '').trim();
            return /^#[0-9a-fA-F]{6}$/.test(value) ? value.toLowerCase() : DEFAULT_ACCENT;
        }

        function rgb(hex) {
            return [parseInt(hex.slice(1, 3), 16), parseInt(hex.slice(3, 5), 16), parseInt(hex.slice(5, 7), 16)];
        }

        function toHex(values) {
            return '#' + values.map(function (value) {
                return Math.max(0, Math.min(255, Math.round(value))).toString(16).padStart(2, '0');
            }).join('');
        }

        function mix(a, b, amount) {
            var left = rgb(a), right = rgb(b);
            return toHex(left.map(function (value, index) { return value + (right[index] - value) * amount; }));
        }

        function readCookie() {
            var prefix = KEY + '=', parts = String(document.cookie || '').split(';');
            for (var i = 0; i < parts.length; i++) {
                var item = parts[i].trim();
                if (item.indexOf(prefix) === 0) {
                    try { return decodeURIComponent(item.slice(prefix.length)); } catch (_) { return item.slice(prefix.length); }
                }
            }
            return '';
        }

        function persist(value) {
            var accent = normalize(value);
            try { localStorage.setItem(KEY, accent); } catch (_) {}
            var cookie = KEY + '=' + encodeURIComponent(accent) + '; Path=/; Max-Age=31536000; SameSite=Lax';
            if (window.location.protocol === 'https:') cookie += '; Secure';
            document.cookie = cookie;
            return accent;
        }

        function apply(value, shouldPersist) {
            var accent = normalize(value), values = rgb(accent), root = document.documentElement;
            var background = mix('#03070a', accent, 0.075), backgroundDeep = mix('#020507', accent, 0.045);
            var background2 = mix('#071014', accent, 0.10), surface = mix('#091217', accent, 0.125);
            var surface2 = mix('#0c1820', accent, 0.16), surfaceHover = mix('#102029', accent, 0.21), accent2 = mix(accent, '#ffffff', 0.20);
            root.style.setProperty('--nodexa-accent', accent); root.style.setProperty('--nodexa-accent-2', accent2);
            root.style.setProperty('--nodexa-accent-rgb', values.join(', ')); root.style.setProperty('--nodexa-accent-soft', 'rgba(' + values.join(', ') + ', 0.12)');
            root.style.setProperty('--nodexa-border', 'rgba(' + values.join(', ') + ', 0.14)'); root.style.setProperty('--nodexa-border-strong', 'rgba(' + values.join(', ') + ', 0.32)');
            root.style.setProperty('--nodexa-surface-glow', 'rgba(' + values.join(', ') + ', 0.07)'); root.style.setProperty('--nodexa-bg', background);
            root.style.setProperty('--nodexa-bg-deep', backgroundDeep); root.style.setProperty('--nodexa-bg-2', background2); root.style.setProperty('--nodexa-surface', surface);
            root.style.setProperty('--nodexa-surface-2', surface2); root.style.setProperty('--nodexa-surface-hover', surfaceHover); root.style.setProperty('--nodexa-text', '#edf7f5'); root.style.setProperty('--nodexa-muted', '#8ba09c');
            root.style.setProperty('--nodexa-admin-bg', background); root.style.setProperty('--nodexa-admin-surface', surface); root.style.setProperty('--nodexa-admin-surface-2', surface2);
            root.style.setProperty('--nodexa-admin-text', '#edf7f5'); root.style.setProperty('--nodexa-admin-muted', '#8ba09c'); root.dataset.nodexaAccent = accent;
            var meta = document.querySelector('meta[name="theme-color"]'); if (meta) meta.setAttribute('content', accent);
            if (shouldPersist !== false) persist(accent); return accent;
        }

        var saved = ''; try { saved = localStorage.getItem(KEY) || ''; } catch (_) {} if (!saved) saved = readCookie(); saved = apply(saved || DEFAULT_ACCENT, true);
        window.NodexaTheme = {key: KEY, defaultAccent: DEFAULT_ACCENT, normalize: normalize, apply: function (value) { return apply(value, true); }, get: function () { return normalize(document.documentElement.dataset.nodexaAccent || saved); }};
        window.addEventListener('nodexa:theme', function (event) { if (event && event.detail && event.detail.accent) apply(event.detail.accent, true); });
        window.addEventListener('storage', function (event) { if (event.key === KEY) apply(event.newValue || DEFAULT_ACCENT, false); });
        window.addEventListener('pageshow', function () { var current = ''; try { current = localStorage.getItem(KEY) || ''; } catch (_) {} if (!current) current = readCookie(); apply(current || DEFAULT_ACCENT, false); });
    })();
</script>

<script id="nodexa-system-identity">
    (function () {
        var version = @json($nodexaInstalledVersion);
        window.NodexaSystem = Object.assign({}, window.NodexaSystem || {}, { version: version });
        function applyIdentity() {
            var footer = document.querySelector('.main-footer');
            if (footer) {
                var oldBrand = footer.querySelector('a[href*="pterodactyl"]');
                if (oldBrand) { var brand = document.createElement('span'); brand.textContent = 'Nodexa Software'; brand.className = oldBrand.className; oldBrand.replaceWith(brand); }
                var right = footer.querySelector('.pull-right'); if (right) right.innerHTML = '<strong><i class="fa fa-fw fa-code-fork"></i></strong> Nodexa v' + version;
            }
            document.querySelectorAll('a[href*="pterodactyl.io"]').forEach(function (link) {
                if (/pterodactyl/i.test(link.textContent || '')) { var replacement = document.createElement('span'); replacement.textContent = 'Nodexa Software'; replacement.className = link.className; link.replaceWith(replacement); }
            });
        }
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', applyIdentity, { once: true }); else applyIdentity();
    })();
</script>

<style id="nodexa-global-theme-base">
    :root {--nodexa-accent:#42e9a6;--nodexa-accent-2:#68edb8;--nodexa-accent-rgb:66,233,166;--nodexa-accent-soft:rgba(66,233,166,.12);--nodexa-border:rgba(66,233,166,.14);--nodexa-border-strong:rgba(66,233,166,.32);--nodexa-bg:#050b0d;--nodexa-bg-deep:#030709;--nodexa-bg-2:#071214;--nodexa-surface:#0a1417;--nodexa-surface-2:#0e1a1e;--nodexa-surface-hover:#122328;--nodexa-text:#edf7f5;--nodexa-muted:#8ba09c}
    html,body{background-color:var(--nodexa-bg)!important}::selection{color:#fff;background:rgba(var(--nodexa-accent-rgb),.42)}
</style>

@if (request()->routeIs('admin.*'))
<script id="nodexa-admin-update-banner">
(function(){function installUpdateBanner(){var wrapper=document.querySelector('.content-wrapper');if(!wrapper||document.getElementById('nodexa-update-available-banner'))return;fetch(@json(route('admin.updates.status')),{headers:{'Accept':'application/json'},credentials:'same-origin'}).then(function(response){if(!response.ok)throw new Error('Status request failed');return response.json()}).then(function(data){if(!data||data.update_available!==true)return;var latestCommit=data.latest&&data.latest.commit?String(data.latest.commit).slice(0,12):'';var banner=document.createElement('div');banner.id='nodexa-update-available-banner';banner.setAttribute('role','status');banner.style.cssText='margin:0;padding:12px 22px;display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;color:#edf7f5;background:linear-gradient(90deg, rgba(var(--nodexa-accent-rgb), .22), rgba(var(--nodexa-accent-rgb), .08));border-bottom:1px solid var(--nodexa-border-strong);box-shadow:0 8px 24px rgba(0,0,0,.14)';var message=document.createElement('div');message.style.cssText='display:flex;align-items:center;gap:10px;min-width:0';var icon=document.createElement('i');icon.className='fa fa-cloud-download';icon.style.cssText='color:var(--nodexa-accent);font-size:18px';var text=document.createElement('span'),strong=document.createElement('strong');strong.textContent='Ny Nodexa-opdatering er tilgængelig';text.appendChild(strong);if(latestCommit)text.appendChild(document.createTextNode(' · GitHub '+latestCommit));message.appendChild(icon);message.appendChild(text);var link=document.createElement('a');link.href=@json(route('admin.updates'));link.className='btn btn-success btn-sm';link.innerHTML='<i class="fa fa-arrow-circle-right"></i> Se opdatering';link.style.cssText='white-space:nowrap';banner.appendChild(message);banner.appendChild(link);wrapper.insertBefore(banner,wrapper.firstChild)}).catch(function(){})}if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',installUpdateBanner,{once:true});else installUpdateBanner()})();
</script>
@endif

@if (request()->routeIs('admin.discord-bot.modules.module'))
<style id="nodexa-discord-boolean-sliders">
.ms-field.nx-boolean-field{display:flex;align-items:center;justify-content:space-between;gap:18px;min-height:52px;padding:10px 0;border-bottom:1px solid rgba(125,211,252,.07)}.ms-field.nx-boolean-field>label:first-child{margin:0!important;color:#e8f3f6!important}.nx-bool-state{display:block;margin-top:4px;color:#708993;font-size:10px}.nx-boolean-field .switch{margin:0;flex:none}
</style>
<script id="nodexa-discord-boolean-sliders-script">
(function(){
 function truthy(value){return ['1','true','yes','on'].indexOf(String(value||'').toLowerCase())!==-1}
 function upgrade(){
  document.querySelectorAll('.ms-field').forEach(function(field){
   if(field.dataset.nxBoolean==='1')return;
   var title=field.querySelector(':scope > label');
   var input=field.querySelector(':scope > input');
   if(!title||!input||title.textContent.indexOf('(1/0)')===-1)return;
   var originalName=input.name, originalValue=input.value;
   if(!originalName)return;
   title.textContent=title.textContent.replace(/\s*\(1\/0\)\s*/g,'').trim();
   var state=document.createElement('span');state.className='nx-bool-state';state.textContent=truthy(originalValue)?'Til':'Fra';title.appendChild(state);
   var hidden=document.createElement('input');hidden.type='hidden';hidden.name=originalName;hidden.value='0';
   var toggle=document.createElement('label');toggle.className='switch';toggle.setAttribute('aria-label',title.firstChild.textContent.trim());
   var checkbox=document.createElement('input');checkbox.type='checkbox';checkbox.name=originalName;checkbox.value='1';checkbox.checked=truthy(originalValue);
   checkbox.addEventListener('change',function(){state.textContent=checkbox.checked?'Til':'Fra'});
   var slider=document.createElement('span');slider.className='slider';
   toggle.appendChild(checkbox);toggle.appendChild(slider);input.replaceWith(hidden,toggle);field.classList.add('nx-boolean-field');field.dataset.nxBoolean='1';
  });
 }
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',upgrade,{once:true});else upgrade();
})();
</script>
@endif
