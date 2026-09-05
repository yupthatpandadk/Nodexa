// Hacky fix for browsers ignoring autocomplete="off"
$(document).ready(function() {
    $('.form-autocomplete-stop').on('click', function () {
        $(this).removeAttr('readonly').blur().focus();
    });
});

// Nodexa admin update banner.
// This file is loaded by the admin layout, so the banner is only shown inside
// the administrator area. The status endpoint caches GitHub checks server-side.
(function () {
    var statusUrl = '/admin/updates/status';
    var updatesUrl = '/admin/updates';
    var bannerId = 'nodexa-admin-update-banner';
    var styleId = 'nodexa-admin-update-banner-style';
    var pollInterval = 5 * 60 * 1000;

    function ensureStyle() {
        if (document.getElementById(styleId)) return;

        var style = document.createElement('style');
        style.id = styleId;
        style.textContent = [
            '#' + bannerId + ' {',
            '  position: sticky;',
            '  top: 50px;',
            '  z-index: 1035;',
            '  display: flex;',
            '  align-items: center;',
            '  justify-content: space-between;',
            '  gap: 16px;',
            '  margin: 0;',
            '  padding: 12px 22px;',
            '  color: #eafff6;',
            '  border-bottom: 1px solid rgba(var(--nodexa-accent-rgb, 66, 233, 166), .34);',
            '  background: linear-gradient(90deg, rgba(var(--nodexa-accent-rgb, 66, 233, 166), .20), rgba(var(--nodexa-accent-rgb, 66, 233, 166), .08)), #071411;',
            '  box-shadow: 0 10px 28px rgba(0,0,0,.22);',
            '}',
            '#' + bannerId + ' .nodexa-update-copy {',
            '  display: flex;',
            '  align-items: center;',
            '  gap: 11px;',
            '  min-width: 0;',
            '}',
            '#' + bannerId + ' .nodexa-update-icon {',
            '  width: 34px;',
            '  height: 34px;',
            '  flex: 0 0 34px;',
            '  display: inline-flex;',
            '  align-items: center;',
            '  justify-content: center;',
            '  border-radius: 10px;',
            '  color: #04100c;',
            '  background: var(--nodexa-accent, #42e9a6);',
            '}',
            '#' + bannerId + ' strong { display:block; color:#fff; font-size:13px; line-height:1.3; }',
            '#' + bannerId + ' small { display:block; margin-top:2px; color:rgba(234,255,246,.72); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }',
            '#' + bannerId + ' .nodexa-update-action {',
            '  flex: 0 0 auto;',
            '  display: inline-flex;',
            '  align-items: center;',
            '  gap: 7px;',
            '  padding: 8px 13px;',
            '  color: #04100c !important;',
            '  border: 1px solid var(--nodexa-accent-2, #68edb8);',
            '  border-radius: 9px;',
            '  background: var(--nodexa-accent, #42e9a6);',
            '  font-weight: 700;',
            '  text-decoration: none !important;',
            '}',
            '#' + bannerId + ' .nodexa-update-action:hover { filter: brightness(1.08); }',
            '@media (max-width: 767px) {',
            '  #' + bannerId + ' { top: 50px; padding: 10px 14px; gap: 10px; }',
            '  #' + bannerId + ' .nodexa-update-icon { display:none; }',
            '  #' + bannerId + ' small { max-width: 54vw; }',
            '  #' + bannerId + ' .nodexa-update-action { padding: 8px 10px; }',
            '  #' + bannerId + ' .nodexa-update-action span { display:none; }',
            '}'
        ].join('\n');
        document.head.appendChild(style);
    }

    function removeBanner() {
        var existing = document.getElementById(bannerId);
        if (existing) existing.remove();
    }

    function showBanner(data) {
        if (!data || data.update_available !== true) {
            removeBanner();
            return;
        }

        ensureStyle();

        var latest = data.latest || {};
        var state = data.state || {};
        var commit = latest.commit ? String(latest.commit).slice(0, 12) : '';
        var message = latest.message ? String(latest.message).split('\n')[0] : 'En nyere Nodexa-version er klar.';
        var running = state.status === 'running';

        var banner = document.getElementById(bannerId);
        if (!banner) {
            banner = document.createElement('div');
            banner.id = bannerId;
            banner.setAttribute('role', 'status');

            var contentWrapper = document.querySelector('.content-wrapper');
            if (!contentWrapper) return;
            contentWrapper.insertBefore(banner, contentWrapper.firstChild);
        }

        banner.innerHTML = '';

        var copy = document.createElement('div');
        copy.className = 'nodexa-update-copy';

        var icon = document.createElement('span');
        icon.className = 'nodexa-update-icon';
        icon.innerHTML = '<i class="fa ' + (running ? 'fa-spinner fa-spin' : 'fa-cloud-download') + '"></i>';

        var text = document.createElement('div');
        var title = document.createElement('strong');
        title.textContent = running ? 'Nodexa-opdatering kører' : 'Ny Nodexa-opdatering er tilgængelig';

        var details = document.createElement('small');
        details.textContent = (commit ? commit + ' · ' : '') + message;

        text.appendChild(title);
        text.appendChild(details);
        copy.appendChild(icon);
        copy.appendChild(text);
        banner.appendChild(copy);

        var action = document.createElement('a');
        action.className = 'nodexa-update-action';
        action.href = updatesUrl;
        action.innerHTML = '<i class="fa fa-arrow-right"></i><span>' + (running ? 'Se status' : 'Se opdatering') + '</span>';
        banner.appendChild(action);
    }

    function checkForUpdate() {
        fetch(statusUrl, {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin',
            cache: 'no-store'
        })
            .then(function (response) {
                if (!response.ok) throw new Error('Update status unavailable');
                return response.json();
            })
            .then(showBanner)
            .catch(function () {
                // Never interrupt the admin interface if GitHub/update status is unavailable.
            });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', checkForUpdate, { once: true });
    } else {
        checkForUpdate();
    }

    window.setInterval(checkForUpdate, pollInterval);
})();
