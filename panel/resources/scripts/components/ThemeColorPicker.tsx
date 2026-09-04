import * as React from 'react';
import { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faPalette, faTimes, faUndo } from '@fortawesome/free-solid-svg-icons';
import styled from 'styled-components/macro';

const STORAGE_KEY = 'nodexa_theme_accent';
const DEFAULT_ACCENT = '#42e9a6';

const PRESETS = [
    { name: 'Nodexa', color: '#42e9a6' },
    { name: 'Blå', color: '#3b82f6' },
    { name: 'Lilla', color: '#8b5cf6' },
    { name: 'Cyan', color: '#22d3ee' },
    { name: 'Orange', color: '#f59e0b' },
    { name: 'Pink', color: '#ec4899' },
    { name: 'Rød', color: '#ef4444' },
];

const Trigger = styled.button`
    display: inline-flex;
    min-width: 2.7rem;
    height: 2.7rem;
    align-items: center;
    justify-content: center;
    padding: 0 0.8rem;
    border: 1px solid transparent;
    border-radius: 12px;
    color: var(--nodexa-muted);
    background: transparent;
    cursor: pointer;
    transition: all 150ms ease;

    &:hover {
        color: #fff;
        border-color: var(--nodexa-border-strong);
        background: var(--nodexa-accent-soft);
    }
`;

const Overlay = styled.div`
    position: fixed;
    inset: 0;
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    background: rgba(2, 8, 7, 0.72);
    backdrop-filter: blur(8px);
`;

const Panel = styled.div`
    width: min(28rem, 100%);
    overflow: hidden;
    border: 1px solid var(--nodexa-border-strong);
    border-radius: 18px;
    color: var(--nodexa-text);
    background: linear-gradient(180deg, rgba(16, 33, 29, 0.99), rgba(7, 16, 14, 0.99));
    box-shadow: 0 28px 80px rgba(0, 0, 0, 0.48), 0 0 40px rgba(var(--nodexa-accent-rgb), 0.08);
`;

const Header = styled.div`
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    padding: 1.15rem 1.2rem;
    border-bottom: 1px solid var(--nodexa-border);

    h2 {
        margin: 0;
        font-size: 1.05rem;
        font-weight: 700;
    }

    p {
        margin: 0.25rem 0 0;
        color: var(--nodexa-muted);
        font-size: 0.78rem;
    }
`;

const Close = styled.button`
    display: inline-flex;
    width: 2.2rem;
    height: 2.2rem;
    flex: 0 0 auto;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--nodexa-border);
    border-radius: 10px;
    color: var(--nodexa-muted);
    background: rgba(255, 255, 255, 0.035);
    cursor: pointer;

    &:hover {
        color: #fff;
        border-color: var(--nodexa-border-strong);
        background: var(--nodexa-accent-soft);
    }
`;

const Body = styled.div`
    padding: 1.2rem;
`;

const Presets = styled.div`
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.65rem;

    @media (max-width: 480px) {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
`;

const Swatch = styled.button<{ $color: string; $active: boolean }>`
    display: flex;
    min-height: 4.3rem;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    padding: 0.55rem;
    border: 1px solid ${({ $active }) => ($active ? 'var(--nodexa-accent)' : 'rgba(148, 163, 184, 0.12)')};
    border-radius: 12px;
    color: ${({ $active }) => ($active ? '#fff' : 'var(--nodexa-muted)')};
    background: ${({ $active }) => ($active ? 'var(--nodexa-accent-soft)' : 'rgba(255, 255, 255, 0.025)')};
    cursor: pointer;
    transition: transform 120ms ease, border-color 120ms ease, background 120ms ease;

    &:hover {
        transform: translateY(-1px);
        border-color: ${({ $color }) => $color};
        background: rgba(255, 255, 255, 0.05);
    }

    > span:first-child {
        width: 1.45rem;
        height: 1.45rem;
        border: 2px solid rgba(255, 255, 255, 0.35);
        border-radius: 999px;
        background: ${({ $color }) => $color};
        box-shadow: 0 0 18px ${({ $color }) => `${$color}55`};
    }

    > span:last-child {
        font-size: 0.68rem;
        font-weight: 700;
    }
`;

const CustomRow = styled.div`
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-top: 1rem;
    padding: 0.85rem;
    border: 1px solid rgba(148, 163, 184, 0.1);
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.025);

    label {
        display: block;
        color: #e7f8f0;
        font-size: 0.78rem;
        font-weight: 700;
    }

    small {
        display: block;
        margin-top: 0.15rem;
        color: var(--nodexa-muted);
        font-size: 0.67rem;
    }

    input[type='color'] {
        width: 3.2rem;
        height: 2.4rem;
        padding: 0.15rem;
        overflow: hidden;
        border: 1px solid var(--nodexa-border-strong);
        border-radius: 10px;
        background: #07100e;
        cursor: pointer;
    }
`;

const Footer = styled.div`
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.9rem 1.2rem 1.1rem;
    border-top: 1px solid rgba(148, 163, 184, 0.08);

    span {
        color: var(--nodexa-muted);
        font-size: 0.67rem;
    }
`;

