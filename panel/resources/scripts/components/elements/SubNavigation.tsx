import styled from 'styled-components/macro';
import tw from 'twin.macro';

const SubNavigation = styled.div`
    ${tw`w-full overflow-x-auto`};
    border-bottom: 1px solid rgba(74, 222, 128, 0.12);
    background: rgba(7, 16, 15, 0.92);
    backdrop-filter: blur(16px);

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
                background: rgba(74, 222, 128, 0.035);
            }

            &:active,
            &.active {
                color: #69f0ae;
                border-bottom-color: #37df93;
                background: linear-gradient(180deg, rgba(55, 223, 147, 0.025), rgba(55, 223, 147, 0.075));
            }
        }
    }
`;

export default SubNavigation;
