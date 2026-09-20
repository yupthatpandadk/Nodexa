import http from '@/api/http';

export interface RegisterData {
    username: string; email: string; name_first: string; name_last: string;
    password: string; password_confirmation: string; recaptchaData?: string;
}

export default (data: RegisterData): Promise<void> =>
    new Promise((resolve, reject) => {
        http.post('/auth/register', {
            ...data,
            'g-recaptcha-response': data.recaptchaData,
        }).then(() => resolve()).catch(reject);
    });
