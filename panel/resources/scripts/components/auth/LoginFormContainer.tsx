import React, { forwardRef } from 'react';
import { Form } from 'formik';
import styled from 'styled-components/macro';
import { breakpoint } from '@/theme';
import FlashMessageRender from '@/components/FlashMessageRender';
import tw from 'twin.macro';

type Props = React.DetailedHTMLProps<React.FormHTMLAttributes<HTMLFormElement>, HTMLFormElement> & {
    title?: string;
};

const Container = styled.div`
    width: 100%;
    margin: 0 auto;
    padding: 1rem;

    ${breakpoint('sm')`
        width: 92%;
    `};

    ${breakpoint('lg')`
        width: 88%;
        max-width: 920px;
    `};
`;

const AuthCard = styled.div`
    display: grid;
    grid-template-columns: minmax(250px, 0.86fr) minmax(320px, 1.14fr);
    overflow: hidden;
    border: 1px solid var(--nodexa-border-strong, rgba(66, 233, 166, 0.22));
    border-radius: 24px;
    background: var(--nodexa-surface, #0a1417);
    box-shadow:
        0 34px 100px rgba(0, 0, 0, 0.42),
        0 0 50px rgba(var(--nodexa-accent-rgb, 66, 233, 166), 0.07);

    @media (max-width: 760px) {
        grid-template-columns: 1fr;
    }
`;

const BrandPanel = styled.div`
    position: relative;
    display: flex;
    min-height: 430px;
    flex-direction: column;
    justify-content: space-between;
    overflow: hidden;
    padding: 2.2rem;
    background:
        radial-gradient(circle at 20% 10%, rgba(var(--nodexa-accent-rgb, 66, 233, 166), 0.23), transparent 19rem),
        radial-gradient(circle at 90% 90%, rgba(var(--nodexa-accent-rgb, 66, 233, 166), 0.09), transparent 18rem),
        linear-gradient(150deg, var(--nodexa-surface-2, #0e1a1e), var(--nodexa-bg-deep, #030709) 78%);

    &::after {
        content: '';
        position: absolute;
        width: 15rem;
        height: 15rem;
        right: -7rem;
        bottom: -7rem;
        border: 1px solid rgba(var(--nodexa-accent-rgb, 66, 233, 166), 0.2);
        border-radius: 50%;
    }

    @media (max-width: 760px) {
        min-height: 245px;
        padding: 1.7rem;
    }
`;

const BrandMark = styled.div`
    position: relative;
    z-index: 1;
    display: flex;
    width: 3.2rem;
    height: 3.2rem;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(var(--nodexa-accent-rgb, 66, 233, 166), 0.48);
    border-radius: 16px;
    color: #071014;
    font-size: 1.4rem;
    font-weight: 800;
    background: linear-gradient(145deg, var(--nodexa-accent-2, #68edb8), var(--nodexa-accent, #42e9a6));
    box-shadow: 0 14px 42px rgba(var(--nodexa-accent-rgb, 66, 233, 166), 0.28);
`;

const BrandTitle = styled.h1`
    margin: 0;
    color: var(--nodexa-text, #f3fff9);
    font-size: 2.25rem;
    font-weight: 750;
    letter-spacing: -0.045em;
`;

const BrandText = styled.p`
    max-width: 19rem;
    margin: 0.6rem 0 0;
    color: var(--nodexa-muted, #8ba09c);
    font-size: 0.9rem;
    line-height: 1.6;
`;

const Status = styled.div`
    position: relative;
    z-index: 1;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    width: fit-content;
    color: var(--nodexa-muted, #8ba09c);
    font-size: 0.66rem;
    font-weight: 700;
    letter-spacing: 0.11em;

    span {
        width: 0.48rem;
        height: 0.48rem;
        border-radius: 50%;
        background: var(--nodexa-accent, #42e9a6);
        box-shadow: 0 0 13px rgba(var(--nodexa-accent-rgb, 66, 233, 166), 0.75);
    }
`;

const FormPanel = styled.div`
    padding: 2.4rem 2.5rem;
    color: #12231d;
    background:
        linear-gradient(180deg, rgba(var(--nodexa-accent-rgb, 66, 233, 166), 0.035), transparent 45%),
        linear-gradient(180deg, rgba(251, 254, 253, 0.99), rgba(242, 248, 245, 0.99));

    @media (max-width: 760px) {
        padding: 1.7rem;
    }
`;

const FormTitle = styled.h2`
    margin: 0 0 0.35rem;
    color: #0c1c16;
    font-size: 1.65rem;
    font-weight: 720;
    letter-spacing: -0.035em;
`;

const FormSubtitle = styled.p`
    margin: 0 0 1.65rem;
    color: #6d827a;
    font-size: 0.82rem;
    line-height: 1.5;
`;

export default forwardRef<HTMLFormElement, Props>(({ title, ...props }, ref) => (
    <Container>
        <FlashMessageRender css={tw`mb-3 px-1`} />
        <Form {...props} ref={ref}>
            <AuthCard>
                <BrandPanel>
                    <BrandMark>N</BrandMark>
                    <div css={tw`relative z-10`}>
                        <BrandTitle>Nodexa</BrandTitle>
                        <BrandText>Game Server Cloud med hurtig kontrol over servere, filer, backups og drift.</BrandText>
                    </div>
                    <Status>
                        <span /> NODEXA CONTROL PLANE
                    </Status>
                </BrandPanel>
                <FormPanel>
                    <FormTitle>{title || 'Velkommen tilbage'}</FormTitle>
                    <FormSubtitle>Log ind på din Nodexa-konto for at fortsætte.</FormSubtitle>
                    {props.children}
                </FormPanel>
            </AuthCard>
        </Form>
        <p css={tw`text-center text-neutral-500 text-xs mt-5`}>
            <span css={tw`text-neutral-400 font-medium`}>Nodexa Software</span>
            {' · '}{new Date().getFullYear()}
        </p>
    </Container>
));
