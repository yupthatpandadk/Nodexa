import React from 'react';
import styled, { css } from 'styled-components/macro';
import tw from 'twin.macro';
import Spinner from '@/components/elements/Spinner';

interface Props {
    isLoading?: boolean;
    size?: 'xsmall' | 'small' | 'large' | 'xlarge';
    color?: 'green' | 'red' | 'primary' | 'grey';
    isSecondary?: boolean;
}

const ButtonStyle = styled.button<Omit<Props, 'isLoading'>>`
    ${tw`relative inline-block rounded p-2 uppercase tracking-wide text-sm transition-all duration-150 border`};
    border-radius: 12px;
    font-weight: 650;

    ${(props) =>
        ((!props.isSecondary && !props.color) || props.color === 'primary') &&
        css<Props>`
            ${(props) =>
                !props.isSecondary &&
                css`
                    border-color: rgba(34, 197, 135, 0.78);
                    color: #062018;
                    background: linear-gradient(135deg, #67efb8, #2bdc98);
                    box-shadow: 0 10px 28px rgba(43, 220, 152, 0.16), inset 0 1px 0 rgba(255, 255, 255, 0.28);
                `};

            &:hover:not(:disabled) {
                transform: translateY(-1px);
                border-color: rgba(86, 245, 183, 0.92);
                background: linear-gradient(135deg, #76f5c2, #38e3a2);
                box-shadow: 0 14px 34px rgba(43, 220, 152, 0.23);
            }
        `};

    ${(props) =>
        props.color === 'grey' &&
        css`
            ${tw`border-neutral-600 bg-neutral-500 text-neutral-50`};

            &:hover:not(:disabled) {
                ${tw`bg-neutral-600 border-neutral-700`};
            }
        `};

    ${(props) =>
        props.color === 'green' &&
        css<Props>`
            ${tw`border-green-600 bg-green-500 text-green-50`};

            &:hover:not(:disabled) {
                ${tw`bg-green-600 border-green-700`};
            }

            ${(props) =>
                props.isSecondary &&
                css`
                    &:active:not(:disabled) {
                        ${tw`bg-green-600 border-green-700`};
                    }
                `};
        `};

    ${(props) =>
        props.color === 'red' &&
        css<Props>`
            ${tw`border-red-600 bg-red-500 text-red-50`};

            &:hover:not(:disabled) {
                ${tw`bg-red-600 border-red-700`};
            }

            ${(props) =>
                props.isSecondary &&
                css`
                    &:active:not(:disabled) {
                        ${tw`bg-red-600 border-red-700`};
                    }
                `};
        `};

    ${(props) => props.size === 'xsmall' && tw`px-2 py-1 text-xs`};
    ${(props) => (!props.size || props.size === 'small') && tw`px-4 py-2`};
    ${(props) => props.size === 'large' && tw`p-4 text-sm`};
    ${(props) => props.size === 'xlarge' && tw`p-4 w-full`};

    ${(props) =>
        props.isSecondary &&
        css<Props>`
            border-color: rgba(137, 166, 156, 0.25);
            background: rgba(10, 24, 20, 0.5);
            color: #d9eee6;

            &:hover:not(:disabled) {
                border-color: rgba(73, 238, 169, 0.24);
                color: #effff8;
                background: rgba(66, 233, 166, 0.07);
                ${(props) => props.color === 'red' && tw`bg-red-500 border-red-600 text-red-50`};
                ${(props) =>
                    props.color === 'primary' &&
                    css`
                        border-color: rgba(73, 238, 169, 0.3);
                        color: #dffff2;
                        background: rgba(66, 233, 166, 0.1);
                    `};
                ${(props) => props.color === 'green' && tw`bg-green-500 border-green-600 text-green-50`};
            }
        `};

    &:disabled {
        opacity: 0.55;
        cursor: default;
    }
`;

type ComponentProps = Omit<JSX.IntrinsicElements['button'], 'ref' | keyof Props> & Props;

const Button: React.FC<ComponentProps> = ({ children, isLoading, ...props }) => (
    <ButtonStyle {...props}>
        {isLoading && (
            <div css={tw`flex absolute justify-center items-center w-full h-full left-0 top-0`}>
                <Spinner size={'small'} />
            </div>
        )}
        <span css={isLoading ? tw`text-transparent` : undefined}>{children}</span>
    </ButtonStyle>
);

type LinkProps = Omit<JSX.IntrinsicElements['a'], 'ref' | keyof Props> & Props;

const LinkButton: React.FC<LinkProps> = (props) => <ButtonStyle as={'a'} {...props} />;

export { LinkButton, ButtonStyle };
export default Button;
