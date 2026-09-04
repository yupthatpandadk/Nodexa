import styled from 'styled-components/macro';
import tw from 'twin.macro';

const SubNavigation = styled.div`
    ${tw`w-full overflow-x-auto`};
    border-bottom: 1px solid var(--nodexa-border);
    background: color-mix(in srgb, var(--nodexa-surface) 92%, transparent 8%);
    backdrop-filter: blur(16px);
    transition: background 180ms ease, border-color 180ms ease;

    & > div {
        ${tw`flex items-center text-sm mx-auto px-3`};
        max-width: 1560px;

        & > a,
        & > div {
            ${tw`inline-block py-4 px-4 no-underline whitespace-nowrap transition-all duration-150`};
            color: #81928d;
            border-bottom: 2px solid transparent;

            &:not(:first-of-type) {
                ${tw`ml-1`};
            }

            &:hover {
                color: #eefcf5;
                background: rgba(var(--nodexa-accent-rgb), 0.045);
            }

            &:active,
            &.active {
                color: var(--nodexa-accent-2);
                border-bottom-color: var(--nodexa-accent);
                background: linear-gradient(180deg, rgba(var(--nodexa-accent-rgb), 0.025), rgba(var(--nodexa-accent-rgb), 0.085));
            }
        }
    }
`;

export default SubNavigation;
