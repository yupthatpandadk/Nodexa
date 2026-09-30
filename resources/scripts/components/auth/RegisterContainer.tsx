import React, { useEffect, useRef, useState } from 'react';
import { Link, RouteComponentProps } from 'react-router-dom';
import { Formik, FormikHelpers } from 'formik';
import { object, ref, string } from 'yup';
import register, { RegisterData } from '@/api/auth/register';
import LoginFormContainer from '@/components/auth/LoginFormContainer';
import Field from '@/components/elements/Field';
import Button from '@/components/elements/Button';
import useFlash from '@/plugins/useFlash';
import { useStoreState } from 'easy-peasy';
import Reaptcha from 'reaptcha';
import tw from 'twin.macro';

const RegisterContainer = ({ history }: RouteComponentProps) => {
    const captchaRef = useRef<Reaptcha>(null);
    const [token, setToken] = useState('');

    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const { enabled: recaptchaEnabled, siteKey } = useStoreState((state) => state.settings.data!.recaptcha);

    useEffect(() => {
        clearFlashes();
    }, []);

    const onSubmit = (values: RegisterData, { setSubmitting }: FormikHelpers<RegisterData>) => {
        clearFlashes();

        // Registration uses the same invisible reCAPTCHA flow as login.
        // When reCAPTCHA is enabled, request a token first and submit again
        // once onVerify has stored the token.
        if (recaptchaEnabled && !token) {
            captchaRef.current!.execute().catch((error) => {
                console.error(error);
                setSubmitting(false);
                clearAndAddHttpError({ error });
            });

            return;
        }

        register({ ...values, recaptchaData: token })
            .then(() => history.push('/auth/login'))
            .catch((error) => {
                console.error(error);

                setToken('');
                if (captchaRef.current) captchaRef.current.reset();

                setSubmitting(false);
                clearAndAddHttpError({ error });
            });
    };

    return (
        <Formik
            onSubmit={onSubmit}
            initialValues={{
                username: '',
                email: '',
                name_first: '',
                name_last: '',
                password: '',
                password_confirmation: '',
            }}
            validationSchema={object().shape({
                name_first: string().required('Fornavn er påkrævet.'),
                name_last: string().required('Efternavn er påkrævet.'),
                username: string().min(3).required('Brugernavn er påkrævet.'),
                email: string().email().required('Email er påkrævet.'),
                password: string().min(8, 'Mindst 8 tegn.').required('Adgangskode er påkrævet.'),
                password_confirmation: string()
                    .oneOf([ref('password')], 'Adgangskoderne matcher ikke.')
                    .required('Gentag adgangskoden.'),
            })}
        >
            {({ isSubmitting, setSubmitting, submitForm }) => (
                <LoginFormContainer title={'Opret din konto'} css={tw`w-full flex`}>
                    <div css={tw`grid grid-cols-2 gap-3`}>
                        <Field light name={'name_first'} label={'Fornavn'} disabled={isSubmitting} />
                        <Field light name={'name_last'} label={'Efternavn'} disabled={isSubmitting} />
                    </div>
                    <div css={tw`mt-4`}>
                        <Field light name={'username'} label={'Brugernavn'} disabled={isSubmitting} />
                    </div>
                    <div css={tw`mt-4`}>
                        <Field light type={'email'} name={'email'} label={'Email'} disabled={isSubmitting} />
                    </div>
                    <div css={tw`mt-4`}>
                        <Field light type={'password'} name={'password'} label={'Adgangskode'} disabled={isSubmitting} />
                    </div>
                    <div css={tw`mt-4`}>
                        <Field
                            light
                            type={'password'}
                            name={'password_confirmation'}
                            label={'Gentag adgangskode'}
                            disabled={isSubmitting}
                        />
                    </div>
                    <div css={tw`mt-6`}>
                        <Button type={'submit'} size={'xlarge'} isLoading={isSubmitting} disabled={isSubmitting}>
                            Opret konto
                        </Button>
                    </div>

                    {recaptchaEnabled && (
                        <Reaptcha
                            ref={captchaRef}
                            size={'invisible'}
                            sitekey={siteKey || '_invalid_key'}
                            onVerify={(response) => {
                                setToken(response);
                                submitForm();
                            }}
                            onExpire={() => {
                                setSubmitting(false);
                                setToken('');
                            }}
                        />
                    )}

                    <div css={tw`mt-6 text-center text-sm text-neutral-400`}>
                        Har du allerede en konto?{' '}
                        <Link
                            to={'/auth/login'}
                            css={tw`text-purple-400 no-underline hover:text-purple-300`}
                        >
                            Log ind
                        </Link>
                    </div>
                </LoginFormContainer>
            )}
        </Formik>
    );
};

export default RegisterContainer;
