import styled from 'styled-components/macro';
import tw, { theme } from 'twin.macro';

const SubNavigation = styled.div`
    ${tw`w-full shadow overflow-x-auto`};
    background: linear-gradient(180deg, #0b1e35 0%, #09182b 100%);
    border-top: 1px solid rgba(96, 165, 250, 0.08);
    border-bottom: 1px solid rgba(96, 165, 250, 0.14);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.16);

    & > div {
        ${tw`flex items-center text-sm mx-auto px-2`};
        max-width: 1200px;
        min-width: max-content;

        & > a,
        & > div {
            ${tw`inline-block py-3 px-4 no-underline whitespace-nowrap transition-all duration-150`};
            color: #8fa5bd;
            position: relative;

            &:not(:first-of-type) {
                ${tw`ml-2`};
            }

            &:hover {
                color: #e8f3ff;
                background: rgba(59, 130, 246, 0.06);
            }

            &:active,
            &.active {
                color: #ffffff;
                background: rgba(34, 211, 238, 0.05);
                box-shadow: inset 0 -2px ${theme`colors.cyan.500`.toString()};
            }

            &.active::after {
                content: '';
                position: absolute;
                left: 25%;
                right: 25%;
                bottom: 0;
                height: 2px;
                border-radius: 9999px;
                background: #22d3ee;
                box-shadow: 0 0 10px rgba(34, 211, 238, 0.45);
            }
        }
    }

    scrollbar-width: thin;
    scrollbar-color: #1f4b70 #09182b;

    &::-webkit-scrollbar {
        height: 4px;
    }

    &::-webkit-scrollbar-track {
        background: #09182b;
    }

    &::-webkit-scrollbar-thumb {
        background: #1f4b70;
        border-radius: 9999px;
    }
`;

export default SubNavigation;
