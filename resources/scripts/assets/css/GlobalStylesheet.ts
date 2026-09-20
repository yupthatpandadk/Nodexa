import tw from 'twin.macro';
import { createGlobalStyle } from 'styled-components/macro';
// @ts-expect-error untyped font file
import font from '@fontsource-variable/ibm-plex-sans/files/ibm-plex-sans-latin-wght-normal.woff2';

export default createGlobalStyle`
    @font-face { font-family:'IBM Plex Sans'; font-style:normal; font-display:swap; font-weight:100 700; src:url(${font}) format('woff2-variations'); unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD; }
    html, body, #app { min-height:100%; }
    body {
        ${tw`font-sans text-neutral-200`};
        letter-spacing:.01em; margin:0;
        background:
          radial-gradient(circle at 15% -10%, rgba(59,130,246,.18), transparent 34rem),
          radial-gradient(circle at 90% 10%, rgba(14,165,233,.12), transparent 30rem),
          linear-gradient(180deg, #13283b 0%, #10263a 38%, #0c2032 100%);
        background-attachment: fixed;
    }
    h1,h2,h3,h4,h5,h6 { ${tw`font-medium tracking-normal font-header`}; color:#f8fafc; }
    p { ${tw`text-neutral-200 leading-snug font-sans`}; }
    form { ${tw`m-0`}; }
    textarea,select,input,button,button:focus,button:focus-visible { ${tw`outline-none`}; }
    input[type=number]::-webkit-outer-spin-button,input[type=number]::-webkit-inner-spin-button{-webkit-appearance:none!important;margin:0}
    input[type=number]{-moz-appearance:textfield!important}
    ::selection{background:rgba(99,102,241,.45);color:white}
    ::-webkit-scrollbar{background:none;width:12px;height:12px}
    ::-webkit-scrollbar-thumb{background:#273047;border:3px solid #080c18;border-radius:999px}
    ::-webkit-scrollbar-corner{background:transparent}
`;
