(() => {
    'use strict';

    const menuButton = document.querySelector('[data-menu-toggle]');
    const menu = document.querySelector('[data-menu]');
    if (menuButton && menu) {
        menuButton.addEventListener('click', () => {
            const open = menu.classList.toggle('open');
            menuButton.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        menu.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => {
            menu.classList.remove('open');
            menuButton.setAttribute('aria-expanded', 'false');
        }));
    }

    const drawer = document.querySelector('[data-theme-drawer]');
    const backdrop = document.querySelector('[data-theme-backdrop]');
    const trigger = document.querySelector('[data-theme-toggle]');
    const close = document.querySelector('[data-theme-close]');
    const custom = document.querySelector('[data-custom-accent]');

    const setDrawer = (open) => {
        if (!drawer || !backdrop) return;
        drawer.classList.toggle('open', open);
        backdrop.classList.toggle('open', open);
        drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
    };

    trigger?.addEventListener('click', () => setDrawer(true));
    close?.addEventListener('click', () => setDrawer(false));
    backdrop?.addEventListener('click', () => setDrawer(false));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') setDrawer(false);
    });

    const apply = (accent) => {
        if (window.NodexaTheme?.apply) {
            const applied = window.NodexaTheme.apply(accent);
            if (custom) custom.value = applied;
            window.dispatchEvent(new CustomEvent('nodexa:theme', { detail: { accent: applied } }));
        }
    };

    document.querySelectorAll('[data-accent]').forEach((button) => {
        button.addEventListener('click', () => apply(button.getAttribute('data-accent')));
    });
    custom?.addEventListener('input', () => apply(custom.value));

    if (custom && window.NodexaTheme?.get) custom.value = window.NodexaTheme.get();
})();
