import styled from 'styled-components/macro';

export default styled.div<{ $hoverable?: boolean }>`
    display: flex;
    position: relative;
    align-items: center;
    overflow: hidden;
    padding: 1rem 1.1rem;
    border: 1px solid var(--nodexa-border);
    border-radius: 16px;
    color: var(--nodexa-text);
    text-decoration: none;
    background: linear-gradient(145deg, var(--nodexa-surface-2), var(--nodexa-surface));
    box-shadow: 0 12px 34px rgba(0, 0, 0, 0.15), inset 0 1px 0 rgba(255, 255, 255, 0.018);
    transition: transform 160ms ease, border-color 160ms ease, background 160ms ease, box-shadow 160ms ease;

    ${(props) =>
        props.$hoverable !== false &&
        `
            &:hover {
                transform: translateY(-1px);
                border-color: var(--nodexa-border-strong);
                background: linear-gradient(145deg, var(--nodexa-surface-hover), var(--nodexa-surface-2));
                box-shadow: 0 16px 42px rgba(0, 0, 0, 0.22), 0 0 26px rgba(var(--nodexa-accent-rgb), 0.06);
            }
        `};

    & .icon {
        display: flex;
        width: 3.35rem;
        height: 3.35rem;
        flex: none;
        align-items: center;
        justify-content: center;
        padding: 0.75rem;
        border: 1px solid var(--nodexa-border-strong);
        border-radius: 14px;
        color: var(--nodexa-accent);
        background: linear-gradient(145deg, rgba(var(--nodexa-accent-rgb), 0.15), rgba(var(--nodexa-accent-rgb), 0.045));
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.03), 0 0 24px rgba(var(--nodexa-accent-rgb), 0.035);
    }
`;
