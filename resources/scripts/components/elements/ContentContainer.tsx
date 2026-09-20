import styled from 'styled-components/macro';
import { breakpoint } from '@/theme';
import tw from 'twin.macro';

const ContentContainer = styled.div`
    width: calc(100% - 2rem);
    max-width: 1180px;
    ${tw`mx-auto`};

    ${breakpoint('xl')`
        ${tw`mx-auto`};
    `};
`;
ContentContainer.displayName = 'ContentContainer';

export default ContentContainer;
