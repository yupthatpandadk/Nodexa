<script>
(function () {
    var endpoint = @json(route('csrf.refresh'));
    var inFlight = null;
    var lastRefresh = 0;
    var minimumAge = 60000;

    function currentToken() {
        var meta = document.querySelector('meta[name="csrf-token"]') || document.querySelector('meta[name="_token"]');
        return meta ? meta.getAttribute('content') : null;
    }

    function applyToken(token) {
        if (!token) return;

        document.querySelectorAll('meta[name="csrf-token"], meta[name="_token"]').forEach(function (meta) {
            meta.setAttribute('content', token);
        });

        document.querySelectorAll('input[name="_token"]').forEach(function (input) {
            input.value = token;
        });

        if (window.jQuery && typeof window.jQuery.ajaxSetup === 'function') {
            window.jQuery.ajaxSetup({
                headers: { 'X-CSRF-TOKEN': token }
            });
        }
    }

    function refresh(force) {
        var existing = currentToken();
        if (!force && existing && Date.now() - lastRefresh < minimumAge) {
            return Promise.resolve(existing);
        }

        if (inFlight) return inFlight;

        inFlight = fetch(endpoint, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(function (response) {
                if (!response.ok) throw new Error('CSRF refresh failed with HTTP ' + response.status);
                return response.json();
            })
            .then(function (data) {
                if (!data || typeof data.token !== 'string' || !data.token) {
                    throw new Error('CSRF refresh returned no token');
                }

                lastRefresh = Date.now();
                applyToken(data.token);
                return data.token;
            })
            .finally(function () {
                inFlight = null;
            });

        return inFlight;
    }

    window.NodexaCsrf = {
        refresh: refresh,
        token: currentToken
    };

    function start() {
        applyToken(currentToken());
        refresh(true).catch(function () {});

        window.setInterval(function () {
            refresh(true).catch(function () {});
        }, 5 * 60 * 1000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            refresh(false).catch(function () {});
        }
    });

    // Blade forms carry a hidden _token input. Refresh that token immediately
    // before unsafe form submissions, while leaving React-controlled forms alone.
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement) || event.defaultPrevented) return;
        if (!form.querySelector('input[name="_token"]')) return;

        var method = (form.getAttribute('method') || 'get').toLowerCase();
        if (method === 'get') return;

        if (form.dataset.nodexaCsrfReady === '1') {
            delete form.dataset.nodexaCsrfReady;
            return;
        }

        event.preventDefault();
        var submitter = event.submitter || null;

        refresh(true)
            .catch(function () {
                return null;
            })
            .then(function () {
                form.dataset.nodexaCsrfReady = '1';

                if (typeof form.requestSubmit === 'function') {
                    try {
                        if (submitter && submitter.form === form) {
                            form.requestSubmit(submitter);
                        } else {
                            form.requestSubmit();
                        }
                        return;
                    } catch (e) {
                        // Fall back to the native submit below.
                    }
                }

                HTMLFormElement.prototype.submit.call(form);
            });
    });
})();
</script>
