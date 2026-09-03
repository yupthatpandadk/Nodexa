import styled from 'styled-components/macro';
import { breakpoint } from '@/theme';
import tw from 'twin.macro';

const ContentContainer = styled.div`
    max-width: 1560px;
    ${tw`mx-3 sm:mx-4`};

    ${breakpoint('xl')`
        ${tw`mx-auto`};
    `};
`;
ContentContainer.displayName = 'ContentContainer';

export default ContentContainer;
