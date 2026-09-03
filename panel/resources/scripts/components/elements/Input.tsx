import styled, { css } from 'styled-components/macro';
import tw from 'twin.macro';

export interface Props {
    isLight?: boolean;
    hasError?: boolean;
}

const light = css<Props>`
    ${tw`bg-white text-neutral-800`};
    border-color: #d8e5df;

    &:hover:not(:disabled) {
        border-color: #b9cec5;
    }

    &:focus {
        border-color: #38dca0;
        box-shadow: 0 0 0 3px rgba(56, 220, 160, 0.12);
    }

    &:disabled {
        ${tw`bg-neutral-100`};
        border-color: #d8e5df;
    }
`;

const checkboxStyle = css<Props>`
    ${tw`cursor-pointer appearance-none inline-block align-middle select-none flex-shrink-0 w-4 h-4 border rounded-sm`};
    color: #42e9a6;
    border-color: rgba(130, 163, 151, 0.55);
    background: #183028;
    color-adjust: exact;
    background-origin: border-box;
    transition: all 75ms linear, box-shadow 25ms linear;

    &:checked {
        ${tw`border-transparent bg-no-repeat bg-center`};
        background-image: url("data:image/svg+xml,%3csvg viewBox='0 0 16 16' fill='%23071511' xmlns='http://www.w3.org/2000/svg'%3e%3cpath d='M5.707 7.293a1 1 0 0 0-1.414 1.414l2 2a1 1 0 0 0 1.414 0l4-4a1 1 0 0 0-1.414-1.414L7 8.586 5.707 7.293z'/%3e%3c/svg%3e");
        background-color: currentColor;
        background-size: 100% 100%;
    }

    &:focus {
        outline: none;
        border-color: rgba(66, 233, 166, 0.72);
        box-shadow: 0 0 0 2px rgba(66, 233, 166, 0.16);
    }
`;

const inputStyle = css<Props>`
    resize: none;
    ${tw`appearance-none outline-none w-full min-w-0`};
    ${tw`p-3 text-sm transition-all duration-150 shadow-none focus:ring-0`};
    border: 1px solid rgba(123, 158, 145, 0.25);
    border-radius: 12px;
    color: #eafbf5;
    background: rgba(13, 31, 26, 0.82);

    &:hover:not(:disabled) {
        border-color: rgba(73, 238, 169, 0.2);
    }

    & + .input-help {
        ${tw`mt-1 text-xs`};
        ${(props) => (props.hasError ? tw`text-red-200` : tw`text-neutral-400`)};
    }

    &:required,
    &:invalid {
        ${tw`shadow-none`};
    }

    &:not(:disabled):not(:read-only):focus {
        border-color: rgba(66, 233, 166, 0.72);
        box-shadow: 0 0 0 3px rgba(66, 233, 166, 0.11);
        ${(props) => props.hasError && tw`border-red-300 ring-red-200`};
    }

    &:disabled {
        ${tw`opacity-75`};
    }

    ${(props) => props.isLight && light};
    ${(props) => props.hasError && tw`text-red-100 border-red-400 hover:border-red-300`};
`;

const Input = styled.input<Props>`
    &:not([type='checkbox']):not([type='radio']) {
        ${inputStyle};
    }

    &[type='checkbox'],
    &[type='radio'] {
        ${checkboxStyle};

        &[type='radio'] {
            ${tw`rounded-full`};
        }
    }
`;
const Textarea = styled.textarea<Props>`
    ${inputStyle}
`;

export { Textarea };
export default Input;
