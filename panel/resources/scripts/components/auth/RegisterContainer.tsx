import React, { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import register from '@/api/auth/register';
import LoginFormContainer from '@/components/auth/LoginFormContainer';
import { useStoreState } from 'easy-peasy';
import { Formik, FormikHelpers } from 'formik';
import { object, ref as yupRef, string } from 'yup';
import Field from '@/components/elements/Field';
import tw from 'twin.macro';
import Button from '@/components/elements/Button';
import Reaptcha from 'reaptcha';
import useFlash from '@/plugins/useFlash';

interface Values {
    nameFirst: string;
    nameLast: string;
    username: string;
    email: string;
    password: string;
    passwordConfirmation: string;
}

const RegisterContainer = () => {
    const recaptchaRef = useRef<Reaptcha>(null);
    const [token, setToken] = useState('');

    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const { enabled: recaptchaEnabled, siteKey } = useStoreState((state) => state.settings.data!.recaptcha);

    useEffect(() => {
        clearFlashes();
    }, []);

    const onSubmit = (values: Values, { setSubmitting }: FormikHelpers<Values>) => {
        clearFlashes();

        if (recaptchaEnabled && !token) {
            recaptchaRef.current!.execute().catch((error) => {
                console.error(error);
                setSubmitting(false);
                clearAndAddHttpError({ error });
            });

            return;
        }

        register({ ...values, recaptchaData: token })
            .then((response) => {
                if (!response.complete) {
                    throw new Error('Kontoen kunne ikke oprettes. Prøv igen.');
                }

                window.location.assign(response.intended || '/');
            })
            .catch((error) => {
                console.error(error);
                setToken('');
                if (recaptchaRef.current) recaptchaRef.current.reset();
                setSubmitting(false);
                clearAndAddHttpError({ error });
            });
    };

    return (
        <Formik
            onSubmit={onSubmit}
            initialValues={{
                nameFirst: '',
                nameLast: '',
                username: '',
                email: '',
                password: '',
                passwordConfirmation: '',
            }}
            validationSchema={object().shape({
                nameFirst: string().trim().required('Indtast dit fornavn.').max(191, 'Fornavnet er for langt.'),
                nameLast: string().trim().required('Indtast dit efternavn.').max(191, 'Efternavnet er for langt.'),
                username: string()
                    .trim()
                    .min(3, 'Brugernavnet skal være mindst 3 tegn.')
                    .max(191, 'Brugernavnet er for langt.')
                    .matches(
                        /^[a-zA-Z0-9](?:[\w.-]*[a-zA-Z0-9])$/,
                        'Brugernavnet må kun indeholde bogstaver, tal, punktum, bindestreg og underscore.'
                    )
                    .required('Vælg et brugernavn.'),
                email: string().trim().email('Indtast en gyldig e-mailadresse.').required('Indtast din e-mailadresse.'),
                password: string().min(8, 'Adgangskoden skal være mindst 8 tegn.').required('Vælg en adgangskode.'),
                passwordConfirmation: string()
                    .oneOf([yupRef('password')], 'Adgangskoderne er ikke ens.')
                    .required('Gentag adgangskoden.'),
            })}
        >
            {({ isSubmitting, setSubmitting, submitForm }) => (
                <LoginFormContainer
                    title={'Opret din Nodexa-konto'}
                    subtitle={'Opret en gratis konto for at få adgang til panel, servere og hosting.'}
                    css={tw`w-full flex`}
                >
                    <div css={tw`grid grid-cols-1 gap-4 sm:grid-cols-2`}>
                        <Field light type={'text'} label={'Fornavn'} name={'nameFirst'} disabled={isSubmitting} />
                        <Field light type={'text'} label={'Efternavn'} name={'nameLast'} disabled={isSubmitting} />
                    </div>
                    <div css={tw`mt-4`}>
                        <Field light type={'text'} label={'Brugernavn'} name={'username'} disabled={isSubmitting} />
                    </div>
                    <div css={tw`mt-4`}>
                        <Field light type={'email'} label={'E-mail'} name={'email'} disabled={isSubmitting} />
                    </div>
                    <div css={tw`grid grid-cols-1 gap-4 mt-4 sm:grid-cols-2`}>
                        <Field light type={'password'} label={'Adgangskode'} name={'password'} disabled={isSubmitting} />
                        <Field
                            light
                            type={'password'}
                            label={'Gentag adgangskode'}
                            name={'passwordConfirmation'}
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
                            ref={recaptchaRef}
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
                    <div css={tw`mt-6 text-center text-xs text-neutral-500`}>
                        Har du allerede en konto?{' '}
                        <Link to={'/auth/login'} css={tw`font-semibold no-underline hover:text-neutral-700`}>
                            Log ind
                        </Link>
                    </div>
                </LoginFormContainer>
            )}
        </Formik>
    );
};

export default RegisterContainer;
