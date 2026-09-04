import React from 'react';
import FlashMessageRender from '@/components/FlashMessageRender';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import tw from 'twin.macro';

type Props = Readonly<
    React.DetailedHTMLProps<React.HTMLAttributes<HTMLDivElement>, HTMLDivElement> & {
        title?: string;
        borderColor?: string;
        showFlashes?: string | boolean;
        showLoadingOverlay?: boolean;
    }
>;

const ContentBox = ({ title, borderColor, showFlashes, showLoadingOverlay, children, ...props }: Props) => (
    <div {...props}>
        {title && <h2 css={tw`text-neutral-300 mb-4 px-4 text-2xl`}>{title}</h2>}
        {showFlashes && (
            <FlashMessageRender byKey={typeof showFlashes === 'string' ? showFlashes : undefined} css={tw`mb-4`} />
        )}
        <div
            css={[tw`p-4 rounded shadow-lg relative`, !!borderColor && tw`border-t-4`]}
            style={{
                borderColor: borderColor || 'var(--nodexa-border)',
                background: 'linear-gradient(145deg, var(--nodexa-surface-2), var(--nodexa-surface))',
                boxShadow: '0 14px 36px rgba(0, 0, 0, 0.16), 0 0 24px rgba(var(--nodexa-accent-rgb), 0.025)',
            }}
        >
            <SpinnerOverlay visible={showLoadingOverlay || false} />
            {children}
        </div>
    </div>
);

export default ContentBox;