const Reset = styled.button`
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.55rem 0.7rem;
    border: 1px solid var(--nodexa-border);
    border-radius: 9px;
    color: var(--nodexa-muted);
    background: rgba(255, 255, 255, 0.025);
    cursor: pointer;

    &:hover {
        color: #fff;
        border-color: var(--nodexa-border-strong);
        background: var(--nodexa-accent-soft);
    }
`;

function normalizeHex(value: string): string | null {
    const color = value.trim();
    return /^#[0-9a-fA-F]{6}$/.test(color) ? color.toLowerCase() : null;
}

function rgb(hex: string): [number, number, number] {
    return [parseInt(hex.slice(1, 3), 16), parseInt(hex.slice(3, 5), 16), parseInt(hex.slice(5, 7), 16)];
}

function mixWithWhite(hex: string, amount = 0.2): string {
    const [r, g, b] = rgb(hex);
    const mix = (value: number) => Math.round(value + (255 - value) * amount).toString(16).padStart(2, '0');
    return `#${mix(r)}${mix(g)}${mix(b)}`;
}

export function applyNodexaAccent(value: string): string {
    const accent = normalizeHex(value) || DEFAULT_ACCENT;
    const [r, g, b] = rgb(accent);
    const root = document.documentElement;

    root.style.setProperty('--nodexa-accent', accent);
    root.style.setProperty('--nodexa-accent-2', mixWithWhite(accent));
    root.style.setProperty('--nodexa-accent-rgb', `${r}, ${g}, ${b}`);
    root.style.setProperty('--nodexa-accent-soft', `rgba(${r}, ${g}, ${b}, 0.12)`);
    root.style.setProperty('--nodexa-border', `rgba(${r}, ${g}, ${b}, 0.13)`);
    root.style.setProperty('--nodexa-border-strong', `rgba(${r}, ${g}, ${b}, 0.28)`);
    root.dataset.nodexaAccent = accent;
    window.dispatchEvent(new CustomEvent('nodexa:theme', { detail: { accent } }));

    return accent;
}

export function loadNodexaAccent(): string {
    let saved = DEFAULT_ACCENT;
    try {
        saved = localStorage.getItem(STORAGE_KEY) || DEFAULT_ACCENT;
    } catch (_) {
        // localStorage may be blocked by browser privacy settings.
    }
    return applyNodexaAccent(saved);
}

export default () => {
    const [open, setOpen] = useState(false);
    const [accent, setAccent] = useState(DEFAULT_ACCENT);

    useEffect(() => {
        setAccent(loadNodexaAccent());
    }, []);

    useEffect(() => {
        if (!open) return;
        const onKeyDown = (event: KeyboardEvent) => event.key === 'Escape' && setOpen(false);
        window.addEventListener('keydown', onKeyDown);
        return () => window.removeEventListener('keydown', onKeyDown);
    }, [open]);

    const choose = (value: string) => {
        const next = applyNodexaAccent(value);
        setAccent(next);
        try {
            localStorage.setItem(STORAGE_KEY, next);
        } catch (_) {
            // Theme still applies for this session when storage is unavailable.
        }
    };

    const reset = () => {
        try {
            localStorage.removeItem(STORAGE_KEY);
        } catch (_) {
            // Ignore unavailable storage.
        }
        setAccent(applyNodexaAccent(DEFAULT_ACCENT));
    };

    return (
        <>
            <Trigger type={'button'} aria-label={'Vælg farve'} onClick={() => setOpen(true)}>
                <FontAwesomeIcon icon={faPalette} />
            </Trigger>
            {open &&
                createPortal(
                    <Overlay onMouseDown={(event) => event.currentTarget === event.target && setOpen(false)}>
                        <Panel role={'dialog'} aria-modal={'true'} aria-label={'Vælg Nodexa farve'}>
                            <Header>
                                <div>
                                    <h2>Udseende</h2>
                                    <p>Vælg accentfarven på dit Nodexa-panel.</p>
                                </div>
                                <Close type={'button'} aria-label={'Luk'} onClick={() => setOpen(false)}>
                                    <FontAwesomeIcon icon={faTimes} />
                                </Close>
                            </Header>
                            <Body>
                                <Presets>
                                    {PRESETS.map((preset) => (
                                        <Swatch
                                            type={'button'}
                                            key={preset.color}
                                            $color={preset.color}
                                            $active={accent === preset.color.toLowerCase()}
                                            onClick={() => choose(preset.color)}
                                        >
                                            <span />
                                            <span>{preset.name}</span>
                                        </Swatch>
                                    ))}
                                </Presets>
                                <CustomRow>
                                    <div>
                                        <label htmlFor={'nodexa-custom-accent'}>Egen farve</label>
                                        <small>Vælg præcis den farve du vil bruge.</small>
                                    </div>
                                    <input
                                        id={'nodexa-custom-accent'}
                                        type={'color'}
                                        value={accent}
                                        onChange={(event) => choose(event.currentTarget.value)}
                                    />
                                </CustomRow>
                            </Body>
                            <Footer>
                                <span>Gemmes automatisk på denne enhed.</span>
                                <Reset type={'button'} onClick={reset}>
                                    <FontAwesomeIcon icon={faUndo} /> Standard
                                </Reset>
                            </Footer>
                        </Panel>
                    </Overlay>,
                    document.body
                )}
        </>
    );
};
