(() => {
    'use strict';

    const BLOCKED_TAGS = new Set(['SCRIPT', 'STYLE', 'NOSCRIPT', 'CODE', 'PRE', 'TEXTAREA']);
    const BLOCKED_SELECTORS = '.xterm, .xterm-screen, .CodeMirror, [data-nodexa-branding-ignore]';
    const ATTRIBUTES = ['title', 'aria-label', 'alt', 'placeholder', 'data-original-title'];

    const brand = (value) => {
        if (typeof value !== 'string' || !/pterodactyl/i.test(value)) return value;

        return value
            .replace(/Pterodactyl Software/gi, 'Nodexa')
            .replace(/Powered by\s+Pterodactyl(?:®|&reg;)?/gi, 'Nodexa')
            .replace(/Pterodactyl(?:®|&reg;)?/gi, 'Nodexa');
    };

    const shouldIgnore = (element) => {
        if (!(element instanceof Element)) return false;
        if (BLOCKED_TAGS.has(element.tagName)) return true;
        return Boolean(element.closest(BLOCKED_SELECTORS));
    };

    const brandTextNode = (node) => {
        if (!(node instanceof Text)) return;
        const parent = node.parentElement;
        if (!parent || shouldIgnore(parent)) return;

        const next = brand(node.nodeValue || '');
        if (next !== node.nodeValue) node.nodeValue = next;
    };

    const brandElement = (element) => {
        if (!(element instanceof Element) || shouldIgnore(element)) return;

        ATTRIBUTES.forEach((attribute) => {
            if (!element.hasAttribute(attribute)) return;
            const current = element.getAttribute(attribute) || '';
            const next = brand(current);
            if (next !== current) element.setAttribute(attribute, next);
        });

        element.childNodes.forEach((child) => {
            if (child.nodeType === Node.TEXT_NODE) brandTextNode(child);
        });
    };

    const brandTree = (root) => {
        if (root instanceof Text) {
            brandTextNode(root);
            return;
        }

        if (!(root instanceof Element || root instanceof Document || root instanceof DocumentFragment)) return;

        if (root instanceof Element) brandElement(root);
        root.querySelectorAll('*').forEach(brandElement);
    };

    const apply = () => {
        const nextTitle = brand(document.title);
        if (nextTitle !== document.title) document.title = nextTitle;
        brandTree(document.body);
    };

    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            if (mutation.type === 'characterData') {
                brandTextNode(mutation.target);
                continue;
            }

            if (mutation.type === 'attributes' && mutation.target instanceof Element) {
                brandElement(mutation.target);
                continue;
            }

            mutation.addedNodes.forEach(brandTree);
        }
    });

    const start = () => {
        apply();
        if (!document.body) return;

        observer.observe(document.body, {
            subtree: true,
            childList: true,
            characterData: true,
            attributes: true,
            attributeFilter: ATTRIBUTES,
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
})();
