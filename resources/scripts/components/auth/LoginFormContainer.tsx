import React, { forwardRef } from 'react';
import { Form } from 'formik';
import styled from 'styled-components/macro';
import FlashMessageRender from '@/components/FlashMessageRender';
import tw from 'twin.macro';

type Props = React.DetailedHTMLProps<React.FormHTMLAttributes<HTMLFormElement>, HTMLFormElement> & {
    title?: string;
};

const Shell = styled.div`
    width: min(1120px, calc(100% - 32px));
    margin: 0 auto;
`;

const Card = styled.div`
    display: grid;
    grid-template-columns: minmax(0, 0.9fr) minmax(320px, 1.1fr);
    overflow: hidden;
    border: 1px solid rgba(148, 163, 184, .14);
    border-radius: 24px;
    background: rgba(10, 18, 33, .94);
    box-shadow: 0 28px 80px rgba(0, 0, 0, .35);

    @media (max-width: 760px) {
        grid-template-columns: 1fr;
    }
`;

export default forwardRef<HTMLFormElement, Props>(({ title, ...props }, ref) => (
    <Shell>
        <div css={tw`text-center mb-8`}>
            <div css={tw`inline-flex items-center justify-center w-12 h-12 rounded-xl bg-purple-600 text-white font-bold text-xl mb-3`}>N</div>
            <h1 css={tw`text-3xl text-white font-bold`}>Nodexa</h1>
            <p css={tw`text-neutral-400 text-sm mt-2`}>Din cloud. Dine servere. Ét kontrolpanel.</p>
        </div>
        <FlashMessageRender css={tw`mb-4`} />
        <Form {...props} ref={ref}>
            <Card>
                <div css={tw`p-8 md:p-10 flex flex-col justify-center bg-neutral-900`}>
                    <span css={tw`text-purple-400 text-xs uppercase tracking-widest font-bold`}>Nodexa Cloud</span>
                    <h2 css={tw`text-3xl text-white font-bold mt-3`}>Alt samlet ét sted.</h2>
                    <p css={tw`text-neutral-400 mt-4 leading-relaxed`}>
                        Administrér servere, filer, backups, databaser og netværk fra et hurtigt og enkelt kontrolpanel.
                    </p>
                    <div css={tw`mt-8 grid grid-cols-2 gap-3 text-sm text-neutral-300`}>
                        <div css={tw`rounded-lg bg-neutral-800 p-3`}>⚡ Hurtig adgang</div>
                        <div css={tw`rounded-lg bg-neutral-800 p-3`}>☁ Cloud control</div>
                        <div css={tw`rounded-lg bg-neutral-800 p-3`}>🔒 Sikker konto</div>
                        <div css={tw`rounded-lg bg-neutral-800 p-3`}>● Live status</div>
                    </div>
                </div>
                <div css={tw`p-8 md:p-10 bg-neutral-800`}>
                    {title && <h2 css={tw`text-2xl text-white font-bold mb-2`}>{title}</h2>}
                    <p css={tw`text-neutral-400 text-sm mb-7`}>Fortsæt til dit Nodexa kontrolpanel.</p>
                    {props.children}
                </div>
            </Card>
        </Form>
        <p css={tw`text-center text-neutral-500 text-xs mt-6`}>
            &copy; {new Date().getFullYear()} Nodexa. All rights reserved.
        </p>
    </Shell>
));
