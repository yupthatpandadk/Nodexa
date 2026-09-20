import React, { useEffect } from 'react';
import ContentContainer from '@/components/elements/ContentContainer';
import { CSSTransition } from 'react-transition-group';
import tw from 'twin.macro';
import FlashMessageRender from '@/components/FlashMessageRender';

export interface PageContentBlockProps { title?: string; className?: string; showFlashKey?: string; }

const PageContentBlock: React.FC<PageContentBlockProps> = ({ title, showFlashKey, className, children }) => {
    useEffect(() => { document.title = title ? `${title} · Nodexa` : 'Nodexa'; }, [title]);
    return (
        <CSSTransition timeout={150} classNames={'fade'} appear in>
            <>
                <ContentContainer css={tw`my-5 sm:my-8`} className={className}>
                    {showFlashKey && <FlashMessageRender byKey={showFlashKey} css={tw`mb-4`} />}
                    {children}
                </ContentContainer>
                <ContentContainer css={tw`mb-8`}>
                    <p css={tw`text-center text-neutral-600 text-xs tracking-wide`}>Nodexa Control Panel &copy; {new Date().getFullYear()}</p>
                </ContentContainer>
            </>
        </CSSTransition>
    );
};
export default PageContentBlock;
