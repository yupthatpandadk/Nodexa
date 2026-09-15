(function () {
    'use strict';

    var LEGACY_HEX = /#42e9a6/gi;
    var LEGACY_LIGHT = /#6af1bc/gi;
    var LEGACY_RGB = /rgba\(\s*66\s*,\s*233\s*,\s*166\s*,\s*([^\)]+)\)/gi;
    var LEGACY_RGB_SOLID = /rgb\(\s*66\s*,\s*233\s*,\s*166\s*\)/gi;

    function rewriteCss(text) {
        if (!text || (text.toLowerCase().indexOf('#42e9a6') === -1 && text.toLowerCase().indexOf('#6af1bc') === -1 && text.indexOf('66') === -1)) return text;
        return text
            .replace(LEGACY_RGB, 'rgba(var(--nodexa-accent-rgb), $1)')
            .replace(LEGACY_RGB_SOLID, 'rgb(var(--nodexa-accent-rgb))')
            .replace(LEGACY_LIGHT, 'var(--nodexa-accent-2)')
            .replace(LEGACY_HEX, 'var(--nodexa-accent)');
    }

    function processStyle(style) {
        if (!style || style.dataset.nodexaAccentBridge === '1') return;
        var next = rewriteCss(style.textContent || '');
        if (next !== style.textContent) style.textContent = next;
        style.dataset.nodexaAccentBridge = '1';
    }

    function processInline(root) {
        if (!root || root.nodeType !== 1) return;
        var nodes = [root];
        if (root.querySelectorAll) nodes = nodes.concat(Array.prototype.slice.call(root.querySelectorAll('[style]')));
        nodes.forEach(function (node) {
            var value = node.getAttribute && node.getAttribute('style');
            if (!value) return;
            var next = rewriteCss(value);
            if (next !== value) node.setAttribute('style', next);
        });
    }

    function run(root) {
        (root || document).querySelectorAll('style:not([data-nodexa-accent-bridge="1"])').forEach(processStyle);
        if (root && root.nodeType === 1) processInline(root);
        else document.querySelectorAll('[style]').forEach(function (node) { processInline(node); });
    }

    function boot() {
        document.documentElement.style.setProperty('--nx-accent', 'var(--nodexa-accent)');
        document.documentElement.style.setProperty('--nx-accent-rgb', 'var(--nodexa-accent-rgb)');
        run(document);
        new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                mutation.addedNodes.forEach(function (node) {
                    if (node.nodeType !== 1) return;
                    if (node.tagName === 'STYLE') processStyle(node);
                    else run(node);
                });
            });
        }).observe(document.documentElement, {childList:true, subtree:true});
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, {once:true});
    else boot();
})();
