import styled from 'styled-components/macro';

export default styled.div<{ $hoverable?: boolean }>`
    display: flex;
    position: relative;
    align-items: center;
    overflow: hidden;
    padding: 1rem 1.1rem;
    border: 1px solid rgba(73, 238, 169, 0.1);
    border-radius: 16px;
    color: #eafbf5;
    text-decoration: none;
    background: linear-gradient(145deg, rgba(16, 33, 29, 0.95), rgba(8, 20, 17, 0.94));
    box-shadow: 0 12px 34px rgba(0, 0, 0, 0.15), inset 0 1px 0 rgba(255, 255, 255, 0.018);
    transition: transform 160ms ease, border-color 160ms ease, background 160ms ease, box-shadow 160ms ease;

    ${(props) =>
        props.$hoverable !== false &&
        `
            &:hover {
                transform: translateY(-1px);
                border-color: rgba(73, 238, 169, 0.25);
                background: linear-gradient(145deg, rgba(19, 42, 36, 0.97), rgba(9, 24, 20, 0.96));
                box-shadow: 0 16px 42px rgba(0, 0, 0, 0.22), 0 0 26px rgba(66, 233, 166, 0.035);
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
        border: 1px solid rgba(73, 238, 169, 0.15);
        border-radius: 14px;
        color: #4ce9aa;
        background: linear-gradient(145deg, rgba(66, 233, 166, 0.12), rgba(56, 189, 248, 0.055));
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.03);
    }
`;
