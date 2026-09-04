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
            return [
                parseInt(hex.slice(1, 3), 16),
                parseInt(hex.slice(3, 5), 16),
                parseInt(hex.slice(5, 7), 16)
            ];
        }

        function toHex(values) {
            return '#' + values.map(function (value) {
                return Math.max(0, Math.min(255, Math.round(value))).toString(16).padStart(2, '0');
            }).join('');
        }

        function mix(a, b, amount) {
            var left = rgb(a);
            var right = rgb(b);
            return toHex(left.map(function (value, index) {
                return value + (right[index] - value) * amount;
            }));
        }

        function readCookie() {
            var prefix = KEY + '=';
            var parts = String(document.cookie || '').split(';');
            for (var i = 0; i < parts.length; i++) {
                var item = parts[i].trim();
                if (item.indexOf(prefix) === 0) {
                    try { return decodeURIComponent(item.slice(prefix.length)); }
                    catch (_) { return item.slice(prefix.length); }
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
            var accent = normalize(value);
            var values = rgb(accent);
            var root = document.documentElement;
            var background = mix('#03070a', accent, 0.075);
            var backgroundDeep = mix('#020507', accent, 0.045);
            var background2 = mix('#071014', accent, 0.10);
            var surface = mix('#091217', accent, 0.125);
            var surface2 = mix('#0c1820', accent, 0.16);
            var surfaceHover = mix('#102029', accent, 0.21);
            var accent2 = mix(accent, '#ffffff', 0.20);

            root.style.setProperty('--nodexa-accent', accent);
            root.style.setProperty('--nodexa-accent-2', accent2);
            root.style.setProperty('--nodexa-accent-rgb', values.join(', '));
            root.style.setProperty('--nodexa-accent-soft', 'rgba(' + values.join(', ') + ', 0.12)');
            root.style.setProperty('--nodexa-border', 'rgba(' + values.join(', ') + ', 0.14)');
            root.style.setProperty('--nodexa-border-strong', 'rgba(' + values.join(', ') + ', 0.32)');
            root.style.setProperty('--nodexa-surface-glow', 'rgba(' + values.join(', ') + ', 0.07)');
            root.style.setProperty('--nodexa-bg', background);
            root.style.setProperty('--nodexa-bg-deep', backgroundDeep);
            root.style.setProperty('--nodexa-bg-2', background2);
            root.style.setProperty('--nodexa-surface', surface);
            root.style.setProperty('--nodexa-surface-2', surface2);
            root.style.setProperty('--nodexa-surface-hover', surfaceHover);
            root.style.setProperty('--nodexa-text', '#edf7f5');
            root.style.setProperty('--nodexa-muted', '#8ba09c');

            /* Aliases used by the legacy admin area. */
            root.style.setProperty('--nodexa-admin-bg', background);
            root.style.setProperty('--nodexa-admin-surface', surface);
            root.style.setProperty('--nodexa-admin-surface-2', surface2);
            root.style.setProperty('--nodexa-admin-text', '#edf7f5');
            root.style.setProperty('--nodexa-admin-muted', '#8ba09c');
            root.dataset.nodexaAccent = accent;

            var meta = document.querySelector('meta[name="theme-color"]');
            if (meta) meta.setAttribute('content', accent);
            if (shouldPersist !== false) persist(accent);
            return accent;
        }

        var saved = '';
        try { saved = localStorage.getItem(KEY) || ''; } catch (_) {}
        if (!saved) saved = readCookie();
        saved = apply(saved || DEFAULT_ACCENT, true);

        window.NodexaTheme = {
            key: KEY,
            defaultAccent: DEFAULT_ACCENT,
            normalize: normalize,
            apply: function (value) { return apply(value, true); },
            get: function () { return normalize(document.documentElement.dataset.nodexaAccent || saved); }
        };

        window.addEventListener('nodexa:theme', function (event) {
            if (event && event.detail && event.detail.accent) apply(event.detail.accent, true);
        });
        window.addEventListener('storage', function (event) {
            if (event.key === KEY) apply(event.newValue || DEFAULT_ACCENT, false);
        });
        window.addEventListener('pageshow', function () {
            var current = '';
            try { current = localStorage.getItem(KEY) || ''; } catch (_) {}
            if (!current) current = readCookie();
            apply(current || DEFAULT_ACCENT, false);
        });
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
                if (oldBrand) {
                    var brand = document.createElement('span');
                    brand.textContent = 'Nodexa Software';
                    brand.className = oldBrand.className;
                    oldBrand.replaceWith(brand);
                }

                var right = footer.querySelector('.pull-right');
                if (right) {
                    right.innerHTML = '<strong><i class="fa fa-fw fa-code-fork"></i></strong> Nodexa v' + version;
                }
            }

            document.querySelectorAll('a[href*="pterodactyl.io"]').forEach(function (link) {
                if (/pterodactyl/i.test(link.textContent || '')) {
                    var replacement = document.createElement('span');
                    replacement.textContent = 'Nodexa Software';
                    replacement.className = link.className;
                    link.replaceWith(replacement);
                }
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', applyIdentity, { once: true });
        } else {
            applyIdentity();
        }
    })();
</script>

<style id="nodexa-global-theme-base">
    :root {
        --nodexa-accent: #42e9a6;
        --nodexa-accent-2: #68edb8;
        --nodexa-accent-rgb: 66, 233, 166;
        --nodexa-accent-soft: rgba(66, 233, 166, 0.12);
        --nodexa-border: rgba(66, 233, 166, 0.14);
        --nodexa-border-strong: rgba(66, 233, 166, 0.32);
        --nodexa-bg: #050b0d;
        --nodexa-bg-deep: #030709;
        --nodexa-bg-2: #071214;
        --nodexa-surface: #0a1417;
        --nodexa-surface-2: #0e1a1e;
        --nodexa-surface-hover: #122328;
        --nodexa-text: #edf7f5;
        --nodexa-muted: #8ba09c;
    }

    html,
    body {
        background-color: var(--nodexa-bg) !important;
    }

    ::selection {
        color: #ffffff;
        background: rgba(var(--nodexa-accent-rgb), 0.42);
    }
</style>
