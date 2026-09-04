import tw from 'twin.macro';
import { createGlobalStyle } from 'styled-components/macro';
// @ts-expect-error untyped font file
import font from '@fontsource-variable/ibm-plex-sans/files/ibm-plex-sans-latin-wght-normal.woff2';

export default createGlobalStyle`
    @font-face {
        font-family: 'IBM Plex Sans';
        font-style: normal;
        font-display: swap;
        font-weight: 100 700;
        src: url(${font}) format('woff2-variations');
        unicode-range: U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD;
    }

    :root {
        --nodexa-bg: #050d0b;
        --nodexa-surface: #0b1714;
        --nodexa-surface-2: #10211d;
        --nodexa-accent: #42e9a6;
        --nodexa-accent-2: #68edb8;
        --nodexa-accent-rgb: 66, 233, 166;
        --nodexa-accent-soft: rgba(66, 233, 166, 0.12);
        --nodexa-border: rgba(66, 233, 166, 0.13);
        --nodexa-border-strong: rgba(66, 233, 166, 0.28);
        --nodexa-blue: #38bdf8;
        --nodexa-text: #effbf6;
        --nodexa-muted: #8ca49b;
    }

    html {
        min-height: 100%;
        background: var(--nodexa-bg);
    }

    body {
        ${tw`font-sans text-neutral-100`};
        margin: 0;
        min-height: 100vh;
        color: var(--nodexa-text);
        letter-spacing: 0.01em;
        background:
            radial-gradient(circle at 12% -5%, rgba(var(--nodexa-accent-rgb), 0.11), transparent 31rem),
            radial-gradient(circle at 90% 2%, rgba(var(--nodexa-accent-rgb), 0.055), transparent 29rem),
            linear-gradient(180deg, #081411 0%, #06100e 48%, #050b0a 100%);
        background-attachment: fixed;
    }

    body::before {
        content: '';
        position: fixed;
        inset: 0;
        pointer-events: none;
        z-index: 0;
        opacity: 0.16;
        background-image:
            linear-gradient(rgba(var(--nodexa-accent-rgb), 0.025) 1px, transparent 1px),
            linear-gradient(90deg, rgba(var(--nodexa-accent-rgb), 0.025) 1px, transparent 1px);
        background-size: 34px 34px;
        mask-image: linear-gradient(to bottom, black, transparent 78%);
    }

    #app {
        position: relative;
        z-index: 1;
        min-height: 100vh;
    }

    h1, h2, h3, h4, h5, h6 {
        ${tw`font-medium tracking-normal font-header`};
        color: var(--nodexa-text);
    }

    p {
        ${tw`leading-snug font-sans`};
    }

    a {
        transition: color 150ms ease, border-color 150ms ease, background-color 150ms ease;
    }

    .nodexa-sidebar-link:hover,
    .nodexa-sidebar-action:hover {
        color: #f4fff9 !important;
        border-color: var(--nodexa-border) !important;
        background: var(--nodexa-accent-soft) !important;
    }

    .nodexa-sidebar-active {
        color: var(--nodexa-accent) !important;
        border-color: var(--nodexa-border-strong) !important;
        background: var(--nodexa-accent-soft) !important;
    }

    ::selection {
        color: #ffffff;
        background: rgba(var(--nodexa-accent-rgb), 0.3);
    }

    form {
        ${tw`m-0`};
    }

    textarea, select, input, button, button:focus, button:focus-visible {
        ${tw`outline-none`};
    }

    input[type=number]::-webkit-outer-spin-button,
    input[type=number]::-webkit-inner-spin-button {
        -webkit-appearance: none !important;
        margin: 0;
    }

    input[type=number] {
        -moz-appearance: textfield !important;
    }

    ::-webkit-scrollbar {
        background: transparent;
        width: 12px;
        height: 12px;
    }

    ::-webkit-scrollbar-thumb {
        border: 3px solid transparent;
        border-radius: 999px;
        background: linear-gradient(180deg, rgba(var(--nodexa-accent-rgb), 0.48), rgba(var(--nodexa-accent-rgb), 0.24));
        background-clip: padding-box;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(180deg, rgba(var(--nodexa-accent-rgb), 0.7), rgba(var(--nodexa-accent-rgb), 0.42));
        background-clip: padding-box;
    }

    ::-webkit-scrollbar-track,
    ::-webkit-scrollbar-corner {
        background: transparent;
    }
`;
