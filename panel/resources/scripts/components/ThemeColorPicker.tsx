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
    position: fixed;
    z-index: 9998;
    top: 48%;
    right: 0;
    display: inline-flex;
    min-width: 3.15rem;
    min-height: 3rem;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0 0.75rem 0 0.85rem;
    transform: translateY(-50%);
    border: 1px solid var(--nodexa-border-strong);
    border-right: 0;
    border-radius: 14px 0 0 14px;
    color: #f2fff9;
    background: linear-gradient(145deg, var(--nodexa-surface-2), var(--nodexa-bg-2));
    box-shadow: -10px 12px 34px rgba(0, 0, 0, 0.28), 0 0 24px rgba(var(--nodexa-accent-rgb), 0.08);
    backdrop-filter: blur(16px);
    cursor: pointer;
    transition: border-color 150ms ease, background 150ms ease, box-shadow 150ms ease, color 150ms ease;

    svg {
        color: var(--nodexa-accent);
    }

    span {
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.04em;
    }

    &:hover {
        border-color: var(--nodexa-accent);
        background: linear-gradient(145deg, var(--nodexa-surface-hover), var(--nodexa-surface));
        box-shadow: -12px 14px 38px rgba(0, 0, 0, 0.32), 0 0 26px rgba(var(--nodexa-accent-rgb), 0.15);
    }

    @media (max-width: 639px) {
        min-width: 2.8rem;
        width: 2.8rem;
        height: 2.8rem;
        padding: 0;

        span {
            display: none;
        }
    }
`;

const Overlay = styled.div`
    position: fixed;
    inset: 0;
    z-index: 10000;
    display: flex;
    align-items: stretch;
    justify-content: flex-end;
    background: color-mix(in srgb, var(--nodexa-accent) 3%, rgba(0, 0, 0, 0.62) 97%);
    backdrop-filter: blur(5px);
`;

const Panel = styled.div`
    width: min(27rem, calc(100vw - 2rem));
    height: 100%;
    overflow-y: auto;
    border-left: 1px solid var(--nodexa-border-strong);
    color: var(--nodexa-text);
    background:
        radial-gradient(circle at 90% 5%, rgba(var(--nodexa-accent-rgb), 0.13), transparent 16rem),
        linear-gradient(180deg, var(--nodexa-surface-2), var(--nodexa-bg));
    box-shadow: -28px 0 80px rgba(0, 0, 0, 0.48), -4px 0 28px rgba(var(--nodexa-accent-rgb), 0.08);
    animation: nodexa-theme-drawer-in 180ms ease-out;

    @keyframes nodexa-theme-drawer-in {
        from {
            transform: translateX(100%);
            opacity: 0.7;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
`;

const Header = styled.div`
    position: sticky;
    z-index: 2;
    top: 0;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    padding: 1.35rem 1.25rem 1.05rem;
    border-bottom: 1px solid var(--nodexa-border);
    background: color-mix(in srgb, var(--nodexa-surface-2) 94%, transparent 6%);
    backdrop-filter: blur(18px);

    h2 {
        margin: 0;
        font-size: 1.08rem;
        font-weight: 800;
    }

    p {
        margin: 0.3rem 0 0;
        color: var(--nodexa-muted);
        font-size: 0.78rem;
    }
`;

const Close = styled.button`
    display: inline-flex;
    width: 2.35rem;
    height: 2.35rem;
    flex: 0 0 auto;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--nodexa-border);
    border-radius: 11px;
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
    padding: 1.2rem 1.25rem;
`;

const SectionTitle = styled.div`
    margin-bottom: 0.75rem;

    strong {
        display: block;
        color: var(--nodexa-text);
        font-size: 0.78rem;
    }

    span {
        display: block;
        margin-top: 0.2rem;
        color: var(--nodexa-muted);
        font-size: 0.68rem;
    }
`;

const Presets = styled.div`
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.7rem;
`;

const Swatch = styled.button<{ $color: string; $active: boolean }>`
    display: flex;
    min-height: 4.1rem;
    align-items: center;
    justify-content: flex-start;
    gap: 0.7rem;
    padding: 0.7rem;
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
        width: 1.55rem;
        height: 1.55rem;
        flex: 0 0 auto;
        border: 2px solid rgba(255, 255, 255, 0.35);
        border-radius: 999px;
        background: ${({ $color }) => $color};
        box-shadow: 0 0 18px ${({ $color }) => `${$color}55`};
    }

    > span:last-child {
        font-size: 0.72rem;
        font-weight: 750;
    }
`;

const CustomRow = styled.div`
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-top: 1.15rem;
    padding: 0.9rem;
    border: 1px solid var(--nodexa-border);
    border-radius: 12px;
    background: var(--nodexa-surface);

    label {
        display: block;
        color: var(--nodexa-text);
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
        width: 3.4rem;
        height: 2.55rem;
        padding: 0.15rem;
        overflow: hidden;
        border: 1px solid var(--nodexa-border-strong);
        border-radius: 10px;
        background: var(--nodexa-bg-2);
        cursor: pointer;
    }
`;

const Footer = styled.div`
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--nodexa-border);

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
    background: var(--nodexa-surface);
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
            <Trigger type={'button'} aria-label={'Åbn temaindstillinger'} onClick={() => setOpen(true)}>
                <FontAwesomeIcon icon={faPalette} />
                <span>Tema</span>
            </Trigger>
            {open &&
                createPortal(
                    <Overlay
                        onMouseDown={(event: React.MouseEvent<HTMLDivElement>) =>
                            event.currentTarget === event.target && setOpen(false)
                        }
                    >
                        <Panel role={'dialog'} aria-modal={'true'} aria-label={'Tema og farver'}>
                            <Header>
                                <div>
                                    <h2>Tema & farver</h2>
                                    <p>Tilpas accentfarven på dit Nodexa-panel.</p>
                                </div>
                                <Close type={'button'} aria-label={'Luk'} onClick={() => setOpen(false)}>
                                    <FontAwesomeIcon icon={faTimes} />
                                </Close>
                            </Header>
                            <Body>
                                <SectionTitle>
                                    <strong>Accentfarve</strong>
                                    <span>Ændringer vises med det samme på hele hjemmesiden.</span>
                                </SectionTitle>
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
                                        onChange={(event: React.ChangeEvent<HTMLInputElement>) => choose(event.currentTarget.value)}
                                    />
                                </CustomRow>
                                <Footer>
                                    <span>Gemmes automatisk på denne enhed.</span>
                                    <Reset type={'button'} onClick={reset}>
                                        <FontAwesomeIcon icon={faUndo} /> Standard
                                    </Reset>
                                </Footer>
                            </Body>
                        </Panel>
                    </Overlay>,
                    document.body
                )}
        </>
    );
};