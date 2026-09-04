import http from '@/api/http';

export interface RegistrationData {
    nameFirst: string;
    nameLast: string;
    username: string;
    email: string;
    password: string;
    passwordConfirmation: string;
    recaptchaData?: string | null;
}

export interface RegistrationResponse {
    complete: boolean;
    intended?: string;
}

export default ({
    nameFirst,
    nameLast,
    username,
    email,
    password,
    passwordConfirmation,
    recaptchaData,
}: RegistrationData): Promise<RegistrationResponse> => {
    return new Promise((resolve, reject) => {
        http.get('/sanctum/csrf-cookie')
            .then(() =>
                http.post('/auth/register', {
                    name_first: nameFirst,
                    name_last: nameLast,
                    username,
                    email,
                    password,
                    password_confirmation: passwordConfirmation,
                    'g-recaptcha-response': recaptchaData,
                })
            )
            .then((response) => {
                if (!(response.data instanceof Object) || !(response.data.data instanceof Object)) {
                    return reject(new Error('Der opstod en fejl under oprettelsen af kontoen.'));
                }

                return resolve({
                    complete: response.data.data.complete === true,
                    intended: response.data.data.intended || undefined,
                });
            })
            .catch(reject);
    });
};
